<?php
declare(strict_types=1);

final class OrderChargePayment
{
    public static function criar(int $chargeItemId, int $orderId, float $amount, string $method = 'simulacao'): array
    {
        $pdo = getPDO();
        $key = 'charge-payment:' . $chargeItemId;
        $stmt = $pdo->prepare('SELECT * FROM order_charge_payments WHERE idempotency_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) return $existing;
        $stmt = $pdo->prepare('INSERT INTO order_charge_payments (charge_item_id, order_id, amount, method, status, idempotency_key, created_at, updated_at) VALUES (?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $stmt->execute([$chargeItemId, $orderId, $amount, $method, 'PENDING', $key]);
        $stmt = $pdo->prepare('SELECT * FROM order_charge_payments WHERE id = ?');
        $stmt->execute([(int)$pdo->lastInsertId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public static function buscarPorCharge(int $chargeItemId): ?array
    {
        $stmt = getPDO()->prepare('SELECT * FROM order_charge_payments WHERE charge_item_id = ? LIMIT 1');
        $stmt->execute([$chargeItemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function aprovarSimulado(int $id, string $externalId): bool
    {
        $stmt = getPDO()->prepare("UPDATE order_charge_payments SET status='APPROVED', external_id=?, approved_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='PENDING'");
        $stmt->execute([$externalId, $id]);
        return $stmt->rowCount() > 0 || self::status($id) === 'APPROVED';
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare('SELECT * FROM order_charge_payments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function status(int $id): ?string
    {
        $stmt = getPDO()->prepare('SELECT status FROM order_charge_payments WHERE id = ?');
        $stmt->execute([$id]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string)$v;
    }
}
