<?php
declare(strict_types=1);

final class MarketingEventController extends BaseController
{
    public function store(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
            return;
        }
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) $body = $_POST;
        $stored = MarketingEventService::record($body);
        echo json_encode(['ok' => true, 'stored' => $stored], JSON_UNESCAPED_UNICODE);
    }
}
