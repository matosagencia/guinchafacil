-- Monetização por indicação de oficinas parceiras v1.
CREATE TABLE IF NOT EXISTS `provider_workshop_settings` (
  `provider_id` INT NOT NULL PRIMARY KEY,
  `taxa_indicacao_fixa` DECIMAL(10,2) NOT NULL DEFAULT 30.00,
  `regra_versao` VARCHAR(30) NOT NULL DEFAULT 'v1',
  `status_parceria` VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
  `raio_checkin_m` INT NOT NULL DEFAULT 150,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  CONSTRAINT `fk_workshop_settings_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedido_indicacoes_oficina` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT NOT NULL,
  `provider_id` INT NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'SELECIONADA',
  `regra_comissao_snapshot_json` LONGTEXT NULL,
  `checkin_evidencia_id` BIGINT UNSIGNED NULL,
  `checkin_geofence_ok` TINYINT(1) NULL,
  `checkin_distancia_m` DECIMAL(8,2) NULL,
  `revisao_admin_id` INT NULL,
  `revisao_admin_nota` VARCHAR(255) NULL,
  `order_charge_item_id` INT NULL,
  `idempotency_key` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uk_indicacao_idempotency` (`idempotency_key`),
  UNIQUE KEY `uk_indicacao_pedido` (`pedido_id`),
  KEY `idx_indicacao_provider` (`provider_id`),
  KEY `idx_indicacao_status` (`status`),
  CONSTRAINT `fk_indicacao_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_indicacao_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_indicacao_evidencia` FOREIGN KEY (`checkin_evidencia_id`) REFERENCES `pedido_evidencias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracoes` (`chave`,`valor`,`descricao`) VALUES ('monetizacao_oficinas_ativo','0','Feature flag da monetização por indicação de oficinas parceiras') ON DUPLICATE KEY UPDATE `descricao`=VALUES(`descricao`);
