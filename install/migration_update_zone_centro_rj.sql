-- Migration: Atualiza Polígono da Zona "Centro - RJ"
-- Abrangência: Centro, Lapa, Catumbi, Praça Onze, Saúde, Gamboa, Santo Cristo
-- Criado em: 22/08/2026

UPDATE `pricing_zones`
SET `polygon_geojson` = '{"type": "Polygon", "coordinates": [[[-43.205, -22.915], [-43.205, -22.89], [-43.18, -22.89], [-43.18, -22.915], [-43.205, -22.915]]]}',
    `updated_at` = NOW()
WHERE `code` = 'centro-rj';
