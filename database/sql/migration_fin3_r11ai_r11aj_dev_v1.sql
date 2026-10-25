-- FIN3 R11AI/R11AJ development migration.
-- Structure only: no QA fixtures or synthetic rows are imported.
-- Apply after a logical backup of guinchafacil_dev.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS fin3_operational_approval (
  event_key char(64) NOT NULL,
  provider varchar(30) NOT NULL,
  external_id varchar(150) NOT NULL,
  pedido_id int NOT NULL,
  pagamento_id int NOT NULL,
  fingerprint char(64) NOT NULL,
  split_json text NOT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (event_key),
  UNIQUE KEY uq_fin3_approval_provider_identity (provider, external_id),
  KEY fk_fin3_approval_order (pedido_id),
  KEY fk_fin3_approval_payment (pagamento_id),
  CONSTRAINT fk_fin3_approval_order FOREIGN KEY (pedido_id) REFERENCES pedidos (id),
  CONSTRAINT fk_fin3_approval_payment FOREIGN KEY (pagamento_id) REFERENCES pagamentos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin3_operational_inbox (
  event_key char(64) NOT NULL,
  fingerprint char(64) NOT NULL,
  status enum('processing','done') NOT NULL,
  claim_token char(32) DEFAULT NULL,
  lease_until datetime(6) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin3_post_commit_outbox (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  event_key char(64) NOT NULL,
  event_type varchar(40) NOT NULL,
  payload_json text NOT NULL,
  status enum('pending','done','failed') NOT NULL DEFAULT 'pending',
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY uq_fin3_outbox_event (event_key,event_type),
  CONSTRAINT fk_fin3_outbox_approval FOREIGN KEY (event_key) REFERENCES fin3_operational_approval (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin3_outbox_delivery_qa (
  outbox_id bigint unsigned NOT NULL,
  event_key char(64) NOT NULL,
  fingerprint char(64) NOT NULL,
  sink_id varchar(80) NOT NULL,
  state varchar(24) NOT NULL,
  claim_token char(32) DEFAULT NULL,
  lease_until datetime(6) DEFAULT NULL,
  claim_count int NOT NULL DEFAULT 0,
  ack_json longtext DEFAULT NULL,
  last_error text DEFAULT NULL,
  created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),
  updated_at datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (outbox_id),
  CONSTRAINT fk_fin3_outbox_delivery FOREIGN KEY (outbox_id) REFERENCES fin3_post_commit_outbox (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin3_payout_dispatch_qa (
  job_id bigint unsigned NOT NULL,
  idempotency_key char(64) NOT NULL,
  fingerprint char(64) NOT NULL,
  request_json longtext NOT NULL,
  state varchar(24) NOT NULL,
  claim_token char(32) DEFAULT NULL,
  lease_until datetime(6) DEFAULT NULL,
  claim_count int NOT NULL DEFAULT 0,
  payout_id varchar(80) DEFAULT NULL,
  transaction_id varchar(80) DEFAULT NULL,
  response_json longtext DEFAULT NULL,
  last_error text DEFAULT NULL,
  created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),
  updated_at datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (job_id),
  UNIQUE KEY uq_fin3_dispatch_key (idempotency_key),
  CONSTRAINT fk_fin3_dispatch_job FOREIGN KEY (job_id) REFERENCES payment_jobs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin3_reconciliation_runs_qa (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  run_key char(32) NOT NULL,
  status varchar(8) NOT NULL,
  report_json longtext NOT NULL,
  report_sha256 char(64) NOT NULL,
  created_at datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_fin3_reconciliation_key (run_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS r11aj_confirmed_price_frozen;
DELIMITER //
CREATE TRIGGER r11aj_confirmed_price_frozen
BEFORE UPDATE ON pedidos
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM fin3_operational_approval WHERE pedido_id=OLD.id)
     AND (NOT(NEW.custo_estimado <=> OLD.custo_estimado)
       OR NOT(NEW.custo_final <=> OLD.custo_final)
       OR NOT(NEW.valor_estimado <=> OLD.valor_estimado)
       OR NOT(NEW.valor_final <=> OLD.valor_final)
       OR NOT(NEW.comissao_valor <=> OLD.comissao_valor)
       OR NOT(NEW.comissao_tipo <=> OLD.comissao_tipo)
       OR NOT(NEW.valor_liquido_parceiro <=> OLD.valor_liquido_parceiro))
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FIN_CONFIRMED_PRICE_FROZEN';
  END IF;
END//
DELIMITER ;
