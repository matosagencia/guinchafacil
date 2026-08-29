-- migration_status_fk_fix.sql
-- Remove FKs duplicadas ou incorretas de status

SET @db_name := DATABASE();

-- Tenta remover a FK pelo nome conhecido
SET @fk_name := (
    SELECT CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedidos'
      AND CONSTRAINT_NAME = 'pedidos_ibfk_3'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

SET @sql := IF(@fk_name IS NOT NULL,
    CONCAT('ALTER TABLE pedidos DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT "FK pedidos_ibfk_3 já não existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'migration_status_fk_fix.sql aplicado.' AS resultado;
