<?php

declare(strict_types=1);

require_once __DIR__ . '/../DTO/PedidoQuoteRequest.php';
require_once __DIR__ . '/../DTO/PedidoCreateRequest.php';
require_once __DIR__ . '/../Services/PedidoCoreService.php';
require_once __DIR__ . '/../Services/Pricing/PedidoPricingService.php';
require_once __DIR__ . '/../Services/Financial/ChargePolicyService.php';
require_once __DIR__ . '/../Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../Services/AuthService.php';

final class PedidoController
{
    public function cotar(): void
    {
        $this->json(function (): array {
            return $this->core()->cotar(new PedidoQuoteRequest($this->payload()));
        });
    }

    public function criar(): void
    {
        $this->json(function (): array {
            $payload = $this->payload();
            if (!AuthService::validarCsrfToken((string)($payload['csrf_token'] ?? ''))) {
                http_response_code(403);
                throw new RuntimeException('Sessao expirada. Recarregue a pagina e tente novamente.');
            }

            $tipoUsuario = (string)($_SESSION['usuario_tipo'] ?? $_SESSION['tipo'] ?? '');
            $usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
            if ($tipoUsuario === 'cliente') {
                $payload['cliente_id'] = $usuarioId;
                $payload['actor_type'] = 'cliente';
                $payload['actor_id'] = $usuarioId;
                unset($payload['provider_id'], $payload['forcar_prestador_id']);
            } elseif ($tipoUsuario === 'admin') {
                $payload['actor_type'] = 'admin';
                $payload['actor_id'] = $usuarioId;
                if (!empty($payload['forcar_prestador_id']) && empty($payload['provider_id'])) {
                    $payload['provider_id'] = (int)$payload['forcar_prestador_id'];
                }
            } else {
                http_response_code(401);
                throw new RuntimeException('Autenticacao obrigatoria para criar pedido.');
            }

            return ['pedido_id' => $this->core()->criar(new PedidoCreateRequest($payload))];
        }, true);
    }

    private function json(callable $handler, bool $mutable = false): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            echo json_encode(['ok' => true, 'data' => $handler(), 'error' => null], JSON_UNESCAPED_UNICODE);
        } catch (InvalidArgumentException $e) {
            if (http_response_code() < 400) {
                http_response_code(422);
            }
            echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            if (http_response_code() < 400) {
                http_response_code($mutable ? 500 : 422);
            }
            echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'ORDER_CORE_ERROR', 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    private function payload(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        return is_array($json) ? ($json + $_POST) : ($_POST + $_GET);
    }

    private function core(): PedidoCoreService
    {
        return new PedidoCoreService(getPDO(), new PedidoPricingService(), new ChargePolicyService(), new PedidoTransitionService());
    }
}
