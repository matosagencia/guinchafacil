-- ===================================================================
-- MIGRAÇÃO: Conclusão manual assistida (salvaguarda p/ falha de GPS/servidor)
-- Data: 2026-07-17
-- Contexto: a máquina de estados hoje (PedidoTransitionService::validatePreconditions)
-- exige geofence + evidência atrelada a um ponto GPS válido para no_local,
-- em_reboque e concluido — SEM NENHUM fallback. Se o GPS do cliente/guincho
-- falhar, ou o servidor cair no meio do atendimento, o pedido fica preso
-- para sempre (nem admin conseguia forçar: pedidoAlterarStatus() chama
-- transition(), que aplica validatePreconditions() também para actorType
-- 'admin' — não há bypass). Esta migração cria a estrutura para uma
-- conclusão manual assistida: admin registra comprovantes (fotos) enviados
-- manualmente (por telefone/WhatsApp com o guincheiro, por ex.), com
-- justificativa obrigatória, e o pedido fica marcado para REVISÃO DE
-- AUDITORIA posterior — nunca conclusão manual "silenciosa" (ver pesquisa:
-- conclusão manual sem verificação é vetor clássico de fraude em apps de
-- entrega/reboque — https://www.incognia.com/blog/food-delivery-app-fraud-location-spoofing).
-- Idempotente: usa INFORMATION_SCHEMA antes de alterar.
-- ===================================================================

CREATE TABLE IF NOT EXISTS pedido_evidencias_manuais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT NOT NULL,
    admin_id INT NOT NULL,
    tipo ENUM('coleta','entrega') NOT NULL,
    justificativa VARCHAR(1000) NOT NULL,
    latitude_informada DECIMAL(10,8) NULL,
    longitude_informada DECIMAL(11,8) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pedido_tipo (pedido_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pedidos.concluido_manualmente — distingue conclusão via GPS/evidência
-- normal de conclusão forçada pelo admin por indisponibilidade técnica.
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'concluido_manualmente') = 0,
    'ALTER TABLE pedidos ADD COLUMN concluido_manualmente TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pedidos.revisao_manual_status — todo pedido concluído manualmente nasce
-- 'pendente' e só sai desse estado quando um admin (idealmente outro, não
-- quem concluiu) revisar os comprovantes e confirmar ou rejeitar.
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'revisao_manual_status') = 0,
    'ALTER TABLE pedidos ADD COLUMN revisao_manual_status ENUM(''pendente'',''confirmada'',''rejeitada'') NULL AFTER concluido_manualmente',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'concluido_manual_admin_id') = 0,
    'ALTER TABLE pedidos ADD COLUMN concluido_manual_admin_id INT NULL AFTER revisao_manual_status',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'concluido_manual_justificativa') = 0,
    'ALTER TABLE pedidos ADD COLUMN concluido_manual_justificativa VARCHAR(1000) NULL AFTER concluido_manual_admin_id',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'concluido_manual_em') = 0,
    'ALTER TABLE pedidos ADD COLUMN concluido_manual_em DATETIME NULL AFTER concluido_manual_justificativa',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'revisao_manual_admin_id') = 0,
    'ALTER TABLE pedidos ADD COLUMN revisao_manual_admin_id INT NULL AFTER concluido_manual_em',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'revisao_manual_em') = 0,
    'ALTER TABLE pedidos ADD COLUMN revisao_manual_em DATETIME NULL AFTER revisao_manual_admin_id',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'revisao_manual_nota') = 0,
    'ALTER TABLE pedidos ADD COLUMN revisao_manual_nota VARCHAR(500) NULL AFTER revisao_manual_em',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Configuração: tamanho mínimo da justificativa de conclusão manual, para
-- impedir "sem sinal, concluindo" (justificativa vazia/genérica demais).
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('conclusao_manual_justificativa_min_chars', '20', 'Tamanho mínimo (caracteres) exigido na justificativa de conclusão manual assistida de um pedido');
