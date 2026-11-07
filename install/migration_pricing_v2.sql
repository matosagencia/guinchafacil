-- Migration idempotente de precificacao por cidade.
-- Mantem a regra global (cidade_id NULL) como fallback.

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND COLUMN_NAME = 'cidade_id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE service_pricing_rules ADD COLUMN cidade_id BIGINT UNSIGNED NULL AFTER service_type_id', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @old_uk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND INDEX_NAME = 'uk_service_pricing_rules_type');
SET @new_uk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND INDEX_NAME = 'uk_service_pricing_rules_type_cidade');
SET @sql := IF(@old_uk_exists = 1 AND @new_uk_exists = 0,
    'ALTER TABLE service_pricing_rules DROP INDEX uk_service_pricing_rules_type, ADD UNIQUE KEY uk_service_pricing_rules_type_cidade (service_type_id, cidade_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND INDEX_NAME = 'idx_service_pricing_rules_cidade');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE service_pricing_rules ADD INDEX idx_service_pricing_rules_cidade (cidade_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND CONSTRAINT_NAME = 'fk_service_pricing_rules_cidade');
SET @tbl_cidades_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades');
SET @sql := IF(@fk_exists = 0 AND @tbl_cidades_exists = 1,
    'ALTER TABLE service_pricing_rules ADD CONSTRAINT fk_service_pricing_rules_cidade FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
