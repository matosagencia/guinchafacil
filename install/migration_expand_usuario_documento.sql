-- Contrato A/B: usuarios.cpf armazena CPF ou CNPJ, com ou sem máscara.
-- Idempotente: só altera quando a capacidade atual for menor que 18 caracteres.
SET @documento_schema := DATABASE();
SET @documento_has_column := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @documento_schema
       AND TABLE_NAME = 'usuarios'
       AND COLUMN_NAME = 'cpf'
);
SET @documento_length := (
    SELECT CHARACTER_MAXIMUM_LENGTH
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @documento_schema
       AND TABLE_NAME = 'usuarios'
       AND COLUMN_NAME = 'cpf'
     LIMIT 1
);
SET @documento_nullable := (
    SELECT IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @documento_schema
       AND TABLE_NAME = 'usuarios'
       AND COLUMN_NAME = 'cpf'
     LIMIT 1
);

SET @documento_sql := IF(
    @documento_has_column = 1 AND @documento_length < 18,
    CONCAT(
        'ALTER TABLE `usuarios` MODIFY COLUMN `cpf` VARCHAR(18)',
        IF(@documento_nullable = 'NO', ' NOT NULL', ' NULL')
    ),
    'SELECT ''[MIG-DOC-CPF-CNPJ][SKIP] usuarios.cpf já suporta 18 caracteres.'' AS migration_log'
);

PREPARE documento_stmt FROM @documento_sql;
EXECUTE documento_stmt;
DEALLOCATE PREPARE documento_stmt;
