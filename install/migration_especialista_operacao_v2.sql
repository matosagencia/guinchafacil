-- Migration aditiva: não remove nem sobrescreve dados existentes.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atendimentos_especialista' AND COLUMN_NAME='ofertado_em')=0,
 'ALTER TABLE atendimentos_especialista ADD COLUMN ofertado_em DATETIME NULL AFTER status', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atendimentos_especialista' AND COLUMN_NAME='expiracao_oferta')=0,
 'ALTER TABLE atendimentos_especialista ADD COLUMN expiracao_oferta DATETIME NULL AFTER ofertado_em', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
