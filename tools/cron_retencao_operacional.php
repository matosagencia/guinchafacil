<?php
/**
 * Cron: retenção operacional (§16)
 * - simulation_artifacts / simulation_runs antigos
 * - traces, vídeos e diretórios qa-runs antigos
 * Execução recomendada: diária às 01:30
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/CronMonitorService.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

$run = CronMonitorService::start('cron_retencao_operacional');
$metrics = [
    'simulation_artifacts_deleted' => 0,
    'simulation_runs_deleted' => 0,
    'cron_executions_deleted' => 0,
    'pedido_localizacoes_deleted' => 0,
    'pedido_evidencias_deleted' => 0,
    'chat_mensagens_deleted' => 0,
    'filesystem_removed' => 0,
];

try {
    $pdo = getPDO();
    $cfg = Configuracao::getMultiplas([
        'retention_simulation_artifacts_days',
        'retention_simulation_runs_days',
        'retention_jsonl_logs_days',
        'retention_cron_executions_days',
        'retention_por_days',
        'retention_evidencias_days',
        'retention_chat_days',
    ]);
    $artifactRetentionDays = max(7, (int)($cfg['retention_simulation_artifacts_days'] ?? '14'));
    $runRetentionDays = max(7, (int)($cfg['retention_simulation_runs_days'] ?? '30'));
    $logRetentionDays = max(7, (int)($cfg['retention_jsonl_logs_days'] ?? '30'));
    $cronExecutionRetentionDays = max(7, (int)($cfg['retention_cron_executions_days'] ?? '60'));
    $porRetentionDays = max(30, (int)($cfg['retention_por_days'] ?? '180'));
    $evidenciaRetentionDays = max(30, (int)($cfg['retention_evidencias_days'] ?? '365'));
    $chatRetentionDays = max(30, (int)($cfg['retention_chat_days'] ?? '365'));
    $artifactThreshold = date('Y-m-d H:i:s', time() - ($artifactRetentionDays * 86400));
    $runThreshold = date('Y-m-d H:i:s', time() - ($runRetentionDays * 86400));
    $cronThreshold = date('Y-m-d H:i:s', time() - ($cronExecutionRetentionDays * 86400));
    $porThreshold = date('Y-m-d H:i:s', time() - ($porRetentionDays * 86400));
    $evidenciaThreshold = date('Y-m-d H:i:s', time() - ($evidenciaRetentionDays * 86400));
    $chatThreshold = date('Y-m-d H:i:s', time() - ($chatRetentionDays * 86400));

    $artifactStmt = $pdo->prepare(
        "SELECT id, private_path
         FROM simulation_artifacts
         WHERE created_at < ?"
    );
    $artifactStmt->execute([$artifactThreshold]);
    $artifacts = $artifactStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($artifacts as $artifact) {
        $path = (string)($artifact['private_path'] ?? '');
        if ($path !== '' && is_file($path) && @unlink($path)) {
            $metrics['filesystem_removed']++;
        }
        $pdo->prepare("DELETE FROM simulation_artifacts WHERE id = ?")->execute([(int)$artifact['id']]);
        $metrics['simulation_artifacts_deleted']++;
    }

    $runDelete = $pdo->prepare(
        "DELETE FROM simulation_runs
         WHERE finished_at IS NOT NULL
           AND finished_at < ?"
    );
    $runDelete->execute([$runThreshold]);
    $metrics['simulation_runs_deleted'] = $runDelete->rowCount();

    $cronDelete = $pdo->prepare(
        "DELETE FROM cron_executions WHERE started_at < ?"
    );
    $cronDelete->execute([$cronThreshold]);
    $metrics['cron_executions_deleted'] = $cronDelete->rowCount();

    $trailDelete = $pdo->prepare(
        "DELETE pl
           FROM pedido_localizacoes pl
           INNER JOIN pedidos p ON p.id = pl.pedido_id
          WHERE p.status IN ('concluido', 'cancelado')
            AND pl.server_timestamp < ?"
    );
    $trailDelete->execute([$porThreshold]);
    $metrics['pedido_localizacoes_deleted'] = $trailDelete->rowCount();

    $evidenciaStmt = $pdo->prepare(
        "SELECT id, stored_name
           FROM pedido_evidencias
          WHERE created_at < ?"
    );
    $evidenciaStmt->execute([$evidenciaThreshold]);
    $evidencias = $evidenciaStmt->fetchAll(PDO::FETCH_ASSOC);
    $evidenciaDir = rtrim((string)UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'evidencias';
    foreach ($evidencias as $evidencia) {
        $storedName = trim((string)($evidencia['stored_name'] ?? ''));
        if ($storedName !== '') {
            $filePath = $evidenciaDir . DIRECTORY_SEPARATOR . $storedName;
            if (is_file($filePath) && @unlink($filePath)) {
                $metrics['filesystem_removed']++;
            }
        }
        $pdo->prepare("DELETE FROM pedido_evidencias WHERE id = ?")->execute([(int)$evidencia['id']]);
        $metrics['pedido_evidencias_deleted']++;
    }

    $chatDelete = $pdo->prepare(
        "DELETE cm
           FROM chat_mensagens cm
           INNER JOIN pedidos p ON p.id = cm.pedido_id
          WHERE p.status IN ('concluido', 'cancelado')
            AND cm.criado_em < ?"
    );
    $chatDelete->execute([$chatThreshold]);
    $metrics['chat_mensagens_deleted'] = $chatDelete->rowCount();

    foreach ([
        dirname(__DIR__) . '/files/qa-runs',
        dirname(__DIR__) . '/qa/test-results',
        dirname(__DIR__) . '/qa/playwright-report',
        dirname(__DIR__) . '/logs',
    ] as $dir) {
        selfDeleteExpiredEntries($dir, $dir === dirname(__DIR__) . '/logs' ? $logRetentionDays : $artifactRetentionDays, $metrics);
    }

    $message = 'Retenção operacional concluída.';
    CronMonitorService::finish($run, 'ok', $message, $metrics);
    echo date('[Y-m-d H:i:s]') . " {$message} " . json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
} catch (Throwable $e) {
    CronMonitorService::finish($run, 'error', $e->getMessage(), $metrics);
    fwrite(STDERR, date('[Y-m-d H:i:s]') . ' Falha na retenção operacional: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

function selfDeleteExpiredEntries(string $dir, int $retentionDays, array &$metrics): void
{
    if (!is_dir($dir)) {
        return;
    }

    $threshold = time() - ($retentionDays * 86400);
    $entries = scandir($dir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
        $mtime = @filemtime($fullPath);
        if ($mtime === false || $mtime >= $threshold) {
            continue;
        }
        removePathRecursive($fullPath, $metrics);
    }
}

function removePathRecursive(string $path, array &$metrics): void
{
    if (is_file($path) || is_link($path)) {
        if (@unlink($path)) {
            $metrics['filesystem_removed']++;
        }
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        removePathRecursive($path . DIRECTORY_SEPARATOR . $item, $metrics);
    }
    @rmdir($path);
}
