-- migration_add_plate_emplacamento.sql
-- Adiciona campos de emplacamento e corrige localização operacional do guincho

SET @db_name := DATABASE();

-- guinchos: cidade/UF da placa
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'guinchos'
      AND COLUMN_NAME = 'cidade_placa'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN cidade_placa VARCHAR(100) NULL AFTER placa_guincho',
    'SELECT "guinchos.cidade_placa já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'guinchos'
      AND COLUMN_NAME = 'uf_placa'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN uf_placa VARCHAR(2) NULL AFTER cidade_placa',
    'SELECT "guinchos.uf_placa já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- guinchos: localização operacional
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'guinchos'
      AND COLUMN_NAME = 'lat_operacao'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN lat_operacao DECIMAL(10,8) NULL AFTER cnh_validade',
    'SELECT "guinchos.lat_operacao já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'guinchos'
      AND COLUMN_NAME = 'lng_operacao'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN lng_operacao DECIMAL(11,8) NULL AFTER lat_operacao',
    'SELECT "guinchos.lng_operacao já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- veiculos: cidade/UF da placa
SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'veiculos'
      AND COLUMN_NAME = 'cidade_placa'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN cidade_placa VARCHAR(100) NULL AFTER placa',
    'SELECT "veiculos.cidade_placa já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'veiculos'
      AND COLUMN_NAME = 'uf_placa'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN uf_placa VARCHAR(2) NULL AFTER cidade_placa',
    'SELECT "veiculos.uf_placa já existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
