<?php
declare(strict_types=1);

/**
 * tools/backfill_ledger_divergencia_2026-08-03.php
 * §RECONCILIACAO-01 (03/08/2026): backfill pontual dos DOIS pagamentos
 * aprovados encontrados por tools/reconciliar_divergencia_financeira.php
 * sem NENHUM lançamento em payout_ledger_entries — causa raiz da diferença
 * de R$ 274,90 entre "pagamentos aprovados" e "ledger contábil":
 *   - pedido 1515 / pagamento 1119: R$ 89,90
 *   - pedido 1543 / pagamento 1162: R$ 185,00
 *
 * Regra suprema do projeto: sem evidência, não está pronto. Por isso este
 * script SEMPRE roda em modo leitura (dry-run) por padrão — só grava com
 * --confirm explícito, e mesmo assim é idempotente: se o pagamento já tiver
 * QUALQUER lançamento no ledger, ele é pulado (nunca duplica crédito).
 *
 * Uso:
 *   php tools/backfill_ledger_divergencia_2026-08-03.php               (dry-run, não grava nada)
 *   php tools/backfill_ledger_divergencia_2026-08-03.php --confirm     (grava de verdade)
 *   php tools/backfill_ledger_divergencia_2026-08-03.php 1119 1543     (outros pagamento_id, dry-run)
 *   php tools/backfill_ledger_divergencia_2026-08-03.php --confirm 1119
 *
 * --assumir-80-20: decisão explícita do usuário em 03/08/2026 para o
 * pagamento 1119 (pedido 1515), que tem valor_guincho/valor_plataforma
 * zerados em `pagamentos` apesar do total de R$ 89,90. Sem essa flag, um
 * pagamento com split zerado é SEMPRE bloqueado (ver checagem abaixo) —
 * ela existe só pra permitir aplicar, de forma auditável e opt-in, a
 * comissão padrão da plataforma (80% guincho / 20% plataforma, a mesma
 * proporção observada no pagamento 1162) quando não há nenhum outro dado
 * pra reconstituir o split real. NUNCA vira comportamento padrão.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Use o terminal.\n");
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/Payment/PayoutLedgerService.php';
require_once dirname(__DIR__) . '/src/Services/Logger.php';

$args = array_slice($argv, 1);
$confirm = false;
$assumir8020 = false;
$pagamentoIds = [];
foreach ($args as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
        continue;
    }
    if ($arg === '--assumir-80-20') {
        $assumir8020 = true;
        continue;
    }
    if (ctype_digit($arg)) {
        $pagamentoIds[] = (int)$arg;
    }
}
if (!$pagamentoIds) {
    // Os dois pagamentos identificados pela reconciliação de 03/08/2026.
    $pagamentoIds = [1119, 1162];
}

$pdo = getPDO();
echo "BACKFILL DE LEDGER — DIVERGÊNCIA 03/08/2026\n";
echo "Modo: " . ($confirm ? "GRAVAÇÃO" : "DRY-RUN (nada será gravado — use --confirm pra aplicar)") . "\n";
echo "Pagamentos alvo: " . implode(', ', $pagamentoIds) . "\n\n";

$referenciaBackfill = 'backfill_reconciliacao_2026-08-03';
$totalAplicados = 0;
$totalPulados = 0;

foreach ($pagamentoIds as $pagamentoId) {
    $stmt = $pdo->prepare("SELECT id, pedido_id, status, valor_total, valor_guincho, valor_plataforma FROM pagamentos WHERE id = ?");
    $stmt->execute([$pagamentoId]);
    $pg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pg) {
        echo "[SKIP] pagamento={$pagamentoId}: não encontrado.\n";
        $totalPulados++;
        continue;
    }
    if ($pg['status'] !== 'aprovado') {
        echo "[SKIP] pagamento={$pagamentoId}: status='{$pg['status']}' (só backfill de pagamentos aprovados).\n";
        $totalPulados++;
        continue;
    }

    $pedidoId = (int)$pg['pedido_id'];
    $valorGuincho = round((float)$pg['valor_guincho'], 2);
    $valorPlataforma = round((float)$pg['valor_plataforma'], 2);
    $valorTotal = round((float)$pg['valor_total'], 2);
    $somaSplit = round($valorGuincho + $valorPlataforma, 2);

    $ledgerAtualStmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN entry_type = 'credito_guincho' THEN valor WHEN entry_type = 'estorno_credito_guincho' THEN -valor ELSE 0 END), 0) AS guincho,
        COALESCE(SUM(CASE WHEN entry_type = 'credito_plataforma' THEN valor WHEN entry_type = 'estorno_credito_plataforma' THEN -valor ELSE 0 END), 0) AS plataforma,
        COUNT(*) AS linhas
        FROM payout_ledger_entries WHERE pagamento_id = ?");
    $ledgerAtualStmt->execute([$pagamentoId]);
    $ledgerAtual = $ledgerAtualStmt->fetch(PDO::FETCH_ASSOC) ?: ['guincho' => 0, 'plataforma' => 0, 'linhas' => 0];
    $somaLedger = round((float)$ledgerAtual['guincho'] + (float)$ledgerAtual['plataforma'], 2);

    if ((int)$ledgerAtual['linhas'] > 0 && abs($somaLedger - $somaSplit) <= 0.02 && abs($somaLedger - $valorTotal) <= 0.02) {
        echo "[SKIP] pagamento={$pagamentoId}: split do ledger já confere com o pagamento (idempotência).\n";
        $totalPulados++;
        continue;
    }
    if ((int)$ledgerAtual['linhas'] > 0) {
        // Já existe lançamento, mas o saldo não bate com o pagamento nem com
        // o split registrado — NUNCA empilhar outro lançamento em cima disso
        // (risco de duplicar crédito). Isso é divergência de outra natureza,
        // fora do escopo de um backfill "sem ledger nenhum" — precisa de
        // investigação manual, não de mais um INSERT automático.
        echo "[SKIP] pagamento={$pagamentoId}: já possui " . (int)$ledgerAtual['linhas'] . " lançamento(s) no ledger, mas o saldo (R$ " .
            number_format($somaLedger, 2, ',', '.') . ") não bate com o split do pagamento nem com o total. " .
            "Backfill automático NÃO é seguro aqui — requer investigação manual (não é o caso dos pagamentos 1119/1162, que estão sem nenhum lançamento).\n";
        $totalPulados++;
        continue;
    }

    $splitAssumido = false;
    if ($somaSplit <= 0) {
        if (!$assumir8020 || $valorTotal <= 0) {
            echo "[SKIP] pagamento={$pagamentoId}: valor_guincho + valor_plataforma = 0,00 — sem split gravado em `pagamentos`, backfill não tem de onde tirar o valor com segurança. Requer decisão manual (ou rode com --assumir-80-20 se for aplicar a comissão padrão).\n";
            $totalPulados++;
            continue;
        }
        // Decisão explícita do usuário (03/08/2026): sem nenhum dado de split
        // persistido, aplica a comissão padrão da plataforma — mesma
        // proporção observada no pagamento 1162 (80% guincho / 20%
        // plataforma). Isso é uma SUPOSIÇÃO auditável, não um valor lido do
        // pagamento original — por isso fica marcado em referencia_externa
        // e no log, e só roda com a flag explícita.
        $valorGuincho = round($valorTotal * 0.80, 2);
        $valorPlataforma = round($valorTotal - $valorGuincho, 2);
        $splitAssumido = true;
        echo "[ASSUMIDO] pagamento={$pagamentoId}: split zerado — aplicando comissão padrão 80/20 sobre o total (R$ " . number_format($valorTotal, 2, ',', '.') . ").\n";
    } elseif (abs($somaSplit - $valorTotal) > 0.02) {
        echo "[SKIP] pagamento={$pagamentoId}: valor_guincho ({$valorGuincho}) + valor_plataforma ({$valorPlataforma}) = {$somaSplit}, mas valor_total = {$valorTotal}. Divergência dentro do próprio pagamento — requer revisão manual antes de gerar ledger.\n";
        $totalPulados++;
        continue;
    }

    $referenciaAtual = $splitAssumido ? $referenciaBackfill . ':assumido_80_20' : $referenciaBackfill;

    echo "[" . ($confirm ? "APLICAR" : "SIMULAR") . "] pedido={$pedidoId} pagamento={$pagamentoId}: credito_guincho=R$ " .
        number_format($valorGuincho, 2, ',', '.') . " + credito_plataforma=R$ " .
        number_format($valorPlataforma, 2, ',', '.') . " (total R$ " . number_format($valorTotal, 2, ',', '.') .
        ")" . ($splitAssumido ? " [SPLIT ASSUMIDO 80/20, NÃO LIDO DO PAGAMENTO]" : "") . "\n";

    if (!$confirm) {
        $totalAplicados++;
        continue;
    }

    try {
        $pdo->beginTransaction();
        PayoutLedgerService::registrarSplitAprovado(
            $pdo,
            $pagamentoId,
            $pedidoId,
            $valorGuincho,
            $valorPlataforma,
            $referenciaAtual
        );
        $pdo->commit();

        Logger::log(
            Logger::LEVEL_WARN,
            'BackfillLedgerDivergencia',
            'run',
            'financeiro',
            "Backfill de ledger aplicado para pagamento #{$pagamentoId} (pedido #{$pedidoId}) — divergência histórica de 03/08/2026" . ($splitAssumido ? ' [split assumido 80/20]' : ''),
            [
                'pagamento_id' => $pagamentoId,
                'pedido_id' => $pedidoId,
                'split_assumido' => $splitAssumido,
                'valor_guincho' => $valorGuincho,
                'valor_plataforma' => $valorPlataforma,
                'referencia_externa' => $referenciaAtual,
            ]
        );
        echo "  → gravado.\n";
        $totalAplicados++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "  → ERRO: " . $e->getMessage() . "\n";
        $totalPulados++;
    }
}

echo "\nResumo: " . $totalAplicados . " " . ($confirm ? "aplicado(s)" : "seriam aplicado(s)") . ", " . $totalPulados . " pulado(s)/ignorado(s).\n";
if (!$confirm) {
    echo "Nada foi gravado. Rode novamente com --confirm para aplicar de verdade.\n";
}
