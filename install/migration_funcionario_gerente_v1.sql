-- ===================================================================
-- MIGRAÇÃO: Papéis "funcionário" e "gerente" + fluxo de demandas
-- Data: 2026-07-18
-- Contexto: até aqui só existiam 3 papéis (admin/guincho/cliente). O time de
-- atendimento (financeiro e operacional, cliente e guincheiro) precisa de um
-- papel "funcionario" com acesso restrito, que NUNCA executa ações sensíveis
-- diretamente — só cria uma "demanda" pendente. Um papel "gerente" (separado
-- de admin, que segue sendo o super-usuário técnico) é quem aprova ou
-- rejeita. Separação de deveres é o núcleo desta migração: um funcionário
-- malicioso sozinho não consegue causar prejuízo (não tem poder de
-- execução), e um gerente malicioso sozinho também não consegue em valores
-- altos (dupla aprovação obrigatória acima do limiar configurado).
-- Idempotente: usa INFORMATION_SCHEMA antes de alterar/criar.
-- ===================================================================

-- usuarios.tipo — adiciona 'funcionario' e 'gerente' ao ENUM existente,
-- preservando os valores atuais (admin/guincho/cliente).
SET @tipo_atual = (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipo'
);
SET @query = IF(
    @tipo_atual NOT LIKE '%funcionario%',
    "ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('admin','guincho','cliente','funcionario','gerente') NOT NULL",
    'SELECT 1'
);
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tabela `demandas`: toda ação sensível que um funcionário "pede" e um
-- gerente "decide". Nunca é o funcionário quem executa — a execução real só
-- acontece dentro de DemandaService::aprovar(), depois de decisão do
-- gerente (ou dos dois gerentes, se valor_envolvido exigir dupla aprovação).
CREATE TABLE IF NOT EXISTS demandas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('cancelamento','conclusao_manual','pagamento','alteracao_dados','reembolso') NOT NULL,
    status ENUM('pendente','aprovada_parcial','aprovada','rejeitada','executada','falhou') NOT NULL DEFAULT 'pendente',

    solicitante_id INT NOT NULL,
    pedido_id INT NULL,
    guincho_id INT NULL,
    payment_job_id INT NULL,

    valor_envolvido DECIMAL(10,2) NULL,
    requer_dupla_aprovacao TINYINT(1) NOT NULL DEFAULT 0,

    justificativa VARCHAR(1000) NOT NULL,
    payload_json TEXT NULL,

    gerente_id INT NULL,
    decidido_em DATETIME NULL,
    nota_gerente VARCHAR(500) NULL,

    segundo_gerente_id INT NULL,
    segundo_decidido_em DATETIME NULL,
    segunda_nota VARCHAR(500) NULL,

    executado_em DATETIME NULL,
    erro_execucao VARCHAR(1000) NULL,

    hash_idempotencia CHAR(64) NULL,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_status_tipo (status, tipo),
    KEY idx_solicitante (solicitante_id),
    KEY idx_gerente (gerente_id),
    KEY idx_pedido (pedido_id),
    UNIQUE KEY uk_demanda_idempotencia (hash_idempotencia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- FKs (idempotentes: só cria se ainda não existir)
SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demandas' AND CONSTRAINT_NAME = 'fk_demandas_solicitante');
SET @query = IF(@fk = 0, 'ALTER TABLE demandas ADD CONSTRAINT fk_demandas_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuarios(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demandas' AND CONSTRAINT_NAME = 'fk_demandas_pedido');
SET @query = IF(@fk = 0, 'ALTER TABLE demandas ADD CONSTRAINT fk_demandas_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demandas' AND CONSTRAINT_NAME = 'fk_demandas_gerente');
SET @query = IF(@fk = 0, 'ALTER TABLE demandas ADD CONSTRAINT fk_demandas_gerente FOREIGN KEY (gerente_id) REFERENCES usuarios(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'demandas' AND CONSTRAINT_NAME = 'fk_demandas_segundo_gerente');
SET @query = IF(@fk = 0, 'ALTER TABLE demandas ADD CONSTRAINT fk_demandas_segundo_gerente FOREIGN KEY (segundo_gerente_id) REFERENCES usuarios(id)', 'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pedidos.observacao_interna — único campo "livre" que a demanda do tipo
-- alteracao_dados pode tocar sem envolver dinheiro (nota de atendimento).
SET @query = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'observacao_interna') = 0,
    'ALTER TABLE pedidos ADD COLUMN observacao_interna VARCHAR(1000) NULL',
    'SELECT 1');
PREPARE stmt FROM @query; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Configurações do fluxo de demandas (governáveis pelo admin depois, via
-- tela de configurações — mesmo padrão de conclusao_manual_justificativa_min_chars).
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES
('demanda_justificativa_min_chars', '20', 'Tamanho mínimo (caracteres) da justificativa exigida do funcionário ao criar uma demanda'),
('demanda_valor_dupla_aprovacao', '500.00', 'Valor (R$) a partir do qual uma demanda de pagamento/reembolso exige aprovação de DOIS gerentes distintos antes de ser executada'),
('demanda_nota_gerente_min_chars', '10', 'Tamanho mínimo (caracteres) da nota exigida do gerente ao aprovar OU rejeitar uma demanda — decisão sem justificativa não é permitida');
