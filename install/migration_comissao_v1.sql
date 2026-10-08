-- Comissão e fatura de parceiros v1. Idempotente para MySQL/MariaDB.
SET @schema := DATABASE();

CREATE TABLE IF NOT EXISTS comissoes_cidade (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cidade_id BIGINT UNSIGNED NOT NULL,
  percentual DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uk_comissoes_cidade_cidade (cidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faturas_parceiro (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parceiro_tipo ENUM('guincho','oficina','especialista') NOT NULL,
  parceiro_id BIGINT UNSIGNED NOT NULL,
  ciclo_inicio DATETIME NOT NULL,
  ciclo_fim DATETIME NOT NULL,
  total_faturado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_repasse DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  saldo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('aberta','paga','vencida','bloqueada','cancelada') NOT NULL DEFAULT 'aberta',
  pix_qrcode MEDIUMTEXT NULL,
  pix_copia_cola TEXT NULL,
  pix_transacao_id VARCHAR(120) NULL,
  vencimento_em DATETIME NOT NULL,
  paga_em DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fatura_parceiro_ciclo (parceiro_tipo, parceiro_id, ciclo_inicio),
  KEY idx_fatura_status_vencimento (status, vencimento_em),
  KEY idx_fatura_pix_transacao (pix_transacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faturas_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fatura_id BIGINT UNSIGNED NOT NULL,
  pedido_id BIGINT UNSIGNED NOT NULL,
  comissao_tipo ENUM('faturada','descontada') NOT NULL,
  valor_total DECIMAL(12,2) NOT NULL,
  comissao_valor DECIMAL(12,2) NOT NULL,
  valor_liquido_parceiro DECIMAL(12,2) NOT NULL,
  saldo_item DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fatura_item_pedido (pedido_id),
  KEY idx_fatura_itens_fatura (fatura_id),
  CONSTRAINT fk_faturas_itens_fatura FOREIGN KEY (fatura_id) REFERENCES faturas_parceiro(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faturas_pagamentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fatura_id BIGINT UNSIGNED NOT NULL,
  transacao_id VARCHAR(120) NOT NULL,
  metodo VARCHAR(32) NOT NULL,
  valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payload MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fatura_pagamento_transacao (transacao_id),
  KEY idx_fatura_pagamentos_fatura (fatura_id),
  CONSTRAINT fk_faturas_pagamentos_fatura FOREIGN KEY (fatura_id) REFERENCES faturas_parceiro(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='comissao_valor')=0,
  'ALTER TABLE pedidos ADD COLUMN comissao_valor DECIMAL(10,2) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='comissao_tipo')=0,
  "ALTER TABLE pedidos ADD COLUMN comissao_tipo ENUM('faturada','descontada') NULL", 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_liquido_parceiro')=0,
  'ALTER TABLE pedidos ADD COLUMN valor_liquido_parceiro DECIMAL(10,2) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='fatura_id')=0,
  'ALTER TABLE pedidos ADD COLUMN fatura_id BIGINT UNSIGNED NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND INDEX_NAME='idx_pedidos_fatura')=0,
  'CREATE INDEX idx_pedidos_fatura ON pedidos(fatura_id)', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND CONSTRAINT_NAME='fk_pedidos_fatura')=0,
  'ALTER TABLE pedidos ADD CONSTRAINT fk_pedidos_fatura FOREIGN KEY (fatura_id) REFERENCES faturas_parceiro(id) ON DELETE SET NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
