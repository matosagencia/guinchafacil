-- Migration: Adiciona Cidade "Rio de Janeiro" e vincula à zona Centro-RJ
-- Criado em: 22/08/2026

-- 1. Insere Rio de Janeiro se não existir
INSERT IGNORE INTO `cidades` (`nome`, `uf`, `slug`, `ativo`, `lat_centro`, `lng_centro`, `raio_km`)
VALUES ('Rio de Janeiro', 'RJ', 'rio-de-janeiro', 1, -22.9068, -43.1729, 30);

-- 2. Vincula a zona Centro-RJ ao ID da cidade inserida
SET @city_id = (SELECT id FROM `cidades` WHERE `slug` = 'rio-de-janeiro' LIMIT 1);
UPDATE `pricing_zones` 
SET `city_id` = @city_id 
WHERE `code` = 'centro-rj';
