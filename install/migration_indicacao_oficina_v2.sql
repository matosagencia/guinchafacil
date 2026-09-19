-- Complemento idempotente: dados comerciais/geográficos da oficina parceira.
ALTER TABLE `provider_workshop_settings` ADD COLUMN `address` VARCHAR(255) NULL;
ALTER TABLE `provider_workshop_settings` ADD COLUMN `latitude` DECIMAL(10,8) NULL;
ALTER TABLE `provider_workshop_settings` ADD COLUMN `longitude` DECIMAL(11,8) NULL;