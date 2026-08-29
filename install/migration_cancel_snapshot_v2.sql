-- install/migration_cancel_snapshot_v2.sql
-- Pacote L1.6 — snapshot versionado e auditável de cancelamento (plano seção 4.7).
-- Idempotente: pode ser reexecutada sem erro.

CREATE TABLE IF NOT EXISTS cancelamento_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT NOT NULL,
    actor_type ENUM('cliente','guincho','admin','sistema') NOT NULL,
    actor_id INT NOT NULL,
    formula_version VARCHAR(20) NOT NULL DEFAULT 'v1',
    factors_json TEXT NULL,
    por_quality VARCHAR(20) NULL,
    fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    penalty_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    snapshot_hash CHAR(64) NOT NULL,
    status ENUM('pending','confirmed','expired','superseded') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    confirmed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pedido_status (pedido_id, status),
    KEY idx_expires_at (expires_at),
    CONSTRAINT fk_cancel_snapshot_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
