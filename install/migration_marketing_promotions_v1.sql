CREATE TABLE IF NOT EXISTS marketing_promotion_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(180) NOT NULL,
    status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    partner_profile VARCHAR(80) NULL,
    vehicle_type VARCHAR(80) NULL,
    vehicle_make VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    service_type_id INT NULL,
    priority INT NOT NULL DEFAULT 0,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    max_uses INT NULL,
    used_count INT NOT NULL DEFAULT 0,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_marketing_promotion_code (code),
    KEY idx_marketing_promotion_match (status, starts_at, ends_at, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pedidos' AND column_name='cupom_codigo')=0, 'ALTER TABLE pedidos ADD COLUMN cupom_codigo VARCHAR(80) NULL, ADD COLUMN desconto_promocional DECIMAL(12,2) NOT NULL DEFAULT 0.00, ADD COLUMN valor_bruto_checkout DECIMAL(12,2) NULL, ADD COLUMN parceiro_indicador_codigo VARCHAR(80) NULL, ADD COLUMN comissao_indicacao_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
