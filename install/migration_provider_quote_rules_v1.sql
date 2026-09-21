-- Faixas comerciais para orçamento prévio de prestadores móveis/oficinas.
CREATE TABLE IF NOT EXISTS `provider_quote_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT NOT NULL,
    `service_code` VARCHAR(80) NOT NULL DEFAULT 'DEFAULT',
    `estimativa_minima` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `estimativa_maxima` DECIMAL(10,2) NOT NULL,
    `taxa_diagnostico_local` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `regra_versao` VARCHAR(30) NOT NULL DEFAULT 'quote-v1',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_provider_quote_rule` (`provider_id`, `service_code`),
    KEY `idx_provider_quote_active` (`provider_id`, `active`),
    CONSTRAINT `fk_provider_quote_rule_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO provider_quote_rules
    (provider_id, service_code, estimativa_minima, estimativa_maxima,
     taxa_diagnostico_local, regra_versao, active, created_at, updated_at)
SELECT ws.provider_id, 'DEFAULT', 0.00, 5000.00, 0.00, 'quote-v1', 1, NOW(), NOW()
  FROM provider_workshop_settings ws
  LEFT JOIN provider_quote_rules qr
    ON qr.provider_id = ws.provider_id AND qr.service_code = 'DEFAULT'
 WHERE qr.id IS NULL;
