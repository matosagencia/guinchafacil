-- Vincula contas locais a uma identidade Google verificada.
-- A coluna e' nullable para preservar todas as contas existentes.
SET @db = DATABASE();

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'google_subject') > 0,
    'SELECT 1',
    'ALTER TABLE usuarios ADD COLUMN google_subject VARCHAR(255) NULL AFTER email'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'uk_usuarios_google_subject') > 0,
    'SELECT 1',
    'ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_google_subject (google_subject)'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Contas criadas pelo Google completam CPF/telefone no primeiro uso que exigir esses dados.
SET @sql = (SELECT IF(
    (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'telefone') = 'YES',
    'SELECT 1',
    'ALTER TABLE usuarios MODIFY COLUMN telefone VARCHAR(20) NULL'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cpf') = 'YES',
    'SELECT 1',
    'ALTER TABLE usuarios MODIFY COLUMN cpf VARCHAR(14) NULL'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
