-- Torna regiao_id opcional em prospeccao_leads.
-- Necessario para aceitar leads de /parceiros/guinchos e /parceiros/oficinas
-- (que nao pedem cidade/UF na primeira etapa).
-- Idempotente para MySQL/MariaDB.

SET @schema := DATABASE();

-- 1. Se existir FK em regiao_id, remove antes do MODIFY
SET @fk := (
  SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'prospeccao_leads'
    AND COLUMN_NAME = 'regiao_id'
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);

SET @sql := IF(@fk IS NOT NULL,
  CONCAT('ALTER TABLE prospeccao_leads DROP FOREIGN KEY ', @fk),
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Torna regiao_id nullable (idempotente)
SET @sql := IF(
  (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @schema
     AND TABLE_NAME = 'prospeccao_leads'
     AND COLUMN_NAME = 'regiao_id') = 'NO',
  'ALTER TABLE prospeccao_leads MODIFY regiao_id BIGINT UNSIGNED NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Se a FK existia, recria com ON DELETE SET NULL (agora faz sentido)
SET @sql := IF(@fk IS NOT NULL,
  CONCAT('ALTER TABLE prospeccao_leads ADD CONSTRAINT ', @fk,
         ' FOREIGN KEY (regiao_id) REFERENCES prospeccao_regioes(id) ON DELETE SET NULL'),
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;