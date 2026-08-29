<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Provider/ProviderUnit.php
// ROADMAP socorro automotivo — Etapa 12 (camada aditiva de prestador).
// Ver Provider.php para contexto. Uma provider_unit é o recurso físico
// enviado ao cliente (moto, carro de apoio, van, caminhão-plataforma) — o
// pedido é atendido por uma pessoa usando uma unidade, não só por uma pessoa.

final class ProviderUnit
{
    private const TBL = 'provider_units';

    public const TYPE_MOTORCYCLE = 'MOTORCYCLE';
    public const TYPE_SUPPORT_CAR = 'SUPPORT_CAR';
    public const TYPE_SERVICE_VAN = 'SERVICE_VAN';
    public const TYPE_TOW_TRUCK = 'TOW_TRUCK';
    public const TYPE_HEAVY_TOW_TRUCK = 'HEAVY_TOW_TRUCK';

    public const TYPES = [
        self::TYPE_MOTORCYCLE, self::TYPE_SUPPORT_CAR, self::TYPE_SERVICE_VAN,
        self::TYPE_TOW_TRUCK, self::TYPE_HEAVY_TOW_TRUCK,
    ];

    public static function listarPorProvider(int $providerId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? ORDER BY id ASC");
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function buscarPorGuinchoId(int $guinchoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE legacy_guincho_id = ? LIMIT 1");
        $stmt->execute([$guinchoId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function criar(int $providerId, string $unitType, ?string $plate = null): int
    {
        if (!in_array($unitType, self::TYPES, true)) {
            throw new \InvalidArgumentException("unit_type inválido: {$unitType}");
        }

        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (provider_id, unit_type, plate, active, legacy_guincho_id, created_at, updated_at)
             VALUES (?,?,?,1,NULL,NOW(),NOW())"
        );
        $stmt->execute([$providerId, $unitType, $plate]);
        return (int)getPDO()->lastInsertId();
    }
}
