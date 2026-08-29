-- install/migration_chat_idempotency_v1.sql
-- Pacote L1.9 — idempotência de envio de chat: evita mensagem duplicada por
-- duplo-clique ou retry de rede (timeout do fetch + reenvio do usuário).
-- Idempotente: pode ser reexecutada sem erro.

ALTER TABLE chat_mensagens
    ADD COLUMN IF NOT EXISTS idempotency_key VARCHAR(64) NULL AFTER mensagem;

-- MySQL/MariaDB tratam múltiplos NULL como distintos em UNIQUE, então mensagens
-- antigas (sem key) e chamadas que não enviarem key continuam funcionando.
ALTER TABLE chat_mensagens
    ADD UNIQUE INDEX IF NOT EXISTS uk_chat_pedido_usuario_idempotency (pedido_id, usuario_id, idempotency_key);
