-- CONTRATO-PEDIDO-v1. Seguro para execucoes repetidas em MySQL/MariaDB.
SET @schema_name = DATABASE();

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN oficina_id INT NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'oficina_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN oficina_nome VARCHAR(255) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'oficina_nome');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN triagem VARCHAR(50) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'triagem');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN custo_assistencia DECIMAL(10,2) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'custo_assistencia');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN custo_reboque DECIMAL(10,2) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'custo_reboque');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN recomendacao ENUM(''assistencia'', ''reboque'') NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'recomendacao');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN origem_funil ENUM(''oficina'', ''especialista'') NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'origem_funil');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD COLUMN triage_session_id CHAR(32) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'pedidos' AND column_name = 'triage_session_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE pedidos ADD UNIQUE KEY uq_pedidos_triage_session_id (triage_session_id)', 'SELECT 1')
  FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'pedidos' AND index_name = 'uq_pedidos_triage_session_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE logs_webhook ADD COLUMN provedor VARCHAR(32) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'logs_webhook' AND column_name = 'provedor');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE logs_webhook ADD COLUMN event_id VARCHAR(191) NULL', 'SELECT 1')
  FROM information_schema.columns WHERE table_schema = @schema_name AND table_name = 'logs_webhook' AND column_name = 'event_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @webhook_columns_ok = (SELECT COUNT(*) = 2 FROM information_schema.columns
  WHERE table_schema = @schema_name AND table_name = 'logs_webhook' AND column_name IN ('provedor', 'event_id'));
SET @sql = (SELECT IF(@webhook_columns_ok = 1 AND COUNT(*) = 0,
  'ALTER TABLE logs_webhook ADD UNIQUE KEY uq_logs_webhook_provedor_event_id (provedor, event_id)', 'SELECT 1')
  FROM information_schema.statistics WHERE table_schema = @schema_name AND table_name = 'logs_webhook' AND index_name = 'uq_logs_webhook_provedor_event_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
