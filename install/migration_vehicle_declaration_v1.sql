-- migration_vehicle_declaration_v1.sql
-- ROADMAP socorro automotivo — Etapa 14 (declaração veicular, MVP).
--
-- Decisão do usuário: sem API de placa, sem catálogo marca/modelo/versão,
-- sem documento obrigatório. Cadastro é 100% formulário manual. O
-- documento (CRLV-e) é opcional e só eleva verification_status.
--
-- Campos permanentes do veículo (o que ele "é"):
--   vehicle_type, fuel_type, transmission_type, electric_type,
--   operational_category, has_spare_tire, has_locking_bolt,
--   document_uploaded, document_path, verification_status.
--
-- Condições temporárias (o que está acontecendo agora com ele: batido,
-- rodas travadas, difícil acesso, garagem/subsolo) NÃO entram aqui —
-- vão em `pedidos`, porque mudam a cada ocorrência e não podem ficar
-- presas ao cadastro (ver migration_vehicle_declaration_pedido_v1 abaixo,
-- neste mesmo arquivo).
--
-- Todas as alterações são defensivas (checam INFORMATION_SCHEMA antes de
-- alterar), seguindo o mesmo padrão usado em todas as migrations do
-- projeto — pode ser rodada em bases que já têm parte das colunas.

-- ============================================================
-- 1) veiculos: campos de declaração
-- ============================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'verification_status'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN verification_status VARCHAR(30) NOT NULL DEFAULT ''DECLARED'' COMMENT ''DECLARED | DOCUMENT_SUBMITTED | VERIFIED'' AFTER tipo',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'vehicle_type'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN vehicle_type VARCHAR(30) NULL COMMENT ''Ex: automovel_passeio, moto, utilitario, caminhao_leve'' AFTER verification_status',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'fuel_type'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN fuel_type VARCHAR(30) NULL COMMENT ''flex, gasolina, etanol, diesel, gnv, eletrico'' AFTER vehicle_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'transmission_type'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN transmission_type VARCHAR(30) NULL COMMENT ''manual, automatico'' AFTER fuel_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'electric_type'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN electric_type VARCHAR(30) NULL COMMENT ''nao_eletrico, hibrido, eletrico_puro'' AFTER transmission_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'operational_category'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN operational_category VARCHAR(40) NULL COMMENT ''derivada de vehicle_type; usada pela Etapa 15 (compatibilidade prestador x veiculo)'' AFTER electric_type',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'has_spare_tire'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN has_spare_tire TINYINT(1) NULL AFTER operational_category',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'has_locking_bolt'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN has_locking_bolt TINYINT(1) NULL AFTER has_spare_tire',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'document_uploaded'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN document_uploaded TINYINT(1) NOT NULL DEFAULT 0 AFTER has_locking_bolt',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'document_path'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN document_path VARCHAR(255) NULL COMMENT ''caminho fora do webroot; nunca expor diretamente'' AFTER document_uploaded',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2) pedidos: condições situacionais (repetidas a cada ocorrência,
--    porque o estado do veículo muda — não confiar no cadastro antigo)
-- ============================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'veiculo_esta_batido'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pedidos ADD COLUMN veiculo_esta_batido TINYINT(1) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'rodas_travadas'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pedidos ADD COLUMN rodas_travadas TINYINT(1) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'local_dificil_acesso'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pedidos ADD COLUMN local_dificil_acesso TINYINT(1) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'em_garagem_subsolo'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE pedidos ADD COLUMN em_garagem_subsolo TINYINT(1) NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3) Grandfathering: veículos já cadastrados antes desta migration
--    ficam DECLARED (não há como retroceder e perguntar de novo, e o
--    MVP não bloqueia pedido comum nem em DECLARED — não há gate a
--    aplicar aqui, é só deixar o valor default já cobrir o caso).
-- ============================================================
-- (nada a fazer: DEFAULT 'DECLARED' já cobre linhas existentes)
