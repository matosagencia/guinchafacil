-- Estado do onboarding iniciado pelo Google.
-- Compatível com MySQL/MariaDB sem ADD COLUMN IF NOT EXISTS.
SET @db = DATABASE();

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE usuarios ADD COLUMN perfil_status VARCHAR(24) NOT NULL DEFAULT ''COMPLETO'' AFTER tipo',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'usuarios' AND column_name = 'perfil_status'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE usuarios ADD COLUMN perfil_tipo_solicitado VARCHAR(24) NULL AFTER perfil_status',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'usuarios' AND column_name = 'perfil_tipo_solicitado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
