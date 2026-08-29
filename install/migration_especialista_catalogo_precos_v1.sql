-- Catálogo comercial próprio dos especialistas.
-- A migração é aditiva e idempotente: não altera pedidos ou tarifas de guincho.
SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='servicos_especialista' AND COLUMN_NAME='preco_atendimento')=0,
 'ALTER TABLE servicos_especialista ADD COLUMN preco_atendimento DECIMAL(10,2) NOT NULL DEFAULT 59.90 AFTER tipo_cobranca', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='servicos_especialista' AND COLUMN_NAME='preco_adicional')=0,
 'ALTER TABLE servicos_especialista ADD COLUMN preco_adicional DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER preco_atendimento', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='servicos_especialista' AND COLUMN_NAME='adicional_noturno')=0,
 'ALTER TABLE servicos_especialista ADD COLUMN adicional_noturno DECIMAL(10,2) NOT NULL DEFAULT 20.00 AFTER preco_adicional', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='servicos_especialista' AND COLUMN_NAME='raio_incluso_km')=0,
 'ALTER TABLE servicos_especialista ADD COLUMN raio_incluso_km DECIMAL(6,2) NOT NULL DEFAULT 5.00 AFTER adicional_noturno', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS especialista_servico_produtos (
    servico_id INT NOT NULL,
    produto_id INT NOT NULL,
    preco_maximo DECIMAL(10,2) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (servico_id, produto_id),
    CONSTRAINT fk_esp_sp_servico FOREIGN KEY (servico_id) REFERENCES servicos_especialista(id) ON DELETE CASCADE,
    CONSTRAINT fk_esp_sp_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=0, adicional_noturno=20 WHERE codigo='BATTERY_DIAG';
UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=29.90, adicional_noturno=20 WHERE codigo='BATTERY_JUMP';
UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=29.90, adicional_noturno=20 WHERE codigo='BATTERY_REPLACE';
UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=39.90, adicional_noturno=20 WHERE codigo='TIRE_CHANGE';
UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=29.90, adicional_noturno=20 WHERE codigo='FUEL_DELIVERY';
UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=59.90, adicional_noturno=20 WHERE codigo='LOCKOUT';
UPDATE servicos_especialista SET preco_atendimento=59.90, preco_adicional=69.90, adicional_noturno=20 WHERE codigo='ELECTRICAL_DIAG';

INSERT IGNORE INTO especialista_servico_produtos (servico_id, produto_id)
SELECT s.id, p.id FROM servicos_especialista s JOIN produtos p ON p.active=1
WHERE s.codigo IN ('BATTERY_DIAG','BATTERY_JUMP','BATTERY_REPLACE') AND p.categoria IN ('bateria','eletrica');
INSERT IGNORE INTO especialista_servico_produtos (servico_id, produto_id)
SELECT s.id, p.id FROM servicos_especialista s JOIN produtos p ON p.active=1
WHERE s.codigo='TIRE_CHANGE' AND p.categoria='pneu';
INSERT IGNORE INTO especialista_servico_produtos (servico_id, produto_id)
SELECT s.id, p.id FROM servicos_especialista s JOIN produtos p ON p.active=1
WHERE s.codigo='FUEL_DELIVERY' AND p.categoria='combustivel';
INSERT IGNORE INTO especialista_servico_produtos (servico_id, produto_id)
SELECT s.id, p.id FROM servicos_especialista s JOIN produtos p ON p.active=1
WHERE s.codigo='LOCKOUT' AND p.categoria='chaveiro';
