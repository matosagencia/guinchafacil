-- FINANCIAL-SNAPSHOT-v1
-- Idempotente para MySQL/MariaDB: preserva dados existentes e apenas cria
-- os snapshots brutos previstos no Contrato Pedido v1.

SET @schema_name = DATABASE();

SET @has_custo_assistencia = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'pedidos'
      AND column_name = 'custo_assistencia'
);
SET @sql_custo_assistencia = IF(
    @has_custo_assistencia = 0,
    'ALTER TABLE pedidos ADD COLUMN custo_assistencia DECIMAL(10,2) NULL AFTER custo_estimado',
    'SELECT ''SKIP: pedidos.custo_assistencia ja existe'' AS migration_status'
);
PREPARE stmt_custo_assistencia FROM @sql_custo_assistencia;
EXECUTE stmt_custo_assistencia;
DEALLOCATE PREPARE stmt_custo_assistencia;

SET @has_custo_reboque = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = @schema_name
      AND table_name = 'pedidos'
      AND column_name = 'custo_reboque'
);
SET @sql_custo_reboque = IF(
    @has_custo_reboque = 0,
    'ALTER TABLE pedidos ADD COLUMN custo_reboque DECIMAL(10,2) NULL AFTER custo_assistencia',
    'SELECT ''SKIP: pedidos.custo_reboque ja existe'' AS migration_status'
);
PREPARE stmt_custo_reboque FROM @sql_custo_reboque;
EXECUTE stmt_custo_reboque;
DEALLOCATE PREPARE stmt_custo_reboque;
