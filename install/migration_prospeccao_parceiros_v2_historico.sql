-- migration_prospeccao_parceiros_v2_historico.sql
-- Historico de contatos obtidos e operacoes realizadas na prospeccao.

CREATE TABLE IF NOT EXISTS prospeccao_atividades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('contato_obtido','operacao') NOT NULL DEFAULT 'operacao',
    acao VARCHAR(80) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    detalhes_json JSON NULL,
    regiao_id BIGINT UNSIGNED NULL,
    lead_id BIGINT UNSIGNED NULL,
    usuario_id INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_atividades_tipo_data (tipo, criado_em),
    KEY idx_atividades_regiao (regiao_id),
    KEY idx_atividades_lead (lead_id),
    CONSTRAINT fk_atividades_regiao FOREIGN KEY (regiao_id) REFERENCES prospeccao_regioes(id) ON DELETE SET NULL,
    CONSTRAINT fk_atividades_lead FOREIGN KEY (lead_id) REFERENCES prospeccao_leads(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
