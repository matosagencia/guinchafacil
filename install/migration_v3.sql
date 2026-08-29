-- GuinchaFácil — Migration V3
-- Aplicar em bases existentes. Seguro para re-executar.

SET @db_name := DATABASE();

-- §AUTH-RL-02: adicionar colunas se ausentes
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rate_limit' AND COLUMN_NAME = 'criado_em');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE rate_limit ADD COLUMN criado_em DATETIME DEFAULT CURRENT_TIMESTAMP', 'SELECT "rate_limit.criado_em já existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rate_limit' AND COLUMN_NAME = 'atualizado_em');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE rate_limit ADD COLUMN atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "rate_limit.atualizado_em já existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- §AUTH-RL-02: consolidar duplicatas antes de criar UNIQUE
CREATE TABLE IF NOT EXISTS rate_limit_tmp AS
SELECT
  MIN(id)                  AS id,
  ip,
  rota,
  MAX(tentativas)          AS tentativas,
  MIN(primeira_tentativa)  AS primeira_tentativa,
  MAX(bloqueado_ate)       AS bloqueado_ate,
  MIN(criado_em)           AS criado_em,
  MAX(atualizado_em)       AS atualizado_em
FROM rate_limit
GROUP BY ip, rota;

TRUNCATE TABLE rate_limit;

INSERT INTO rate_limit (id, ip, rota, tentativas, primeira_tentativa, bloqueado_ate, criado_em, atualizado_em)
SELECT id, ip, rota, tentativas, primeira_tentativa, bloqueado_ate, criado_em, atualizado_em
FROM rate_limit_tmp;

DROP TABLE IF EXISTS rate_limit_tmp;

-- Adiciona UNIQUE KEY (ignora se já existir)
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rate_limit' AND INDEX_NAME = 'uk_ip_rota');
SET @sql := IF(@idx_exists = 0, 'ALTER TABLE rate_limit ADD UNIQUE KEY uk_ip_rota (ip, rota)', 'SELECT "uk_ip_rota já existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- §INSTALL-SEED-01: migrar comissao_percentual → comissao_plataforma
-- Fix ambiguous column by referencing configuracoes.chave explicitly
INSERT INTO configuracoes (chave, valor, descricao, criado_em, atualizado_em)
SELECT
  'comissao_plataforma',
  ROUND(c.valor / 100, 4),
  'Comissão da plataforma em decimal (ex: 0.15 = 15%)',
  NOW(),
  NOW()
FROM configuracoes c
WHERE c.chave = 'comissao_percentual'
ON DUPLICATE KEY UPDATE chave = configuracoes.chave;

DELETE FROM configuracoes WHERE chave = 'comissao_percentual';
