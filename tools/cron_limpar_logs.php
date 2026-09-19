<?php
/**
 * Cron: limpeza de logs (§10 + §LOG-RET-01)
 * - app_logs: retém 90 dias
 * - logs_webhook: retém 365 dias
 * Execução recomendada: diária às 00:30
 * cPanel: 30 0 * * * php /home/usuario/public_html/tools/cron_limpar_logs.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

// §CRON-LOCK-01: lock exclusivo
$lockFile = sys_get_temp_dir() . '/guinchafacil_logs.lock';
$fp = fopen($lockFile, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    echo date('[Y-m-d H:i:s]') . " [cron_logs] Lock não obtido — outro processo em execução.\n";
    exit(0);
}

try {
    require_once dirname(__DIR__) . '/config.php';
    require_once dirname(__DIR__) . '/src/Services/CronMonitorService.php';

    $run = CronMonitorService::start('cron_limpar_logs');

    $pdo = getPDO();
    $metrics = [
        'app_logs_deleted' => 0,
        'webhook_logs_deleted' => 0,
        'geocoding_cache_deleted' => 0,
    ];

    // §LOG-RET-01: app_logs — retenção de 90 dias
    try {
        $stmt = $pdo->prepare("DELETE FROM app_logs WHERE criado_em < NOW() - INTERVAL 90 DAY");
        $stmt->execute();
        $appLogs = $stmt->rowCount();
        $metrics['app_logs_deleted'] = $appLogs;
        echo date('[Y-m-d H:i:s]') . " app_logs removidos (>90d): {$appLogs}\n";
        error_log("[cron_logs] app_logs removidos: {$appLogs}");
    } catch (PDOException $e) {
        error_log("[cron_logs] app_logs não encontrada ou erro: " . $e->getMessage());
    }

    // §LOG-RET-01: logs_webhook — retenção de 365 dias
    try {
        $stmt = $pdo->prepare("DELETE FROM logs_webhook WHERE criado_em < NOW() - INTERVAL 365 DAY");
        $stmt->execute();
        $webhookLogs = $stmt->rowCount();
        $metrics['webhook_logs_deleted'] = $webhookLogs;
        echo date('[Y-m-d H:i:s]') . " logs_webhook removidos (>365d): {$webhookLogs}\n";
        error_log("[cron_logs] logs_webhook removidos: {$webhookLogs}");
    } catch (PDOException $e) {
        error_log("[cron_logs] logs_webhook erro: " . $e->getMessage());
    }

    // §GEO-CACHE-01: geocoding_cache — retenção de 31 dias
    try {
        $stmt = $pdo->prepare("DELETE FROM geocoding_cache WHERE created_at < NOW() - INTERVAL 31 DAY");
        $stmt->execute();
        $geoCache = $stmt->rowCount();
        $metrics['geocoding_cache_deleted'] = $geoCache;
        echo date('[Y-m-d H:i:s]') . " geocoding_cache removidos (>31d): {$geoCache}\n";
        error_log("[cron_logs] geocoding_cache removidos: {$geoCache}");
    } catch (PDOException $e) {
        // Silencioso — tabela pode não existir ainda
    }

    CronMonitorService::finish($run, 'ok', 'Limpeza de logs concluída.', $metrics);
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}

exit(0);
