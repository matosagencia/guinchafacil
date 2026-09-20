-- Reversal linkage for immutable financial history.
SET @has_reversal := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_charge_items'
      AND COLUMN_NAME = 'reverses_charge_item_id'
);
SET @sql := IF(@has_reversal = 0,
    'ALTER TABLE `order_charge_items` ADD COLUMN `reverses_charge_item_id` INT NULL AFTER `block_reason_code`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_reversal_index := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'order_charge_items'
      AND INDEX_NAME = 'idx_charge_item_reversal'
);
SET @sql := IF(@has_reversal_index = 0,
    'ALTER TABLE `order_charge_items` ADD KEY `idx_charge_item_reversal` (`reverses_charge_item_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
