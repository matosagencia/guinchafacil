-- Indicadores externos oficiais para contexto territorial.
-- Não são pré-cotações e não entram no cálculo de Pedra Viva/Morta.
CREATE TABLE IF NOT EXISTS territorial_external_indicators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(120) NOT NULL, uf CHAR(2) NOT NULL,
    indicator_code VARCHAR(80) NOT NULL, indicator_name VARCHAR(180) NOT NULL,
    reference_year SMALLINT UNSIGNED NOT NULL, value_decimal DECIMAL(12,4) NOT NULL,
    unit VARCHAR(80) NOT NULL, source_name VARCHAR(180) NOT NULL, source_url VARCHAR(500) NOT NULL,
    notes VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_external_indicator (city_name, uf, indicator_code, reference_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO territorial_external_indicators (city_name,uf,indicator_code,indicator_name,reference_year,value_decimal,unit,source_name,source_url,notes) VALUES
('Niterói','RJ','people_involved_accidents','Pessoas envolvidas em acidentes de trânsito',2023,1257,'pessoas','ISPTrânsito / ISP-RJ','https://www.rj.gov.br/isp/node/1052','Indicador municipal agregado; não representa pontos geográficos.'),
('Niterói','RJ','accidents_rate_100k','Taxa de acidentes de trânsito',2024,165.44,'acidentes por 100 mil habitantes','Relatório de Monitoramento PPA 2024 / Prefeitura de Niterói','https://www.portalplanejamento.niteroi.rj.gov.br/wp-content/uploads/2025/06/Relatorio-de-Monitoramento-PPA-ano-2024.pdf','Indicador municipal agregado; não distribuir artificialmente pelas zonas.'),
('Niterói','RJ','traffic_deaths_rate_100k','Taxa de mortes no trânsito',2024,1.45,'mortes por 100 mil habitantes','Relatório de Monitoramento PPA 2024 / Prefeitura de Niterói','https://www.portalplanejamento.niteroi.rj.gov.br/wp-content/uploads/2025/06/Relatorio-de-Monitoramento-PPA-ano-2024.pdf','Indicador municipal agregado; não distribuir artificialmente pelas zonas.')
ON DUPLICATE KEY UPDATE value_decimal=VALUES(value_decimal), source_url=VALUES(source_url), notes=VALUES(notes), updated_at=NOW();
