-- Migration: Adiciona Zona Geográfica "Centro - RJ" (Idempotente)
-- Criado em: 22/08/2026

INSERT IGNORE INTO `pricing_zones` (`code`, `name`, `polygon_geojson`, `active`, `created_at`, `updated_at`)
VALUES (
    'centro-rj',
    'Centro - Rio de Janeiro',
    '{"type": "Polygon", "coordinates": [[[-43.195, -22.915], [-43.165, -22.915], [-43.165, -22.895], [-43.195, -22.895], [-43.195, -22.915]]]}',
    1,
    NOW(),
    NOW()
);
