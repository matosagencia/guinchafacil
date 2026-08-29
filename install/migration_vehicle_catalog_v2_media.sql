-- migration_vehicle_catalog_v2_media.sql
-- §CATALOGO-VISUAL-01 (02/08/2026): biblioteca visual de seleção de veículo
-- por marca/modelo — ressuscita o catálogo estruturado criado em
-- migration_vehicle_catalog_v1.sql (marca/modelo/versão), que existia no
-- schema mas nunca foi usado por controller/view algum. Adiciona só o que
-- faltava pra exibir visualmente: logo da marca e imagem do modelo.
--
-- Decisão do usuário (02/08/2026): sem logos oficiais de marca por enquanto
-- (risco de marca registrada) — cada marca sem logo cadastrado usa um badge
-- com a inicial do nome (ver AdminVehicleCatalogController/veiculoform.php).
-- `logo_path`/`image_path` ficam NULL até o admin fazer upload — aditivo,
-- não quebra nada do catálogo já seedado em v1.

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'vehicle_brands' AND COLUMN_NAME = 'logo_path'
);
SET @sql1 := IF(@col_exists = 0,
    'ALTER TABLE vehicle_brands ADD COLUMN logo_path VARCHAR(255) NULL AFTER name',
    'SELECT "vehicle_brands.logo_path já existe" AS info'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'vehicle_models' AND COLUMN_NAME = 'image_path'
);
SET @sql2 := IF(@col_exists = 0,
    'ALTER TABLE vehicle_models ADD COLUMN image_path VARCHAR(255) NULL AFTER name',
    'SELECT "vehicle_models.image_path já existe" AS info'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;
