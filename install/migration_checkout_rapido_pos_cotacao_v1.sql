-- Checkout rapido pos-cotacao - v1
-- Idempotente: pode rodar multiplas vezes sem quebrar.
-- Aplicado por install/migrate.php (que ja gerencia transacao e registro
-- em schema_migrations).

-- 1) Login sem senha (Google Sign-in)
ALTER TABLE usuarios
  MODIFY COLUMN senha_hash VARCHAR(255) NULL;

-- 2) Vinculo triagem -> veiculo/pedido
ALTER TABLE triage_sessions
  ADD COLUMN IF NOT EXISTS veiculo_id INT(11) NULL AFTER cliente_id,
  ADD COLUMN IF NOT EXISTS pedido_id  INT(11) NULL AFTER veiculo_id;
