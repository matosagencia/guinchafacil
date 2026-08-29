CREATE TABLE IF NOT EXISTS order_charge_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    charge_item_id INT NOT NULL,
    order_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    method VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    external_id VARCHAR(120) NULL,
    approved_at DATETIME NULL,
    idempotency_key VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uk_charge_payment_idempotency (idempotency_key),
    UNIQUE KEY uk_charge_payment_item (charge_item_id),
    KEY idx_charge_payment_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
