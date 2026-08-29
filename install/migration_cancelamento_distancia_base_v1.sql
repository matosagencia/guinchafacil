-- migration_cancelamento_distancia_base_v1.sql
-- §A1 (auditoria 21/07): CancellationCalculationService normalizava a
-- distância GPS percorrida pelo guincho (truck→origem) contra
-- pedidos.distancia_km, que é a distância origem→destino cotada ao cliente
-- na criação do pedido — duas distâncias sem relação entre si. Esta coluna
-- guarda a distância real guincho→origem no momento do aceite (Haversine),
-- que é o denominador correto do distance_ratio.
--
-- Não reaproveita `distancia_guincho_percorrida` (de
-- migration_add_distancia_percorrida.sql): aquela coluna nunca chegou a ser
-- lida por nenhum código (grep confirma zero uso em src/), semântica não
-- documentada — mais seguro criar uma coluna nova com propósito explícito
-- do que herdar uma coluna morta.

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'distancia_guincho_origem_km'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pedidos ADD COLUMN distancia_guincho_origem_km DECIMAL(6,2) NULL AFTER distancia_km', 'SELECT "distancia_guincho_origem_km já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
