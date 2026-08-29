<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Vehicle/VehicleOperationalCategory.php
// ROADMAP socorro automotivo — Etapa 14 (catálogo veicular estruturado).
// Taxonomia usada pela Etapa 15 (compatibilidade prestador×veículo) para
// decidir quem pode atender qual veículo — não confundir com a
// `categoria_tarifa` antiga (4 baldes usados só para precificar reboque).

class VehicleOperationalCategory
{
    private const TBL = 'vehicle_operational_categories';

    public static function listarAtivas(): array
    {
        $stmt = getPDO()->query("SELECT * FROM " . self::TBL . " WHERE active = 1 ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorCodigo(string $code): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
