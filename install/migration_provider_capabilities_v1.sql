-- Capacidades operacionais do provider parceiro.
-- Mantemos provider_workshop_settings por compatibilidade com o MVP financeiro.
SET @db = DATABASE();

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE provider_workshop_settings ADD COLUMN recebe_veiculo_patio TINYINT(1) NOT NULL DEFAULT 1 AFTER permite_resgate_direto',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'provider_workshop_settings' AND column_name = 'recebe_veiculo_patio'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE provider_workshop_settings ADD COLUMN faz_resgate_direto TINYINT(1) NOT NULL DEFAULT 1 AFTER recebe_veiculo_patio',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'provider_workshop_settings' AND column_name = 'faz_resgate_direto'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE provider_workshop_settings
   SET faz_resgate_direto = COALESCE(permite_resgate_direto, 0)
 WHERE faz_resgate_direto IS NULL;
