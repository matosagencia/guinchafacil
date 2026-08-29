-- Camada de orquestração do marketing territorial.
CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(180) NOT NULL,
    objetivo ENUM('captar_clientes','captar_guinchos','captar_especialistas','aumentar_cobertura','reativar_demanda') NOT NULL,
    publico ENUM('cliente','guincho','especialista','misto') NOT NULL DEFAULT 'cliente',
    pricing_zone_id INT NULL,
    service_type_id INT NULL,
    canal VARCHAR(40) NOT NULL DEFAULT 'organico',
    utm_campaign VARCHAR(180) NULL,
    mensagem TEXT NULL,
    landing_url VARCHAR(500) NULL,
    orcamento_planejado DECIMAL(12,2) NOT NULL DEFAULT 0,
    inicio DATE NULL,
    fim DATE NULL,
    status ENUM('rascunho','planejada','ativa','pausada','encerrada') NOT NULL DEFAULT 'rascunho',
    criado_por_admin_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_marketing_campaigns_zone (pricing_zone_id), KEY idx_marketing_campaigns_status (status),
    CONSTRAINT fk_marketing_campaigns_zone FOREIGN KEY (pricing_zone_id) REFERENCES pricing_zones(id) ON DELETE SET NULL,
    CONSTRAINT fk_marketing_campaigns_service FOREIGN KEY (service_type_id) REFERENCES service_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_marketing_campaigns_admin FOREIGN KEY (criado_por_admin_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_campaign_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    user_id INT NULL,
    partner_prospect_id BIGINT UNSIGNED NULL,
    pricing_zone_id INT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_campaign_events_campaign_date (campaign_id, created_at), KEY idx_campaign_events_type_date (event_type, created_at),
    CONSTRAINT fk_campaign_events_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
