<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Catalog/ProviderVehicleCompatibility.php
// ROADMAP socorro automotivo — Fundamento 2 (compatibilidade de veículo do prestador).

class ProviderVehicleCompatibility
{
    private const TBL = 'provider_vehicle_compatibility';

    public static function listarPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? ORDER BY vehicle_category ASC");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function suportaCategoria(int $providerId, string $vehicleCategory): bool
    {
        $stmt = getPDO()->prepare(
            "SELECT COUNT(*) FROM " . self::TBL . " WHERE provider_id = ? AND vehicle_category = ?"
        );
        $stmt->execute([$providerId, strtoupper(trim($vehicleCategory))]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /** Idempotente por (provider_id, vehicle_category) — ver UNIQUE no schema. */
    public static function declarar(int $providerId, string $vehicleCategory, array $dados = []): int
    {
        $pdo = getPDO();
        $categoria = strtoupper(trim($vehicleCategory));

        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (provider_id, vehicle_category, max_weight_kg, max_height_m, supports_electric,
                 supports_hybrid, supports_motorcycle, supports_utility, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                max_weight_kg = VALUES(max_weight_kg),
                max_height_m = VALUES(max_height_m),
                supports_electric = VALUES(supports_electric),
                supports_hybrid = VALUES(supports_hybrid),
                supports_motorcycle = VALUES(supports_motorcycle),
                supports_utility = VALUES(supports_utility),
                updated_at = NOW()"
        );
        $stmt->execute([
            $providerId,
            $categoria,
            $dados['max_weight_kg'] ?? null,
            $dados['max_height_m'] ?? null,
            !empty($dados['supports_electric']) ? 1 : 0,
            !empty($dados['supports_hybrid']) ? 1 : 0,
            !empty($dados['supports_motorcycle']) ? 1 : 0,
            !empty($dados['supports_utility']) ? 1 : 0,
        ]);

        $id = (int)$pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }
        $stmt2 = $pdo->prepare("SELECT id FROM " . self::TBL . " WHERE provider_id = ? AND vehicle_category = ? LIMIT 1");
        $stmt2->execute([$providerId, $categoria]);
        return (int)($stmt2->fetchColumn() ?: 0);
    }
}
