-- migration_pedido_tipo_problema_v1.sql
-- CONTRATO B -> A (2026-10-07)
--
-- Problema:
-- pedidos.tipo_problema era restritivo (ENUM legado em bases existentes).
-- Com sql_mode permissivo, slugs novos como "reboque" podiam ser convertidos
-- silenciosamente para '' em vez de gerar erro.
--
-- Regra:
-- tipo_problema passa a aceitar o slug operacional enviado pelo funil.
-- A validacao de vazio continua na camada PHP; esta migration apenas remove
-- o gargalo de ENUM do schema.
--
-- Nao faz backfill de pedidos historicos: evita inferir retroativamente um
-- problema que nao foi persistido corretamente na origem.

SET @db := DATABASE();

SET @col_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db
       AND TABLE_NAME = 'pedidos'
       AND COLUMN_NAME = 'tipo_problema'
);

SET @sql := IF(
    @col_exists = 1,
    'ALTER TABLE pedidos MODIFY tipo_problema VARCHAR(80) NOT NULL DEFAULT ''outro''',
    'SELECT ''pedidos.tipo_problema ausente'' AS aviso'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
