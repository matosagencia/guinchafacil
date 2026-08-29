-- GuinchaFacil - correcoes de cancelamento e auditoria
-- Aplicacao segura: aditiva e idempotente.
CREATE TABLE IF NOT EXISTS pedido_cancelamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT NOT NULL,
    ator_tipo ENUM('cliente','guincho','admin','sistema') NOT NULL,
    ator_id INT NULL,
    motivo VARCHAR(1000) NOT NULL,
    status_anterior VARCHAR(40) NOT NULL,
    penalidade DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cancelamento_pedido (pedido_id),
    KEY idx_cancelamento_ator (ator_tipo, ator_id),
    KEY idx_cancelamento_data (criado_em),
    CONSTRAINT fk_cancelamento_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
