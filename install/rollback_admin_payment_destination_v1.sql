-- Reversao de admin payment destination v1. ATENCAO: remove o historico das colunas novas.
SET @admin_payment_schema := DATABASE();

SELECT IF(COUNT(*) > 0,
    'ALTER TABLE pedidos DROP COLUMN pagamento_destino_evento',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pagamento_destino_evento';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) > 0,
    'ALTER TABLE pedidos DROP COLUMN pagamento_destino_registrado_em',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pagamento_destino_registrado_em';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) > 0,
    'ALTER TABLE pedidos DROP COLUMN pagamento_destino_admin_id',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'pagamento_destino_admin_id';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) > 0,
    'ALTER TABLE pedidos DROP COLUMN provedor_pagamento_escolhido',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'provedor_pagamento_escolhido';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;

SELECT IF(COUNT(*) > 0,
    'ALTER TABLE pedidos DROP COLUMN forma_pagamento_escolhida',
    'SELECT 1') INTO @admin_payment_sql
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @admin_payment_schema AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'forma_pagamento_escolhida';
PREPARE admin_payment_stmt FROM @admin_payment_sql; EXECUTE admin_payment_stmt; DEALLOCATE PREPARE admin_payment_stmt;