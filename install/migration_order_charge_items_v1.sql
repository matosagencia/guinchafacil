-- migration_order_charge_items_v1.sql
-- ROADMAP socorro automotivo — Etapa 11 (financeiro de duas fases).
-- Schema definido explicitamente pelo usuário em 22/07/2026. NÃO alterar
-- nomes de coluna/tabela sem re-confirmar — este é um schema financeiro.
--
-- order_charge_items  -> o que foi cobrado, item a item, por fase e prestador.
-- order_provider_settlements -> quanto cada prestador deve receber (consolidado).
--
-- IMPORTANTE: esta migration só cria estrutura. Nenhum controller/service
-- ainda grava nessas tabelas em produção (ver ChargePolicyService — política
-- pura, ainda não chamada por nenhum fluxo real). O gatilho automático de
-- geração/pagamento depende da Etapa 6 (Proof-of-Service/evidências), que
-- ainda não existe.
--
-- Nota (correção pós-primeira tentativa de migrate): `id`/FKs usam `INT`,
-- não `BIGINT UNSIGNED` — precisa casar exatamente com `pedidos.id` e
-- `guinchos.id` (ambos `INT` signed em install/migrate.php), senão o MySQL
-- recusa a FK com errno 150 "Foreign key constraint is incorrectly formed".

CREATE TABLE IF NOT EXISTS `order_charge_items` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,

    `order_id`                  INT NOT NULL,
    `provider_id`                INT NULL,
    `service_execution_id`      INT NULL,

    `phase_code`                 VARCHAR(40)  NOT NULL,
    `charge_type`                 VARCHAR(40)  NOT NULL,
    `description`                 VARCHAR(255) NOT NULL,

    `quantity`                     DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    `unit_amount`                  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `gross_amount`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    `discount_amount`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `platform_fee_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `provider_net_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    `charge_status`                VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `payable_status`               VARCHAR(30) NOT NULL DEFAULT 'NOT_ELIGIBLE',

    `calculation_version`         VARCHAR(30) NOT NULL,
    `calculation_context_json`   LONGTEXT NULL,

    `evidence_required`           TINYINT(1) NOT NULL DEFAULT 0,
    `evidence_validated_at`       DATETIME NULL,

    `approved_at`                  DATETIME NULL,
    `payable_at`                   DATETIME NULL,
    `paid_at`                      DATETIME NULL,
    `blocked_at`                   DATETIME NULL,
    `cancelled_at`                 DATETIME NULL,

    `block_reason_code`           VARCHAR(50) NULL,
    `idempotency_key`             VARCHAR(100) NOT NULL,

    `created_at`                   DATETIME NOT NULL,
    `updated_at`                   DATETIME NOT NULL,

    UNIQUE KEY `uk_charge_item_idempotency` (`idempotency_key`),
    KEY `idx_charge_order` (`order_id`),
    KEY `idx_charge_provider` (`provider_id`),
    KEY `idx_charge_status` (`charge_status`),
    KEY `idx_charge_payable` (`payable_status`),
    KEY `idx_charge_execution` (`service_execution_id`),

    CONSTRAINT `fk_charge_item_order` FOREIGN KEY (`order_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_charge_item_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_provider_settlements` (
    `id`                        INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`                  INT NOT NULL,
    `provider_id`                INT NOT NULL,
    `service_execution_id`      INT NULL,

    `gross_amount`                 DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `platform_fee_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_amount`                   DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    `settlement_status`           VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    `eligibility_reason_code`     VARCHAR(50) NULL,

    `approved_at`                  DATETIME NULL,
    `scheduled_at`                 DATETIME NULL,
    `paid_at`                      DATETIME NULL,

    `idempotency_key`             VARCHAR(100) NOT NULL,
    `created_at`                   DATETIME NOT NULL,
    `updated_at`                   DATETIME NOT NULL,

    UNIQUE KEY `uk_settlement_idempotency` (`idempotency_key`),
    KEY `idx_settlement_order_provider` (`order_id`, `provider_id`),
    KEY `idx_settlement_status` (`settlement_status`),

    CONSTRAINT `fk_settlement_order` FOREIGN KEY (`order_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_settlement_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
