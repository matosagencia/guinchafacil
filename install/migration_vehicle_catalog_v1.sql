-- migration_vehicle_catalog_v1.sql
-- ROADMAP socorro automotivo — Etapa 14 (catálogo veicular estruturado).
--
-- Decisão do usuário (22/07/2026): catálogo INTERNO manual para começar —
-- sem integração de API paga de consulta de placa. A placa continua sendo
-- só identificação comercial; marca→modelo→versão são escolhidos em
-- dropdowns encadeados a partir deste catálogo (ver VehicleVersion.php /
-- AdminVehicleCatalogController.php). Desenho pensado para permitir plugar
-- uma API externa depois (`identification_source` já suporta 'API_EXTERNA')
-- sem precisar remodelar tabela.
--
-- IMPORTANTE — não quebra pedidos existentes: veículos já cadastrados são
-- retroativamente marcados `CUSTOMER_CONFIRMED`/`MANUAL` (grandfathered),
-- porque já sustentam o único fluxo em produção real (reboque). Nenhum
-- gate de bloqueio de pedido é ligado nesta migration — isso é decisão de
-- matching (Etapa 15), não de schema.

CREATE TABLE IF NOT EXISTS `vehicle_brands` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`         VARCHAR(80) NOT NULL,
    `active`       TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_vehicle_brand_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_models` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `brand_id`     INT NOT NULL,
    `name`         VARCHAR(100) NOT NULL,
    `active`       TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_vehicle_model` (`brand_id`, `name`),
    CONSTRAINT `fk_vehicle_model_brand` FOREIGN KEY (`brand_id`)
        REFERENCES `vehicle_brands` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_operational_categories` (
    `id`                          INT AUTO_INCREMENT PRIMARY KEY,
    `code`                          VARCHAR(30) NOT NULL,
    `name`                          VARCHAR(80) NOT NULL,
    `max_weight_kg`                INT NULL,
    `max_length_mm`                INT NULL,
    `max_height_mm`                INT NULL,
    `requires_platform_default`   TINYINT(1) NOT NULL DEFAULT 0,
    `active`                        TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_vehicle_op_category_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_versions` (
    `id`                          INT AUTO_INCREMENT PRIMARY KEY,
    `model_id`                     INT NOT NULL,
    `name`                          VARCHAR(120) NOT NULL COMMENT 'ex.: "1.0 Flex Manual", "Active 1.4"',
    `start_year`                   INT NULL,
    `end_year`                     INT NULL,
    `engine`                        VARCHAR(40) NULL,
    `fuel_type`                     VARCHAR(20) NULL COMMENT 'flex/gasolina/diesel/eletrico/hibrido',
    `transmission_type`           VARCHAR(20) NULL COMMENT 'manual/automatico/cvt',
    `traction_type`                VARCHAR(10) NULL COMMENT '4x2/4x4',
    `body_type`                     VARCHAR(30) NULL COMMENT 'hatch/sedan/suv/pickup/van/moto',
    `start_stop`                    TINYINT(1) NOT NULL DEFAULT 0,
    `electric_type`                VARCHAR(20) NULL COMMENT 'NULL=combustao, hibrido, eletrico',
    `operational_category_id`     INT NOT NULL,
    `curb_weight_kg`               INT NULL,
    `gross_weight_kg`               INT NULL,
    `length_mm`                     INT NULL,
    `height_mm`                     INT NULL,
    `active`                        TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em`                     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_vehicle_version_model` (`model_id`),
    KEY `idx_vehicle_version_category` (`operational_category_id`),
    CONSTRAINT `fk_vehicle_version_model` FOREIGN KEY (`model_id`)
        REFERENCES `vehicle_models` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_vehicle_version_category` FOREIGN KEY (`operational_category_id`)
        REFERENCES `vehicle_operational_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── veiculos: colunas novas e opcionais (defensivo, idêntico ao padrão já
-- usado em migration_service_catalog_v1.sql para pedidos.service_type_id) ──
SET @db_name = DATABASE();

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'vehicle_version_id'
);
SET @sql1 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN vehicle_version_id INT NULL AFTER categoria_tarifa',
    'SELECT "veiculos.vehicle_version_id já existe" AS info'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'operational_category_id'
);
SET @sql2 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN operational_category_id INT NULL AFTER vehicle_version_id',
    'SELECT "veiculos.operational_category_id já existe" AS info'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'identification_source'
);
SET @sql3 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN identification_source VARCHAR(20) NULL DEFAULT \'MANUAL\' AFTER operational_category_id',
    'SELECT "veiculos.identification_source já existe" AS info'
);
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'identification_status'
);
SET @sql4 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN identification_status VARCHAR(30) NOT NULL DEFAULT \'PENDING_IDENTIFICATION\' AFTER identification_source',
    'SELECT "veiculos.identification_status já existe" AS info'
);
PREPARE s4 FROM @sql4; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'identification_confirmed_at'
);
SET @sql5 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN identification_confirmed_at DATETIME NULL AFTER identification_status',
    'SELECT "veiculos.identification_confirmed_at já existe" AS info'
);
PREPARE s5 FROM @sql5; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'requires_platform'
);
SET @sql6 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN requires_platform TINYINT(1) NULL AFTER identification_confirmed_at',
    'SELECT "veiculos.requires_platform já existe" AS info'
);
PREPARE s6 FROM @sql6; EXECUTE s6; DEALLOCATE PREPARE s6;

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'special_restrictions_json'
);
SET @sql7 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN special_restrictions_json LONGTEXT NULL AFTER requires_platform',
    'SELECT "veiculos.special_restrictions_json já existe" AS info'
);
PREPARE s7 FROM @sql7; EXECUTE s7; DEALLOCATE PREPARE s7;

-- FKs (defensivas — só adiciona se ainda não existir)
SET @fk_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND CONSTRAINT_NAME = 'fk_veiculos_vehicle_version'
);
SET @sqlfk1 := IF(@fk_exists = 0,
    'ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_vehicle_version FOREIGN KEY (vehicle_version_id) REFERENCES vehicle_versions (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_veiculos_vehicle_version já existe" AS info'
);
PREPARE sfk1 FROM @sqlfk1; EXECUTE sfk1; DEALLOCATE PREPARE sfk1;

SET @fk_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND CONSTRAINT_NAME = 'fk_veiculos_operational_category'
);
SET @sqlfk2 := IF(@fk_exists = 0,
    'ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_operational_category FOREIGN KEY (operational_category_id) REFERENCES vehicle_operational_categories (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_veiculos_operational_category já existe" AS info'
);
PREPARE sfk2 FROM @sqlfk2; EXECUTE sfk2; DEALLOCATE PREPARE sfk2;

-- Grandfathering: veículos que já existiam antes desta etapa continuam
-- funcionando exatamente como hoje — marcados como confirmados via
-- identificação manual, nunca bloqueados por um gate que não existia
-- quando foram cadastrados. Só roda uma vez (WHERE identification_confirmed_at IS NULL
-- garante idempotência em reexecução).
UPDATE `veiculos`
SET identification_status = 'CUSTOMER_CONFIRMED',
    identification_source = 'MANUAL',
    identification_confirmed_at = criado_em
WHERE vehicle_version_id IS NULL AND identification_confirmed_at IS NULL;

-- ── Seed: categorias operacionais (taxonomia do roadmap do usuário) ──
INSERT INTO `vehicle_operational_categories` (code, name, max_weight_kg, requires_platform_default, active)
VALUES
    ('MOTORCYCLE_LIGHT',   'Motocicleta leve',            250,  0, 1),
    ('MOTORCYCLE_HEAVY',   'Motocicleta pesada',           400,  0, 1),
    ('PASSENGER_COMPACT',  'Passeio compacto',            1300, 0, 1),
    ('PASSENGER_SEDAN',    'Passeio sedã',                1500, 0, 1),
    ('PASSENGER_HATCH',    'Passeio hatch',                1300, 0, 1),
    ('SUV_LIGHT',          'SUV leve',                     1800, 0, 1),
    ('SUV_HEAVY',          'SUV pesado',                   2500, 1, 1),
    ('PICKUP_LIGHT',       'Picape leve',                  2000, 0, 1),
    ('PICKUP_HEAVY',       'Picape pesada',                3500, 1, 1),
    ('VAN_LIGHT',          'Van leve',                     2200, 1, 1),
    ('COMMERCIAL_LIGHT',   'Comercial leve',               2200, 1, 1),
    ('ELECTRIC_LIGHT',     'Elétrico leve',                1800, 0, 1),
    ('HYBRID_LIGHT',       'Híbrido leve',                 1600, 0, 1),
    ('DAMAGED_VEHICLE',    'Veículo batido/não rola',      2000, 1, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ── Seed inicial: marcas/modelos/versões mais comuns na frota do Rio de
-- Janeiro. Cobertura deliberadamente pequena para começar — expandir é
-- trabalho contínuo de admin (AdminVehicleCatalogController), não algo
-- para resolver de uma vez nesta migration.
INSERT INTO vehicle_brands (name) VALUES
    ('Volkswagen'), ('Chevrolet'), ('Fiat'), ('Ford'), ('Hyundai'),
    ('Renault'), ('Honda'), ('Toyota'), ('Yamaha')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO vehicle_models (brand_id, name)
SELECT b.id, m.name FROM (
    SELECT 'Volkswagen' AS marca, 'Gol' AS name UNION ALL
    SELECT 'Volkswagen', 'Voyage' UNION ALL
    SELECT 'Chevrolet', 'Onix' UNION ALL
    SELECT 'Chevrolet', 'Celta' UNION ALL
    SELECT 'Fiat', 'Uno' UNION ALL
    SELECT 'Fiat', 'Strada' UNION ALL
    SELECT 'Fiat', 'Palio' UNION ALL
    SELECT 'Ford', 'Ka' UNION ALL
    SELECT 'Ford', 'Ranger' UNION ALL
    SELECT 'Hyundai', 'HB20' UNION ALL
    SELECT 'Renault', 'Kwid' UNION ALL
    SELECT 'Honda', 'Civic' UNION ALL
    SELECT 'Honda', 'CG 160' UNION ALL
    SELECT 'Toyota', 'Corolla' UNION ALL
    SELECT 'Toyota', 'Hilux' UNION ALL
    SELECT 'Yamaha', 'Factor 150'
) m
JOIN vehicle_brands b ON b.name = m.marca
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO vehicle_versions
    (model_id, name, fuel_type, transmission_type, body_type, start_stop, electric_type, operational_category_id, curb_weight_kg, active)
SELECT vm.id, v.versao, v.combustivel, v.cambio, v.carroceria, 0, NULL, voc.id, v.peso, 1
FROM (
    SELECT 'Volkswagen' AS marca, 'Gol' AS modelo, '1.0 Flex Manual' AS versao, 'flex' AS combustivel, 'manual' AS cambio, 'hatch' AS carroceria, 'PASSENGER_HATCH' AS categoria, 1050 AS peso UNION ALL
    SELECT 'Volkswagen', 'Voyage', '1.6 Flex Manual', 'flex', 'manual', 'sedan', 'PASSENGER_SEDAN', 1150 UNION ALL
    SELECT 'Chevrolet', 'Onix', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_HATCH', 1080 UNION ALL
    SELECT 'Chevrolet', 'Celta', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_COMPACT', 980 UNION ALL
    SELECT 'Fiat', 'Uno', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_COMPACT', 950 UNION ALL
    SELECT 'Fiat', 'Strada', '1.4 Flex Manual Cabine Simples', 'flex', 'manual', 'pickup', 'PICKUP_LIGHT', 1250 UNION ALL
    SELECT 'Fiat', 'Palio', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_COMPACT', 1000 UNION ALL
    SELECT 'Ford', 'Ka', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_COMPACT', 990 UNION ALL
    SELECT 'Ford', 'Ranger', '3.2 Diesel 4x4 Automática', 'diesel', 'automatico', 'pickup', 'PICKUP_HEAVY', 2200 UNION ALL
    SELECT 'Hyundai', 'HB20', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_HATCH', 1080 UNION ALL
    SELECT 'Renault', 'Kwid', '1.0 Flex Manual', 'flex', 'manual', 'hatch', 'PASSENGER_COMPACT', 940 UNION ALL
    SELECT 'Honda', 'Civic', '2.0 Flex Automático', 'flex', 'automatico', 'sedan', 'PASSENGER_SEDAN', 1350 UNION ALL
    SELECT 'Honda', 'CG 160', 'Flex Manual', 'flex', 'manual', 'moto', 'MOTORCYCLE_LIGHT', 135 UNION ALL
    SELECT 'Toyota', 'Corolla', '2.0 Flex Automático', 'flex', 'automatico', 'sedan', 'PASSENGER_SEDAN', 1420 UNION ALL
    SELECT 'Toyota', 'Hilux', '2.8 Diesel 4x4 Automática', 'diesel', 'automatico', 'pickup', 'PICKUP_HEAVY', 2135 UNION ALL
    SELECT 'Yamaha', 'Factor 150', 'Flex Manual', 'flex', 'manual', 'moto', 'MOTORCYCLE_LIGHT', 129
) v
JOIN vehicle_brands vb ON vb.name = v.marca
JOIN vehicle_models vm ON vm.brand_id = vb.id AND vm.name = v.modelo
JOIN vehicle_operational_categories voc ON voc.code = v.categoria
LEFT JOIN vehicle_versions existing ON existing.model_id = vm.id AND existing.name = v.versao
WHERE existing.id IS NULL;
