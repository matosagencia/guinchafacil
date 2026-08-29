-- migration_pricing_zones_v4_pedido.sql
-- §CELULAS-NITEROI-01 (04/08/2026): marca em qual célula territorial
-- (pricing_zones) o pedido caiu, resolvido por point-in-polygon
-- (ZonePricingService::resolverZonaPorCoordenada) no momento da criação.
-- Puramente analítico — não influencia preço nem matching. Fica NULL
-- enquanto a célula não tiver polígono desenhado (comportamento normal até
-- o admin desenhar os polígonos em /admin/precificacao/zonas) ou quando o
-- pedido cair fora de toda célula cadastrada.

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pricing_zone_id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pedidos ADD COLUMN pricing_zone_id INT NULL', 'SELECT "pricing_zone_id já existe em pedidos" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND INDEX_NAME = 'idx_pedidos_pricing_zone_id');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE pedidos ADD INDEX idx_pedidos_pricing_zone_id (pricing_zone_id)', 'SELECT "idx_pedidos_pricing_zone_id já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND CONSTRAINT_NAME = 'fk_pedidos_pricing_zone_id');
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_pricing_zone_id FOREIGN KEY (pricing_zone_id) REFERENCES pricing_zones (id) ON DELETE SET NULL',
    'SELECT "fk_pedidos_pricing_zone_id já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
