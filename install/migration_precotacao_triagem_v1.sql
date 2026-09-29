-- Migration v1: triagem de servicos da pre-cotacao v2
-- Idempotente.

CREATE TABLE IF NOT EXISTS `precotacao_triagem_servicos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(64) NOT NULL,
    `nome` VARCHAR(120) NOT NULL,
    `descricao` VARCHAR(255) DEFAULT NULL,
    `icone` VARCHAR(64) DEFAULT NULL,
    `oficina_tipo` VARCHAR(64) DEFAULT NULL,
    `service_type_code` VARCHAR(64) DEFAULT NULL,
    `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 100,
    `ativo` TINYINT(1) NOT NULL DEFAULT 0,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`),
    KEY `idx_ativo_ordem` (`ativo`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
