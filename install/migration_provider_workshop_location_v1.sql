-- Coordinates and display address for partner workshops.
SET @has_address := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_workshop_settings'
      AND COLUMN_NAME = 'address'
);
SET @sql := IF(@has_address = 0,
    'ALTER TABLE `provider_workshop_settings` ADD COLUMN `address` VARCHAR(255) NULL AFTER `raio_checkin_m`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_latitude := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_workshop_settings'
      AND COLUMN_NAME = 'latitude'
);
SET @sql := IF(@has_latitude = 0,
    'ALTER TABLE `provider_workshop_settings` ADD COLUMN `latitude` DECIMAL(10,8) NULL AFTER `address`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_longitude := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provider_workshop_settings'
      AND COLUMN_NAME = 'longitude'
);
SET @sql := IF(@has_longitude = 0,
    'ALTER TABLE `provider_workshop_settings` ADD COLUMN `longitude` DECIMAL(11,8) NULL AFTER `latitude`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
