<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/Payment/PayoutLedgerService.php';
require_once dirname(__DIR__) . '/src/Models/Finance/PayoutLedgerEntry.php';
require_once dirname(__DIR__) . '/src/Services/Logger.php';

/**
 * Backfill do ledger contábil (payout_ledger_entries) — NÃO apaga nada, só
 * INSERE os lançamentos 'credito_guincho'/'credito_plataforma' que ficaram
 * faltando pra pagamentos já aprovados. Ver achado real em
 * CarteiraService::checarReconciliacaoGlobal() (tela /admin/carteiras):
 * pagamentos aprovados em modo 'freeflow' (antes do fix em
 * GuinchoController.php) e/ou inseridos por seeds de QA nunca passaram
 * pelo caminho que grava o ledger (PedidoTransitionService::approvePayment).
 *
 * Uso (sempre rode SEM --confirmar primeiro, pra revisar o preview):
 *   php tools/backfill_ledger_pagamentos_sem_lancamento.php
 *   php tools/backfill_ledger_pagamentos_sem_lancamento.php --confirmar
 *
 * Cada lançamento gravado é marcado com referencia_externa =
 * 'backfill:<pagamento_id>' e metadata_json com o motivo — auditável,
 * distinguível de um lançamento original (nunca finge ser um lançamento
 * feito no momento da aprovação real).
 */

$confirmar = in_array('--confirmar', $argv, true);
$limite = 500;
foreach ($argv as $arg) {
    if (preg_match('/^--limite=(\d+)$/', $arg, $m)) {
        $limite = max(1, (int)$m[1]);
    }
}

$pdo = getPDO();

$stmt = $pdo->prepare(
    "SELECT p.id AS pagamento_id, p.pedido_id, p.metodo, p.status, p.status_pix,
            p.valor_total, p.valor_guincho, p.valor_plataforma, p.pago_guincho, p.criado_em
     FROM pagamentos p
     WHERE p.status = 'aprovado'
       AND NOT EXISTS (
           SELECT 1 FROM payout_ledger_entries l
           WHERE l.pagamento_id = p.id AND l.entry_type = 'credito_guincho'
       )
     ORDER BY p.criado_em ASC
     LIMIT ?"
);
$stmt->bindValue(1, $limite, PDO::PARAM_INT);
$stmt->execute();
$faltantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($faltantes)) {
    echo "Nenhum pagamento aprovado sem lançamento de ledger encontrado. Nada a fazer.\n";
    exit(0);
}

echo "Encontrados " . count($faltantes) . " pagamento(s) aprovado(s) sem lançamento 'credito_guincho' no ledger:\n\n";
printf("%-8s %-8s %-14s %-12s %-12s %-12s %-20s\n", 'pag_id', 'ped_id', 'metodo', 'valor_total', 'v_guincho', 'v_plataf', 'criado_em');
$totalGuincho = 0.0;
$totalPlataforma = 0.0;
foreach ($faltantes as $f) {
    printf(
        "%-8d %-8d %-14s %-12s %-12s %-12s %-20s\n",
        (int)$f['pagamento_id'],
        (int)$f['pedido_id'],
        (string)$f['metodo'],
        number_format((float)$f['valor_total'], 2, ',', '.'),
        number_format((float)$f['valor_guincho'], 2, ',', '.'),
        number_format((float)$f['valor_plataforma'], 2, ',', '.'),
        (string)$f['criado_em']
    );
    $totalGuincho += (float)$f['valor_guincho'];
    $totalPlataforma += (float)$f['valor_plataforma'];
}
echo "\nTotal a lançar: credito_guincho = R$ " . number_format($totalGuincho, 2, ',', '.') .
     " | credito_plataforma = R$ " . number_format($totalPlataforma, 2, ',', '.') . "\n\n";

if (!$confirmar) {
    echo "MODO PREVIEW (nada foi gravado). Revise a lista acima.\n";
    echo "Pra gravar de verdade, rode: php tools/backfill_ledger_pagamentos_sem_lancamento.php --confirmar\n";
    exit(0);
}

echo "Gravando lançamentos de backfill...\n";
$gravados = 0;
$falhas = 0;
foreach ($faltantes as $f) {
    $pdo->beginTransaction();
    try {
        $pagamentoId = (int)$f['pagamento_id'];
        $pedidoId = (int)$f['pedido_id'];
        $valorGuincho = (float)$f['valor_guincho'];
        $valorPlataforma = (float)$f['valor_plataforma'];
        $valorTotal = (float)$f['valor_total'];
        // Reserva de gateway implícita = o que sobra entre total e guincho+plataforma
        // (será 0 pra pagamentos que nunca descontaram reserva, ex. freeflow antigo —
        // registrarSplitAprovado só grava o lançamento de reserva se > 0).
        $valorReserva = max(0.0, round($valorTotal - $valorGuincho - $valorPlataforma, 2));

        PayoutLedgerService::registrarSplitAprovado(
            $pdo,
            $pagamentoId,
            $pedidoId,
            $valorGuincho,
            $valorPlataforma,
            'backfill:' . $pagamentoId,
            $valorReserva
        );

        $pdo->commit();
        $gravados++;
        Logger::log(Logger::LEVEL_INFO, 'BackfillLedger', 'main', 'financeiro',
            "Backfill: lançamento de ledger gravado pro pagamento #{$pagamentoId} (pedido #{$pedidoId}).",
            ['pagamento_id' => $pagamentoId, 'pedido_id' => $pedidoId, 'valor_guincho' => $valorGuincho, 'valor_plataforma' => $valorPlataforma, 'valor_reserva' => $valorReserva]);
        echo "  [OK] pagamento #{$pagamentoId} (pedido #{$pedidoId})\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        $falhas++;
        Logger::exception('BackfillLedger', 'main', 'financeiro', $e, ['pagamento_id' => $f['pagamento_id'] ?? null]);
        echo "  [FALHA] pagamento #" . ($f['pagamento_id'] ?? '?') . ": " . $e->getMessage() . "\n";
    }
}

echo "\nConcluído. {$gravados} lançamento(s) gravado(s), {$falhas} falha(s).\n";
echo "Confira o resultado em /admin/carteiras — a divergência de reconciliação deve ter fechado (ou diminuído, se ainda houver pagamentos fora deste lote de {$limite}).\n";
