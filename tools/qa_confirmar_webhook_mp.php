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
 * QA: mesma simulação de tools/qa_test_webhook_mp_sandbox.php, mas devolvendo
 * UMA linha de JSON em vez de texto formatado — pra poder ser chamado direto
 * por qa/helpers/seed.ts (mesmo padrão de runSeedScript() usado por todo
 * o resto da suíte Playwright).
 *
 * Necessário porque, em ambiente local (XAMPP sem túnel público), o Mercado
 * Pago não consegue chamar de volta a notification_url (que aponta pra
 * APP_URL, não localhost) depois de um checkout real de sandbox — então o
 * teste E2E completa o checkout de verdade na página do MP (cartão de teste),
 * pega o payment_id real devolvido na URL de retorno, e usa este script pra
 * reproduzir o webhook que o MP mandaria em produção.
 *
 * Uso: php tools/qa_confirmar_webhook_mp.php <payment_id> [base_url]
 */

function saida(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if ($argc < 2 || !ctype_digit($argv[1])) {
    saida(['ok' => false, 'erro' => 'Uso: php qa_confirmar_webhook_mp.php <payment_id> [base_url]']);
    exit(1);
}

$dataId  = $argv[1];
$baseUrl = $argv[2] ?? (getenv('PLAYWRIGHT_BASE_URL') ?: 'http://localhost:8080');

$reqId = 'qa-test-' . bin2hex(random_bytes(6));
$ts    = (string)time();
$manifest = "id:{$dataId};request-id:{$reqId};ts:{$ts};";
$hash     = hash_hmac('sha256', $manifest, (string)MP_WEBHOOK_SECRET);
$xSig     = "ts={$ts},v1={$hash}";

$body = json_encode([
    'action' => 'payment.updated',
    'data'   => ['id' => $dataId],
], JSON_UNESCAPED_SLASHES);

$url = rtrim($baseUrl, '/') . '/webhook/mercadopago?data.id=' . urlencode($dataId);

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
    saida(['ok' => false, 'erro' => "cURL: {$curlErr}"]);
    exit(1);
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT p.id AS pagamento_id, p.pedido_id, p.status AS pagamento_status, p.status_pix,
                p.id_externo, p.valor_total, p.valor_guincho, p.valor_plataforma,
                pd.status AS pedido_status
           FROM pagamentos p
           JOIN pedidos pd ON pd.id = p.pedido_id
          WHERE p.id_externo = ?
          LIMIT 1"
    );
    $stmt->execute(['mp_' . $dataId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    saida(['ok' => false, 'erro' => 'DB: ' . $e->getMessage(), 'http_code' => $httpCode]);
    exit(1);
}

if (!$row) {
    saida([
        'ok' => false,
        'erro' => "Nenhum pagamento com id_externo=mp_{$dataId}",
        'http_code' => $httpCode,
        'resposta' => $resp,
    ]);
    exit(0);
}

// §WEBHOOK-QA-HIBRIDO-01 (27/07/2026, achado ao montar o E2E do complementar
// híbrido): esta checagem hardcodava 'aguardando_guincho' como ÚNICO status
// de sucesso possível pós-aprovação. Isso é verdade para reboque comum e
// para o complementar NÃO-híbrido, mas o complementar HÍBRIDO
// (ConversionService::finalizarCaminhoHibrido) aprova o pagamento e leva o
// pedido direto pra 'preparacao_veiculo' (mesmo prestador, sem nova disputa
// de matching) — com a checagem antiga, este script reportaria
// aprovado=false para um webhook que na verdade funcionou perfeitamente,
// quebrando qualquer E2E que dependesse dele no caminho híbrido. Qualquer
// status que NÃO seja "ainda aguardando pagamento" já prova que o webhook
// avançou o pedido de verdade.
$statusPosPagamento = ['aguardando_pagamento', 'aguardando_pagamento_reboque_hibrido'];
$aprovado = $row['pagamento_status'] === 'aprovado' && !in_array($row['pedido_status'], $statusPosPagamento, true);

saida([
    'ok' => true,
    'http_code' => (int)$httpCode,
    'payment_id' => $dataId,
    'pagamento_id' => (int)$row['pagamento_id'],
    'pedido_id' => (int)$row['pedido_id'],
    'pedido_status' => $row['pedido_status'],
    'pagamento_status' => $row['pagamento_status'],
    'status_pix' => $row['status_pix'],
    'valor_total' => (float)$row['valor_total'],
    'valor_guincho' => (float)$row['valor_guincho'],
    'valor_plataforma' => (float)$row['valor_plataforma'],
    'aprovado' => $aprovado,
]);
