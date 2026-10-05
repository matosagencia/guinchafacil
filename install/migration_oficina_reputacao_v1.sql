-- migration_oficina_reputacao_v1.sql
-- Idempotente. Adiciona:
--   1) 'oficina' ao enum pedido_cancelamentos.ator_tipo
--   2) oficinas.reputacao + oficinas.total_cancelamentos
--   3) config penalidade_reputacao_cancelamento_oficina

-- 1) Enum ator_tipo com 'oficina'
SET @has_oficina := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'pedido_cancelamentos'
       AND COLUMN_NAME = 'ator_tipo'
       AND COLUMN_TYPE LIKE '%''oficina''%'
);
SET @sql := IF(@has_oficina = 0,
    'ALTER TABLE pedido_cancelamentos MODIFY COLUMN ator_tipo ENUM(''cliente'',''guincho'',''oficina'',''admin'',''sistema'') NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2a) oficinas.reputacao
SET @has_rep := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oficinas' AND COLUMN_NAME = 'reputacao'
);
SET @sql := IF(@has_rep = 0,
    'ALTER TABLE oficinas ADD COLUMN reputacao DECIMAL(5,4) NOT NULL DEFAULT 5.0000 AFTER disponivel',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2b) oficinas.total_cancelamentos
SET @has_tc := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oficinas' AND COLUMN_NAME = 'total_cancelamentos'
);
SET @sql := IF(@has_tc = 0,
    'ALTER TABLE oficinas ADD COLUMN total_cancelamentos INT UNSIGNED NOT NULL DEFAULT 0 AFTER reputacao',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) config (idempotente)
INSERT INTO configuracoes (chave, valor, descricao)
VALUES ('penalidade_reputacao_cancelamento_oficina', '0.25',
        'Penalidade de reputacao aplicada a oficina ao cancelar um atendimento aceito.')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);