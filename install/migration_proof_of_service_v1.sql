-- migration_proof_of_service_v1.sql
-- ROADMAP socorro automotivo — Etapa 6 (Proof-of-Service).
--
-- Formaliza, em UM registro consultável por pedido, o que já estava sendo
-- capturado espalhado (diagnóstico em pedido_diagnosticos, fotos em
-- pedido_evidencias): diagnóstico exigido foi feito? evidência de antes/
-- depois exigida foi enviada? checklist está completo ou não?
--
-- Isso é o que falta pra religar o financeiro de duas fases de verdade
-- (Etapa 11/ChargePolicyService) — hoje `order_charge_items.payable_status`
-- nasce `PENDING_EVIDENCE` mas nada preenche `evidence_validated_at`
-- automaticamente porque não existia uma fonte estruturada de "a prova
-- existe e está completa". Esta migration cria essa fonte; LIGAR o
-- gatilho automático de pagamento nela continua fora de escopo aqui —
-- mesma cautela de sempre com dinheiro.

CREATE TABLE IF NOT EXISTS `service_executions` (
    `id`                          INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id`                    INT NOT NULL,
    `provider_id`                  INT NOT NULL,
    `phase_code`                   VARCHAR(40) NOT NULL COMMENT 'ver ChargeCodes::PHASE_* (Etapa 11) — mesmo vocabulário',
    `requires_diagnostic`         TINYINT(1) NOT NULL DEFAULT 0,
    `requires_before_evidence`   TINYINT(1) NOT NULL DEFAULT 0,
    `requires_after_evidence`     TINYINT(1) NOT NULL DEFAULT 0,
    `has_diagnostic`               TINYINT(1) NOT NULL DEFAULT 0,
    `has_before_evidence`         TINYINT(1) NOT NULL DEFAULT 0,
    `has_after_evidence`           TINYINT(1) NOT NULL DEFAULT 0,
    `checklist_status`             VARCHAR(20) NOT NULL DEFAULT 'INCOMPLETO' COMMENT 'COMPLETO | INCOMPLETO',
    `avaliado_em`                   DATETIME NOT NULL,
    `criado_em`                     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_service_execution_pedido` (`pedido_id`),
    KEY `idx_service_execution_provider` (`provider_id`),
    KEY `idx_service_execution_status` (`checklist_status`),
    CONSTRAINT `fk_service_execution_pedido` FOREIGN KEY (`pedido_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_service_execution_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fecha o loop deixado aberto na Etapa 11: order_charge_items.service_execution_id
-- e order_provider_settlements.service_execution_id existem desde aquela
-- migration, mas sem FK (a tabela alvo não existia ainda). Adiciona agora,
-- de forma defensiva (só se a constraint ainda não existir), sem alterar
-- nenhum dado — ambas as colunas já são NULLable e ninguém grava nelas.
SET @db_name = DATABASE();

SET @fk_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'order_charge_items'
      AND CONSTRAINT_NAME = 'fk_charge_item_execution'
);
SET @sql_fk1 := IF(@fk_exists = 0,
    'ALTER TABLE order_charge_items ADD CONSTRAINT fk_charge_item_execution FOREIGN KEY (service_execution_id) REFERENCES service_executions (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_charge_item_execution já existe" AS info'
);
PREPARE stmt_fk1 FROM @sql_fk1;
EXECUTE stmt_fk1;
DEALLOCATE PREPARE stmt_fk1;

SET @fk_exists2 = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'order_provider_settlements'
      AND CONSTRAINT_NAME = 'fk_settlement_execution'
);
SET @sql_fk2 := IF(@fk_exists2 = 0,
    'ALTER TABLE order_provider_settlements ADD CONSTRAINT fk_settlement_execution FOREIGN KEY (service_execution_id) REFERENCES service_executions (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_settlement_execution já existe" AS info'
);
PREPARE stmt_fk2 FROM @sql_fk2;
EXECUTE stmt_fk2;
DEALLOCATE PREPARE stmt_fk2;
