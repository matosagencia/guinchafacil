-- Habilita evidência específica do check-in de oficina.
ALTER TABLE `pedido_evidencias` MODIFY COLUMN `tipo` ENUM('coleta','entrega','CHECKIN_OFICINA') NOT NULL;
