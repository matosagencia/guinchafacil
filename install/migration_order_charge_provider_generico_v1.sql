-- Generalize financial provider foreign keys to providers.id.

SET @fk_name := (
    SELECT kcu.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE kcu
    WHERE kcu.TABLE_SCHEMA = DATABASE()
      AND kcu.TABLE_NAME = 'order_charge_items'
      AND kcu.COLUMN_NAME = 'provider_id'
      AND kcu.REFERENCED_TABLE_NAME = 'guinchos'
    LIMIT 1
);
SET @sql := IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `order_charge_items` DROP FOREIGN KEY `', @fk_name, '`'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @provider_fk_exists := (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_charge_items'
      AND COLUMN_NAME = 'provider_id' AND REFERENCED_TABLE_NAME = 'providers'
);
SET @sql := IF(@provider_fk_exists = 0,
    'ALTER TABLE `order_charge_items` ADD CONSTRAINT `fk_charge_item_provider_providers` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_name := (
    SELECT kcu.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE kcu
    WHERE kcu.TABLE_SCHEMA = DATABASE()
      AND kcu.TABLE_NAME = 'order_provider_settlements'
      AND kcu.COLUMN_NAME = 'provider_id'
      AND kcu.REFERENCED_TABLE_NAME = 'guinchos'
    LIMIT 1
);
SET @sql := IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `order_provider_settlements` DROP FOREIGN KEY `', @fk_name, '`'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @provider_fk_exists := (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'order_provider_settlements'
      AND COLUMN_NAME = 'provider_id' AND REFERENCED_TABLE_NAME = 'providers'
);
SET @sql := IF(@provider_fk_exists = 0,
    'ALTER TABLE `order_provider_settlements` ADD CONSTRAINT `fk_settlement_provider_providers` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
