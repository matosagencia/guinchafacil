-- migration_pricing_zones_v1.sql
-- ROADMAP socorro automotivo — Etapa 13 (preço governado por zona).
--
-- Complementa `service_pricing_rules` (Etapa 9, MVP: uma regra global por
-- service_type) com segmentação geográfica. NÃO substitui nem altera
-- `service_pricing_rules` nem `TarifaService` (reboque, produção) — é uma
-- camada adicional que passa a valer quando uma zona tem regra própria;
-- na ausência de regra por zona, a regra global de service_pricing_rules
-- continua sendo o fallback (comportamento não muda até isso ser lido em
-- algum controller — o que ainda não acontece nesta etapa).
--
-- `providers` aqui é a tabela nova da Etapa 12 (camada aditiva). Um
-- provider_price_preferences vazio não impede nada — é só uma faixa que o
-- prestador pode pedir para si dentro do que a zona permite.

CREATE TABLE IF NOT EXISTS `pricing_zones` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `city_id`             VARCHAR(10) NULL COMMENT 'reservado para quando o sistema operar múltiplas cidades; NULL = cidade única atual',
    `code`                 VARCHAR(40) NOT NULL,
    `name`                 VARCHAR(120) NOT NULL,
    `polygon_geojson`     LONGTEXT NULL COMMENT 'polígono da zona em GeoJSON; NULL = zona ainda não desenhada, regra não se aplica geograficamente ainda',
    `active`               TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`           DATETIME NOT NULL,
    `updated_at`           DATETIME NOT NULL,
    UNIQUE KEY `uk_pricing_zones_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_price_rules` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `pricing_zone_id`             INT NOT NULL,
    `service_type_id`             INT NOT NULL,
    `vehicle_category`           VARCHAR(30) NULL,

    `base_customer_price`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `minimum_customer_price`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `maximum_customer_price`   DECIMAL(12,2) NULL,

    `provider_base_amount`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `platform_fee_type`         VARCHAR(20) NOT NULL DEFAULT 'PERCENTAGE',
    `platform_fee_value`         DECIMAL(10,4) NOT NULL DEFAULT 0.00,

    `included_distance_km`     DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `extra_distance_price`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `included_minutes`           INT NOT NULL DEFAULT 0,
    `extra_minute_price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    `night_multiplier`           DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    `holiday_multiplier`         DECIMAL(4,2) NOT NULL DEFAULT 1.00,

    `effective_from`             DATE NULL,
    `effective_until`             DATE NULL,
    `active`                       TINYINT(1) NOT NULL DEFAULT 1,
    `version`                       INT NOT NULL DEFAULT 1,

    `created_at`                   DATETIME NOT NULL,
    `updated_at`                   DATETIME NOT NULL,

    UNIQUE KEY `uk_service_price_rule` (`pricing_zone_id`, `service_type_id`, `vehicle_category`, `version`),
    KEY `idx_price_rules_zone` (`pricing_zone_id`),
    KEY `idx_price_rules_service` (`service_type_id`),
    KEY `idx_price_rules_active` (`active`),

    CONSTRAINT `fk_price_rules_zone` FOREIGN KEY (`pricing_zone_id`)
        REFERENCES `pricing_zones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_price_rules_service_type` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_price_preferences` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`                 INT NOT NULL,
    `service_type_id`             INT NOT NULL,
    `pricing_zone_id`             INT NOT NULL,
    `requested_base_price`     DECIMAL(12,2) NOT NULL,
    `approved_base_price`       DECIMAL(12,2) NULL,
    `approval_status`             VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `approved_by`                 INT NULL,
    `approved_at`                 DATETIME NULL,
    `created_at`                   DATETIME NOT NULL,
    `updated_at`                   DATETIME NOT NULL,

    UNIQUE KEY `uk_provider_price_pref` (`provider_id`, `service_type_id`, `pricing_zone_id`),
    KEY `idx_price_pref_status` (`approval_status`),

    CONSTRAINT `fk_price_pref_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_price_pref_service_type` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_price_pref_zone` FOREIGN KEY (`pricing_zone_id`)
        REFERENCES `pricing_zones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_price_pref_admin` FOREIGN KEY (`approved_by`)
        REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- order_price_snapshots: o pedido congela a regra usada no momento da
-- cobrança — mudar a tabela de preços amanhã não altera um pedido já aberto.
CREATE TABLE IF NOT EXISTS `order_price_snapshots` (
    `id`                            INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`                       INT NOT NULL,
    `pricing_rule_id`                 INT NULL,
    `pricing_version`                 INT NULL,
    `calculation_context_json`      LONGTEXT NULL,
    `customer_total`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `provider_total`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `platform_fee`                     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `snapshot_hash`                   VARCHAR(64) NOT NULL,
    `created_at`                       DATETIME NOT NULL,

    UNIQUE KEY `uk_order_price_snapshot_order` (`order_id`),
    KEY `idx_order_price_snapshot_rule` (`pricing_rule_id`),

    CONSTRAINT `fk_order_price_snapshot_order` FOREIGN KEY (`order_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_price_snapshot_rule` FOREIGN KEY (`pricing_rule_id`)
        REFERENCES `service_price_rules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
