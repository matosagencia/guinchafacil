<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Models/Financial/OrderChargeItem.php';
require_once __DIR__ . '/../../Models/Financial/OrderChargePayment.php';
require_once __DIR__ . '/../Diagnostico/DiagnosticoService.php';
require_once __DIR__ . '/../Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../DTO/PedidoTransitionRequest.php';

final class SupplementalChargeService
{
    public static function criarCheckout(int $orderId, int $chargeItemId, string $method = 'simulacao'): array
    {
        $charge = OrderChargeItem::buscarPorId($chargeItemId);
        if (!$charge || (int)$charge['order_id'] !== $orderId) {
            throw new InvalidArgumentException('Item de cobrança inválido.');
        }
        $total = 0.0;
        foreach (OrderChargeItem::listarPorPedido($orderId) as $row) {
            if (($row['charge_status'] ?? '') !== 'CANCELLED') $total += (float)$row['gross_amount'];
        }
        return OrderChargePayment::criar($chargeItemId, $orderId, round($total, 2), $method);
    }

    public static function buscarContextoCliente(int $chargeItemId, int $clienteId): ?array
    {
        $stmt = getPDO()->prepare(
            'SELECT cp.*, ci.order_id, ci.gross_amount, ci.description, p.cliente_id, p.status AS pedido_status
             FROM order_charge_payments cp
             JOIN order_charge_items ci ON ci.id = cp.charge_item_id
             JOIN pedidos p ON p.id = ci.order_id
             WHERE cp.charge_item_id = ? AND p.cliente_id = ? LIMIT 1'
        );
        $stmt->execute([$chargeItemId, $clienteId]);
        $ctx = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$ctx) return null;
        $items = getPDO()->prepare('SELECT description, quantity, unit_amount, gross_amount, charge_type FROM order_charge_items WHERE order_id = ? AND charge_status <> ? ORDER BY id ASC');
        $items->execute([(int)$ctx['order_id'], 'CANCELLED']);
        $ctx['charge_items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        return $ctx;
    }

    public static function buscarContextoPorPagamento(int $paymentId): ?array
    {
        $stmt = getPDO()->prepare(
            'SELECT cp.*, ci.order_id, p.cliente_id
             FROM order_charge_payments cp
             JOIN order_charge_items ci ON ci.id = cp.charge_item_id
             JOIN pedidos p ON p.id = ci.order_id
             WHERE cp.id = ? LIMIT 1'
        );
        $stmt->execute([$paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function aprovarPagamentoSimulado(int $orderId, string $externalId): bool
    {
        $charges = OrderChargeItem::listarPorPedido($orderId);
        $pending = array_values(array_filter($charges, static fn(array $c): bool => ($c['charge_status'] ?? '') === 'AWAITING_CUSTOMER_APPROVAL'));
        if (!$pending) return false;
        $charge = $pending[0];
        $payment = OrderChargePayment::buscarPorCharge((int)$charge['id']);
        if (!$payment) $payment = self::criarCheckout($orderId, (int)$charge['id']);
        if (!OrderChargePayment::aprovarSimulado((int)$payment['id'], $externalId)) return false;
        foreach ($pending as $row) OrderChargeItem::atualizarChargeStatus((int)$row['id'], 'APPROVED');
        $result = PedidoTransitionService::transition(new PedidoTransitionRequest('system', 0, $orderId, 'em_execucao_servico'));
        if (!$result->ok) return false;
        self::baixarEstoqueAposPagamento($orderId);
        return true;
    }

    public static function aprovarPagamentoGateway(int $orderId, int $chargePaymentId, string $externalId, string $payload): bool
    {
        $stmt = getPDO()->prepare('SELECT * FROM order_charge_payments WHERE id = ? AND order_id = ? LIMIT 1');
        $stmt->execute([$chargePaymentId, $orderId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payment) return false;
        if (($payment['status'] ?? '') === 'APPROVED') return self::aprovarTodosItensEExecutar($orderId);
        if (($payment['status'] ?? '') !== 'PENDING') return false;
        $stmt = getPDO()->prepare("UPDATE order_charge_payments SET status='APPROVED', external_id=?, approved_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='PENDING'");
        $stmt->execute([$externalId, $chargePaymentId]);
        return self::aprovarTodosItensEExecutar($orderId);
    }

    private static function aprovarTodosItensEExecutar(int $orderId): bool
    {
        $charges = OrderChargeItem::listarPorPedido($orderId);
        foreach ($charges as $row) {
            if (($row['charge_status'] ?? '') === 'AWAITING_CUSTOMER_APPROVAL') OrderChargeItem::atualizarChargeStatus((int)$row['id'], 'APPROVED');
        }
        $result = PedidoTransitionService::transition(new PedidoTransitionRequest('system', 0, $orderId, 'em_execucao_servico'));
        if (!$result->ok) return false;
        // Mantém a cobrança complementar no ledger canônico do incidente.
        $ctx = getPDO()->prepare('SELECT incidente_id FROM pedidos WHERE id=?');
        $ctx->execute([$orderId]); $incidenteId = (int)$ctx->fetchColumn();
        if ($incidenteId > 0) {
            $sum = getPDO()->prepare("SELECT COALESCE(SUM(gross_amount),0) FROM order_charge_items WHERE order_id=? AND charge_status='APPROVED'");
            $sum->execute([$orderId]);
            require_once __DIR__ . '/../IncidenteFinanceiroService.php';
            IncidenteFinanceiroService::registrar($incidenteId, 'cobranca_cliente', 'cobranca_complementar', $orderId, (float)$sum->fetchColumn(), 'confirmado');
        }
        self::baixarEstoqueAposPagamento($orderId);
        return true;
    }

    private static function baixarEstoqueAposPagamento(int $orderId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT * FROM pedido_orcamentos WHERE pedido_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orcamento) return;
        $itens = json_decode((string)$orcamento['itens_json'], true) ?: [];
        $pedidoStmt = $pdo->prepare('SELECT guincho_id FROM pedidos WHERE id = ?');
        $pedidoStmt->execute([$orderId]);
        $providerId = (int)$pedidoStmt->fetchColumn();
        require_once __DIR__ . '/../Estoque/EstoqueService.php';
        foreach ($itens as $item) {
            $productId = (int)($item['produto_id'] ?? 0);
            if ($productId > 0) EstoqueService::baixarPorPedido($providerId, $productId, $orderId, max(1, (int)($item['quantidade'] ?? 1)), 'Cobrança complementar aprovada');
        }
    }
}
