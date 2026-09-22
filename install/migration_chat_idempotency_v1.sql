-- Idempotent chat message key and deduplication index.
SET @db_name := DATABASE();
SET @has_idempotency_key := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='chat_mensagens' AND COLUMN_NAME='idempotency_key'
);
SET @sql_idempotency_key := IF(@has_idempotency_key=0,
    'ALTER TABLE chat_mensagens ADD COLUMN idempotency_key VARCHAR(64) NULL AFTER mensagem',
    'SELECT 1'
);
PREPARE stmt_idempotency_key FROM @sql_idempotency_key;
EXECUTE stmt_idempotency_key;
DEALLOCATE PREPARE stmt_idempotency_key;

SET @has_chat_idempotency_index := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='chat_mensagens'
      AND INDEX_NAME='uk_chat_pedido_usuario_idempotency'
);
SET @sql_chat_idempotency_index := IF(@has_chat_idempotency_index=0,
    'ALTER TABLE chat_mensagens ADD UNIQUE INDEX uk_chat_pedido_usuario_idempotency (pedido_id, usuario_id, idempotency_key)',
    'SELECT 1'
);
PREPARE stmt_chat_idempotency_index FROM @sql_chat_idempotency_index;
EXECUTE stmt_chat_idempotency_index;
DEALLOCATE PREPARE stmt_chat_idempotency_index;
