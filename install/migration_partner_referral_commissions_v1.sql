-- Configuração versionada da comissão/recompensa de indicação por perfil.
-- Não cria saldo nem libera resgate; apenas armazena a regra vigente.
CREATE TABLE IF NOT EXISTS partner_referral_commission_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_profile ENUM('guincho','oficina','distribuicao') NOT NULL,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    min_order_value DECIMAL(12,2) NULL,
    max_commission_value DECIMAL(12,2) NULL,
    status ENUM('rascunho','ativo','pausado','encerrado') NOT NULL DEFAULT 'rascunho',
    effective_from DATETIME NOT NULL,
    effective_until DATETIME NULL,
    created_by_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_partner_referral_profile_period (partner_profile, status, effective_from),
    CONSTRAINT fk_partner_referral_commission_admin FOREIGN KEY (created_by_admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
