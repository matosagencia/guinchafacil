<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Dispatch/ServiceVehicleRequirement.php
// ROADMAP socorro automotivo — Etapa 15.

class ServiceVehicleRequirement
{
    private const TBL = 'service_vehicle_requirements';

    /**
     * Requisito mais específico primeiro: procura por (serviço, categoria);
     * se não houver, cai para a regra geral (categoria NULL). Retorna null
     * quando o serviço não tem nenhum requisito configurado.
     */
    public static function resolver(int $serviceTypeId, string $vehicleCategory): ?array
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT * FROM " . self::TBL . "
             WHERE service_type_id = ? AND vehicle_category = ? AND active = 1 LIMIT 1"
        );
        $stmt->execute([$serviceTypeId, $vehicleCategory]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        $stmt = $pdo->prepare(
            "SELECT * FROM " . self::TBL . "
             WHERE service_type_id = ? AND vehicle_category IS NULL AND active = 1 LIMIT 1"
        );
        $stmt->execute([$serviceTypeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarPorServico(int $serviceTypeId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . " WHERE service_type_id = ? ORDER BY vehicle_category IS NULL DESC, vehicle_category ASC"
        );
        $stmt->execute([$serviceTypeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upsert idempotente via UNIQUE(service_type_id, vehicle_category).
     * $vehicleCategory vazio/null = regra geral do serviço.
     */
    public static function salvar(int $serviceTypeId, ?string $vehicleCategory, array $d): int
    {
        $cat = ($vehicleCategory === null || $vehicleCategory === '') ? null : $vehicleCategory;
        $pdo = getPDO();

        // Localiza existente (categoria NULL precisa de comparação especial).
        if ($cat === null) {
            $stmt = $pdo->prepare("SELECT id FROM " . self::TBL . " WHERE service_type_id = ? AND vehicle_category IS NULL LIMIT 1");
            $stmt->execute([$serviceTypeId]);
        } else {
            $stmt = $pdo->prepare("SELECT id FROM " . self::TBL . " WHERE service_type_id = ? AND vehicle_category = ? LIMIT 1");
            $stmt->execute([$serviceTypeId, $cat]);
        }
        $existenteId = (int)($stmt->fetchColumn() ?: 0);

        $bind = [
            (int)($d['requires_platform'] ?? 0) ? 1 : 0,
            (int)($d['requires_winch'] ?? 0) ? 1 : 0,
            (int)($d['requires_dolly'] ?? 0) ? 1 : 0,
            (int)($d['requires_battery_tester'] ?? 0) ? 1 : 0,
            (int)($d['requires_jump_starter'] ?? 0) ? 1 : 0,
            (int)($d['requires_hydraulic_jack'] ?? 0) ? 1 : 0,
            $d['minimum_unit_capacity_kg'] !== '' && isset($d['minimum_unit_capacity_kg']) ? (float)$d['minimum_unit_capacity_kg'] : null,
            (int)($d['electric_certification_required'] ?? 0) ? 1 : 0,
            (int)($d['active'] ?? 1) ? 1 : 0,
        ];

        if ($existenteId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE " . self::TBL . " SET
                    requires_platform = ?, requires_winch = ?, requires_dolly = ?,
                    requires_battery_tester = ?, requires_jump_starter = ?, requires_hydraulic_jack = ?,
                    minimum_unit_capacity_kg = ?, electric_certification_required = ?, active = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute(array_merge($bind, [$existenteId]));
            return $existenteId;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (requires_platform, requires_winch, requires_dolly, requires_battery_tester,
                 requires_jump_starter, requires_hydraulic_jack, minimum_unit_capacity_kg,
                 electric_certification_required, active, service_type_id, vehicle_category, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute(array_merge($bind, [$serviceTypeId, $cat]));
        return (int)$pdo->lastInsertId();
    }
}
