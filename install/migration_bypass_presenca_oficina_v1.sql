-- Amostragem de presença do cliente e casos de bypass para revisão humana.
CREATE TABLE IF NOT EXISTS `pedido_presenca_localizacoes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `usuario_id` INT NOT NULL,
    `latitude` DECIMAL(10,8) NOT NULL,
    `longitude` DECIMAL(11,8) NOT NULL,
    `precisao_metros` DECIMAL(8,2) NOT NULL,
    `origem_ponto` VARCHAR(30) NOT NULL DEFAULT 'APP_CLIENTE',
    `captured_at` DATETIME NOT NULL,
    KEY `idx_presenca_pedido` (`pedido_id`),
    KEY `idx_presenca_usuario` (`usuario_id`),
    KEY `idx_presenca_captured` (`captured_at`),
    CONSTRAINT `fk_presenca_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedido_bypass_cases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `amostras_validas_count` INT NOT NULL DEFAULT 0,
    `permanencia_minutos` INT NOT NULL DEFAULT 0,
    `precisao_media_m` DECIMAL(8,2) NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'SUSPEITO',
    `decisao_admin_id` INT NULL,
    `decisao_nota` VARCHAR(255) NULL,
    `order_charge_item_id` INT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_bypass_pedido_provider` (`pedido_id`, `provider_id`),
    KEY `idx_bypass_status` (`status`),
    CONSTRAINT `fk_bypass_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
    CONSTRAINT `fk_bypass_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
