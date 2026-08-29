-- migration_service_pricing_v2_cidade.sql
-- `service_pricing_rules` era uma regra GLOBAL por tipo de serviço (UNIQUE
-- em service_type_id só) — mesmo preço em qualquer cidade-alvo. Esta
-- migration adiciona `cidade_id` (nullable) pra permitir uma regra
-- ESPECÍFICA por cidade, mantendo a linha com cidade_id NULL como
-- fallback global de sempre (ver ServicePricingRule::buscarPorServiceType,
-- que passa a tentar cidade_id específico primeiro e cair pro NULL).
--
-- A troca de UNIQUE KEY é necessária porque o índice antigo
-- (service_type_id) sozinho não permite mais de uma linha por tipo de
-- serviço — precisamos permitir N linhas (uma por cidade + uma global).
-- Nota: MySQL trata múltiplos NULL como distintos num índice único, então
-- o novo índice (service_type_id, cidade_id) NÃO impede duas linhas
-- globais (cidade_id NULL) do mesmo service_type — a garantia de "só uma
-- linha global por tipo" fica por conta da aplicação
-- (ServicePricingRule::salvar faz select-then-insert/update explícito, não
-- depende só do índice).

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND COLUMN_NAME = 'cidade_id');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE service_pricing_rules ADD COLUMN cidade_id BIGINT UNSIGNED NULL AFTER service_type_id', 'SELECT "cidade_id já existe em service_pricing_rules" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @old_uk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND INDEX_NAME = 'uk_service_pricing_rules_type');
SET @new_uk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND INDEX_NAME = 'uk_service_pricing_rules_type_cidade');
SET @sql := IF(@old_uk_exists = 1 AND @new_uk_exists = 0,
    'ALTER TABLE service_pricing_rules DROP INDEX uk_service_pricing_rules_type, ADD UNIQUE KEY uk_service_pricing_rules_type_cidade (service_type_id, cidade_id)',
    'SELECT "índice já migrado ou índice antigo não existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND INDEX_NAME = 'idx_service_pricing_rules_cidade');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE service_pricing_rules ADD INDEX idx_service_pricing_rules_cidade (cidade_id)', 'SELECT "idx_service_pricing_rules_cidade já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'service_pricing_rules' AND CONSTRAINT_NAME = 'fk_service_pricing_rules_cidade');
SET @tbl_cidades_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'cidades');
SET @sql := IF(@fk_exists = 0 AND @tbl_cidades_exists = 1,
    'ALTER TABLE service_pricing_rules ADD CONSTRAINT fk_service_pricing_rules_cidade FOREIGN KEY (cidade_id) REFERENCES cidades(id) ON DELETE CASCADE',
    'SELECT "fk_service_pricing_rules_cidade já existe ou tabela cidades ainda não existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
