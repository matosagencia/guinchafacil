-- Generaliza o prestador financeiro para providers.id.
ALTER TABLE `order_charge_items` DROP FOREIGN KEY `fk_charge_item_provider`;
ALTER TABLE `order_charge_items` ADD CONSTRAINT `fk_charge_item_provider_new` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `order_provider_settlements` DROP FOREIGN KEY `fk_settlement_provider`;
ALTER TABLE `order_provider_settlements` ADD CONSTRAINT `fk_settlement_provider_new` FOREIGN KEY (`provider_id`) REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
