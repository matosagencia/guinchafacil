-- Aplicação idempotente dos polígonos e metas essenciais de Niterói.
UPDATE pricing_zones SET
    meta_guinchos_min = CASE WHEN code='niteroi-celula-1' THEN 4 ELSE meta_guinchos_min END,
    meta_especialistas_min = CASE WHEN code='niteroi-celula-1' THEN 6 ELSE meta_especialistas_min END
WHERE code IN ('niteroi-celula-1','niteroi-celula-2','niteroi-celula-3','niteroi-celula-4','niteroi-celula-5');
