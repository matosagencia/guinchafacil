-- Magic link de autenticacao (login recorrente sem senha)
-- Canais: whatsapp (padrao), email (fallback), sms (Brevo; desativado ate admin ligar)
-- Portavel MySQL 5.7+/MariaDB (INFORMATION_SCHEMA + dynamic SQL).

SET @db = DATABASE();

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'magic_token_hash') > 0,
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN magic_token_hash CHAR(64) NULL AFTER google_subject'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'magic_token_expira_em') > 0,
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN magic_token_expira_em DATETIME NULL AFTER magic_token_hash'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'magic_token_canal') > 0,
    'SELECT 1',
    "ALTER TABLE usuarios ADD COLUMN magic_token_canal ENUM('whatsapp','email','sms') NULL AFTER magic_token_expira_em"));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_magic_token') > 0,
    'SELECT 1',
    'ALTER TABLE usuarios ADD KEY idx_usuarios_magic_token (magic_token_hash)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO configuracoes (chave, valor, descricao)
SELECT 'sms_enabled', '0', 'SMS via Brevo (0=off, 1=on). Fallback do magic link.'
WHERE NOT EXISTS (SELECT 1 FROM configuracoes WHERE chave = 'sms_enabled');