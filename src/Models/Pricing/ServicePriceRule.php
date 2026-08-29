<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Pricing/ServicePriceRule.php
// ROADMAP socorro automotivo — Etapa 13 (preço governado por zona).
// Ver PricingZone.php para contexto — schema/models apenas, ainda não lido
// por nenhum controller de produção.

final class ServicePriceRule
{
    private const TBL = 'service_price_rules';

    public static function buscarVigente(int $pricingZoneId, int $serviceTypeId, ?string $vehicleCategory = null): ?array
    {
        // §PORTABILIDADE-SQL-01: `<=>` (NULL-safe equals) e CURDATE() são
        // MySQL-only e quebravam sob SQLite (tests/bootstrap.php) com
        // "syntax error". Reescrito de forma portável: comparação NULL-safe
        // via OR explícito, e a data de hoje calculada em PHP em vez de
        // depender de função de SQL específica do dialeto.
        $hoje = date('Y-m-d');
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . "
             WHERE pricing_zone_id = ? AND service_type_id = ?
               AND ((vehicle_category = ?) OR (vehicle_category IS NULL AND ? IS NULL))
               AND active = 1
               AND (effective_from IS NULL OR effective_from <= ?)
               AND (effective_until IS NULL OR effective_until >= ?)
             ORDER BY version DESC LIMIT 1"
        );
        $stmt->execute([$pricingZoneId, $serviceTypeId, $vehicleCategory, $vehicleCategory, $hoje, $hoje]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarPorZona(int $pricingZoneId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT spr.*, st.name AS service_name, st.code AS service_code
             FROM " . self::TBL . " spr
             JOIN service_types st ON st.id = spr.service_type_id
             WHERE spr.pricing_zone_id = ? AND spr.active = 1
             ORDER BY st.name ASC"
        );
        $stmt->execute([$pricingZoneId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT spr.*, st.name AS service_name, st.code AS service_code
             FROM " . self::TBL . " spr
             JOIN service_types st ON st.id = spr.service_type_id
             WHERE spr.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** Desativa a versão específica (não cria versão nova — só remove do cálculo). */
    public static function desativar(int $id): bool
    {
        return getPDO()->prepare("UPDATE " . self::TBL . " SET active = 0, updated_at = NOW() WHERE id = ?")->execute([$id]);
    }

    /**
     * Cria uma NOVA versão da regra (nunca sobrescreve — histórico completo
     * preservado via `version` incremental, condição necessária para
     * order_price_snapshots continuar válido mesmo depois de reprecificação).
     */
    public static function criarNovaVersao(int $pricingZoneId, int $serviceTypeId, array $dados, ?string $vehicleCategory = null): int
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(MAX(version), 0) FROM " . self::TBL . "
             WHERE pricing_zone_id = ? AND service_type_id = ?
               AND ((vehicle_category = ?) OR (vehicle_category IS NULL AND ? IS NULL))"
        );
        $stmt->execute([$pricingZoneId, $serviceTypeId, $vehicleCategory, $vehicleCategory]);
        $proximaVersao = (int)$stmt->fetchColumn() + 1;

        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (pricing_zone_id, service_type_id, vehicle_category, base_customer_price, minimum_customer_price,
                 maximum_customer_price, provider_base_amount, platform_fee_type, platform_fee_value,
                 included_distance_km, extra_distance_price, included_minutes, extra_minute_price,
                 night_multiplier, holiday_multiplier, effective_from, effective_until, active, version,
                 created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW(),NOW())"
        );
        $stmt->execute([
            $pricingZoneId,
            $serviceTypeId,
            $vehicleCategory,
            (float)($dados['base_customer_price'] ?? 0),
            (float)($dados['minimum_customer_price'] ?? 0),
            isset($dados['maximum_customer_price']) && $dados['maximum_customer_price'] !== '' ? (float)$dados['maximum_customer_price'] : null,
            (float)($dados['provider_base_amount'] ?? 0),
            (string)($dados['platform_fee_type'] ?? 'PERCENTAGE'),
            (float)($dados['platform_fee_value'] ?? 0),
            (float)($dados['included_distance_km'] ?? 0),
            (float)($dados['extra_distance_price'] ?? 0),
            (int)($dados['included_minutes'] ?? 0),
            (float)($dados['extra_minute_price'] ?? 0),
            (float)($dados['night_multiplier'] ?? 1.0),
            (float)($dados['holiday_multiplier'] ?? 1.0),
            $dados['effective_from'] ?? null,
            $dados['effective_until'] ?? null,
            $proximaVersao,
        ]);

        return (int)$pdo->lastInsertId();
    }
}
