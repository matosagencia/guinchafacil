<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AdminLogService.php';

/** Controller do dashboard operacional de logs administrativos. */
final class AdminLogsController extends BaseController
{
    public function index(): void
    {
        AuthService::requireAuth('admin');

        $filtros = AdminLogService::normalizeFilters($_GET);
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $dashboard = AdminLogService::fetchDashboard($filtros, $pagina, 50);
        $webhookLogs = AdminLogService::fetchWebhookLogs(80);
        $fileData = AdminLogService::readFileTail(200);
        $queryBase = array_filter(array_merge($filtros), static fn ($value): bool => $value !== '' && $value !== null);

        $appLogs = $dashboard['appLogs'];
        $appTotal = $dashboard['appTotal'];
        $stats = $dashboard['stats'];
        $charts = $dashboard['charts'];
        $latency = $dashboard['latency'];
        $correlation = $dashboard['correlation'];
        $totalPaginas = $dashboard['totalPaginas'];
        $logFile = $fileData['file'];
        $fileTail = $fileData['lines'];

        require __DIR__ . '/../Views/admin/logs_v2.php';
    }
}
