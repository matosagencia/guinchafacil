-- migration_produtos_estoque_v1.sql
-- ROADMAP socorro automotivo — Etapa 8 (produtos e estoque, começando por bateria).
--
-- Camada aditiva. Complementa o flag `requires_parts` (service_types) e a fase
-- PARTS_SUPPLY/PARTS_FEE (ChargeCodes/ChargePolicyService), que existiam sem
-- catálogo real de produto por trás. MVP focado em bateria: um catálogo de
-- produtos, estoque por prestador e um livro-razão de movimentos idempotente.
--
-- Sem gatilho financeiro automático (mesma postura das Etapas 11/12/13): a
-- baixa de estoque é chamada explicitamente por EstoqueService; nada aqui
-- cria cobrança sozinho.
--
-- Tipos INT (regra de FK do projeto: guinchos.id/pedidos.id são INT).
-- Idempotente: CREATE TABLE IF NOT EXISTS + seeds com INSERT IGNORE.

-- ============================================================
-- 1) Catálogo de produtos (global)
-- ============================================================
CREATE TABLE IF NOT EXISTS `produtos` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `sku`               VARCHAR(40) NOT NULL,
    `nome`              VARCHAR(160) NOT NULL,
    `categoria`         VARCHAR(40) NOT NULL DEFAULT 'bateria' COMMENT 'bateria | pneu | fluido | ... (MVP: bateria)',
    `descricao`         VARCHAR(255) NULL,
    `especificacao`     VARCHAR(120) NULL COMMENT 'ex.: 60Ah, 12V',
    `preco_referencia`  DECIMAL(10,2) NULL COMMENT 'preço sugerido; o prestador pode sobrescrever no estoque',
    `unidade`           VARCHAR(12) NOT NULL DEFAULT 'un',
    `active`            TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_produto_sku` (`sku`),
    KEY `idx_produto_categoria` (`categoria`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2) Estoque por prestador (= guinchos.id)
-- ============================================================
CREATE TABLE IF NOT EXISTS `provider_produtos_estoque` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`       INT NOT NULL COMMENT '= guinchos.id',
    `produto_id`        INT NOT NULL,
    `quantidade`        INT NOT NULL DEFAULT 0,
    `preco_venda`       DECIMAL(10,2) NULL COMMENT 'sobrescreve preco_referencia do produto',
    `active`            TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_provider_produto` (`provider_id`, `produto_id`),
    KEY `idx_estoque_provider` (`provider_id`, `active`),
    CONSTRAINT `fk_estoque_produto` FOREIGN KEY (`produto_id`)
        REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_estoque_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3) Livro-razão de movimentos de estoque (idempotente por hash)
-- ============================================================
CREATE TABLE IF NOT EXISTS `estoque_movimentos` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`       INT NOT NULL,
    `produto_id`        INT NOT NULL,
    `pedido_id`         INT NULL,
    `tipo`              VARCHAR(20) NOT NULL COMMENT 'ENTRADA | SAIDA | AJUSTE | ESTORNO',
    `quantidade`        INT NOT NULL COMMENT 'delta aplicado (pode ser negativo em SAIDA)',
    `saldo_apos`        INT NOT NULL,
    `hash_idempotencia` VARCHAR(80) NULL,
    `descricao`         VARCHAR(255) NULL,
    `criado_em`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_estoque_mov_hash` (`hash_idempotencia`),
    KEY `idx_estoque_mov_provider` (`provider_id`, `produto_id`),
    KEY `idx_estoque_mov_pedido` (`pedido_id`),
    CONSTRAINT `fk_estoque_mov_produto` FOREIGN KEY (`produto_id`)
        REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_estoque_mov_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `guinchos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4) Seeds — catálogo inicial de baterias (comuns no RJ)
-- ============================================================
INSERT IGNORE INTO `produtos` (`sku`, `nome`, `categoria`, `descricao`, `especificacao`, `preco_referencia`, `unidade`) VALUES
('BAT-45AH', 'Bateria 45Ah',  'bateria', 'Bateria automotiva 45Ah para carros compactos.',       '45Ah 12V',  380.00, 'un'),
('BAT-50AH', 'Bateria 50Ah',  'bateria', 'Bateria automotiva 50Ah para carros de passeio.',       '50Ah 12V',  420.00, 'un'),
('BAT-60AH', 'Bateria 60Ah',  'bateria', 'Bateria automotiva 60Ah para sedans e SUVs leves.',     '60Ah 12V',  520.00, 'un'),
('BAT-70AH', 'Bateria 70Ah',  'bateria', 'Bateria automotiva 70Ah para SUVs e utilitários.',      '70Ah 12V',  640.00, 'un'),
('BAT-MOTO', 'Bateria Moto',  'bateria', 'Bateria para motocicletas (5Ah a 12Ah).',                '5-12Ah 12V',180.00, 'un');
