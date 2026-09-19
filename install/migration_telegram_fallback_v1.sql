-- migration_telegram_fallback_v1.sql
-- Fallback gratuito da fase 3 via Telegram Bot API.

SET @telegram_chat_id_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE usuarios ADD COLUMN telegram_chat_id VARCHAR(32) NULL AFTER telefone',
        'SELECT 1'
    )
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'usuarios'
      AND column_name = 'telegram_chat_id'
);
PREPARE telegram_chat_id_stmt FROM @telegram_chat_id_sql;
EXECUTE telegram_chat_id_stmt;
DEALLOCATE PREPARE telegram_chat_id_stmt;
