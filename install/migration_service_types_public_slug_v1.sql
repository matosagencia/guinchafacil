-- Add the public catalog identifier required by the public funnel.
-- Idempotent for databases where service_types already has the column.

SET @has_col := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'service_types'
      AND COLUMN_NAME = 'public_slug'
);

SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE service_types ADD COLUMN public_slug VARCHAR(80) NULL AFTER code',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Existing internal codes are the only safe initial public identifiers.
UPDATE service_types
SET public_slug = LOWER(code)
WHERE (public_slug IS NULL OR TRIM(public_slug) = '')
  AND code IS NOT NULL
  AND TRIM(code) <> '';

SET @has_index := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'service_types'
      AND INDEX_NAME = 'uk_service_types_public_slug'
);

SET @sql := IF(
    @has_index = 0,
    'ALTER TABLE service_types ADD UNIQUE KEY uk_service_types_public_slug (public_slug)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
