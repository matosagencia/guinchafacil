-- migration_categoria_tarifa_veiculo_v1.sql
-- Adds the optional tariff category using MySQL-compatible dynamic DDL.

SET @db_name := DATABASE();
SET @has_categoria_tarifa := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='veiculos' AND COLUMN_NAME='categoria_tarifa'
);
SET @sql_categoria_tarifa := IF(@has_categoria_tarifa=0,
    'ALTER TABLE veiculos ADD COLUMN categoria_tarifa VARCHAR(20) NULL AFTER tipo',
    'SELECT 1'
);
PREPARE stmt_categoria_tarifa FROM @sql_categoria_tarifa;
EXECUTE stmt_categoria_tarifa;
DEALLOCATE PREPARE stmt_categoria_tarifa;
