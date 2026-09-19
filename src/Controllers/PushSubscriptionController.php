<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/../Models/PushSubscription.php';

class PushSubscriptionController extends BaseController
{
    public function subscribe(): void
    {
        $user = AuthService::requireAuth(null);
        $this->assertAllowedProfile($user);

        $input = $this->getRequestData();
        if (!AuthService::validarCsrfToken((string)($input['csrf_token'] ?? ''))) {
            $this->json(['ok' => false, 'erro' => 'csrf_invalido'], 403);
        }

        $subscription = $input['subscription'] ?? null;
        if (!is_array($subscription)) {
            $this->json(['ok' => false, 'erro' => 'subscription_invalida'], 422);
        }

        try {
            $saved = PushSubscription::upsertForUser(
                (int)$user['id'],
                (string)$user['tipo'],
                $subscription,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            );

            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'PUSH',
                'phase' => 'subscribe',
                'code' => 'PUSH-001',
                'message' => 'Subscription Web Push registrada.',
                'context' => [
                    'usuario_id' => (int)$user['id'],
                    'tipo_usuario' => (string)$user['tipo'],
                    'endpoint_hash' => $saved['endpoint_hash'],
                ],
            ]);

            $this->json(['ok' => true, 'assinatura' => $saved]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    public function unsubscribe(): void
    {
        $user = AuthService::requireAuth(null);
        $this->assertAllowedProfile($user);

        $input = $this->getRequestData();
        if (!AuthService::validarCsrfToken((string)($input['csrf_token'] ?? ''))) {
            $this->json(['ok' => false, 'erro' => 'csrf_invalido'], 403);
        }

        $endpoint = (string)($input['endpoint'] ?? '');
        if ($endpoint === '') {
            $this->json(['ok' => false, 'erro' => 'endpoint_invalido'], 422);
        }

        try {
            $deleted = PushSubscription::deleteForUserEndpoint((int)$user['id'], (string)$user['tipo'], $endpoint);

            Logger::event([
                'level' => Logger::LEVEL_INFO,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'PUSH',
                'phase' => 'unsubscribe',
                'code' => 'PUSH-002',
                'message' => $deleted ? 'Subscription Web Push removida.' : 'Subscription Web Push nao encontrada.',
                'context' => [
                    'usuario_id' => (int)$user['id'],
                    'tipo_usuario' => (string)$user['tipo'],
                    'endpoint_hash' => hash('sha256', $endpoint),
                ],
            ]);

            $this->json(['ok' => true, 'removida' => $deleted]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    private function assertAllowedProfile(array $user): void
    {
        if (!in_array((string)($user['tipo'] ?? ''), ['guincho', 'especialista', 'admin'], true)) {
            $this->json(['ok' => false, 'erro' => 'perfil_nao_suportado'], 403);
        }
    }

    private function getRequestData(): array
    {
        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '', true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }

    private function json(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
