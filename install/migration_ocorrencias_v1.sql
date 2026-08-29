-- ===================================================================
-- MIGRAÇÃO: Ocorrências operacionais (pedido_ocorrencias)
-- Data: 2026-08-01
-- Contexto: item "Ocorrências" da nav reorganizada da Central Operacional
-- (Pacote L2.3) estava marcado "em breve" — não existia NENHUM registro
-- estruturado de incidente de atendimento (avaria, atraso, conduta,
-- segurança etc.), só os alertas 100% derivados/computados do
-- AdminAlertService (sem input humano) e a tabela `demandas` (workflow de
-- aprovação gerencial, não incidente de campo). Esta migração cria a
-- tabela dedicada, seguindo o mesmo padrão de idempotência (INFORMATION_
-- SCHEMA antes de criar/alterar) usado em migration_funcionario_gerente_v1.sql
-- e migration_cancelamento.sql.
-- ===================================================================

CREATE TABLE IF NOT EXISTS pedido_ocorrencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    pedido_id INT NOT NULL,

    tipo ENUM('avaria','atraso','conduta','veiculo','seguranca','outro') NOT NULL DEFAULT 'outro',
    severidade ENUM('baixa','media','alta','critica') NOT NULL DEFAULT 'media',
    status ENUM('aberta','em_analise','resolvida','arquivada') NOT NULL DEFAULT 'aberta',

    relator_tipo ENUM('admin','guincho','cliente','sistema') NOT NULL DEFAULT 'admin',
    relator_id INT NULL,

    descricao VARCHAR(2000) NOT NULL,

    resolucao VARCHAR(2000) NULL,
    resolvido_por INT NULL,
    resolvido_em DATETIME NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_ocorrencia_pedido (pedido_id),
    KEY idx_ocorrencia_status (status),
    KEY idx_ocorrencia_severidade (severidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FK (idempotente: só cria se ainda não existir)
SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido_ocorrencias' AND CONSTRAINT_NAME = 'fk_ocorrencia_pedido');
SET @query = IF(@fk = 0, 'ALTER TABLE pedido_ocorrencias ADD CONSTRAINT fk_ocorrencia_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido_ocorrencias' AND CONSTRAINT_NAME = 'fk_ocorrencia_relator');
SET @query = IF(@fk = 0, 'ALTER TABLE pedido_ocorrencias ADD CONSTRAINT fk_ocorrencia_relator FOREIGN KEY (relator_id) REFERENCES usuarios(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido_ocorrencias' AND CONSTRAINT_NAME = 'fk_ocorrencia_resolvido_por');
SET @query = IF(@fk = 0, 'ALTER TABLE pedido_ocorrencias ADD CONSTRAINT fk_ocorrencia_resolvido_por FOREIGN KEY (resolvido_por) REFERENCES usuarios(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;
