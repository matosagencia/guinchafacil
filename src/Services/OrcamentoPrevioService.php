<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/PedidoOrcamentoPrevio.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/ProviderWorkshopService.php';
require_once __DIR__ . '/AuditTrailService.php';

final class OrcamentoPrevioService
{
    public static function criarOrcamento(array $dados, int $actorId): array
    {
        $id = PedidoOrcamentoPrevio::criar($dados);
        AuditTrailService::evento('orcamento_previo_criado', __CLASS__, __FUNCTION__, [
            'orcamento_id' => $id, 'pedido_id' => (int)$dados['pedido_id'], 'actor_id' => $actorId,
        ]);
        return PedidoOrcamentoPrevio::buscarPorPedido((int)$dados['pedido_id']) ?? ['id' => $id];
    }

    public static function aprovarPeloCliente(int $pedidoId, int $clienteId): array
    {
        $orcamento = PedidoOrcamentoPrevio::buscarPorPedido($pedidoId);
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$orcamento || !$pedido || (int)($pedido['cliente_id'] ?? 0) !== $clienteId) {
            throw new InvalidArgumentException('Orçamento prévio não encontrado.');
        }
        if (!PedidoOrcamentoPrevio::aprovar((int)$orcamento['id'])) {
            throw new RuntimeException('Orçamento prévio não está pendente de aceite.');
        }
        AuditTrailService::evento('orcamento_previo_aprovado', __CLASS__, __FUNCTION__, [
            'orcamento_id' => (int)$orcamento['id'], 'pedido_id' => $pedidoId, 'cliente_id' => $clienteId,
        ]);
        return PedidoOrcamentoPrevio::buscarPorPedido($pedidoId) ?? $orcamento;
    }

    public static function calcularAbatimentoOS(int $pedidoId, float $valorOs): float
    {
        $orcamento = PedidoOrcamentoPrevio::buscarPorPedido($pedidoId);
        return $orcamento ? PedidoOrcamentoPrevio::calcularAbatimento($orcamento, $valorOs) : 0.0;
    }
}
