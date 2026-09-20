-- Add the workshop check-in evidence type once.
SET @enum_has_checkin := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pedido_evidencias'
      AND COLUMN_NAME = 'tipo'
      AND COLUMN_TYPE LIKE "%CHECKIN_OFICINA%"
);
SET @sql := IF(
    @enum_has_checkin = 0,
    "ALTER TABLE `pedido_evidencias` MODIFY COLUMN `tipo` ENUM('coleta','entrega','CHECKIN_OFICINA') NOT NULL",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
