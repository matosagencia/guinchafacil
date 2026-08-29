<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


require_once dirname(__DIR__) . '/config.php';

/**
 * QA: reproduz exatamente o webhook que o Mercado Pago envia para
 * /webhook/mercadopago, usando um payment_id REAL de sandbox (obtido
 * completando um checkout de teste no MP). Isso valida, sem precisar de
 * túnel público (ngrok etc.), toda a cadeia real:
 *
 *   assinatura HMAC (WebhookController::mercadoPago)
 *     -> GET real na API do MP pelo payment_id (buscarEProcessarMp)
 *     -> aprovarPagamento() -> PedidoTransitionService::approvePayment()
 *     -> pedido sai de aguardando_pagamento -> aguardando_guincho
 *     -> pagamentos.status = aprovado + split calculado
 *
 * Uso:
 *   php tools/qa_test_webhook_mp_sandbox.php <payment_id> [base_url]
 *
 * base_url default: http://localhost:8080 (ajuste se seu vhost usar outra porta)
 */

if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, "[ERRO] Uso: php qa_test_webhook_mp_sandbox.php <payment_id> [base_url]\n");
    exit(1);
}

$dataId  = $argv[1];
$baseUrl = $argv[2] ?? 'http://localhost:8080';

if (MP_ENV !== 'sandbox') {
    fwrite(STDERR, "[AVISO] MP_ENV atual = '" . MP_ENV . "' (esperado 'sandbox'). Confirme o .env.local antes de continuar.\n");
}

$reqId = 'qa-test-' . bin2hex(random_bytes(6));
$ts    = (string)time();

// Mesma fórmula usada em WebhookController::mercadoPago() (§MP-SIG-01)
$manifest = "id:{$dataId};request-id:{$reqId};ts:{$ts};";
$hash     = hash_hmac('sha256', $manifest, (string)MP_WEBHOOK_SECRET);
$xSig     = "ts={$ts},v1={$hash}";

$body = json_encode([
    'action' => 'payment.updated',
    'data'   => ['id' => $dataId],
], JSON_UNESCAPED_SLASHES);

$url = rtrim($baseUrl, '/') . '/webhook/mercadopago?data.id=' . urlencode($dataId);

echo "== Simulando webhook Mercado Pago ==\n";
echo "URL:          {$url}\n";
echo "payment_id:   {$dataId}\n";
echo "X-Request-Id: {$reqId}\n";
echo "X-Signature:  {$xSig}\n";
echo "Manifest:     {$manifest}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Signature: ' . $xSig,
        'X-Request-Id: ' . $reqId,
    ],
    CURLOPT_TIMEOUT => 20,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr !== '') {
    fwrite(STDERR, "[ERRO cURL] {$curlErr}\n");
    exit(1);
}

echo "HTTP status:  {$httpCode}\n";
echo "Resposta:     " . ($resp !== false ? $resp : '(vazia)') . "\n\n";

// Checagem pós-webhook: consulta direto no banco o que aconteceu.
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT p.id AS pagamento_id, p.pedido_id, p.status, p.status_pix, p.id_externo,
                p.valor_total, p.valor_guincho, p.valor_plataforma,
                pd.status AS pedido_status
           FROM pagamentos p
           JOIN pedidos pd ON pd.id = p.pedido_id
          WHERE p.id_externo = ?
          LIMIT 1"
    );
    $stmt->execute(['mp_' . $dataId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo "== Estado após o webhook (lido do banco) ==\n";
        foreach ($row as $k => $v) {
            echo str_pad($k, 18) . ': ' . ($v ?? 'NULL') . "\n";
        }
        if ($row['pedido_status'] === 'aguardando_guincho' && $row['status'] === 'aprovado') {
            echo "\n[OK] Pagamento aprovado e pedido avançou para aguardando_guincho — fluxo de webhook funcionando.\n";
        } else {
            echo "\n[ATENÇÃO] Estado não é o esperado. Veja logs_webhook e o log de erro do PHP/Apache para detalhes.\n";
        }
    } else {
        echo "[ATENÇÃO] Nenhum pagamento encontrado com id_externo = mp_{$dataId}. Possíveis causas:\n";
        echo "  - O payment_id não corresponde a um pedido real deste sistema (external_reference incorreto).\n";
        echo "  - O webhook foi rejeitado (confira HTTP status acima e a tabela logs_webhook).\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "[ERRO ao consultar banco] " . $e->getMessage() . "\n");
}
