-- Permite a entrada inicial do parceiro antes da qualificacao do reboque.
-- O matching continua bloqueando reboque enquanto reboque_aprovado=0.
SET @nn := (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guinchos' AND COLUMN_NAME='cnh_numero');
SET @sql := IF(@nn='NO', 'ALTER TABLE guinchos MODIFY cnh_numero VARCHAR(20) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @nn := (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='guinchos' AND COLUMN_NAME='placa_guincho');
SET @sql := IF(@nn='NO', 'ALTER TABLE guinchos MODIFY placa_guincho VARCHAR(8) NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
