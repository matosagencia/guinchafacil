-- migration_pricing_zones_v3_expansao.sql
-- §CELULAS-NITEROI-01 (04/08/2026): decisão do usuário — a estratégia de
-- expansão de Niterói não é "cidade inteira de uma vez", é domínio
-- progressivo por CÉLULA territorial (grupo de bairros), com gate de
-- indicadores pra abrir a próxima. `pricing_zones` já é a entidade
-- geográfica certa pra isso (point-in-polygon via ZonePricingService) —
-- só faltavam os campos de governança da expansão. Esta migration:
--   1) adiciona ordem_expansao / status_expansao / bairros_referencia;
--   2) semeia as 5 células de Niterói do plano territorial do usuário,
--      SEM polígono desenhado ainda (o admin desenha depois em
--      /admin/precificacao/zonas — até lá, a zona existe só como registro
--      organizacional e não interfere em nenhum cálculo de preço, mesmo
--      comportamento aditivo já documentado em ZonePricingService).
-- Idempotente: idempotência de coluna via INFORMATION_SCHEMA; seed via
-- ON DUPLICATE KEY UPDATE (uk_pricing_zones_code já existe desde v1).

SET @db_name := DATABASE();

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'ordem_expansao');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN ordem_expansao INT NULL AFTER cidade_id', 'SELECT "ordem_expansao já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'status_expansao');
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE pricing_zones ADD COLUMN status_expansao ENUM('nao_ativada','pedra_morta','pedra_viva') NOT NULL DEFAULT 'nao_ativada' AFTER ordem_expansao",
    'SELECT "status_expansao já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'pricing_zones' AND COLUMN_NAME = 'bairros_referencia');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE pricing_zones ADD COLUMN bairros_referencia TEXT NULL AFTER status_expansao', 'SELECT "bairros_referencia já existe" AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed das 5 células de Niterói (plano territorial do usuário, 04/08/2026).
-- cidade_id resolvido pelo slug fixo já semeado em migration_cidades_v1.sql.
INSERT INTO pricing_zones (city_id, cidade_id, code, name, polygon_geojson, active, ordem_expansao, status_expansao, bairros_referencia, created_at, updated_at)
SELECT NULL, c.id, v.code, v.name, NULL, 1, v.ordem, 'nao_ativada', v.bairros, NOW(), NOW()
FROM cidades c
JOIN (
    SELECT 'niteroi-celula-1' AS code, 'Praias da Baía Central' AS name, 1 AS ordem,
        'Icaraí, Santa Rosa, Vital Brazil, São Francisco, Ingá, Boa Viagem, Centro' AS bairros
    UNION ALL
    SELECT 'niteroi-celula-2', 'Norte e acessos à Ponte', 2,
        'Fonseca, Ponto Cem Réis, Cubango, Engenhoca, Barreto, Ponta d''Areia, Ilha da Conceição'
    UNION ALL
    SELECT 'niteroi-celula-3', 'Pendotiba e eixo intermediário', 3,
        'Largo da Batalha, Badu, Sapê, Matapaca, Maria Paula, Cantagalo, Maceió, Ititioca'
    UNION ALL
    SELECT 'niteroi-celula-4', 'Região Oceânica', 4,
        'Piratininga, Cafubá, Camboinhas, Itaipu, Itacoatiara, Maravista, Serra Grande, Engenho do Mato, Jacaré'
    UNION ALL
    SELECT 'niteroi-celula-5', 'Fronteiras e expansão metropolitana', 5,
        'Rio do Ouro, Várzea das Moças, limite com São Gonçalo, acessos para Maricá'
) v ON 1 = 1
WHERE c.slug = 'niteroi-rj'
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    ordem_expansao = VALUES(ordem_expansao),
    bairros_referencia = VALUES(bairros_referencia),
    cidade_id = VALUES(cidade_id);
