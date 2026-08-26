CREATE DATABASE IF NOT EXISTS guinchafacil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE guinchafacil;

-- Tabela de usuários (base para todos os tipos)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    tipo ENUM('admin','guincho','cliente') NOT NULL,
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_login DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_cpf (cpf),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
);

-- Tabela de endereços dos usuários
CREATE TABLE enderecos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cep VARCHAR(10) NOT NULL,
    logradouro VARCHAR(200) NOT NULL,
    numero VARCHAR(20) NOT NULL,
    complemento VARCHAR(100),
    bairro VARCHAR(100) NOT NULL,
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(2) NOT NULL,
    lat DECIMAL(10,8),
    lng DECIMAL(11,8),
    principal BOOLEAN DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_principal (principal),
    INDEX idx_coordenadas (lat, lng)
);

-- Tabela de veículos dos clientes
CREATE TABLE veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    placa VARCHAR(8) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    ano INT NOT NULL,
    cor VARCHAR(30) NOT NULL,
    tipo ENUM('carro','moto','caminhao','van') NOT NULL,
    ativo BOOLEAN DEFAULT 1,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_placa (placa),
    INDEX idx_ativo (ativo)
);

-- Tabela de oficinas favoritas dos clientes
CREATE TABLE oficinas_favoritas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    endereco VARCHAR(300) NOT NULL,
    lat DECIMAL(10,8),
    lng DECIMAL(11,8),
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_coordenadas (lat, lng)
);

-- Tabela específica para dados dos guinchos
CREATE TABLE guinchos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cnh_numero VARCHAR(20) NOT NULL,
    cnh_validade DATE NOT NULL,
    placa_guincho VARCHAR(8) NOT NULL,
    capacidade_ton DECIMAL(5,2) NOT NULL,
    raio_cobertura_km INT NOT NULL DEFAULT 20,
    chave_pix VARCHAR(100) NOT NULL,
    chave_pix_tipo ENUM('cpf','email','telefone','aleatoria') NOT NULL,
    lat_atual DECIMAL(10,8),
    lng_atual DECIMAL(11,8),
    disponivel BOOLEAN DEFAULT 1,
    aprovado BOOLEAN DEFAULT 0,
    foto_veiculo VARCHAR(255),
    doc_cnh_frente VARCHAR(255),
    doc_cnh_verso VARCHAR(255),
    reputacao DECIMAL(3,2) DEFAULT 0.00,
    total_avaliacoes INT DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_disponivel (disponivel),
    INDEX idx_aprovado (aprovado),
    INDEX idx_coordenadas (lat_atual, lng_atual),
    INDEX idx_reputacao (reputacao)
);

-- Tabela de pedidos de guincho
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    guincho_id INT NULL,
    tipo_problema ENUM('eletrica','pneu','colisao','bateria','combustivel','outro') NOT NULL,
    descricao_problema TEXT,
    lat_origem DECIMAL(10,8) NOT NULL,
    lng_origem DECIMAL(11,8) NOT NULL,
    endereco_origem VARCHAR(500) NOT NULL,
    lat_destino DECIMAL(10,8) NOT NULL,
    lng_destino DECIMAL(11,8) NOT NULL,
    endereco_destino VARCHAR(500) NOT NULL,
    distancia_km DECIMAL(8,2) NOT NULL,
    custo_estimado DECIMAL(10,2) NOT NULL,
    custo_final DECIMAL(10,2) NULL,
    status ENUM('aguardando_pagamento','aguardando_guincho','a_caminho','no_local','em_reboque','concluido','cancelado') DEFAULT 'aguardando_pagamento',
    raio_atual_km INT DEFAULT 10,
    score_minimo_atual DECIMAL(5,4) DEFAULT 0.5000,
    expiracao_aceite DATETIME NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (guincho_id) REFERENCES guinchos(id) ON DELETE SET NULL,
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_guincho_id (guincho_id),
    INDEX idx_status (status),
    INDEX idx_criado_em (criado_em),
    INDEX idx_coordenadas_origem (lat_origem, lng_origem),
    INDEX idx_expiracao (expiracao_aceite)
);

-- Tabela de pagamentos
CREATE TABLE pagamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    metodo ENUM('mercadopago','pagseguro') NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    valor_guincho DECIMAL(10,2) NOT NULL,
    valor_plataforma DECIMAL(10,2) NOT NULL,
    status ENUM('pendente','aprovado','recusado','estornado') DEFAULT 'pendente',
    id_externo VARCHAR(100),
    pago_guincho BOOLEAN DEFAULT 0,
    data_pagamento DATETIME NULL,
    data_pagamento_guincho DATETIME NULL,
    webhook_payload TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    INDEX idx_pedido_id (pedido_id),
    INDEX idx_status (status),
    INDEX idx_id_externo (id_externo)
);

-- Tabela de avaliações
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    cliente_id INT NOT NULL,
    guincho_id INT NOT NULL,
    estrelas TINYINT NOT NULL CHECK (estrelas >= 1 AND estrelas <= 5),
    comentario TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (guincho_id) REFERENCES guinchos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_avaliacao (pedido_id),
    INDEX idx_guincho_id (guincho_id),
    INDEX idx_estrelas (estrelas)
);

-- Tabela de mensagens do chat
CREATE TABLE chat_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_pedido_id (pedido_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_lida (lida),
    INDEX idx_criado_em (criado_em)
);

-- Tabela de configurações do sistema
CREATE TABLE configuracoes (
    chave VARCHAR(100) PRIMARY KEY,
    valor TEXT NOT NULL,
    descricao VARCHAR(255),
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de rate limiting
CREATE TABLE rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    rota VARCHAR(100) NOT NULL,
    tentativas INT DEFAULT 1,
    primeira_tentativa DATETIME DEFAULT CURRENT_TIMESTAMP,
    bloqueado_ate DATETIME NULL,
    INDEX idx_ip_rota (ip, rota),
    INDEX idx_bloqueado_ate (bloqueado_ate)
);

-- Tabela de logs de webhooks
CREATE TABLE logs_webhook (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fonte VARCHAR(50) NOT NULL,
    evento VARCHAR(100) NOT NULL,
    payload TEXT,
    status_http INT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fonte (fonte),
    INDEX idx_evento (evento),
    INDEX idx_criado_em (criado_em)
);

-- Inserir configurações padrão do sistema
INSERT INTO configuracoes (chave, valor, descricao) VALUES
('tarifa_por_km', '3.50', 'Valor cobrado por quilômetro rodado'),
('taxa_fixa', '15.00', 'Taxa fixa do serviço de guincho'),
('comissao_percentual', '20', 'Percentual de comissão da plataforma'),
('tempo_expiracao_min', '5', 'Tempo em minutos para expirar aceite do guincho'),
('raio_inicial_km', '10', 'Raio inicial de busca por guinchos em km'),
('raio_maximo_km', '50', 'Raio máximo de busca por guinchos em km'),
('mercadopago_access_token', '', 'Token de acesso do Mercado Pago'),
('mercadopago_public_key', '', 'Chave pública do Mercado Pago'),
('pagseguro_token', '', 'Token do PagSeguro'),
('pagseguro_email', '', 'Email da conta PagSeguro');


-- Correcao: adiciona colunas faltantes na rate_limit
ALTER TABLE rate_limit ADD COLUMN IF NOT EXISTS criado_em DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE rate_limit ADD COLUMN IF NOT EXISTS atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Tabela de recuperacao de senha
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expira_em DATETIME NOT NULL,
    usado BOOLEAN DEFAULT 0,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expira (expira_em)
);

