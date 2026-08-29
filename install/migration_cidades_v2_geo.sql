-- migration_cidades_geo_v1.sql
-- Adiciona um centro geográfico (lat/lng) e um raio de abrangência a cada
-- cidade-alvo, permitindo resolver automaticamente "este pedido (lat,lng)
-- pertence a qual cidade-alvo?" via distância haversine (GeoService já
-- tem esse cálculo pronto, reaproveitado em Cidade::resolverPorCoordenada()).
--
-- Nullable e sem seed automático: enquanto nenhuma cidade tiver
-- lat_centro/lng_centro preenchidos, a resolução devolve null e todo o
-- cálculo de preço continua caindo no fallback global de sempre — esta
-- migration não muda nenhum comportamento por si só, só abre o campo pro
-- admin preencher quando quiser segmentar preço por cidade.

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades' AND COLUMN_NAME = 'lat_centro');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE cidades ADD COLUMN lat_centro DECIMAL(10,8) NULL AFTER slug', 'SELECT "lat_centro já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades' AND COLUMN_NAME = 'lng_centro');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE cidades ADD COLUMN lng_centro DECIMAL(11,8) NULL AFTER lat_centro', 'SELECT "lng_centro já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades' AND COLUMN_NAME = 'raio_km');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE cidades ADD COLUMN raio_km INT NULL DEFAULT 30 AFTER lng_centro', 'SELECT "raio_km já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
