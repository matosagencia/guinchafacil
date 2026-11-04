-- Adiciona o perfil administrativo de parceiro de indicação.
SET @tipo := (SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'tipo');
SET @sql := IF(@tipo LIKE '%parceiro%', 'SELECT 1', "ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('admin','guincho','cliente','funcionario','gerente','especialista','oficina','parceiro') NOT NULL");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
