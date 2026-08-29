-- migration_prestador_tipo_v1.sql
-- Tipo de prestador definido no cadastro + reboque como aprovação adicional.
--
-- Modelo (decisão de produto, conversa com o usuário):
--   * Todo prestador é uma linha em `guinchos` (a identidade de prestador).
--   * `oferece_reboque` = ele declara que oferece reboque (guincho).
--   * `reboque_aprovado` = documentos de guincho conferidos pelo admin.
--   * `aprovado` (já existente) = aprovação GERAL do prestador (fila única).
--   Um especialista (chaveiro, elétrica, pneu...) pode ter aprovado=1 e
--   reboque_aprovado=0. O matching de REBOQUE passa a exigir reboque_aprovado=1,
--   então especialista nunca recebe chamado de reboque.
--
-- Backfill: guinchos que já existem SÃO prestadores de reboque — preserva o
-- comportamento atual (oferece_reboque=1, e reboque_aprovado espelha o
-- aprovado deles). Defensivo (checa INFORMATION_SCHEMA antes de alterar).

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'oferece_reboque');
SET @sql := IF(@col = 0,
    'ALTER TABLE guinchos ADD COLUMN oferece_reboque TINYINT(1) NOT NULL DEFAULT 1 AFTER aprovado',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'reboque_aprovado');
SET @sql := IF(@col = 0,
    'ALTER TABLE guinchos ADD COLUMN reboque_aprovado TINYINT(1) NOT NULL DEFAULT 0 AFTER oferece_reboque',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Especialista (sem reboque) não tem CNH/validade — a coluna DATE NOT NULL
-- rejeitaria o cadastro. Torna nullable (widening, seguro). Idempotente:
-- MODIFY é no-op se já estiver NULL.
SET @nn := (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'guinchos' AND COLUMN_NAME = 'cnh_validade');
SET @sql := IF(@nn = 'NO', 'ALTER TABLE guinchos MODIFY cnh_validade DATE NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill único: só ajusta linhas que ainda não foram tocadas (reboque_aprovado
-- ainda 0 mas o prestador já está aprovado como guincho no modelo antigo).
UPDATE guinchos SET reboque_aprovado = 1, oferece_reboque = 1
WHERE aprovado = 1 AND reboque_aprovado = 0;
