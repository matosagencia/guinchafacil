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
 * QA: devolve o id_externo (e o payment_id numérico do MP, sem o prefixo
 * "mp_") do pagamento ATUAL (vivo) e, se existir, do pagamento ARQUIVADO
 * mais recente de um pedido — usado por
 * qa/suites/conversao-hibrida-complementar.spec.ts (E2E-HIBRIDO-001) pra
 * conseguir repetir de propósito o webhook do pagamento ORIGINAL (arquivado)
 * DEPOIS que o complementar híbrido já foi cobrado/aprovado, provando que
 * PagamentoAprovacaoService::aprovar() ignora webhooks atrasados de
 * cobranças já arquivadas (Pagamento::buscarArquivadoPorIdExterno) sem
 * alterar o pagamento complementar vivo.
 *
 * Sem este script não havia como o teste E2E obter de volta o payment_id
 * numérico do pagamento original real (o checkout transparente/Brick não
 * devolve o idExterno pro cliente — só aprova de forma síncrona no
 * servidor, ver PagamentoController::mercadoPagoTransparente()).
 *
 * Uso: php tools/qa_pagamento_id_externo.php <pedido_id>
 */

function saida(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function extrairPaymentIdNumerico(?string $idExterno): ?string
{
    if ($idExterno === null || $idExterno === '') {
        return null;
    }
    if (str_starts_with($idExterno, 'mp_')) {
        return substr($idExterno, 3);
    }
    return $idExterno;
}

if ($argc < 2 || !ctype_digit($argv[1])) {
    saida(['ok' => false, 'erro' => 'Uso: php qa_pagamento_id_externo.php <pedido_id>']);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        "SELECT id, status, id_externo FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$pedidoId]);
    $vivo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $arquivado = null;
    $stmtTab = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'pagamentos_arquivados'"
    );
    $stmtTab->execute([DB_NAME]);
    if ((int)$stmtTab->fetchColumn() > 0) {
        $stmtArq = $pdo->prepare(
            "SELECT id, status, id_externo FROM pagamentos_arquivados WHERE pedido_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmtArq->execute([$pedidoId]);
        $arquivado = $stmtArq->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    saida([
        'ok' => true,
        'pedido_id' => $pedidoId,
        'vivo_pagamento_id' => $vivo ? (int)$vivo['id'] : null,
        'vivo_status' => $vivo['status'] ?? null,
        'vivo_id_externo' => $vivo['id_externo'] ?? null,
        'vivo_payment_id_numerico' => extrairPaymentIdNumerico($vivo['id_externo'] ?? null),
        'arquivado_pagamento_id' => $arquivado ? (int)$arquivado['id'] : null,
        'arquivado_status' => $arquivado['status'] ?? null,
        'arquivado_id_externo' => $arquivado['id_externo'] ?? null,
        'arquivado_payment_id_numerico' => extrairPaymentIdNumerico($arquivado['id_externo'] ?? null),
    ]);
} catch (Throwable $e) {
    saida(['ok' => false, 'erro' => $e->getMessage()]);
    exit(1);
}
