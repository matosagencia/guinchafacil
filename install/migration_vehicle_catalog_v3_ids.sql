-- migration_vehicle_catalog_v3_ids.sql
-- §CATALOGO-VISUAL-01 (02/08/2026, revisão): decisão do usuário foi por um
-- campo de busca com autocomplete (marca → modelo filtrado pelo fabricante),
-- não um seletor visual em grid com passo de versão. Isso significa que o
-- vínculo ao catálogo acontece no nível marca/modelo, sem exigir uma versão
-- específica (vehicle_version_id, que já existia desde v1, continua
-- disponível pra quando o admin quiser refinar depois, mas não é obrigatório
-- neste fluxo). Adiciona vehicle_brand_id/vehicle_model_id em `veiculos`
-- (cliente) e em `guinchos` (caminhão do guincheiro — mesmo padrão aplicado
-- aos dois, por pedido explícito do usuário).

SET @db_name := DATABASE();

-- ── veiculos (cliente) ──────────────────────────────────────────────────────
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'vehicle_brand_id'
);
SET @sql1 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN vehicle_brand_id INT NULL AFTER modelo',
    'SELECT "veiculos.vehicle_brand_id já existe" AS info'
);
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'vehicle_model_id'
);
SET @sql2 := IF(@col_exists = 0,
    'ALTER TABLE veiculos ADD COLUMN vehicle_model_id INT NULL AFTER vehicle_brand_id',
    'SELECT "veiculos.vehicle_model_id já existe" AS info'
);
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND CONSTRAINT_NAME = 'fk_veiculos_vehicle_brand'
);
SET @sqlfk1 := IF(@fk_exists = 0,
    'ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_vehicle_brand FOREIGN KEY (vehicle_brand_id) REFERENCES vehicle_brands (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_veiculos_vehicle_brand já existe" AS info'
);
PREPARE sfk1 FROM @sqlfk1; EXECUTE sfk1; DEALLOCATE PREPARE sfk1;

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'veiculos' AND CONSTRAINT_NAME = 'fk_veiculos_vehicle_model'
);
SET @sqlfk2 := IF(@fk_exists = 0,
    'ALTER TABLE veiculos ADD CONSTRAINT fk_veiculos_vehicle_model FOREIGN KEY (vehicle_model_id) REFERENCES vehicle_models (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_veiculos_vehicle_model já existe" AS info'
);
PREPARE sfk2 FROM @sqlfk2; EXECUTE sfk2; DEALLOCATE PREPARE sfk2;

-- ── guinchos (caminhão do guincheiro) ───────────────────────────────────────
-- Hoje `guinchos` não tem NENHUM campo de marca/modelo (só placa_guincho e
-- capacidade_ton) — texto livre igual ao padrão de `veiculos.marca/modelo`,
-- mais os ids do catálogo quando o autocomplete casar com algo cadastrado.
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'marca_caminhao'
);
SET @sql3 := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN marca_caminhao VARCHAR(80) NULL AFTER placa_guincho',
    'SELECT "guinchos.marca_caminhao já existe" AS info'
);
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'modelo_caminhao'
);
SET @sql4 := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN modelo_caminhao VARCHAR(100) NULL AFTER marca_caminhao',
    'SELECT "guinchos.modelo_caminhao já existe" AS info'
);
PREPARE s4 FROM @sql4; EXECUTE s4; DEALLOCATE PREPARE s4;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'vehicle_brand_id'
);
SET @sql5 := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN vehicle_brand_id INT NULL AFTER modelo_caminhao',
    'SELECT "guinchos.vehicle_brand_id já existe" AS info'
);
PREPARE s5 FROM @sql5; EXECUTE s5; DEALLOCATE PREPARE s5;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'vehicle_model_id'
);
SET @sql6 := IF(@col_exists = 0,
    'ALTER TABLE guinchos ADD COLUMN vehicle_model_id INT NULL AFTER vehicle_brand_id',
    'SELECT "guinchos.vehicle_model_id já existe" AS info'
);
PREPARE s6 FROM @sql6; EXECUTE s6; DEALLOCATE PREPARE s6;

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND CONSTRAINT_NAME = 'fk_guinchos_vehicle_brand'
);
SET @sqlfk3 := IF(@fk_exists = 0,
    'ALTER TABLE guinchos ADD CONSTRAINT fk_guinchos_vehicle_brand FOREIGN KEY (vehicle_brand_id) REFERENCES vehicle_brands (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_guinchos_vehicle_brand já existe" AS info'
);
PREPARE sfk3 FROM @sqlfk3; EXECUTE sfk3; DEALLOCATE PREPARE sfk3;

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'guinchos' AND CONSTRAINT_NAME = 'fk_guinchos_vehicle_model'
);
SET @sqlfk4 := IF(@fk_exists = 0,
    'ALTER TABLE guinchos ADD CONSTRAINT fk_guinchos_vehicle_model FOREIGN KEY (vehicle_model_id) REFERENCES vehicle_models (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "fk_guinchos_vehicle_model já existe" AS info'
);
PREPARE sfk4 FROM @sqlfk4; EXECUTE sfk4; DEALLOCATE PREPARE sfk4;

-- ── Seed extra: marcas/modelos de CAMINHÃO (o seed original de v1 era só
-- carro/moto de passeio — precisamos de fabricantes de caminhão pro
-- autocomplete do guincheiro ter o que sugerir). ──────────────────────────
INSERT INTO vehicle_brands (name) VALUES
    ('Mercedes-Benz'), ('Volvo'), ('Iveco'), ('Scania'), ('MAN')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO vehicle_models (brand_id, name)
SELECT b.id, m.name FROM (
    SELECT 'Mercedes-Benz' AS marca, 'Accelo 815' AS name UNION ALL
    SELECT 'Mercedes-Benz', 'Atego 1719' UNION ALL
    SELECT 'Volkswagen', 'Delivery 9.170' UNION ALL
    SELECT 'Volvo', 'VM 270' UNION ALL
    SELECT 'Iveco', 'Tector' UNION ALL
    SELECT 'Ford', 'Cargo 816' UNION ALL
    SELECT 'Scania', 'P 250' UNION ALL
    SELECT 'MAN', 'TGX 29.480'
) m
JOIN vehicle_brands b ON b.name = m.marca
ON DUPLICATE KEY UPDATE name = VALUES(name);
