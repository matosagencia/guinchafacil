<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/HealthService.php';
require_once __DIR__ . '/../Services/CronMonitorService.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Services/Logger.php';

/** Controller especializado da tela de saúde operacional do administrador. */
final class AdminHealthController extends BaseController
{
    public function health(): void
    {
        AuthService::requireAuth('admin');
        $checks = HealthService::runAll();
        $productionChecklist = HealthService::productionChecklist();
        $cronJobs = CronMonitorService::listJobs();
        $cronExecutions = CronMonitorService::listRecentExecutions(20);
        $cronInstallCommands = CronMonitorService::installationCommands();
        $retentionConfig = Configuracao::getMultiplas([
            'retention_simulation_artifacts_days', 'retention_simulation_runs_days',
            'retention_jsonl_logs_days', 'retention_cron_executions_days',
            'retention_por_days', 'retention_evidencias_days', 'retention_chat_days',
        ]);
        Logger::log(Logger::LEVEL_INFO, 'AdminHealthController', 'health', 'health',
            'Health check executado.',
            ['ok' => count(array_filter($checks, fn($c) => $c['ok'])), 'total' => count($checks)]
        );
        require __DIR__ . '/../Views/admin/health.php';
    }
}
