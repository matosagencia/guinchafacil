-- migration_cidades_v1.sql
-- Cidade-alvo: unidade de expansão territorial (ex.: Niterói) usada para
-- vincular guincheiros a uma cidade de atuação e segmentar o planejamento
-- de lançamento (/admin/planejamento) por cidade. Cliente não é vinculado
-- a nenhuma cidade — pode solicitar de qualquer lugar.

CREATE TABLE IF NOT EXISTS cidades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    uf CHAR(2) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cidades_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semente: cidade-alvo inicial do piloto (Niterói/RJ), idempotente.
INSERT INTO cidades (nome, uf, slug, ativo)
SELECT 'Niterói', 'RJ', 'niteroi-rj', 1
WHERE NOT EXISTS (SELECT 1 FROM cidades WHERE slug = 'niteroi-rj');
