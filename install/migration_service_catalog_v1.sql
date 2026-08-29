-- migration_service_catalog_v1.sql
-- ETAPA 1 do ROADMAP — EXPANSÃO PARA SOCORRO AUTOMOTIVO (Fundamento 1 e 2)
-- Cria o catálogo de serviços (categorias + tipos) e a camada de capacidades
-- do prestador (equipamentos, compatibilidade de veículo), SEM alterar o
-- comportamento do fluxo de reboque existente. `pedidos` recebe duas colunas
-- novas e opcionais (service_type_id, attendance_mode) só para referência —
-- nenhum controller/service existente lê essas colunas ainda, portanto o
-- fluxo atual de reboque continua 100% inalterado (ver doc/ROADMAP_SOCORRO_AUTOMOTIVO.md).

-- ─── service_categories ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `service_categories` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `code`        VARCHAR(60)   NOT NULL,
    `name`        VARCHAR(120)  NOT NULL,
    `description` TEXT          NULL,
    `icon`        VARCHAR(60)   NULL,
    `active`      TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`  INT           NOT NULL DEFAULT 0,
    `created_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_service_categories_code` (`code`),
    INDEX `idx_service_categories_active` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── service_types ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `service_types` (
    `id`                           INT AUTO_INCREMENT PRIMARY KEY,
    `category_id`                  INT           NOT NULL,
    `code`                         VARCHAR(60)   NOT NULL,
    `name`                         VARCHAR(120)  NOT NULL,
    `description`                  TEXT          NULL,
    `attendance_mode`              ENUM('TOWING','ON_SITE','HYBRID') NOT NULL DEFAULT 'ON_SITE',
    `requires_destination`         TINYINT(1)    NOT NULL DEFAULT 0,
    `allows_conversion_to_towing`  TINYINT(1)    NOT NULL DEFAULT 0,
    `requires_diagnostic`          TINYINT(1)    NOT NULL DEFAULT 0,
    `requires_parts`               TINYINT(1)    NOT NULL DEFAULT 0,
    `requires_before_evidence`     TINYINT(1)    NOT NULL DEFAULT 1,
    `requires_after_evidence`      TINYINT(1)    NOT NULL DEFAULT 1,
    `estimated_duration_minutes`   INT           NOT NULL DEFAULT 30,
    `active`                       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`                   DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                   DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_service_types_code` (`code`),
    INDEX `idx_service_types_category` (`category_id`),
    INDEX `idx_service_types_active` (`active`),
    CONSTRAINT `fk_service_types_category` FOREIGN KEY (`category_id`)
        REFERENCES `service_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── provider_capabilities ──────────────────────────────────────────────────
-- provider_id referencia guinchos.id — reaproveita o cadastro existente do
-- prestador (decisão arquitetural do roadmap: não renomear `guinchos` agora).
CREATE TABLE IF NOT EXISTS `provider_capabilities` (
    `id`                          INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`                 INT            NOT NULL,
    `service_type_id`             INT            NOT NULL,
    `enabled`                     TINYINT(1)     NOT NULL DEFAULT 0,
    `approval_status`             ENUM('PENDING','APPROVED','SUSPENDED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `base_price`                  DECIMAL(10,2)  NULL,
    `price_per_km`                DECIMAL(10,2)  NULL,
    `price_per_minute`            DECIMAL(10,2)  NULL,
    `night_surcharge`             DECIMAL(10,2)  NULL,
    `holiday_surcharge`           DECIMAL(10,2)  NULL,
    `coverage_radius_km`          INT            NULL,
    `estimated_duration_minutes`  INT            NULL,
    `requires_inventory`          TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`                  DATETIME       DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_provider_capabilities_provider_service` (`provider_id`, `service_type_id`),
    INDEX `idx_provider_capabilities_service` (`service_type_id`),
    INDEX `idx_provider_capabilities_status` (`approval_status`, `enabled`),
    CONSTRAINT `fk_provider_capabilities_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_provider_capabilities_service_type` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── provider_equipment ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `provider_equipment` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`    INT            NOT NULL,
    `equipment_code` VARCHAR(60)    NOT NULL,
    `description`    VARCHAR(255)   NULL,
    `quantity`       INT            NOT NULL DEFAULT 1,
    `verified_at`    DATETIME       NULL,
    `expires_at`     DATE           NULL,
    `status`         ENUM('PENDENTE_VERIFICACAO','ATIVO','VENCIDO') NOT NULL DEFAULT 'PENDENTE_VERIFICACAO',
    `created_at`     DATETIME       DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_provider_equipment_provider_code` (`provider_id`, `equipment_code`),
    INDEX `idx_provider_equipment_status` (`status`),
    CONSTRAINT `fk_provider_equipment_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── provider_vehicle_compatibility ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `provider_vehicle_compatibility` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`        INT            NOT NULL,
    `vehicle_category`   VARCHAR(40)    NOT NULL,
    `max_weight_kg`      INT            NULL,
    `max_height_m`       DECIMAL(4,2)   NULL,
    `supports_electric`  TINYINT(1)     NOT NULL DEFAULT 0,
    `supports_hybrid`    TINYINT(1)     NOT NULL DEFAULT 0,
    `supports_motorcycle` TINYINT(1)    NOT NULL DEFAULT 0,
    `supports_utility`   TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_provider_vehicle_compat` (`provider_id`, `vehicle_category`),
    CONSTRAINT `fk_provider_vehicle_compat_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── pedidos: referência opcional ao catálogo (não muda comportamento atual) ─
SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'service_type_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pedidos ADD COLUMN service_type_id INT NULL AFTER guincho_id',
    'SELECT "pedidos.service_type_id já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'attendance_mode'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pedidos ADD COLUMN attendance_mode ENUM(''TOWING'',''ON_SITE'',''HYBRID'') NOT NULL DEFAULT ''TOWING'' AFTER service_type_id',
    'SELECT "pedidos.attendance_mode já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND CONSTRAINT_NAME = 'fk_pedidos_service_type'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_service_type FOREIGN KEY (service_type_id) REFERENCES service_types (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_pedidos_service_type já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── Seeds: categorias ───────────────────────────────────────────────────────
INSERT IGNORE INTO `service_categories` (`code`, `name`, `description`, `icon`, `sort_order`) VALUES
('TOWING',                'Reboque',                    'Transporte do veículo em caminhão-plataforma/guincho.',     'truck-pickup',    10),
('ROADSIDE_ASSISTANCE',   'Socorro Mecânico',           'Assistência mecânica emergencial no local.',                 'wrench',          20),
('ELECTRICAL_ASSISTANCE', 'Assistência Elétrica',       'Partida auxiliar, teste e troca de bateria, pane elétrica.', 'bolt',            30),
('TIRE_ASSISTANCE',       'Assistência de Pneu',        'Troca e calibragem de pneu.',                                'circle-notch',    40),
('LOCKSMITH',             'Chaveiro Automotivo',        'Abertura e confecção de chave automotiva.',                  'key',             50),
('FUEL_ASSISTANCE',       'Combustível',                'Entrega emergencial de combustível (pane seca).',            'gas-pump',        60);

-- ─── Seeds: tipos de serviço ─────────────────────────────────────────────────
-- TOWING (reboque) — attendance_mode=TOWING, requer destino, sem diagnóstico obrigatório.
INSERT IGNORE INTO `service_types`
    (`category_id`, `code`, `name`, `description`, `attendance_mode`, `requires_destination`, `allows_conversion_to_towing`, `requires_diagnostic`, `requires_parts`, `estimated_duration_minutes`)
SELECT c.id, v.code, v.name, v.description, 'TOWING', 1, 0, 0, 0, v.duration
FROM (SELECT 'TOWING' AS cat_code) AS x
JOIN `service_categories` c ON c.code = x.cat_code
JOIN (
    SELECT 'TOW_CAR' AS code, 'Reboque de Automóvel' AS name, 'Reboque de carro de passeio ou similar.' AS description, 45 AS duration
    UNION ALL SELECT 'TOW_MOTORCYCLE', 'Reboque de Motocicleta', 'Reboque de motocicleta com suporte adequado.', 35
    UNION ALL SELECT 'TOW_UTILITY', 'Reboque de Utilitário', 'Reboque de van, utilitário ou veículo de carga leve.', 60
) AS v ON 1=1;

-- ROADSIDE_ASSISTANCE (socorro no local)
INSERT IGNORE INTO `service_types`
    (`category_id`, `code`, `name`, `description`, `attendance_mode`, `requires_destination`, `allows_conversion_to_towing`, `requires_diagnostic`, `requires_parts`, `estimated_duration_minutes`)
SELECT c.id, v.code, v.name, v.description, 'ON_SITE', 0, 1, v.diag, v.parts, v.duration
FROM `service_categories` c
JOIN (
    SELECT 'MECHANICAL_ASSISTANCE' AS code, 'Socorro Mecânico Emergencial' AS name, 'Reparo emergencial simples no local para permitir que o veículo siga viagem.' AS description, 1 AS diag, 1 AS parts, 40 AS duration
) AS v ON c.code = 'ROADSIDE_ASSISTANCE';

-- ELECTRICAL_ASSISTANCE
INSERT IGNORE INTO `service_types`
    (`category_id`, `code`, `name`, `description`, `attendance_mode`, `requires_destination`, `allows_conversion_to_towing`, `requires_diagnostic`, `requires_parts`, `estimated_duration_minutes`)
SELECT c.id, v.code, v.name, v.description, 'ON_SITE', 0, 1, v.diag, v.parts, v.duration
FROM `service_categories` c
JOIN (
    SELECT 'JUMP_START' AS code, 'Partida Auxiliar' AS name, 'Partida auxiliar com equipamento próprio quando a bateria está fraca ou descarregada.' AS description, 0 AS diag, 0 AS parts, 20 AS duration
    UNION ALL SELECT 'BATTERY_TEST', 'Teste de Bateria', 'Avaliação da bateria e do sistema de carga.', 1, 0, 20
    UNION ALL SELECT 'BATTERY_REPLACEMENT', 'Troca de Bateria', 'Venda e instalação de bateria compatível, com garantia.', 1, 1, 40
    UNION ALL SELECT 'ELECTRICAL_DIAGNOSIS', 'Diagnóstico de Pane Elétrica', 'Diagnóstico de falha elétrica, com recomendação de reparo ou reboque.', 1, 0, 40
) AS v ON c.code = 'ELECTRICAL_ASSISTANCE';

-- TIRE_ASSISTANCE
INSERT IGNORE INTO `service_types`
    (`category_id`, `code`, `name`, `description`, `attendance_mode`, `requires_destination`, `allows_conversion_to_towing`, `requires_diagnostic`, `requires_parts`, `estimated_duration_minutes`)
SELECT c.id, v.code, v.name, v.description, 'ON_SITE', 0, 1, v.diag, v.parts, v.duration
FROM `service_categories` c
JOIN (
    SELECT 'TIRE_CHANGE' AS code, 'Troca de Pneu' AS name, 'Substituição do pneu danificado pelo estepe do veículo.' AS description, 0 AS diag, 0 AS parts, 25 AS duration
    UNION ALL SELECT 'TIRE_INFLATION', 'Calibragem de Pneu', 'Calibragem ou reparo simples de furo com kit de emergência.', 0, 0, 15
) AS v ON c.code = 'TIRE_ASSISTANCE';

-- LOCKSMITH
INSERT IGNORE INTO `service_types`
    (`category_id`, `code`, `name`, `description`, `attendance_mode`, `requires_destination`, `allows_conversion_to_towing`, `requires_diagnostic`, `requires_parts`, `estimated_duration_minutes`)
SELECT c.id, 'AUTOMOTIVE_LOCKSMITH', 'Chaveiro Automotivo', 'Abertura de veículo trancado ou confecção de chave/cópia.', 'ON_SITE', 0, 0, 0, 1, 35
FROM `service_categories` c WHERE c.code = 'LOCKSMITH';

-- FUEL_ASSISTANCE
INSERT IGNORE INTO `service_types`
    (`category_id`, `code`, `name`, `description`, `attendance_mode`, `requires_destination`, `allows_conversion_to_towing`, `requires_diagnostic`, `requires_parts`, `estimated_duration_minutes`)
SELECT c.id, 'FUEL_DELIVERY', 'Entrega de Combustível (Pane Seca)', 'Entrega de quantidade limitada de combustível para o veículo seguir até um posto.', 'ON_SITE', 0, 1, 0, 1, 20
FROM `service_categories` c WHERE c.code = 'FUEL_ASSISTANCE';

-- ─── Compatibilidade retroativa: pedidos existentes viram TOW_CAR/TOWING ─────
-- Só preenche linhas que ainda não têm service_type_id — não sobrescreve nada,
-- e não é lido por nenhum código existente ainda (Etapa 1 é só domínio/dado).
UPDATE `pedidos` p
JOIN `service_types` st ON st.code = 'TOW_CAR'
SET p.service_type_id = st.id, p.attendance_mode = 'TOWING'
WHERE p.service_type_id IS NULL;
