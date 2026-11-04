<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Services/MarketingEventReportService.php';

final class AdminMarketingEventsController extends BaseController
{
    public function index(): void
    {
        AuthService::requireAuth('admin');
        $inicio = (string)($_GET['inicio'] ?? date('Y-m-01'));
        $fim = (string)($_GET['fim'] ?? date('Y-m-d'));
        $eventos = MarketingEventReportService::resumo($inicio, $fim);
        require __DIR__ . '/../Views/admin/marketing_eventos.php';
    }
}
