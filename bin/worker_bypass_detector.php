<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Services/CronMonitorService.php';
require_once __DIR__ . '/../src/Services/BypassDetectorService.php';

$pdo = getPDO();
$run = CronMonitorService::start('cron_bypass_detector');
$processed = 0;
try {
    $stmt = $pdo->query("SELECT id FROM pedidos WHERE status = 'cancelado' AND updated_at >= NOW() - INTERVAL 6 HOUR ORDER BY id ASC");
    $errors = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pedidoId) {
        try {
            if (BypassDetectorService::analisarPermanenciaPosCancelamento((int)$pedidoId, $pdo) !== null) {
                $processed++;
            }
        } catch (Throwable $e) {
            $errors++;
            error_log('[bypass_detector] pedido #' . (int)$pedidoId . ': ' . $e->getMessage());
        }
    }
    CronMonitorService::finish($run, $errors > 0 ? 'warning' : 'ok', 'Detector de bypass concluído.', [
        'casos_criados_ou_atualizados' => $processed, 'erros' => $errors,
    ]);
    fwrite(STDOUT, json_encode(['processed' => $processed, 'errors' => $errors], JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $e) {
    CronMonitorService::finish($run, 'error', $e->getMessage(), ['casos_criados_ou_atualizados' => $processed, 'erros' => 1]);
    fwrite(STDERR, '[bypass_detector] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
