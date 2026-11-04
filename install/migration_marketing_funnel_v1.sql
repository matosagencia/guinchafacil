-- Funil comercial first-party. Complementa marketing_events e campanhas existentes.
-- Não grava dados de pagamento sensíveis; registra apenas estado comercial e atribuição.

CREATE TABLE IF NOT EXISTS marketing_leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_hash CHAR(64) NULL,
    user_id INT NULL,
    nome VARCHAR(180) NULL,
    email VARCHAR(180) NULL,
    telefone VARCHAR(40) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    origem VARCHAR(120) NULL,
    status ENUM('novo','qualificado','convertido','perdido','anonimizado') NOT NULL DEFAULT 'novo',
    first_event VARCHAR(80) NULL,
    last_event VARCHAR(80) NULL,
    metadata_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_marketing_leads_session (session_hash), KEY idx_marketing_leads_user (user_id),
    KEY idx_marketing_leads_status_date (status, criado_em), KEY idx_marketing_leads_city (cidade, uf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_partner_leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NULL,
    partner_type ENUM('guincho','oficina','distribuicao') NOT NULL,
    segmento VARCHAR(80) NULL,
    nome_empresa VARCHAR(180) NULL,
    nome_contato VARCHAR(180) NULL,
    email VARCHAR(180) NULL,
    telefone VARCHAR(40) NULL,
    cidade VARCHAR(120) NULL,
    uf CHAR(2) NULL,
    status ENUM('novo','em_analise','aprovado','rejeitado','convertido') NOT NULL DEFAULT 'novo',
    origem VARCHAR(120) NULL,
    metadata_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_partner_leads_type_status (partner_type, status), KEY idx_partner_leads_city (cidade, uf), KEY idx_partner_leads_date (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_referrals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    referral_code VARCHAR(80) NOT NULL,
    partner_user_id INT NULL,
    lead_id BIGINT UNSIGNED NULL,
    pedido_id INT NULL,
    status ENUM('capturado','qualificado','convertido','cancelado','rejeitado') NOT NULL DEFAULT 'capturado',
    commission_base DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission_percent DECIMAL(7,4) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    converted_at DATETIME NULL,
    metadata_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_referrals_code_status (referral_code, status), KEY idx_referrals_partner (partner_user_id, status), KEY idx_referrals_order (pedido_id), KEY idx_referrals_date (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_consents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_hash CHAR(64) NULL,
    user_id INT NULL,
    consent_type VARCHAR(50) NOT NULL,
    consent_value ENUM('granted','denied','withdrawn') NOT NULL,
    policy_version VARCHAR(40) NULL,
    source VARCHAR(120) NULL,
    ip_hash CHAR(64) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_consents_subject (session_hash, user_id, consent_type), KEY idx_consents_date (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_partner_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    partner_user_id INT NOT NULL,
    score DECIMAL(7,3) NOT NULL DEFAULT 0,
    response_rate DECIMAL(7,3) NULL,
    completion_rate DECIMAL(7,3) NULL,
    rating_average DECIMAL(4,2) NULL,
    cancellations_count INT NOT NULL DEFAULT 0,
    period_start DATE NULL,
    period_end DATE NULL,
    metadata_json JSON NULL,
    calculado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_partner_scores_partner_date (partner_user_id, calculado_em), KEY idx_partner_scores_score (score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NULL,
    partner_lead_id BIGINT UNSIGNED NULL,
    canal ENUM('email','whatsapp','sms','interno') NOT NULL,
    template_key VARCHAR(100) NOT NULL,
    status ENUM('pendente','enviado','entregue','falhou','cancelado') NOT NULL DEFAULT 'pendente',
    destinatario_hash CHAR(64) NULL,
    provider_message_id VARCHAR(180) NULL,
    enviado_em DATETIME NULL,
    metadata_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_marketing_messages_status (status, canal), KEY idx_marketing_messages_lead (lead_id), KEY idx_marketing_messages_partner (partner_lead_id), KEY idx_marketing_messages_date (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
