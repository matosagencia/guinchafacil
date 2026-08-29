<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Catalog/ProviderEquipment.php
// ROADMAP socorro automotivo — Fundamento 2 (equipamentos do prestador).

require_once __DIR__ . '/../../Services/Logger.php';

class ProviderEquipment
{
    private const TBL = 'provider_equipment';

    public const STATUS_PENDENTE = 'PENDENTE_VERIFICACAO';
    public const STATUS_ATIVO    = 'ATIVO';
    public const STATUS_VENCIDO  = 'VENCIDO';

    public static function listarPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? ORDER BY equipment_code ASC");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Equipamentos ATIVO e não vencidos — usado pelo filtro de matching (Etapa 4). */
    public static function listarAtivosPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . "
             WHERE provider_id = ? AND status = ? AND (expires_at IS NULL OR expires_at >= CURDATE())"
        );
        $stmt->execute([$providerId, self::STATUS_ATIVO]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function possuiEquipamentoAtivo(int $providerId, string $equipmentCode): bool
    {
        $stmt = getPDO()->prepare(
            "SELECT COUNT(*) FROM " . self::TBL . "
             WHERE provider_id = ? AND equipment_code = ? AND status = ?
               AND (expires_at IS NULL OR expires_at >= CURDATE())"
        );
        $stmt->execute([$providerId, strtoupper(trim($equipmentCode)), self::STATUS_ATIVO]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /** Cadastro/edição idempotente por (provider_id, equipment_code) — ver UNIQUE no schema. */
    public static function declarar(int $providerId, string $equipmentCode, array $dados = []): int
    {
        $pdo = getPDO();
        $code = strtoupper(trim($equipmentCode));

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO " . self::TBL . " (provider_id, equipment_code, description, quantity, status, created_at, updated_at)
                 VALUES (?,?,?,?,?,NOW(),NOW())
                 ON DUPLICATE KEY UPDATE
                    description = VALUES(description),
                    quantity = VALUES(quantity),
                    updated_at = NOW()"
            );
            $stmt->execute([
                $providerId,
                $code,
                $dados['description'] ?? null,
                (int)($dados['quantity'] ?? 1),
                self::STATUS_PENDENTE,
            ]);
            $id = (int)$pdo->lastInsertId();
            if ($id > 0) {
                return $id;
            }
            $stmt2 = $pdo->prepare("SELECT id FROM " . self::TBL . " WHERE provider_id = ? AND equipment_code = ? LIMIT 1");
            $stmt2->execute([$providerId, $code]);
            return (int)($stmt2->fetchColumn() ?: 0);
        } catch (\PDOException $e) {
            Logger::exception('ProviderEquipment', 'declarar', 'catalogo_prestador', $e,
                ['provider_id' => $providerId, 'equipment_code' => $code]);
            return 0;
        }
    }

    public static function verificar(int $equipmentId, int $adminId, bool $vencido = false): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET status = ?, verified_at = NOW(), updated_at = NOW() WHERE id = ?"
        );
        $status = $vencido ? self::STATUS_VENCIDO : self::STATUS_ATIVO;
        $ok = $stmt->execute([$status, $equipmentId]);

        Logger::log(Logger::LEVEL_INFO, 'ProviderEquipment', 'verificar', 'catalogo_prestador',
            "Equipamento #{$equipmentId} -> {$status} verificado por admin #{$adminId}",
            ['equipment_id' => $equipmentId, 'status' => $status, 'admin_id' => $adminId]);

        return $ok;
    }
}
