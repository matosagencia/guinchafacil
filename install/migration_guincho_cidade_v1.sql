-- migration_guincho_cidade_v1.sql
-- Vincula o guincheiro a uma cidade-alvo cadastrada (tabela `cidades`,
-- ver migration_cidades_v1.sql). Cliente não tem esse vínculo — só o
-- prestador de serviço é escopado por cidade de atuação.
-- Nullable por compatibilidade com guinchos já cadastrados antes desta
-- migration; o cadastro novo (AuthController::registroGuincho) passa a
-- exigir o preenchimento.

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'cidade_id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE guinchos ADD COLUMN cidade_id BIGINT UNSIGNED NULL AFTER usuario_id', 'SELECT "cidade_id já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND INDEX_NAME = 'idx_guinchos_cidade_id');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE guinchos ADD INDEX idx_guinchos_cidade_id (cidade_id)', 'SELECT "idx_guinchos_cidade_id já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK só é aplicada se a tabela `cidades` já existir (garantido pela ordem
-- alfabética: migration_cidades_v1.sql roda antes de
-- migration_guincho_cidade_v1.sql) e se ainda não tiver sido criada.
SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND CONSTRAINT_NAME = 'fk_guinchos_cidade_id');
SET @tbl_cidades_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades');
SET @sql := IF(@fk_exists = 0 AND @tbl_cidades_exists = 1,
    'ALTER TABLE guinchos ADD CONSTRAINT fk_guinchos_cidade_id FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE SET NULL',
    'SELECT "fk_guinchos_cidade_id já existe ou tabela cidades ainda não existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
