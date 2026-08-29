-- migration_add_proof_of_service_photos.sql
-- Adiciona colunas para foto de prova de serviço na tabela pedidos

SET @db_name := DATABASE();

-- Function to check if column exists
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'foto_plataforma'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pedidos ADD COLUMN foto_plataforma VARCHAR(255) NULL AFTER status', 'SELECT "foto_plataforma já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'foto_destino'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pedidos ADD COLUMN foto_destino VARCHAR(255) NULL AFTER foto_plataforma', 'SELECT "foto_destino já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
