-- Reprecificacao regional - Niteroi e Rio de Janeiro (CORREÇÃO DE SINTAXE - REMOÇÃO DE COLLATE)
-- O erro 1064 indica que o uso de COLLATE nas cláusulas JOIN/WHERE não é suportado pelo servidor.
-- Removendo as declarações explícitas de COLLATE nessas posições.

START TRANSACTION;

SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci';

DROP TEMPORARY TABLE IF EXISTS `tmp_pricing_20260823`;
CREATE TEMPORARY TABLE `tmp_pricing_20260823` (
    `service_code` VARCHAR(40) NOT NULL PRIMARY KEY,
    `market_average` DECIMAL(12,2) NOT NULL,
    `customer_price` DECIMAL(12,2) NOT NULL,
    `minimum_price` DECIMAL(12,2) NOT NULL,
    `maximum_price` DECIMAL(12,2) NULL,
    `included_km` DECIMAL(6,2) NOT NULL,
    `extra_km_price` DECIMAL(10,2) NOT NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO `tmp_pricing_20260823`
    (`service_code`, `market_average`, `customer_price`, `minimum_price`, `maximum_price`, `included_km`, `extra_km_price`)
VALUES
    ('TOW_CAR',                230.00, 250.00, 250.00, NULL,   15.00, 6.50),
    ('TOW_MOTORCYCLE',         150.00, 165.00, 165.00, NULL,   15.00, 5.50),
    ('TOW_UTILITY',            275.00, 300.00, 300.00, NULL,   15.00, 7.50),
    ('MECHANICAL_ASSISTANCE',  140.00, 150.00, 150.00, 220.00,  0.00, 0.00),
    ('JUMP_START',              90.00, 100.00, 100.00, 130.00,  0.00, 0.00),
    ('BATTERY_TEST',            65.00,  70.00,  70.00,  90.00,  0.00, 0.00),
    ('BATTERY_REPLACEMENT',    110.00, 120.00, 120.00, 160.00,  0.00, 0.00),
    ('ELECTRICAL_DIAGNOSIS',   130.00, 140.00, 140.00, 200.00,  0.00, 0.00),
    ('TIRE_CHANGE',             60.00,  65.00,  65.00,  90.00,  0.00, 0.00),
    ('TIRE_INFLATION',          50.00,  55.00,  55.00,  75.00,  0.00, 0.00),
    ('AUTOMOTIVE_LOCKSMITH',   175.00, 190.00, 190.00, 260.00,  0.00, 0.00),
    ('FUEL_DELIVERY',           90.00, 100.00, 100.00, 130.00,  0.00, 0.00);

-- Desativa somente as regras substituidas nas duas zonas-alvo.
UPDATE `service_price_rules` spr
JOIN `pricing_zones` pz ON pz.`id` = spr.`pricing_zone_id`
JOIN `service_types` st ON st.`id` = spr.`service_type_id`
JOIN `tmp_pricing_20260823` src ON src.`service_code` = st.`code`
SET spr.`active` = 0,
    spr.`effective_until` = COALESCE(spr.`effective_until`, '2026-08-22'),
    spr.`updated_at` = NOW()
WHERE pz.`code` IN ('NITEROI_GERAL', 'centro-rj')
  AND spr.`vehicle_category` IS NULL
  AND spr.`version` <> 20260823
  AND spr.`active` = 1;

-- Convergencia quando esta migracao ja foi aplicada.
UPDATE `service_price_rules` spr
JOIN `pricing_zones` pz ON pz.`id` = spr.`pricing_zone_id`
JOIN `service_types` st ON st.`id` = spr.`service_type_id`
JOIN `tmp_pricing_20260823` src ON src.`service_code` = st.`code`
SET spr.`base_customer_price` = src.`customer_price`,
    spr.`minimum_customer_price` = src.`minimum_price`,
    spr.`maximum_customer_price` = src.`maximum_price`,
    spr.`provider_base_amount` = src.`market_average`,
    spr.`platform_fee_type` = 'PERCENTAGE',
    spr.`platform_fee_value` = 0.0800,
    spr.`included_distance_km` = src.`included_km`,
    spr.`extra_distance_price` = src.`extra_km_price`,
    spr.`included_minutes` = 0,
    spr.`extra_minute_price` = 0.00,
    spr.`night_multiplier` = 1.25,
    spr.`holiday_multiplier` = 1.25,
    spr.`effective_from` = '2026-08-23',
    spr.`effective_until` = NULL,
    spr.`active` = 1,
    spr.`updated_at` = NOW()
WHERE pz.`code` IN ('NITEROI_GERAL', 'centro-rj')
  AND spr.`vehicle_category` IS NULL
  AND spr.`version` = 20260823;

INSERT INTO `service_price_rules` (
    `pricing_zone_id`, `service_type_id`, `vehicle_category`,
    `base_customer_price`, `minimum_customer_price`, `maximum_customer_price`,
    `provider_base_amount`, `platform_fee_type`, `platform_fee_value`,
    `included_distance_km`, `extra_distance_price`, `included_minutes`,
    `extra_minute_price`, `night_multiplier`, `holiday_multiplier`,
    `effective_from`, `effective_until`, `active`, `version`, `created_at`, `updated_at`
)
SELECT
    pz.`id`, st.`id`, NULL,
    src.`customer_price`, src.`minimum_price`, src.`maximum_price`,
    src.`market_average`, 'PERCENTAGE', 0.0800,
    src.`included_km`, src.`extra_km_price`, 0,
    0.00, 1.25, 1.25,
    '2026-08-23', NULL, 1, 20260823, NOW(), NOW()
FROM `pricing_zones` pz
CROSS JOIN `tmp_pricing_20260823` src
JOIN `service_types` st ON st.`code` = src.`service_code`
WHERE pz.`code` IN ('NITEROI_GERAL', 'centro-rj')
  AND NOT EXISTS (
      SELECT 1
      FROM `service_price_rules` existing
      WHERE existing.`pricing_zone_id` = pz.`id`
        AND existing.`service_type_id` = st.`id`
        AND existing.`vehicle_category` IS NULL
        AND existing.`version` = 20260823
  );

DROP TEMPORARY TABLE IF EXISTS `tmp_pricing_20260823`;
COMMIT;
