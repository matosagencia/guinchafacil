-- Admin payment destination v1. Idempotente para MySQL/MariaDB.
-- Executar uma vez pelo mecanismo de migrations do projeto.
SET @admin_payment_schema := DATABASE();

SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pedidos ADD COLUMN forma_pagamento_escolhida VARCHAR(32) NULL AFTER pricing_snapshot_version',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'forma_pagamento_escolhida';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pedidos ADD COLUMN provedor_pagamento_escolhido VARCHAR(32) NULL AFTER forma_pagamento_escolhida',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'provedor_pagamento_escolhido';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pedidos ADD COLUMN pagamento_destino_admin_id INT(11) NULL AFTER provedor_pagamento_escolhido',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pagamento_destino_admin_id';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pedidos ADD COLUMN pagamento_destino_registrado_em DATETIME NULL AFTER pagamento_destino_admin_id',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pagamento_destino_registrado_em';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) = 0,
    'ALTER TABLE pedidos ADD COLUMN pagamento_destino_evento VARCHAR(80) NULL AFTER pagamento_destino_registrado_em',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pagamento_destino_evento';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;