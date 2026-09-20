<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Services/BypassDetectorService.php';

$pdo = getPDO();
$stmt = $pdo->query("SELECT id FROM pedidos WHERE status = 'cancelado' AND updated_at >= NOW() - INTERVAL 6 HOUR ORDER BY id ASC");
$processed = 0;
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pedidoId) {
    if (BypassDetectorService::analisarPermanenciaPosCancelamento((int)$pedidoId, $pdo) !== null) {
        $processed++;
    }
}
fwrite(STDOUT, json_encode(['processed' => $processed], JSON_UNESCAPED_UNICODE) . PHP_EOL);
