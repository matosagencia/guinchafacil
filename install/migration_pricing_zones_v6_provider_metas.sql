-- Metas mínimas separadas para o gate Pedra Viva.
SET @db_name := DATABASE();
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pricing_zones' AND COLUMN_NAME='meta_guinchos_min');
SET @sql := IF(@col_exists=0, 'ALTER TABLE pricing_zones ADD COLUMN meta_guinchos_min INT NULL AFTER bairros_referencia', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pricing_zones' AND COLUMN_NAME='meta_especialistas_min');
SET @sql := IF(@col_exists=0, 'ALTER TABLE pricing_zones ADD COLUMN meta_especialistas_min INT NULL AFTER meta_guinchos_min', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE pricing_zones SET meta_guinchos_min=4, meta_especialistas_min=6 WHERE code='niteroi-celula-1' AND meta_guinchos_min IS NULL;
