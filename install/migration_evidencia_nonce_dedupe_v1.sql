-- install/migration_evidencia_nonce_dedupe_v1.sql
-- §A2 (auditoria 21/07): o nonce de evidência (EvidenceService::issueNonce)
-- era um token HMAC stateless — válido pra reuso até expirar (5min), sem
-- nada impedindo duas submissões aceitas com o mesmo token. Também não
-- havia dedupe por conteúdo (sha256 calculado mas nunca checado), então a
-- mesma foto podia ser registrada mais de uma vez para o mesmo
-- pedido+etapa. As duas UNIQUE KEYs abaixo fecham os dois gaps via
-- constraint de banco: EvidenceService::storeUploadedEvidence() passa a
-- tratar a violação como "nonce já utilizado"/"evidência já registrada"
-- (idempotente), não como erro genérico.
-- Idempotente: pode ser reexecutada sem erro.

ALTER TABLE pedido_evidencias
    ADD UNIQUE INDEX IF NOT EXISTS uk_nonce_token (nonce_token);

ALTER TABLE pedido_evidencias
    ADD UNIQUE INDEX IF NOT EXISTS uk_pedido_tipo_sha256 (pedido_id, tipo, sha256);
