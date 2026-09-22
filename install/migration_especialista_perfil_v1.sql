-- Optional PIX change-request fields for specialists.
SET @db_name := DATABASE();

SET @has_pix_pending := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='especialistas' AND COLUMN_NAME='chave_pix_pendente');
SET @sql_pix_pending := IF(@has_pix_pending=0, 'ALTER TABLE especialistas ADD COLUMN chave_pix_pendente VARCHAR(150) NULL AFTER chave_pix', 'SELECT 1');
PREPARE stmt_pix_pending FROM @sql_pix_pending; EXECUTE stmt_pix_pending; DEALLOCATE PREPARE stmt_pix_pending;

SET @has_pix_type_pending := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='especialistas' AND COLUMN_NAME='chave_pix_tipo_pendente');
SET @sql_pix_type_pending := IF(@has_pix_type_pending=0, 'ALTER TABLE especialistas ADD COLUMN chave_pix_tipo_pendente ENUM(\'cpf\',\'cnpj\',\'email\',\'telefone\',\'aleatoria\') NULL AFTER chave_pix_pendente', 'SELECT 1');
PREPARE stmt_pix_type_pending FROM @sql_pix_type_pending; EXECUTE stmt_pix_type_pending; DEALLOCATE PREPARE stmt_pix_type_pending;

SET @has_pix_requested := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='especialistas' AND COLUMN_NAME='chave_pix_solicitada_em');
SET @sql_pix_requested := IF(@has_pix_requested=0, 'ALTER TABLE especialistas ADD COLUMN chave_pix_solicitada_em DATETIME NULL AFTER chave_pix_tipo_pendente', 'SELECT 1');
PREPARE stmt_pix_requested FROM @sql_pix_requested; EXECUTE stmt_pix_requested; DEALLOCATE PREPARE stmt_pix_requested;
