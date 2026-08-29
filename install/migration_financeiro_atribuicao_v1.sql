-- Atribuição de marketing e fechamento financeiro.
-- Idempotente: todas as alterações verificam o schema atual.
SET @db_name := DATABASE();

-- Nota: NÃO usar "AFTER observacao_interna" aqui — essa coluna só é criada
-- por migration_funcionario_gerente_v1.sql, que ordena DEPOIS deste arquivo
-- (glob natural: "financeiro_atribuicao" < "funcionario_gerente"). Instalação
-- fresca quebraria silenciosamente. Sem AFTER, a coluna vai pro fim da tabela.
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='utm_source')=0,
 'ALTER TABLE pedidos ADD COLUMN utm_source VARCHAR(120) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='utm_medium')=0,
 'ALTER TABLE pedidos ADD COLUMN utm_medium VARCHAR(120) NULL AFTER utm_source', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='utm_campaign')=0,
 'ALTER TABLE pedidos ADD COLUMN utm_campaign VARCHAR(180) NULL AFTER utm_medium', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='utm_content')=0,
 'ALTER TABLE pedidos ADD COLUMN utm_content VARCHAR(180) NULL AFTER utm_campaign', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='utm_term')=0,
 'ALTER TABLE pedidos ADD COLUMN utm_term VARCHAR(180) NULL AFTER utm_content', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='canal_aquisicao')=0,
 'ALTER TABLE pedidos ADD COLUMN canal_aquisicao VARCHAR(40) NOT NULL DEFAULT ''organico'' AFTER utm_term', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='referrer_url')=0,
 'ALTER TABLE pedidos ADD COLUMN referrer_url VARCHAR(1000) NULL AFTER canal_aquisicao', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='landing_page')=0,
 'ALTER TABLE pedidos ADD COLUMN landing_page VARCHAR(1000) NULL AFTER referrer_url', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND COLUMN_NAME='cidade_id')=0,
 'ALTER TABLE pedidos ADD COLUMN cidade_id BIGINT UNSIGNED NULL AFTER landing_page', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='usuarios' AND COLUMN_NAME='utm_source_cadastro')=0,
 'ALTER TABLE usuarios ADD COLUMN utm_source_cadastro VARCHAR(120) NULL AFTER atualizado_em', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='usuarios' AND COLUMN_NAME='utm_medium_cadastro')=0,
 'ALTER TABLE usuarios ADD COLUMN utm_medium_cadastro VARCHAR(120) NULL AFTER utm_source_cadastro', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='usuarios' AND COLUMN_NAME='utm_campaign_cadastro')=0,
 'ALTER TABLE usuarios ADD COLUMN utm_campaign_cadastro VARCHAR(180) NULL AFTER utm_medium_cadastro', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS gastos_marketing (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 canal VARCHAR(40) NOT NULL,
 campanha VARCHAR(180) NOT NULL DEFAULT '',
 data DATE NOT NULL,
 valor_gasto DECIMAL(12,2) NOT NULL,
 cidade_id BIGINT UNSIGNED NULL,
 origem_lancamento ENUM('manual','import_csv') NOT NULL DEFAULT 'manual',
 criado_por_admin_id INT NULL,
 hash_idem CHAR(64) NOT NULL,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 excluido_por_admin_id INT NULL,
 excluido_em DATETIME NULL,
 criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uk_gastos_marketing_hash (hash_idem),
 KEY idx_gastos_marketing_data (data),
 KEY idx_gastos_marketing_canal_data (canal,data),
 KEY idx_gastos_marketing_cidade (cidade_id),
 CONSTRAINT fk_gastos_marketing_cidade FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE SET NULL,
 CONSTRAINT fk_gastos_marketing_admin FOREIGN KEY (criado_por_admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND INDEX_NAME='idx_pedidos_canal_aquisicao');
SET @sql := IF(@idx=0,'CREATE INDEX idx_pedidos_canal_aquisicao ON pedidos(canal_aquisicao, criado_em)','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedidos' AND CONSTRAINT_NAME='fk_pedidos_cidade_atribuicao');
SET @sql := IF(@fk=0,'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_cidade_atribuicao FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE SET NULL','SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
