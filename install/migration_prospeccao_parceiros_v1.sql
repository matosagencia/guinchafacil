-- migration_prospeccao_parceiros_v1.sql
-- Schema do módulo de prospecção integrado ao marketing.
-- Idempotente para rodar junto com o runner de migrations do projeto.

CREATE TABLE IF NOT EXISTS prospeccao_regioes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    cidade VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(10,7) NOT NULL,
    raio_km SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    categorias_alvo VARCHAR(500) NOT NULL
        COMMENT 'CSV: guincho,reboque,autoeletrica,borracheiro,chaveiro_automotivo,mecanico_movel,oficina_mecanica,bateria_automotiva,socorro_veicular',
    quota_alvo SMALLINT UNSIGNED NOT NULL DEFAULT 5
        COMMENT 'Numero de guinchos/especialistas cadastrados que encerra a prospecção nesta regiao',
    quota_atingida SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    prioridade_fuseki SMALLINT NOT NULL DEFAULT 100
        COMMENT 'Menor = prospectada primeiro (abertura de território)',
    status ENUM('ativa','pausada','concluida') NOT NULL DEFAULT 'ativa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_regioes_status_prioridade (status, prioridade_fuseki)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospeccao_leads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    regiao_id BIGINT UNSIGNED NOT NULL,
    place_id VARCHAR(120) NULL COMMENT 'data_id/data_cid do Google Maps, quando disponivel',
    nome_negocio VARCHAR(180) NOT NULL,
    categoria VARCHAR(60) NOT NULL,
    telefone VARCHAR(30) NULL,
    telefone_normalizado VARCHAR(20) NULL COMMENT 'Somente digitos, com DDI, usado para dedupe',
    endereco VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    rating DECIMAL(2,1) NULL,
    reviews_count INT UNSIGNED NULL,
    score_go DECIMAL(6,2) NOT NULL DEFAULT 0,
    fonte VARCHAR(40) NOT NULL DEFAULT 'serpapi_google_maps',
    status ENUM('novo','na_fila','enviado','respondeu','cadastrado','recusado','invalido') NOT NULL DEFAULT 'novo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lead_regiao_telefone (regiao_id, telefone_normalizado),
    KEY idx_leads_status_score (status, score_go),
    KEY idx_leads_place_id (place_id),
    CONSTRAINT fk_leads_regiao FOREIGN KEY (regiao_id) REFERENCES prospeccao_regioes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospeccao_convites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id BIGINT UNSIGNED NOT NULL,
    canal ENUM('whatsapp_manual','sms','whatsapp_api') NOT NULL DEFAULT 'whatsapp_manual',
    mensagem_texto TEXT NOT NULL,
    wa_link VARCHAR(500) NULL,
    enviado_por_usuario_id INT UNSIGNED NULL,
    enviado_em DATETIME NULL,
    resposta_status ENUM('pendente','positiva','negativa','sem_resposta') NOT NULL DEFAULT 'pendente',
    observacao VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_convites_lead (lead_id),
    CONSTRAINT fk_convites_lead FOREIGN KEY (lead_id) REFERENCES prospeccao_leads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
