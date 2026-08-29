-- Completa a atribuição geográfica dos pedidos já após a v1.
SET @db_name := DATABASE();
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='cidade_id')=0,
 'ALTER TABLE pedidos ADD COLUMN cidade_id BIGINT UNSIGNED NULL AFTER landing_page', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND CONSTRAINT_NAME='fk_pedidos_cidade_atribuicao');
SET @sql := IF(@fk=0,'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cidade_atribuicao FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE SET NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
