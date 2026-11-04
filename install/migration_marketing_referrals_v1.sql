-- Partner referral codes and click ledger. No automatic campaign activation.
CREATE TABLE IF NOT EXISTS marketing_referral_codes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(80) NOT NULL,
    label VARCHAR(180) NOT NULL,
    partner_type VARCHAR(40) NOT NULL DEFAULT 'distribuicao',
    partner_name VARCHAR(180) NULL,
    status ENUM('rascunho','ativo','pausado','encerrado') NOT NULL DEFAULT 'rascunho',
    criado_por_admin_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketing_referral_code (code),
    KEY idx_marketing_referral_status (status),
    CONSTRAINT fk_marketing_referral_admin FOREIGN KEY (criado_por_admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
