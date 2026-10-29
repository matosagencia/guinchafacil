CREATE TABLE IF NOT EXISTS order_conversion_price_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id BIGINT UNSIGNED NOT NULL,
    fase VARCHAR(40) NOT NULL,
    customer_total DECIMAL(12,2) NOT NULL,
    km_cobrado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pricing_version VARCHAR(80) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uk_conversion_snapshot_phase (pedido_id, fase), KEY idx_conversion_snapshot_order (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
