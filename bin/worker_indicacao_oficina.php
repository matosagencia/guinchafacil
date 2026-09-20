<?php

declare(strict_types=1);

// Worker cron: reconcilia entregas e liquidações de oficinas parceiras.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Services/IndicacaoOficinaService.php';
require_once __DIR__ . '/../src/Services/Financial/WorkshopSettlementService.php';

$limit = max(1, min((int)($argv[1] ?? 100), 500));
if (!IndicacaoOficinaService::ativo()) {
    echo "monetizacao_oficinas_ativo=0\n";
    exit(0);
}

$pdo = getPDO();
$rows = $pdo->query(
    "SELECT i.pedido_id
       FROM pedido_indicacoes_oficina i
       JOIN pedidos p ON p.id = i.pedido_id
      WHERE p.status = 'concluido'
        AND i.status IN ('SELECIONADA','EM_TRANSITO','AGUARDANDO_CHECKIN')
      ORDER BY i.id ASC
      LIMIT {$limit}"
)->fetchAll(PDO::FETCH_COLUMN);

$checked = 0;
$checkinFailures = 0;
foreach ($rows as $pedidoId) {
    try {
        IndicacaoOficinaService::processarEntrega((int)$pedidoId);
        $checked++;
    } catch (Throwable $e) {
        $checkinFailures++;
        error_log('[IndicacaoOficinaWorker] pedido ' . $pedidoId . ': ' . $e->getMessage());
    }
}

$settlement = WorkshopSettlementService::reconciliar($limit);
echo sprintf(
    "checkins=%d checkin_falhas=%d settlements=%d settlement_falhas=%d\n",
    $checked,
    $checkinFailures,
    (int)$settlement['processed'],
    (int)$settlement['failed']
);
