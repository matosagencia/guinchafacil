-- Atualiza a faixa de preco do atendimento de pneu em Niteroi.
-- Pesquisa de mercado em 23/08/2026: faixa competitiva sugerida de R$ 50 a R$ 70.
-- O catalogo atual identifica o servico como TIRE_CHANGE (id 12 neste banco).
--
-- Idempotencia:
--   * a versao 2 e um identificador estavel desta revisao de preco;
--   * se ela ja existir, seus valores convergem para os definidos abaixo;
--   * se nao existir, ela e criada uma unica vez;
--   * versoes anteriores permanecem como historico, mas inativas.

START TRANSACTION;

SET @tire_service_type_id := (
    SELECT `id`
      FROM `service_types`
     WHERE `code` = 'TIRE_CHANGE'
     LIMIT 1
);

SET @niteroi_zone_id := (
    SELECT `id`
      FROM `pricing_zones`
     WHERE `code` = 'NITEROI_GERAL'
     LIMIT 1
);

-- Mantem uma unica regra vigente para o servico na zona geral de Niteroi.
UPDATE `service_price_rules`
   SET `active` = 0,
       `effective_until` = COALESCE(`effective_until`, '2026-08-22'),
       `updated_at` = NOW()
 WHERE `pricing_zone_id` = @niteroi_zone_id
   AND `service_type_id` = @tire_service_type_id
   AND `vehicle_category` IS NULL
   AND `version` <> 2
   AND `active` = 1;

-- Convergencia quando esta migracao ja foi aplicada.
UPDATE `service_price_rules`
   SET `base_customer_price` = 60.00,
       `minimum_customer_price` = 50.00,
       `maximum_customer_price` = 70.00,
       `effective_from` = '2026-08-23',
       `effective_until` = NULL,
       `active` = 1,
       `updated_at` = NOW()
 WHERE `pricing_zone_id` = @niteroi_zone_id
   AND `service_type_id` = @tire_service_type_id
   AND `vehicle_category` IS NULL
   AND `version` = 2;

-- Criacao da regra somente se a versao estavel ainda nao existir.
INSERT INTO `service_price_rules` (
    `pricing_zone_id`,
    `service_type_id`,
    `vehicle_category`,
    `base_customer_price`,
    `minimum_customer_price`,
    `maximum_customer_price`,
    `provider_base_amount`,
    `platform_fee_type`,
    `platform_fee_value`,
    `included_distance_km`,
    `extra_distance_price`,
    `included_minutes`,
    `extra_minute_price`,
    `night_multiplier`,
    `holiday_multiplier`,
    `effective_from`,
    `effective_until`,
    `active`,
    `version`,
    `created_at`,
    `updated_at`
)
SELECT
    @niteroi_zone_id,
    @tire_service_type_id,
    NULL,
    60.00,
    50.00,
    70.00,
    0.00,
    'PERCENTAGE',
    0.2000,
    0.00,
    0.00,
    0,
    0.00,
    1.30,
    1.30,
    '2026-08-23',
    NULL,
    1,
    2,
    NOW(),
    NOW()
WHERE @niteroi_zone_id IS NOT NULL
  AND @tire_service_type_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
        FROM `service_price_rules`
       WHERE `pricing_zone_id` = @niteroi_zone_id
         AND `service_type_id` = @tire_service_type_id
         AND `vehicle_category` IS NULL
         AND `version` = 2
  );

COMMIT;
