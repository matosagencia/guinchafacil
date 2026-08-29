-- migration_add_distancia_percorrida.sql
-- Adiciona coluna para registrar a distância percorrida pelo guincho antes do cancelamento

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'distancia_guincho_percorrida'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pedidos ADD COLUMN distancia_guincho_percorrida DECIMAL(6,2) NULL AFTER distancia_km', 'SELECT "distancia_guincho_percorrida já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
