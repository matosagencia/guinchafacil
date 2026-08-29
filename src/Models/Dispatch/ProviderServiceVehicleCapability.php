<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Dispatch/ProviderServiceVehicleCapability.php
// ROADMAP socorro automotivo — Etapa 15.

class ProviderServiceVehicleCapability
{
    private const TBL = 'provider_service_vehicle_capabilities';

    /** Existe QUALQUER config veicular deste prestador para este serviço? */
    public static function existeConfig(int $providerId, int $serviceTypeId): bool
    {
        $stmt = getPDO()->prepare(
            "SELECT 1 FROM " . self::TBL . " WHERE provider_id = ? AND service_type_id = ? LIMIT 1"
        );
        $stmt->execute([$providerId, $serviceTypeId]);
        return (bool)$stmt->fetchColumn();
    }

    /** Linha específica para (prestador, serviço, categoria) — ou null. */
    public static function buscar(int $providerId, int $serviceTypeId, string $vehicleCategory): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . "
             WHERE provider_id = ? AND service_type_id = ? AND vehicle_category = ? LIMIT 1"
        );
        $stmt->execute([$providerId, $serviceTypeId, $vehicleCategory]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT pc.*, st.code AS service_code, st.name AS service_name
             FROM " . self::TBL . " pc
             JOIN service_types st ON st.id = pc.service_type_id
             WHERE pc.provider_id = ?
             ORDER BY st.name ASC, pc.vehicle_category ASC"
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert idempotente via UNIQUE(provider_id, service_type_id, vehicle_category). */
    public static function salvar(int $providerId, int $serviceTypeId, string $vehicleCategory, array $dados): int
    {
        $pdo = getPDO();
        $existente = self::buscar($providerId, $serviceTypeId, $vehicleCategory);
        if ($existente) {
            $stmt = $pdo->prepare(
                "UPDATE " . self::TBL . " SET
                    approval_status = ?, enabled = ?, max_vehicle_weight_kg = ?,
                    supports_electric = ?, supports_hybrid = ?, supports_locked_wheels = ?,
                    supports_damaged_vehicle = ?, supports_subsoil_access = ?, requires_manual_confirmation = ?,
                    updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([
                $dados['approval_status'] ?? $existente['approval_status'],
                array_key_exists('enabled', $dados) ? (int)$dados['enabled'] : (int)$existente['enabled'],
                $dados['max_vehicle_weight_kg'] ?? $existente['max_vehicle_weight_kg'],
                (int)($dados['supports_electric'] ?? $existente['supports_electric']),
                (int)($dados['supports_hybrid'] ?? $existente['supports_hybrid']),
                (int)($dados['supports_locked_wheels'] ?? $existente['supports_locked_wheels']),
                (int)($dados['supports_damaged_vehicle'] ?? $existente['supports_damaged_vehicle']),
                (int)($dados['supports_subsoil_access'] ?? $existente['supports_subsoil_access']),
                (int)($dados['requires_manual_confirmation'] ?? $existente['requires_manual_confirmation']),
                (int)$existente['id'],
            ]);
            return (int)$existente['id'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (provider_id, service_type_id, vehicle_category, approval_status, enabled,
                 max_vehicle_weight_kg, supports_electric, supports_hybrid, supports_locked_wheels,
                 supports_damaged_vehicle, supports_subsoil_access, requires_manual_confirmation,
                 created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $stmt->execute([
            $providerId, $serviceTypeId, $vehicleCategory,
            $dados['approval_status'] ?? 'APPROVED',
            array_key_exists('enabled', $dados) ? (int)$dados['enabled'] : 1,
            $dados['max_vehicle_weight_kg'] ?? null,
            (int)($dados['supports_electric'] ?? 1),
            (int)($dados['supports_hybrid'] ?? 1),
            (int)($dados['supports_locked_wheels'] ?? 0),
            (int)($dados['supports_damaged_vehicle'] ?? 0),
            (int)($dados['supports_subsoil_access'] ?? 0),
            (int)($dados['requires_manual_confirmation'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }
}
