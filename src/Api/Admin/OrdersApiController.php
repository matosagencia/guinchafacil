<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Services/Admin/OrderWorklistService.php';

final class OrdersApiController
{
    public function index(): void
    {
        $this->json(['ok' => true, 'data' => OrderWorklistService::list($_GET)]);
    }

    public function show(int $id): void
    {
        $data = OrderWorklistService::find($id);
        if ($data === null) { $this->json(['ok' => false, 'error' => 'order_not_found'], 404); return; }
        $this->json(['ok' => true, 'data' => $data]);
    }

    public function tracking(int $id): void { $this->json(['ok' => true, 'data' => OrderWorklistService::tracking($id)]); }
    public function timeline(int $id): void { $this->json(['ok' => true, 'data' => OrderWorklistService::timeline($id)]); }

    public function messages(int $id): void
    {
        $this->json(['ok' => true, 'data' => OrderWorklistService::messages($id)]);
    }

    public function sendMessage(int $id): void
    {
        $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
        if (!AuthService::validarCsrfToken($token)) { $this->json(['ok' => false, 'error' => 'csrf_invalid'], 403); return; }
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) $body = $_POST;
        $user = AuthService::getCurrentUser();
        try {
            $data = OrderWorklistService::sendMessage($id, (int)$user['id'], (string)($body['message'] ?? $body['mensagem'] ?? ''), isset($body['idempotency_key']) ? (string)$body['idempotency_key'] : null);
            $this->json(['ok' => true, 'data' => $data], 201);
        } catch (InvalidArgumentException $e) { $this->json(['ok' => false, 'error' => $e->getMessage()], 422); }
          catch (RuntimeException $e) { $this->json(['ok' => false, 'error' => $e->getMessage()], 404); }
    }

    private function json(array $payload, int $status = 200): void
    {
        if (ob_get_length() > 0) ob_clean();
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
