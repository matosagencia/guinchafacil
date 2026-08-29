-- migration_add_guincho_manual_location.sql
-- Adiciona colunas para localização manual do guincho

SET @db_name := DATABASE();

-- Function to check if column exists
SET @col_exists_lat := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'lat_operacao'
);
SET @sql_lat := IF(@col_exists_lat = 0, 'ALTER TABLE guinchos ADD COLUMN lat_operacao DECIMAL(10,8) NULL AFTER cnh_validade', 'SELECT "lat_operacao já existe" AS info');
PREPARE stmt_lat FROM @sql_lat; EXECUTE stmt_lat; DEALLOCATE PREPARE stmt_lat;

SET @col_exists_lng := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'lng_operacao'
);
SET @sql_lng := IF(@col_exists_lng = 0, 'ALTER TABLE guinchos ADD COLUMN lng_operacao DECIMAL(10,8) NULL AFTER lat_operacao', 'SELECT "lng_operacao já existe" AS info');
PREPARE stmt_lng FROM @sql_lng; EXECUTE stmt_lng; DEALLOCATE PREPARE stmt_lng;
