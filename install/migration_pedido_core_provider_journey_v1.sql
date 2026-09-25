-- Pedido core/provider journey v1
-- Idempotente: pode ser executada repetidas vezes sem DROP destrutivo.

SET @db := DATABASE();

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'desconto_continuidade_aplicado'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedidos ADD COLUMN desconto_continuidade_aplicado TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pricing_snapshot_version'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedidos ADD COLUMN pricing_snapshot_version VARCHAR(40) NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS pedido_financial_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    taxa_saida DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    km_cobrado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    km_excedente DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    intermediacao DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    desconto_continuidade DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    moeda CHAR(3) NOT NULL DEFAULT 'BRL',
    pricing_version VARCHAR(40) NOT NULL DEFAULT 'pedido-pricing-v1',
    snapshot_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pedido_financial_snapshots_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    evento VARCHAR(80) NOT NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pedido_eventos_pedido (pedido_id),
    KEY idx_pedido_eventos_evento (evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'valor_mao_obra'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN valor_mao_obra DECIMAL(10,2) NULL AFTER provider_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'valor_pecas'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN valor_pecas DECIMAL(10,2) NULL AFTER valor_mao_obra',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'valor_total'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN valor_total DECIMAL(10,2) NULL AFTER valor_pecas',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'taxa_saida_abater'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN taxa_saida_abater DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER valor_total',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'descricao'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN descricao TEXT NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'approved_at'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN approved_at DATETIME NULL AFTER updated_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'rejected_at'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN rejected_at DATETIME NULL AFTER approved_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'pedido_orcamentos_previos' AND COLUMN_NAME = 'modelo_orcamento'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE pedido_orcamentos_previos ADD COLUMN modelo_orcamento VARCHAR(40) NOT NULL DEFAULT ''PROVIDER_QUOTE'' AFTER rejected_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE pedido_orcamentos_previos
   SET modelo_orcamento = 'LEGACY_ESTIMATE'
 WHERE (estimativa_minima IS NOT NULL OR estimativa_maxima IS NOT NULL)
   AND valor_mao_obra IS NULL
   AND valor_pecas IS NULL
   AND valor_total IS NULL;
