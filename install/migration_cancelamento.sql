-- ===================================================================
-- MIGRAÇÃO: Cancelamento com penalidade de tarifa (cliente e guincho)
-- Data: 2026-07-08
-- Idempotente: usa INFORMATION_SCHEMA antes de alterar
-- ===================================================================

-- pedidos.cancelado_por
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'cancelado_por') = 0,
    'ALTER TABLE pedidos ADD COLUMN cancelado_por ENUM(''cliente'',''guincho'',''admin'',''sistema'') NULL AFTER status',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pedidos.motivo_cancelamento
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'motivo_cancelamento') = 0,
    'ALTER TABLE pedidos ADD COLUMN motivo_cancelamento VARCHAR(255) NULL AFTER cancelado_por',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pedidos.taxa_cancelamento
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'taxa_cancelamento') = 0,
    'ALTER TABLE pedidos ADD COLUMN taxa_cancelamento DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER motivo_cancelamento',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pedidos.cancelado_em
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'cancelado_em') = 0,
    'ALTER TABLE pedidos ADD COLUMN cancelado_em DATETIME NULL AFTER taxa_cancelamento',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- guinchos.total_cancelamentos
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'total_cancelamentos') = 0,
    'ALTER TABLE guinchos ADD COLUMN total_cancelamentos INT NOT NULL DEFAULT 0 AFTER total_avaliacoes',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Configurações de penalidade (INSERT IGNORE preserva valores já ajustados pelo admin)
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('cancelamento_gratis_min',            '5',    'Minutos após criação em que o cancelamento do cliente é isento de taxa'),
('taxa_cancelamento_percent',          '20',   'Percentual do custo estimado cobrado ao cliente que cancela com guincho a caminho'),
('taxa_cancelamento_fixa',             '15.00','Taxa mínima (R$) cobrada ao cliente que cancela com guincho a caminho'),
('km_bloqueio_cancelamento',           '2',    'Distância (km) do guincho à origem abaixo da qual o cliente não pode mais cancelar'),
('penalidade_reputacao_cancelamento',  '0.25', 'Pontos de reputação descontados do guincho que cancela um atendimento aceito');
