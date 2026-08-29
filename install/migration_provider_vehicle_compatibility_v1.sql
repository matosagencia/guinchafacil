-- migration_provider_vehicle_compatibility_v1.sql
-- ROADMAP socorro automotivo — Etapa 15 (compatibilidade prestador × veículo).
--
-- Consome a declaração veicular da Etapa 14 (veiculos.operational_category,
-- fuel_type, electric_type, has_spare_tire, has_locking_bolt) + as condições
-- situacionais gravadas no pedido (veiculo_esta_batido, rodas_travadas,
-- local_dificil_acesso, em_garagem_subsolo) e decide quem pode atender.
--
-- Decisões de adaptação à realidade deste código (divergindo do rascunho):
--   * tipos INT (não BIGINT UNSIGNED) — regra do projeto: guinchos.id,
--     service_types.id, pedidos.id são INT; misturar tipos causa errno 150.
--   * categoria como VARCHAR(40) (não FK para uma tabela de categorias) —
--     coerente com a Etapa 14, que definiu operational_category como texto
--     derivado do tipo (automovel_passeio/moto/utilitario/caminhao_leve),
--     sem catálogo. Não recriamos vehicle_operational_categories.
--   * provider_id = guinchos.id — mesma convenção da tabela provider_capabilities
--     (Etapa 1/4) já em uso. Não introduzimos provider_legacy_links: seria
--     uma segunda ponte concorrente com a de Etapa 12, sem ganho no MVP.
--
-- FALLBACK CONSERVADOR (crítico): estas tabelas nascem VAZIAS. Enquanto um
-- prestador não tiver nenhuma linha em provider_service_vehicle_capabilities
-- para um (serviço), o serviço de compatibilidade trata como legado e devolve
-- ELIGIBLE — ou seja, o fluxo de reboque em produção continua idêntico. As
-- restrições só passam a valer quando o admin configura capacidades veiculares.
--
-- Idempotente: CREATE TABLE IF NOT EXISTS + colunas via INFORMATION_SCHEMA.

-- ============================================================
-- 1) Capacidade do prestador por serviço e categoria de veículo
-- ============================================================
CREATE TABLE IF NOT EXISTS `provider_service_vehicle_capabilities` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`               INT NOT NULL COMMENT '= guinchos.id (mesma convencao de provider_capabilities)',
    `service_type_id`           INT NOT NULL,
    `vehicle_category`          VARCHAR(40) NOT NULL COMMENT 'automovel_passeio | moto | utilitario | caminhao_leve | ...',

    `approval_status`           VARCHAR(30) NOT NULL DEFAULT 'APPROVED' COMMENT 'PENDING | APPROVED | SUSPENDED | REJECTED',
    `enabled`                   TINYINT(1) NOT NULL DEFAULT 1,

    `max_vehicle_weight_kg`     DECIMAL(10,2) NULL,
    `supports_electric`         TINYINT(1) NOT NULL DEFAULT 1,
    `supports_hybrid`           TINYINT(1) NOT NULL DEFAULT 1,
    `supports_locked_wheels`    TINYINT(1) NOT NULL DEFAULT 0,
    `supports_damaged_vehicle`  TINYINT(1) NOT NULL DEFAULT 0,
    `supports_subsoil_access`   TINYINT(1) NOT NULL DEFAULT 0,

    `requires_manual_confirmation` TINYINT(1) NOT NULL DEFAULT 0,

    `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_psvc` (`provider_id`, `service_type_id`, `vehicle_category`),
    KEY `idx_psvc_lookup` (`provider_id`, `service_type_id`, `approval_status`, `enabled`),
    KEY `idx_psvc_category` (`vehicle_category`, `service_type_id`),
    CONSTRAINT `fk_psvc_service` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_psvc_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2) Requisitos do serviço (por categoria; categoria NULL = regra geral)
-- ============================================================
CREATE TABLE IF NOT EXISTS `service_vehicle_requirements` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `service_type_id`           INT NOT NULL,
    `vehicle_category`          VARCHAR(40) NULL COMMENT 'NULL = aplica a todas as categorias deste servico',

    `requires_platform`         TINYINT(1) NOT NULL DEFAULT 0,
    `requires_winch`            TINYINT(1) NOT NULL DEFAULT 0,
    `requires_dolly`            TINYINT(1) NOT NULL DEFAULT 0,
    `requires_battery_tester`   TINYINT(1) NOT NULL DEFAULT 0,
    `requires_jump_starter`     TINYINT(1) NOT NULL DEFAULT 0,
    `requires_hydraulic_jack`   TINYINT(1) NOT NULL DEFAULT 0,

    `minimum_unit_capacity_kg`  DECIMAL(10,2) NULL,
    `electric_certification_required` TINYINT(1) NOT NULL DEFAULT 0,

    `active`                    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_svr` (`service_type_id`, `vehicle_category`),
    CONSTRAINT `fk_svr_service` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3) Snapshot do cenário do pedido (congela o que foi avaliado)
--    A compatibilidade não pode depender do cadastro atual do veículo,
--    porque a ocorrência muda. Este registro fixa o que valia no pedido.
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_vehicle_requirements` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`                  INT NOT NULL,
    `vehicle_id`                INT NOT NULL,

    `vehicle_category`          VARCHAR(40) NULL,
    `declared_vehicle_type`     VARCHAR(30) NULL,
    `fuel_type`                 VARCHAR(30) NULL,
    `electric_vehicle`          TINYINT(1) NOT NULL DEFAULT 0,
    `hybrid_vehicle`            TINYINT(1) NOT NULL DEFAULT 0,

    `damaged_vehicle`           TINYINT(1) NULL,
    `wheels_locked`             TINYINT(1) NULL,
    `underground_location`      TINYINT(1) NULL,
    `difficult_access`          TINYINT(1) NULL,

    `spare_tire_available`      TINYINT(1) NULL,
    `locking_bolt_present`      TINYINT(1) NULL,

    `requires_platform`         TINYINT(1) NOT NULL DEFAULT 0,
    `manual_review_required`    TINYINT(1) NOT NULL DEFAULT 0,

    `verification_status`       VARCHAR(30) NULL COMMENT 'copia de veiculos.verification_status no momento do pedido',
    `requirements_json`         LONGTEXT NULL,
    `snapshot_version`          VARCHAR(30) NOT NULL DEFAULT 'v1',

    `created_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_ovr_order` (`order_id`),
    KEY `idx_ovr_vehicle` (`vehicle_id`),
    CONSTRAINT `fk_ovr_order` FOREIGN KEY (`order_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
