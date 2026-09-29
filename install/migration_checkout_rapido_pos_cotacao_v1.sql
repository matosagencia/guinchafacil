-- Checkout rapido pos-cotacao - v1
-- Autor: refatoracao v3
-- Data: 2026-09-29
-- Descricao:
--   1. usuarios.senha_hash aceita NULL (login Google-only)
--   2. triage_sessions ganha veiculo_id + pedido_id (rastreabilidade do funil)
--   3. indices auxiliares em triage_sessions

USE guinchafacil_dev;

START TRANSACTION;

-- 1) Login sem senha (Google Sign-in)
ALTER TABLE usuarios
  MODIFY COLUMN senha_hash VARCHAR(255) NULL;

-- 2) Vinculo triagem -> veiculo/pedido
ALTER TABLE triage_sessions
  ADD COLUMN veiculo_id INT(11) NULL AFTER cliente_id,
  ADD COLUMN pedido_id  INT(11) NULL AFTER veiculo_id,
  ADD KEY idx_triage_veiculo (veiculo_id),
  ADD KEY idx_triage_pedido  (pedido_id);

COMMIT;

-- Registro em schema_migrations
INSERT INTO schema_migrations (version, filename, checksum_sha256, applied_by, success)
VALUES (
  '202609291200',
  'V202609291200__checkout_rapido_pos_cotacao.sql',
  SHA2('checkout_rapido_pos_cotacao_v1', 256),
  'manual',
  1
);