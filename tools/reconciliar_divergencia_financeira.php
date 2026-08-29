<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); die("Acesso negado. Use o terminal.\n"); }
require_once dirname(__DIR__) . '/config.php';

$desde = $argv[1] ?? null;
$ate = $argv[2] ?? null;
$where = ["pg.status IN ('aprovado','estornado')"];
$params = [];
if ($desde && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $where[] = 'DATE(COALESCE(pg.data_pagamento,pg.criado_em)) >= ?'; $params[] = $desde; }
if ($ate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $where[] = 'DATE(COALESCE(pg.data_pagamento,pg.criado_em)) <= ?'; $params[] = $ate; }
$sql = "SELECT pg.id pagamento_id, pg.pedido_id, pg.status, pg.valor_total, pg.valor_guincho, pg.valor_plataforma,
 COALESCE((SELECT SUM(CASE WHEN l.entry_type='credito_guincho' THEN l.valor WHEN l.entry_type='estorno_credito_guincho' THEN -l.valor ELSE 0 END) FROM payout_ledger_entries l WHERE l.pagamento_id=pg.id),0) ledger_guincho,
 COALESCE((SELECT SUM(CASE WHEN l.entry_type='credito_plataforma' THEN l.valor WHEN l.entry_type='estorno_credito_plataforma' THEN -l.valor ELSE 0 END) FROM payout_ledger_entries l WHERE l.pagamento_id=pg.id),0) ledger_plataforma,
 COALESCE((SELECT SUM(pl.valor_liquido) FROM pagamento_liquidacoes pl WHERE pl.pagamento_id=pg.id),0) liquidado_gateway
 FROM pagamentos pg WHERE " . implode(' AND ', $where) . " ORDER BY pg.id";
$stmt = getPDO()->prepare($sql); $stmt->execute($params);
$total = ['valor_total'=>0.0,'ledger_guincho'=>0.0,'ledger_plataforma'=>0.0,'diferenca'=>0.0]; $divergentes = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $esperado = (float)$r['valor_total'];
    $contabil = (float)$r['ledger_guincho'] + (float)$r['ledger_plataforma'];
    $dif = round($esperado - $contabil, 2);
    foreach (['valor_total','ledger_guincho','ledger_plataforma'] as $k) $total[$k] += (float)$r[$k];
    $total['diferenca'] += $dif;
    if (abs($dif) > 0.02) { $r['diferenca'] = $dif; $r['causa_provavel'] = ((float)$r['ledger_guincho'] === 0.0 && (float)$r['ledger_plataforma'] === 0.0) ? 'sem lançamento de ledger' : 'split/estorno/taxa não espelhado'; $divergentes[] = $r; }
}
echo "RECONCILIAÇÃO FINANCEIRA\nPeríodo: " . ($desde ?: 'início') . " até " . ($ate ?: 'fim') . "\n";
echo "Pagamentos considerados: " . count($divergentes) . " divergentes encontrados\n";
printf("Totais: pagos R$ %.2f | ledger guincho R$ %.2f | ledger plataforma R$ %.2f | diferença R$ %.2f\n", $total['valor_total'], $total['ledger_guincho'], $total['ledger_plataforma'], $total['diferenca']);
foreach ($divergentes as $r) printf("[DIVERGENTE] pedido=%d pagamento=%d status=%s pago=%.2f guincho=%.2f plataforma=%.2f ledger_g=%.2f ledger_p=%.2f diferença=%.2f causa=%s\n", $r['pedido_id'],$r['pagamento_id'],$r['status'],$r['valor_total'],$r['valor_guincho'],$r['valor_plataforma'],$r['ledger_guincho'],$r['ledger_plataforma'],$r['diferenca'],$r['causa_provavel']);
if (!$divergentes) echo "[OK] Nenhuma divergência acima de R$ 0,02.\n";
