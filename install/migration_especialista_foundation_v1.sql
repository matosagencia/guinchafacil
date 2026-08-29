-- Especialistas: fundacao aditiva do dominio de incidentes automotivos.
-- Requer MySQL 8+/MariaDB com InnoDB. Aplicar uma unica vez pelo executor de migrations.

SET @usuario_tipo = (SELECT column_type FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='usuarios' AND column_name='tipo');
SET @usuario_tipo_sql = IF(
    @usuario_tipo NOT LIKE '%especialista%',
    "ALTER TABLE usuarios MODIFY tipo ENUM('admin','guincho','cliente','funcionario','gerente','especialista') NOT NULL",
    'SELECT 1'
);
PREPARE usuario_tipo_stmt FROM @usuario_tipo_sql;
EXECUTE usuario_tipo_stmt;
DEALLOCATE PREPARE usuario_tipo_stmt;

CREATE TABLE IF NOT EXISTS incidentes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    tipo_problema VARCHAR(50) NOT NULL,
    descricao_problema TEXT NULL,
    lat_origem DECIMAL(10,8) NOT NULL,
    lng_origem DECIMAL(11,8) NOT NULL,
    endereco_origem VARCHAR(500) NOT NULL,
    status ENUM('aberto','procurando_especialista','especialista_designado','em_atendimento','resolvido_local','necessita_reboque','procurando_guincho','em_reboque','concluido','cancelado') NOT NULL DEFAULT 'aberto',
    resolucao_tipo ENUM('local','reboque','cancelamento') NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_incidente_status (status),
    KEY idx_incidente_cliente (cliente_id),
    KEY idx_incidente_criado (criado_em),
    CONSTRAINT fk_incidente_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    CONSTRAINT fk_incidente_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS especialistas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    nome_profissional VARCHAR(150) NULL,
    cpf_cnpj VARCHAR(18) NOT NULL,
    documento_tipo VARCHAR(30) NOT NULL,
    documento_numero VARCHAR(50) NULL,
    documento_arquivo VARCHAR(255) NULL,
    selfie_arquivo VARCHAR(255) NULL,
    chave_pix VARCHAR(150) NOT NULL,
    chave_pix_tipo ENUM('cpf','cnpj','email','telefone','aleatoria') NOT NULL,
    bio TEXT NULL,
    aprovado TINYINT(1) NOT NULL DEFAULT 0,
    disponivel TINYINT(1) NOT NULL DEFAULT 0,
    lat_atual DECIMAL(10,8) NULL,
    lng_atual DECIMAL(11,8) NULL,
    raio_atendimento_km DECIMAL(6,2) NOT NULL DEFAULT 10,
    reputacao DECIMAL(3,2) NOT NULL DEFAULT 0,
    total_avaliacoes INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_especialista_usuario (usuario_id),
    CONSTRAINT fk_especialista_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS especialista_documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    especialista_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    numero VARCHAR(100) NULL,
    arquivo VARCHAR(255) NULL,
    status ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
    observacao_admin TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_especialista_documentos_status (status),
    CONSTRAINT fk_especialista_documentos FOREIGN KEY (especialista_id) REFERENCES especialistas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicos_especialista (
    id INT NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    categoria ENUM('bateria','eletrica','pneu','combustivel','chaveiro') NOT NULL,
    tipo_cobranca ENUM('fixo','adicional','orcamento') NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uk_servico_especialista_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO servicos_especialista (codigo,nome,categoria,tipo_cobranca) VALUES
('BATTERY_DIAG','Avaliacao de bateria','bateria','fixo'),
('BATTERY_JUMP','Partida auxiliar','bateria','adicional'),
('BATTERY_REPLACE','Troca de bateria','bateria','orcamento'),
('TIRE_CHANGE','Troca pelo estepe','pneu','adicional'),
('FUEL_DELIVERY','Entrega emergencial de combustivel','combustivel','adicional'),
('LOCKOUT','Destravamento simples','chaveiro','adicional'),
('ELECTRICAL_DIAG','Avaliacao eletrica inicial','eletrica','fixo');

CREATE TABLE IF NOT EXISTS especialista_servicos (
    especialista_id BIGINT UNSIGNED NOT NULL,
    servico_id INT NOT NULL,
    habilitado TINYINT(1) NOT NULL DEFAULT 1,
    preco_pretendido DECIMAL(10,2) NULL,
    PRIMARY KEY (especialista_id, servico_id),
    CONSTRAINT fk_es_servico_especialista FOREIGN KEY (especialista_id) REFERENCES especialistas(id) ON DELETE CASCADE,
    CONSTRAINT fk_es_servico_catalogo FOREIGN KEY (servico_id) REFERENCES servicos_especialista(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atendimentos_especialista (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incidente_id BIGINT UNSIGNED NOT NULL,
    especialista_id BIGINT UNSIGNED NULL,
    servico_solicitado_id INT NOT NULL,
    status ENUM('aguardando_pagamento','procurando','ofertado','aceito','a_caminho','no_local','em_diagnostico','aguardando_aprovacao','em_execucao','resolvido','necessita_reboque','cancelado') NOT NULL,
    provider_amount DECIMAL(10,2) NOT NULL,
    platform_amount DECIMAL(10,2) NOT NULL,
    customer_amount DECIMAL(10,2) NOT NULL,
    aceito_em DATETIME NULL,
    chegou_em DATETIME NULL,
    iniciado_em DATETIME NULL,
    concluido_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_atendimento_especialista_status (status),
    CONSTRAINT fk_atendimento_incidente FOREIGN KEY (incidente_id) REFERENCES incidentes(id),
    CONSTRAINT fk_atendimento_especialista FOREIGN KEY (especialista_id) REFERENCES especialistas(id),
    CONSTRAINT fk_atendimento_servico FOREIGN KEY (servico_solicitado_id) REFERENCES servicos_especialista(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atendimento_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('servico','peca','material') NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    status ENUM('proposto','aprovado','recusado','executado') NOT NULL DEFAULT 'proposto',
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    aprovado_em DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_atendimento_item FOREIGN KEY (atendimento_id) REFERENCES atendimentos_especialista(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS atendimento_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incidente_id BIGINT UNSIGNED NOT NULL,
    atendimento_tipo ENUM('especialista','guincho') NOT NULL,
    atendimento_id BIGINT UNSIGNED NOT NULL,
    evento VARCHAR(50) NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    accuracy_m DECIMAL(8,2) NULL,
    metadata_json JSON NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_atendimento_evento (atendimento_tipo, atendimento_id, criado_em),
    CONSTRAINT fk_evento_incidente FOREIGN KEY (incidente_id) REFERENCES incidentes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financeiro_lancamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    incidente_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('cobranca_cliente','repasse_especialista','repasse_guincho','taxa_plataforma','estorno','ajuste') NOT NULL,
    referencia_tipo VARCHAR(50) NOT NULL,
    referencia_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    status ENUM('pendente','processando','confirmado','falhou','estornado') NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_financeiro_incidente (incidente_id, criado_em),
    CONSTRAINT fk_financeiro_incidente FOREIGN KEY (incidente_id) REFERENCES incidentes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @pedido_incidente_sql = IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='pedidos' AND column_name='incidente_id') = 0,
    'ALTER TABLE pedidos ADD COLUMN incidente_id BIGINT UNSIGNED NULL, ADD KEY idx_pedido_incidente (incidente_id), ADD CONSTRAINT fk_pedido_incidente FOREIGN KEY (incidente_id) REFERENCES incidentes(id)',
    'SELECT 1'
);
PREPARE pedido_incidente_stmt FROM @pedido_incidente_sql;
EXECUTE pedido_incidente_stmt;
DEALLOCATE PREPARE pedido_incidente_stmt;
