-- migration_system_towing_service_v1.sql
-- ROADMAP socorro automotivo — Etapa 16 (serviço de sistema protegido).
--
-- O reboque é estrutural: pode ser CONFIGURADO (tarifa, categorias atendidas,
-- evidências), mas não pode ser removido nem desativado. Isso não é só UI
-- escondida — a proteção é reforçada na camada de serviço
-- (SystemServiceProtectionService, DomainException 'SRV-SYS-001').
--
-- Colunas novas em service_types:
--   is_system     1 = serviço estrutural do GuinchaFácil
--   is_removable  0 = não pode ser excluído
--   can_disable   0 = não pode ser desativado (active nunca vai a 0)
--
-- Todos os serviços TOWING (TOW_CAR, TOW_MOTORCYCLE, TOW_UTILITY — o reboque
-- em produção) são marcados como protegidos. Serviços novos (ON_SITE/HYBRID)
-- nascem removíveis/desativáveis normalmente.
--
-- Defensiva: checa INFORMATION_SCHEMA antes de alterar.

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_types' AND COLUMN_NAME = 'is_system'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE service_types ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER active',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_types' AND COLUMN_NAME = 'is_removable'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE service_types ADD COLUMN is_removable TINYINT(1) NOT NULL DEFAULT 1 AFTER is_system',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_types' AND COLUMN_NAME = 'can_disable'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE service_types ADD COLUMN can_disable TINYINT(1) NOT NULL DEFAULT 1 AFTER is_removable',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Marca o reboque como serviço de sistema protegido (idempotente).
UPDATE `service_types`
SET is_system = 1, is_removable = 0, can_disable = 0, active = 1
WHERE attendance_mode = 'TOWING';
