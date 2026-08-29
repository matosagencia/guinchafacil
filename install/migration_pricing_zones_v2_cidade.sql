-- migration_pricing_zones_v2_cidade.sql
-- `pricing_zones` já tinha uma coluna `city_id VARCHAR(10) NULL` reservada
-- desde a Etapa 13 original, mas nunca usada em nenhuma query — a
-- resolução de zona sempre foi só por polígono (lat/lng), independente de
-- cidade. Esta migration adiciona uma coluna NOVA e tipada corretamente
-- (`cidade_id BIGINT UNSIGNED`, FK de verdade pra `cidades.id`) pra que o
-- admin marque explicitamente a qual cidade-alvo cada zona pertence — é
-- só uma tag organizacional/de auditoria (o point-in-polygon continua
-- sendo a fonte real da geografia); NÃO substitui `city_id`, que
-- permanece intocado por compatibilidade retroativa.

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'cidade_id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN cidade_id BIGINT UNSIGNED NULL AFTER city_id', 'SELECT "cidade_id já existe em pricing_zones" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND INDEX_NAME = 'idx_pricing_zones_cidade_id');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE pricing_zones ADD INDEX idx_pricing_zones_cidade_id (cidade_id)', 'SELECT "idx_pricing_zones_cidade_id já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND CONSTRAINT_NAME = 'fk_pricing_zones_cidade_id');
SET @tbl_cidades_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades');
SET @sql := IF(@fk_exists = 0 AND @tbl_cidades_exists = 1,
    'ALTER TABLE pricing_zones ADD CONSTRAINT fk_pricing_zones_cidade_id FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE SET NULL',
    'SELECT "fk_pricing_zones_cidade_id já existe ou tabela cidades ainda não existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
