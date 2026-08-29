-- Ajustes de autenticacao/rate-limit e configuracao legada.
-- Aplicar em bancos ja instalados a partir de install/guinchafacil.sql antigo.

SET @db_name := DATABASE();

SET @idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rate_limit' AND INDEX_NAME = 'idx_ip_rota'
);
SET @sql := IF(@idx > 0,
    'ALTER TABLE rate_limit DROP INDEX idx_ip_rota',
    'SELECT ''idx_ip_rota já não existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Drop and re-add unique key to ensure it exists
SET @idx_uniq := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rate_limit' AND INDEX_NAME = 'uniq_ip_rota'
);
SET @sql := IF(@idx_uniq > 0,
    'ALTER TABLE rate_limit DROP INDEX uniq_ip_rota',
    'SELECT ''uniq_ip_rota não existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE rate_limit ADD UNIQUE KEY uniq_ip_rota (ip, rota);

UPDATE configuracoes
SET chave = 'comissao_plataforma'
WHERE chave = 'comissao_percentual';
