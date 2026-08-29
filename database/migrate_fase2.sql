-- GuinchaFácil — Migração Fase 2 — Carteira Financeira e Governança
-- Executar uma vez no banco de produção e desenvolvimento.
-- Idempotente: usa IF NOT EXISTS em todas as criações de tabela.
-- Data: 2026-04-19

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
-- Tabela: guincheiro_carteira
-- Saldo consolidado por guincheiro
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `guincheiro_carteira` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `guincho_id`          BIGINT UNSIGNED NOT NULL,
    `saldo_total`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Soma de tudo',
    `saldo_compensacao`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Aguardando liquidação do gateway',
    `saldo_liberado`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Disponível para saque',
    `saldo_reservado`     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Em solicitação de saque pendente',
    `saldo_pago`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Já repassado ao guincheiro',
    `atualizado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_guincho_carteira` (`guincho_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tabela: guincheiro_movimentos
-- Histórico de cada crédito/débito na carteira
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `guincheiro_movimentos` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `guincho_id`      BIGINT UNSIGNED NOT NULL,
    `pedido_id`       BIGINT UNSIGNED NULL,
    `pagamento_id`    BIGINT UNSIGNED NULL,
    `saque_id`        BIGINT UNSIGNED NULL,
    `tipo`            ENUM('credito','debito','liberacao','reserva','estorno','cancelamento') NOT NULL,
    `valor`           DECIMAL(10,2) NOT NULL,
    `saldo_apos`      DECIMAL(10,2) NOT NULL,
    `descricao`       VARCHAR(500) NOT NULL,
    `admin_id`        BIGINT UNSIGNED NULL,
    `hash_idem`       VARCHAR(64) NULL COMMENT 'SHA256 para idempotência',
    `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_hash_idem` (`hash_idem`),
    KEY `idx_guincho_id` (`guincho_id`),
    KEY `idx_pedido_id` (`pedido_id`),
    KEY `idx_saque_id` (`saque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tabela: pagamento_liquidacoes
-- Evolução entre aprovação e liquidação no gateway
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pagamento_liquidacoes` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pagamento_id`                BIGINT UNSIGNED NOT NULL,
    `gateway`                     VARCHAR(30) NOT NULL DEFAULT 'mercadopago',
    `status_liquidacao_gateway`   VARCHAR(50) NULL,
    `valor_bruto`                 DECIMAL(10,2) NULL,
    `valor_liquido`               DECIMAL(10,2) NULL,
    `taxas_gateway`               DECIMAL(10,2) NULL,
    `data_prevista_liquidacao`    DATE NULL,
    `data_liquidacao_confirmada`  DATE NULL,
    `payload_resumido`            TEXT NULL,
    `hash_evento`                 VARCHAR(64) NULL COMMENT 'Idempotência de webhook',
    `criado_em`                   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pagamento_id` (`pagamento_id`),
    KEY `idx_hash_evento` (`hash_evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tabela: saques_guincheiro
-- Solicitações de saque por parte do guincheiro
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `saques_guincheiro` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `guincho_id`          BIGINT UNSIGNED NOT NULL,
    `valor_solicitado`    DECIMAL(10,2) NOT NULL,
    `status`              ENUM('pendente','autorizado','pago','reprovado','cancelado') NOT NULL DEFAULT 'pendente',
    `id_transacao_pix`    VARCHAR(100) NULL,
    `solicitado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `autorizado_em`       DATETIME NULL,
    `pago_em`             DATETIME NULL,
    `admin_id_autorizador` BIGINT UNSIGNED NULL,
    `observacao_admin`    TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_guincho_id` (`guincho_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tabela: saque_eventos
-- Histórico de eventos por solicitação de saque
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `saque_eventos` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `saque_id`        BIGINT UNSIGNED NOT NULL,
    `tipo_evento`     VARCHAR(50) NOT NULL,
    `descricao`       TEXT NOT NULL,
    `admin_id`        BIGINT UNSIGNED NULL,
    `payload_resumido` TEXT NULL,
    `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_saque_id` (`saque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Tabela: env_auditoria
-- Registro de alterações no .env pelo admin
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `env_auditoria` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`        BIGINT UNSIGNED NOT NULL,
    `chave`           VARCHAR(100) NOT NULL,
    `valor_mascarado` VARCHAR(200) NOT NULL,
    `acao`            ENUM('criado','alterado','removido') NOT NULL DEFAULT 'alterado',
    `hash_alteracao`  VARCHAR(64) NOT NULL,
    `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────────────────────
-- Seed inicial: criar carteira para guinchos existentes
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `guincheiro_carteira` (`guincho_id`, `saldo_total`, `saldo_compensacao`, `saldo_liberado`, `saldo_reservado`, `saldo_pago`)
SELECT `id`, 0.00, 0.00, 0.00, 0.00, 0.00
FROM `guinchos`
WHERE NOT EXISTS (
    SELECT 1 FROM `guincheiro_carteira` WHERE `guincho_id` = `guinchos`.`id`
);
