-- Sinais agregados de demanda para planejamento territorial.
-- Nao armazena endereco, usuario, token ou coordenada exata.
CREATE TABLE IF NOT EXISTS `pre_quote_demand_cells` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `cell_lat` DECIMAL(4,2) NOT NULL,
    `cell_lng` DECIMAL(5,2) NOT NULL,
    `period_date` DATE NOT NULL,
    `service_key` VARCHAR(40) NOT NULL DEFAULT 'outro',
    `vehicle_category` VARCHAR(30) NOT NULL DEFAULT 'popular',
    `quote_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `accepted_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `converted_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_pre_quote_demand_cell` (`cell_lat`,`cell_lng`,`period_date`,`service_key`,`vehicle_category`),
    KEY `idx_pre_quote_demand_date` (`period_date`),
    KEY `idx_pre_quote_demand_volume` (`quote_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
