-- migration_pricing_zones_v5_metas.sql
-- §CELULAS-NITEROI-01 (04/08/2026): metas de oferta/demanda/margem por
-- célula territorial — o admin precisa ver, ao vivo, se cada célula está
-- cumprindo o piloto de 90 dias definido na estratégia de domínio
-- progressivo (ver TerritorioMetasService). Guarda só o NÚMERO-ALVO; o
-- REALIZADO é sempre calculado ao vivo a partir de pedidos/pagamentos/
-- payout_ledger_entries — nunca duplicado/cacheado aqui.
-- Idempotente: idempotência de coluna via INFORMATION_SCHEMA; seed via
-- UPDATE condicional (não usa INSERT, célula já existe desde v3).

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_prestadores_min');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_prestadores_min INT NULL AFTER bairros_referencia', 'SELECT "meta_prestadores_min já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_prestadores_max');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_prestadores_max INT NULL AFTER meta_prestadores_min', 'SELECT "meta_prestadores_max já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_disponibilidade_simultanea');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_disponibilidade_simultanea INT NULL AFTER meta_prestadores_max', 'SELECT "meta_disponibilidade_simultanea já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_atendimentos_mes1');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_atendimentos_mes1 INT NULL AFTER meta_disponibilidade_simultanea', 'SELECT "meta_atendimentos_mes1 já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_atendimentos_mes2');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_atendimentos_mes2 INT NULL AFTER meta_atendimentos_mes1', 'SELECT "meta_atendimentos_mes2 já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_atendimentos_mes3');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_atendimentos_mes3 INT NULL AFTER meta_atendimentos_mes2', 'SELECT "meta_atendimentos_mes3 já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_margem_operacional_min_pct');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_margem_operacional_min_pct DECIMAL(5,2) NULL AFTER meta_atendimentos_mes3', 'SELECT "meta_margem_operacional_min_pct já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_margem_pos_marketing_min_pct');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_margem_pos_marketing_min_pct DECIMAL(5,2) NULL AFTER meta_margem_operacional_min_pct', 'SELECT "meta_margem_pos_marketing_min_pct já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_composicao_prestadores');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_composicao_prestadores TEXT NULL AFTER meta_margem_pos_marketing_min_pct', 'SELECT "meta_composicao_prestadores já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'meta_ciclo_inicio');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN meta_ciclo_inicio DATE NULL AFTER meta_composicao_prestadores', 'SELECT "meta_ciclo_inicio já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed do piloto (04/08/2026): números fornecidos pelo usuário para os
-- primeiros 90 dias, aplicados à célula 1 (Praias da Baía Central — a
-- primeira a ser ativada na ordem de expansão). As demais células ficam
-- sem meta até serem ativadas (ordem_expansao 2..5), pra não sugerir
-- progresso contra uma meta que ainda não foi decidida pra elas.
UPDATE pricing_zones
SET
    meta_prestadores_min = 10,
    meta_prestadores_max = 15,
    meta_disponibilidade_simultanea = 3,
    meta_atendimentos_mes1 = 20,
    meta_atendimentos_mes2 = 40,
    meta_atendimentos_mes3 = 60,
    meta_margem_operacional_min_pct = 18.00,
    meta_margem_pos_marketing_min_pct = 5.00,
    meta_composicao_prestadores = '4 guinchos leves, 4 moto-socorristas/apoio, 2 oficinas parceiras, 2 especialistas bateria/elétrica, 1 prestador de pneu, 1 reserva operacional',
    meta_ciclo_inicio = CURDATE()
WHERE code = 'niteroi-celula-1'
  AND meta_atendimentos_mes1 IS NULL;
