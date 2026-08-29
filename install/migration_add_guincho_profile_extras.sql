-- migration_add_guincho_profile_extras.sql
-- Adiciona campos extras ao perfil do guincho (cidade, UF da placa, foto caminhão)

SET @db_name := DATABASE();

-- Function to check/add column
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'cidade_placa');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE guinchos ADD COLUMN cidade_placa VARCHAR(100) NULL AFTER lat_operacao', 'SELECT "cidade_placa já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'uf_placa');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE guinchos ADD COLUMN uf_placa VARCHAR(2) NULL AFTER cidade_placa', 'SELECT "uf_placa já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'foto_caminhao');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE guinchos ADD COLUMN foto_caminhao VARCHAR(255) NULL AFTER uf_placa', 'SELECT "foto_caminhao já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
