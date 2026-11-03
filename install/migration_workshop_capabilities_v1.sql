-- Capacidades de catalogo para providers WORKSHOP.
-- provider_capabilities permanece reservado ao legado de guinchos (provider_id = guinchos.id).
CREATE TABLE IF NOT EXISTS `provider_workshop_capabilities` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `provider_id` INT NOT NULL,
    `service_type_id` INT NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `approval_status` ENUM('PENDING','APPROVED','SUSPENDED','REJECTED') NOT NULL DEFAULT 'PENDING',
    `base_price` DECIMAL(10,2) DEFAULT NULL,
    `price_per_km` DECIMAL(10,2) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_workshop_capability_service` (`provider_id`, `service_type_id`),
    KEY `idx_workshop_capability_status` (`approval_status`, `enabled`),
    CONSTRAINT `fk_workshop_capability_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_workshop_capability_service` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
