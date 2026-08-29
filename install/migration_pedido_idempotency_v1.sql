CREATE TABLE IF NOT EXISTS pedido_idempotency (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idempotency_key VARCHAR(120) NOT NULL,
    pedido_id INT NOT NULL,
    operation VARCHAR(64) NOT NULL,
    actor_type VARCHAR(32) NOT NULL,
    actor_id INT NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    response_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pedido_idempotency_key (idempotency_key),
    KEY idx_pedido_idempotency_pedido (pedido_id, operation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
