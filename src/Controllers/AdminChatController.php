<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/AuthService.php';
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Models/Chat.php';
require_once __DIR__ . '/../Models/Pedido.php';

/** Controller da visualização administrativa de conversas de um pedido. */
final class AdminChatController extends BaseController
{
    public function index(): void
    {
        AuthService::requireAuth('admin');
        $pedidoId = (int)($_GET['pedido_id'] ?? 0);
        $mensagens = $pedidoId ? Chat::listarPorPedido($pedidoId) : [];
        $pedido = $pedidoId ? Pedido::buscarPorId($pedidoId) : null;
        require __DIR__ . '/../Views/admin/pedidodetalhe.php';
    }
}
