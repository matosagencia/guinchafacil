-- Checkout rapido pos-cotacao - v1
-- Portavel: MySQL (qualquer versao) + MariaDB.
-- Nao usa ADD COLUMN IF NOT EXISTS (extensao MariaDB-only).
-- Idempotente via INFORMATION_SCHEMA + dynamic SQL.

-- 1) Login sem senha (Google Sign-in)
ALTER TABLE usuarios
  MODIFY COLUMN senha_hash VARCHAR(255) NULL;

-- 2) Vinculo triagem -> veiculo/pedido (idempotente)

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'triage_sessions'
    AND COLUMN_NAME = 'veiculo_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE triage_sessions ADD COLUMN veiculo_id INT(11) NULL AFTER cliente_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'triage_sessions'
    AND COLUMN_NAME = 'pedido_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE triage_sessions ADD COLUMN pedido_id INT(11) NULL AFTER veiculo_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
