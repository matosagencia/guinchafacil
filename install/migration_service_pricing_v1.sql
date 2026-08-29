-- migration_service_pricing_v1.sql
-- ROADMAP socorro automotivo — Fundamento 9 (tarifa multisserviço), trazido
-- para agora a pedido do usuário: o admin precisa configurar preço por tipo
-- de serviço (não só reboque). MVP: uma regra global por service_type (sem
-- segmentação por cidade/categoria de veículo ainda — ver roadmap §10.2
-- para evolução futura).

CREATE TABLE IF NOT EXISTS `service_pricing_rules` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `service_type_id`    INT            NOT NULL,
    `base_fee`           DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `pickup_km_price`    DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `tow_km_price`       DECIMAL(10,2)  NULL,
    `labor_fee`          DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `minimum_price`      DECIMAL(10,2)  NOT NULL DEFAULT 0,
    `night_multiplier`   DECIMAL(4,2)   NOT NULL DEFAULT 1.00,
    `holiday_multiplier` DECIMAL(4,2)   NOT NULL DEFAULT 1.00,
    `active`             TINYINT(1)     NOT NULL DEFAULT 1,
    `created_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_service_pricing_rules_type` (`service_type_id`),
    CONSTRAINT `fk_service_pricing_rules_type` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
