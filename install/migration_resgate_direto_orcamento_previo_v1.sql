-- Fundação do resgate direto por oficina e orçamento prévio.
-- A migração é segura para reexecução em ambientes já parcialmente atualizados.

SET @db_name := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'modalidade_socorro') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `modalidade_socorro` VARCHAR(30) NOT NULL DEFAULT ''REBOQUE_TRADICIONAL'' AFTER `status`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'local_resgate_lat') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `local_resgate_lat` DECIMAL(10,8) NULL AFTER `lng_destino`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'local_resgate_lng') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `local_resgate_lng` DECIMAL(11,8) NULL AFTER `local_resgate_lat`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'provider_workshop_settings' AND COLUMN_NAME = 'taxa_resgate_direto') = 0,
    'ALTER TABLE `provider_workshop_settings` ADD COLUMN `taxa_resgate_direto` DECIMAL(10,2) NOT NULL DEFAULT 20.00 AFTER `taxa_indicacao_fixa`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'provider_workshop_settings' AND COLUMN_NAME = 'permite_resgate_direto') = 0,
    'ALTER TABLE `provider_workshop_settings` ADD COLUMN `permite_resgate_direto` TINYINT(1) NOT NULL DEFAULT 1 AFTER `taxa_resgate_direto`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `pedido_orcamentos_previos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `taxa_diagnostico_local` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estimativa_minima` DECIMAL(10,2) NOT NULL,
    `estimativa_maxima` DECIMAL(10,2) NOT NULL,
    `descricao_avaria` TEXT NOT NULL,
    `abater_diagnostico_na_os` TINYINT(1) NOT NULL DEFAULT 1,
    `status` VARCHAR(30) NOT NULL DEFAULT 'PENDENTE_CLIENTE',
    `termo_aceite_cliente_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_orcamento_previo_pedido` (`pedido_id`),
    KEY `idx_orcamento_previo_provider` (`provider_id`),
    KEY `idx_orcamento_previo_status` (`status`),
    CONSTRAINT `fk_orcamento_previo_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_orcamento_previo_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
