<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Dispatch/OrderVehicleRequirement.php
// ROADMAP socorro automotivo — Etapa 15 (snapshot do cenário do pedido).

class OrderVehicleRequirement
{
    private const TBL = 'order_vehicle_requirements';

    public static function buscarPorPedido(int $orderId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE order_id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Upsert idempotente via UNIQUE(order_id) — refazer o snapshot não duplica. */
    public static function salvar(int $orderId, array $d): int
    {
        $pdo = getPDO();
        $existente = self::buscarPorPedido($orderId);
        if ($existente) {
            $stmt = $pdo->prepare(
                "UPDATE " . self::TBL . " SET
                    vehicle_id = ?, vehicle_category = ?, declared_vehicle_type = ?, fuel_type = ?,
                    electric_vehicle = ?, hybrid_vehicle = ?, damaged_vehicle = ?, wheels_locked = ?,
                    underground_location = ?, difficult_access = ?, spare_tire_available = ?,
                    locking_bolt_present = ?, requires_platform = ?, manual_review_required = ?,
                    verification_status = ?, requirements_json = ?, snapshot_version = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute(array_merge(self::bind($d), [(int)$existente['id']]));
            return (int)$existente['id'];
        }
        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (vehicle_id, vehicle_category, declared_vehicle_type, fuel_type,
                 electric_vehicle, hybrid_vehicle, damaged_vehicle, wheels_locked,
                 underground_location, difficult_access, spare_tire_available,
                 locking_bolt_present, requires_platform, manual_review_required,
                 verification_status, requirements_json, snapshot_version, order_id, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute(array_merge(self::bind($d), [$orderId]));
        return (int)$pdo->lastInsertId();
    }

    /** Ordem dos binds compartilhada entre INSERT e UPDATE (menos order_id/id). */
    private static function bind(array $d): array
    {
        $tin = static fn($v) => $v === null ? null : (int)(bool)$v;
        return [
            (int)($d['vehicle_id'] ?? 0),
            $d['vehicle_category'] ?? null,
            $d['declared_vehicle_type'] ?? null,
            $d['fuel_type'] ?? null,
            (int)($d['electric_vehicle'] ?? 0),
            (int)($d['hybrid_vehicle'] ?? 0),
            $tin($d['damaged_vehicle'] ?? null),
            $tin($d['wheels_locked'] ?? null),
            $tin($d['underground_location'] ?? null),
            $tin($d['difficult_access'] ?? null),
            $tin($d['spare_tire_available'] ?? null),
            $tin($d['locking_bolt_present'] ?? null),
            (int)($d['requires_platform'] ?? 0),
            (int)($d['manual_review_required'] ?? 0),
            $d['verification_status'] ?? null,
            isset($d['requirements_json']) ? (is_string($d['requirements_json']) ? $d['requirements_json'] : json_encode($d['requirements_json'])) : null,
            $d['snapshot_version'] ?? 'v1',
        ];
    }
}
