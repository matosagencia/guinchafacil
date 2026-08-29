<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Vehicle/VehicleBrand.php
// ROADMAP socorro automotivo — Etapa 14 (catálogo veicular estruturado).

class VehicleBrand
{
    private const TBL = 'vehicle_brands';

    public static function listarAtivas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " WHERE active = 1 ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarTodas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Idempotente via UNIQUE(name) — cadastro repetido não duplica marca. */
    public static function criar(string $name): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (name, active) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE active = 1"
        );
        $stmt->execute([trim($name)]);
        $stmt2 = getPDO()->prepare("SELECT id FROM " . self::TBL . " WHERE name = ? LIMIT 1");
        $stmt2->execute([trim($name)]);
        return (int)($stmt2->fetchColumn() ?: 0);
    }

    /**
     * §CATALOGO-VISUAL-01: admin edita nome/status e opcionalmente troca o
     * logo (path já resolvido por MediaUploadService::storeVehicleBrandLogo
     * antes de chegar aqui — este model não sabe nada de upload).
     * $logoPath null preserva o logo atual; string vazia explicitamente
     * remove (volta pro badge de inicial).
     */
    public static function atualizar(int $id, string $name, bool $active, ?string $logoPath = null): bool
    {
        if ($logoPath === null) {
            $stmt = getPDO()->prepare(
                "UPDATE " . self::TBL . " SET name = ?, active = ? WHERE id = ?"
            );
            return $stmt->execute([trim($name), $active ? 1 : 0, $id]);
        }
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET name = ?, active = ?, logo_path = ? WHERE id = ?"
        );
        return $stmt->execute([trim($name), $active ? 1 : 0, $logoPath !== '' ? $logoPath : null, $id]);
    }
}
