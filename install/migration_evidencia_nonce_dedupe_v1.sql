-- Idempotent evidence nonce and content deduplication indexes.
SET @db_name := DATABASE();

SET @has_nonce_index := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedido_evidencias' AND INDEX_NAME='uk_nonce_token'
);
SET @sql_nonce_index := IF(@has_nonce_index=0,
    'ALTER TABLE pedido_evidencias ADD UNIQUE INDEX uk_nonce_token (nonce_token)', 'SELECT 1');
PREPARE stmt_nonce_index FROM @sql_nonce_index; EXECUTE stmt_nonce_index; DEALLOCATE PREPARE stmt_nonce_index;

SET @has_sha_index := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='pedido_evidencias' AND INDEX_NAME='uk_pedido_tipo_sha256'
);
SET @sql_sha_index := IF(@has_sha_index=0,
    'ALTER TABLE pedido_evidencias ADD UNIQUE INDEX uk_pedido_tipo_sha256 (pedido_id, tipo, sha256)', 'SELECT 1');
PREPARE stmt_sha_index FROM @sql_sha_index; EXECUTE stmt_sha_index; DEALLOCATE PREPARE stmt_sha_index;
