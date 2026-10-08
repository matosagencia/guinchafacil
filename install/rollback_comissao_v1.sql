-- Rollback da comissão e fatura de parceiros v1 (MySQL/MariaDB).
-- Idempotente e conservador: só remove a estrutura se as quatro tabelas
-- introduzidas pela migration estiverem vazias. Em caso contrário, não altera
-- nada e devolve mensagens SKIPPED para evitar perda de dados.
SET @schema := DATABASE();

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='comissoes_cidade') > 0,
  'SELECT COUNT(*) INTO @comissoes_cidade_rows FROM comissoes_cidade',
  'SELECT 0 INTO @comissoes_cidade_rows'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='faturas_parceiro') > 0,
  'SELECT COUNT(*) INTO @faturas_parceiro_rows FROM faturas_parceiro',
  'SELECT 0 INTO @faturas_parceiro_rows'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='faturas_itens') > 0,
  'SELECT COUNT(*) INTO @faturas_itens_rows FROM faturas_itens',
  'SELECT 0 INTO @faturas_itens_rows'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='faturas_pagamentos') > 0,
  'SELECT COUNT(*) INTO @faturas_pagamentos_rows FROM faturas_pagamentos',
  'SELECT 0 INTO @faturas_pagamentos_rows'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;

SET @rollback_safe := IF(
  @comissoes_cidade_rows = 0
  AND @faturas_parceiro_rows = 0
  AND @faturas_itens_rows = 0
  AND @faturas_pagamentos_rows = 0,
  1, 0
);
SELECT IF(@rollback_safe = 1,
  'ROLLBACK-COMISSAO-V1: estrutura vazia; remoção autorizada.',
  'ROLLBACK-COMISSAO-V1: SKIPPED; existem dados de comissão/fatura. Restaure o backup para reverter.'
) AS resultado;

SET @sql := IF(@rollback_safe = 1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND CONSTRAINT_NAME='fk_pedidos_fatura') > 0,
  'ALTER TABLE pedidos DROP FOREIGN KEY fk_pedidos_fatura',
  'SELECT ''ROLLBACK-COMISSAO-V1: FK pedidos ignorada'' AS resultado'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND INDEX_NAME='idx_pedidos_fatura') > 0,
  'DROP INDEX idx_pedidos_fatura ON pedidos',
  'SELECT ''ROLLBACK-COMISSAO-V1: índice pedidos ignorado'' AS resultado'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;

SET @sql := IF(@rollback_safe = 1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='fatura_id') > 0,
  'ALTER TABLE pedidos DROP COLUMN fatura_id',
  'SELECT ''ROLLBACK-COMISSAO-V1: coluna fatura_id ignorada'' AS resultado'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='valor_liquido_parceiro') > 0,
  'ALTER TABLE pedidos DROP COLUMN valor_liquido_parceiro',
  'SELECT ''ROLLBACK-COMISSAO-V1: coluna valor_liquido_parceiro ignorada'' AS resultado'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='comissao_tipo') > 0,
  'ALTER TABLE pedidos DROP COLUMN comissao_tipo',
  'SELECT ''ROLLBACK-COMISSAO-V1: coluna comissao_tipo ignorada'' AS resultado'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1 AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@schema AND TABLE_NAME='pedidos' AND COLUMN_NAME='comissao_valor') > 0,
  'ALTER TABLE pedidos DROP COLUMN comissao_valor',
  'SELECT ''ROLLBACK-COMISSAO-V1: coluna comissao_valor ignorada'' AS resultado'
); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;

SET @sql := IF(@rollback_safe = 1, 'DROP TABLE IF EXISTS faturas_pagamentos', 'SELECT ''ROLLBACK-COMISSAO-V1: faturas_pagamentos preservada'' AS resultado'); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1, 'DROP TABLE IF EXISTS faturas_itens', 'SELECT ''ROLLBACK-COMISSAO-V1: faturas_itens preservada'' AS resultado'); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1, 'DROP TABLE IF EXISTS faturas_parceiro', 'SELECT ''ROLLBACK-COMISSAO-V1: faturas_parceiro preservada'' AS resultado'); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
SET @sql := IF(@rollback_safe = 1, 'DROP TABLE IF EXISTS comissoes_cidade', 'SELECT ''ROLLBACK-COMISSAO-V1: comissoes_cidade preservada'' AS resultado'); PREPARE rollback_stmt FROM @sql; EXECUTE rollback_stmt; DEALLOCATE PREPARE rollback_stmt;
