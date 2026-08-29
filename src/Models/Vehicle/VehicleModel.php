<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Vehicle/VehicleModel.php
// ROADMAP socorro automotivo — Etapa 14 (catálogo veicular estruturado).

class VehicleModel
{
    private const TBL = 'vehicle_models';

    public static function listarPorMarca(int $brandId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE brand_id = ? AND active = 1 ORDER BY name ASC");
        $stmt->execute([$brandId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Idempotente via UNIQUE(brand_id, name). */
    public static function criar(int $brandId, string $name): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (brand_id, name, active) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE active = 1"
        );
        $stmt->execute([$brandId, trim($name)]);
        $stmt2 = getPDO()->prepare("SELECT id FROM " . self::TBL . " WHERE brand_id = ? AND name = ? LIMIT 1");
        $stmt2->execute([$brandId, trim($name)]);
        return (int)($stmt2->fetchColumn() ?: 0);
    }

    /**
     * §CATALOGO-VISUAL-01: mesma semântica de VehicleBrand::atualizar() —
     * $imagePath null preserva a imagem atual, string vazia remove
     * (volta pro placeholder genérico de silhueta).
     */
    public static function atualizar(int $id, string $name, bool $active, ?string $imagePath = null): bool
    {
        if ($imagePath === null) {
            $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET name = ?, active = ? WHERE id = ?");
            return $stmt->execute([trim($name), $active ? 1 : 0, $id]);
        }
        $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET name = ?, active = ?, image_path = ? WHERE id = ?");
        return $stmt->execute([trim($name), $active ? 1 : 0, $imagePath !== '' ? $imagePath : null, $id]);
    }

    /** Contagem de modelos ativos por marca — usado no grid de marcas do admin/cliente. */
    public static function contarPorMarca(): array
    {
        $stmt = getPDO()->query(
            "SELECT brand_id, COUNT(*) AS total FROM " . self::TBL . " WHERE active = 1 GROUP BY brand_id"
        );
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['brand_id']] = (int)$row['total'];
        }
        return $out;
    }
}
