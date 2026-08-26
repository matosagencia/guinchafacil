-- ============================================================
-- GuinchaFácil — migration_fix.sql (v3)
-- Corrige drift de schema detectado pelo diagnostico.php
-- Compatível com MySQL 8.x
-- ============================================================

SET @db_name := DATABASE();
SELECT CONCAT('Banco atual: ', COALESCE(@db_name, '(nenhum)')) AS info;

-- ----------------------------------------------------------------
-- FIX 1: nomes de colunas antigos na tabela guinchos
-- ----------------------------------------------------------------
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'capacidade_kg'
        ),
        'ALTER TABLE guinchos CHANGE COLUMN `capacidade_kg` `capacidade_ton` DECIMAL(5,2) NOT NULL',
        'SELECT ''guinchos.capacidade_kg inexistente ou já corrigida'' AS info'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'latitude_atual'
        ),
        'ALTER TABLE guinchos CHANGE COLUMN `latitude_atual` `lat_atual` DECIMAL(10,8) NULL',
        'SELECT ''guinchos.latitude_atual inexistente ou já corrigida'' AS info'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'longitude_atual'
        ),
        'ALTER TABLE guinchos CHANGE COLUMN `longitude_atual` `lng_atual` DECIMAL(11,8) NULL',
        'SELECT ''guinchos.longitude_atual inexistente ou já corrigida'' AS info'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------
-- FIX 2: seeds obrigatórios
-- ----------------------------------------------------------------
INSERT INTO configuracoes (chave, valor, descricao)
SELECT 'tarifa_por_km', '5.00', 'Valor cobrado por km rodado (R$)'
WHERE NOT EXISTS (SELECT 1 FROM configuracoes WHERE chave = 'tarifa_por_km');

INSERT INTO configuracoes (chave, valor, descricao)
SELECT 'taxa_fixa', '10.00', 'Taxa fixa de acionamento por pedido (R$)'
WHERE NOT EXISTS (SELECT 1 FROM configuracoes WHERE chave = 'taxa_fixa');

INSERT INTO configuracoes (chave, valor, descricao)
SELECT 'comissao_plataforma', '0.15', 'Comissão da plataforma em decimal (0-1)'
WHERE NOT EXISTS (SELECT 1 FROM configuracoes WHERE chave = 'comissao_plataforma');

UPDATE configuracoes
SET valor = CASE
    WHEN CAST(valor AS DECIMAL(10,4)) > 1 THEN CAST(valor AS DECIMAL(10,4)) / 100
    ELSE valor
END,
    descricao = 'Comissão da plataforma em decimal (0-1)'
WHERE chave = 'comissao_percentual';

-- ----------------------------------------------------------------
-- FIX 3: password_resets
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expira_em DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- FIX 4: colunas críticas ausentes em pagamentos
-- ----------------------------------------------------------------
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS metodo ENUM('mercadopago','pagseguro') NOT NULL DEFAULT 'mercadopago' AFTER pedido_id,
    ADD COLUMN IF NOT EXISTS valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER metodo,
    ADD COLUMN IF NOT EXISTS valor_guincho DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER valor_total,
    ADD COLUMN IF NOT EXISTS valor_plataforma DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER valor_guincho,
    ADD COLUMN IF NOT EXISTS status ENUM('pendente','aprovado','recusado','estornado') NOT NULL DEFAULT 'pendente' AFTER valor_plataforma,
    ADD COLUMN IF NOT EXISTS id_externo VARCHAR(100) NULL AFTER status,
    ADD COLUMN IF NOT EXISTS pago_guincho TINYINT(1) NOT NULL DEFAULT 0 AFTER id_externo,
    ADD COLUMN IF NOT EXISTS data_pagamento DATETIME NULL AFTER pago_guincho,
    ADD COLUMN IF NOT EXISTS data_pagamento_guincho DATETIME NULL AFTER data_pagamento,
    ADD COLUMN IF NOT EXISTS webhook_payload TEXT NULL AFTER data_pagamento_guincho,
    ADD COLUMN IF NOT EXISTS criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER webhook_payload;

SET @idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pagamentos' AND INDEX_NAME = 'idx_pagamentos_id_externo'
);
SET @sql := IF(@idx = 0,
    'CREATE INDEX idx_pagamentos_id_externo ON pagamentos (id_externo)',
    'SELECT ''idx_pagamentos_id_externo já existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pagamentos' AND INDEX_NAME = 'idx_pagamentos_status'
);
SET @sql := IF(@idx = 0,
    'CREATE INDEX idx_pagamentos_status ON pagamentos (status)',
    'SELECT ''idx_pagamentos_status já existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------
-- FIX 5: FK pedidos.guincho_id -> guinchos.id
-- ----------------------------------------------------------------
SET @fk_pedidos := (
    SELECT CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'guincho_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);
SET @sql := IF(@fk_pedidos IS NOT NULL,
    CONCAT('ALTER TABLE pedidos DROP FOREIGN KEY `', @fk_pedidos, '`'),
    'SELECT ''pedidos.guincho_id sem FK antiga'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_pedidos := (
    SELECT INDEX_NAME
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'pedidos'
      AND COLUMN_NAME = 'guincho_id'
      AND INDEX_NAME <> 'PRIMARY'
    LIMIT 1
);
SET @sql := IF(@idx_pedidos IS NOT NULL,
    CONCAT('ALTER TABLE pedidos DROP INDEX `', @idx_pedidos, '`'),
    'SELECT ''pedidos.guincho_id sem índice antigo'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND INDEX_NAME = 'idx_pedidos_guincho_id'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE pedidos ADD INDEX idx_pedidos_guincho_id (guincho_id)',
    'SELECT ''idx_pedidos_guincho_id já existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pedidos' AND CONSTRAINT_NAME = 'fk_pedidos_guincho'
);
SET @sql := IF(@fk = 0,
    'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_guincho FOREIGN KEY (guincho_id) REFERENCES guinchos(id) ON DELETE SET NULL',
    'SELECT ''fk_pedidos_guincho já existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------
-- FIX 6: FK avaliacoes.guincho_id -> guinchos.id
-- ----------------------------------------------------------------
SET @fk_avaliacoes := (
    SELECT CONSTRAINT_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'avaliacoes'
      AND COLUMN_NAME = 'guincho_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);
SET @sql := IF(@fk_avaliacoes IS NOT NULL,
    CONCAT('ALTER TABLE avaliacoes DROP FOREIGN KEY `', @fk_avaliacoes, '`'),
    'SELECT ''avaliacoes.guincho_id sem FK antiga'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_avaliacoes := (
    SELECT INDEX_NAME
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'avaliacoes'
      AND COLUMN_NAME = 'guincho_id'
      AND INDEX_NAME <> 'PRIMARY'
    LIMIT 1
);
SET @sql := IF(@idx_avaliacoes IS NOT NULL,
    CONCAT('ALTER TABLE avaliacoes DROP INDEX `', @idx_avaliacoes, '`'),
    'SELECT ''avaliacoes.guincho_id sem índice antigo'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'avaliacoes' AND INDEX_NAME = 'idx_avaliacoes_guincho_id'
);
SET @sql := IF(@idx = 0,
    'ALTER TABLE avaliacoes ADD INDEX idx_avaliacoes_guincho_id (guincho_id)',
    'SELECT ''idx_avaliacoes_guincho_id já existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'avaliacoes' AND CONSTRAINT_NAME = 'fk_avaliacoes_guincho'
);
SET @sql := IF(@fk = 0,
    'ALTER TABLE avaliacoes ADD CONSTRAINT fk_avaliacoes_guincho FOREIGN KEY (guincho_id) REFERENCES guinchos(id) ON DELETE CASCADE',
    'SELECT ''fk_avaliacoes_guincho já existe'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------
-- FIX 7: colunas Pix em pagamentos (§4.3)
-- ----------------------------------------------------------------
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS id_transacao_pix VARCHAR(100) NULL AFTER pago_guincho,
    ADD COLUMN IF NOT EXISTS status_pix ENUM('pendente','processando','concluido','falha') NOT NULL DEFAULT 'pendente' AFTER id_transacao_pix;

-- ----------------------------------------------------------------
-- FIX 8: tabela logs_webhook (usada por WebhookController::log())
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs_webhook (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    fonte       VARCHAR(30)  NOT NULL,
    evento      VARCHAR(100) NOT NULL,
    payload     MEDIUMTEXT   NULL,
    status_http SMALLINT     NOT NULL DEFAULT 200,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_webhook_fonte (fonte),
    INDEX idx_logs_webhook_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'migration_fix.sql v5 aplicado com sucesso.' AS resultado;
