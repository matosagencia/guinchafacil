-- migration_diagnostico_orcamento_v1.sql
-- ROADMAP socorro automotivo — Etapa 5 (diagnóstico e orçamento complementar).
--
-- Cobre o meio do fluxo ON_SITE/HYBRID (OnSiteFlowDefinition, já com os
-- estados no ENUM de pedidos.status desde Etapa 3): o prestador chega
-- (no_local), diagnostica (diagnostico_iniciado → diagnostico_concluido) e,
-- se precisar de peça/serviço adicional não coberto pela tarifa-base, monta
-- um orçamento complementar que o CLIENTE precisa aprovar explicitamente
-- antes da execução (autorizacao_servico_pendente → em_execucao_servico).
--
-- `id`/FKs usam INT, mesmo padrão de `pedidos.id`/`guinchos.id` (evita o
-- mesmo erro de FK signed/unsigned já corrigido em migrations anteriores).

CREATE TABLE IF NOT EXISTS `pedido_diagnosticos` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id`        INT NOT NULL,
    `guincho_id`       INT NOT NULL,
    `resultado`        VARCHAR(30) NOT NULL COMMENT 'RESOLVIDO_SEM_ORCAMENTO | REQUER_ORCAMENTO | REQUER_REBOQUE',
    `descricao`        TEXT NULL,
    `criado_em`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_pedido_diagnostico` (`pedido_id`),
    KEY `idx_diagnostico_guincho` (`guincho_id`),
    CONSTRAINT `fk_diagnostico_pedido` FOREIGN KEY (`pedido_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_diagnostico_guincho` FOREIGN KEY (`guincho_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedido_orcamentos` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id`        INT NOT NULL,
    `diagnostico_id`   INT NOT NULL,
    `itens_json`       LONGTEXT NOT NULL COMMENT 'array [{descricao, valor}] — itens propostos pelo prestador',
    `valor_total`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status`           VARCHAR(20) NOT NULL DEFAULT 'PENDENTE' COMMENT 'PENDENTE | APROVADO | RECUSADO',
    `decidido_em`      DATETIME NULL,
    `criado_em`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_pedido_orcamento` (`pedido_id`),
    KEY `idx_orcamento_diagnostico` (`diagnostico_id`),
    KEY `idx_orcamento_status` (`status`),
    CONSTRAINT `fk_orcamento_pedido` FOREIGN KEY (`pedido_id`)
        REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_orcamento_diagnostico` FOREIGN KEY (`diagnostico_id`)
        REFERENCES `pedido_diagnosticos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
