-- Rankings territoriais agregados do RENAEST para apoio estratégico.
-- Não são pedidos GuinchaFácil e não devem ser usados como pontos exatos no mapa.
CREATE TABLE IF NOT EXISTS territorial_external_rankings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL,
    ranking_type VARCHAR(30) NOT NULL,
    label VARCHAR(180) NOT NULL,
    reference_year SMALLINT UNSIGNED NOT NULL,
    occurrence_count INT UNSIGNED NOT NULL,
    source_name VARCHAR(180) NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_external_ranking (city_name, uf, ranking_type, label, reference_year),
    KEY idx_external_ranking_scope (city_name, uf, ranking_type, reference_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO territorial_external_rankings
    (city_name, uf, ranking_type, label, reference_year, occurrence_count, source_name, source_url, notes)
VALUES
('Niterói','RJ','via','Francisco da Cruz Nunes',2024,533,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','Caetano Monteiro',2018,221,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','São Boaventura',2018,311,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Soma das grafias São Boaventura e São Boa Ventura.'),
('Niterói','RJ','via','Ewerton da Costa Xavier',2018,288,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Soma das grafias Ewerton da Costa Xavier e Ewerton Xavier.'),
('Niterói','RJ','via','Noronha Torrezão',2018,139,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','Visconde do Rio Branco',2018,134,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','Almirante Tamandaré',2018,129,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','Irene Lopes Sodré',2018,124,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','Rui Barbosa',2018,118,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','via','Raul de Oliveira Rodrigues',2018,102,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; nomes de vias normalizados.'),
('Niterói','RJ','bairro','Itaipu',2018,423,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.'),
('Niterói','RJ','bairro','Centro',2018,341,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.'),
('Niterói','RJ','bairro','Icaraí',2018,340,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.'),
('Niterói','RJ','bairro','Fonseca',2018,316,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.'),
('Niterói','RJ','bairro','Piratininga',2018,250,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.'),
('Niterói','RJ','bairro','Santa Rosa',2018,168,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.'),
('Niterói','RJ','bairro','São Francisco',2018,131,'RENAEST / Ministério dos Transportes','https://dados.transportes.gov.br/pt_BR/dataset/renaest','Total agregado de 2018 a 2024; bairro informado no registro.')
ON DUPLICATE KEY UPDATE occurrence_count=VALUES(occurrence_count), notes=VALUES(notes), updated_at=NOW();
