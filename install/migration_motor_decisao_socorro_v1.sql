-- Motor de decisao do socorro em etapas.
--
-- O valor detalhado do orcamento continua privado entre cliente e prestador.
-- A plataforma registra apenas os marcos necessarios para decidir o proximo
-- passo: orcamento informado, aprovacao e necessidade de reboque.

SET @db_name := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'orcamento_informado_at') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `orcamento_informado_at` DATETIME NULL AFTER `custo_estimado`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'cliente_decisao_orcamento') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `cliente_decisao_orcamento` VARCHAR(20) NULL AFTER `orcamento_informado_at`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'cliente_decisao_reboque') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `cliente_decisao_reboque` VARCHAR(20) NULL AFTER `cliente_decisao_orcamento`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'decisoes_socorro_at') = 0,
    'ALTER TABLE `pedidos` ADD COLUMN `decisoes_socorro_at` DATETIME NULL AFTER `cliente_decisao_reboque`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'provider_workshop_settings'
      AND COLUMN_NAME = 'raio_resgate_direto_km') = 0,
    'ALTER TABLE `provider_workshop_settings` ADD COLUMN `raio_resgate_direto_km` DECIMAL(6,2) NULL AFTER `raio_checkin_m`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`)
VALUES
    ('comissao_orcamento_oficina_movel', '25.00', 'Comissao cobrada da oficina movel quando o cliente aprova o orcamento.'),
    ('desconto_saida_oficina_percentual', '0.21', 'Desconto aplicado no segundo reboque quando o cliente sai da oficina parceira apos recusar o orcamento.'),
    ('monetizacao_oficinas_ativo', '1', 'Ativa o motor de monetizacao e indicacao de oficinas parceiras.')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

CREATE TABLE IF NOT EXISTS `pedido_decisoes_socorro` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `provider_id` INT NULL,
    `actor_type` VARCHAR(20) NOT NULL,
    `actor_id` INT NULL,
    `decision_code` VARCHAR(50) NOT NULL,
    `metadata_json` LONGTEXT NULL,
    `idempotency_key` VARCHAR(120) NOT NULL,
    `created_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_pedido_decisao_socorro_idempotency` (`idempotency_key`),
    KEY `idx_pedido_decisao_socorro_pedido` (`pedido_id`),
    KEY `idx_pedido_decisao_socorro_provider` (`provider_id`),
    CONSTRAINT `fk_pedido_decisao_socorro_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pedido_decisao_socorro_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
