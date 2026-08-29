-- migration_produtos_catalogo_socorro_v1.sql
-- ROADMAP socorro automotivo — catálogo de peças por categoria de serviço.
--
-- Amplia o catálogo de produtos (antes só baterias) para as peças/insumos
-- comumente requisitados em cada categoria de socorro, com preço MÉDIO de
-- referência (BRL, base de mercado) já cadastrado. E cria a associação
-- serviço → produtos como PRÉ-SELEÇÃO: quando o prestador monta o orçamento
-- de um serviço, a plataforma já sugere as peças pertinentes.
--
-- Decisão de produto (conversa com o usuário): a peça não precisa ser
-- estocada nem vendida obrigatoriamente pela plataforma. Esta pré-seleção
-- serve para (1) transparência de preço antes do serviço, (2) orçamento
-- aprovado pelo cliente in-app (anti-abuso/anti-vazamento), (3) matching por
-- compatibilidade. O preço aqui é REFERÊNCIA; o prestador pode ajustar no
-- estoque dele (provider_produtos_estoque.preco_venda).
--
-- Idempotente: INSERT IGNORE (sku/uk únicos). Tipos INT nas FKs.

-- ============================================================
-- 1) Novos produtos (peças) com preço médio de referência
-- ============================================================
INSERT IGNORE INTO `produtos` (`sku`, `nome`, `categoria`, `descricao`, `especificacao`, `preco_referencia`, `unidade`) VALUES
-- Baterias (complementa as já existentes)
('BAT-40AH',  'Bateria 40Ah',  'bateria',     'Bateria automotiva 40Ah para carros populares/urbanos.',   '40Ah 12V',    340.00, 'un'),
('BAT-100AH', 'Bateria 100Ah', 'bateria',     'Bateria 100Ah para utilitários, vans e veículos a diesel.', '100Ah 12V',  950.00, 'un'),
-- Elétrica / consumíveis
('FUSIVEL-KIT',     'Kit de Fusíveis',     'eletrica', 'Jogo de fusíveis automotivos variados para reposição.', 'sortido',   25.00, 'un'),
('TERMINAL-BATERIA','Terminal de Bateria', 'eletrica', 'Terminal/polo de bateria para reposição.',              'par',       30.00, 'un'),
-- Pneu
('PNEU-ARO13',      'Pneu Aro 13',         'pneu', 'Pneu novo aro 13 para carros populares.',       'aro 13',     290.00, 'un'),
('PNEU-ARO14',      'Pneu Aro 14',         'pneu', 'Pneu novo aro 14.',                              'aro 14',     340.00, 'un'),
('PNEU-ARO15',      'Pneu Aro 15',         'pneu', 'Pneu novo aro 15 para sedãs e SUVs leves.',      'aro 15',     420.00, 'un'),
('PNEU-MOTO',       'Pneu de Moto',        'pneu', 'Pneu para motocicleta.',                         'diverso',    260.00, 'un'),
('KIT-REPARO-PNEU', 'Kit Reparo de Pneu',  'pneu', 'Kit de reparo emergencial de furo (macarrão/selante).', 'kit',  55.00, 'un'),
('VALVULA-PNEU',    'Válvula de Pneu',     'pneu', 'Válvula (bico) de pneu para reposição.',         'un',          12.00, 'un'),
-- Combustível (pane seca) — preço por litro
('GASOLINA-L', 'Gasolina Comum',   'combustivel', 'Gasolina comum para pane seca (por litro).', 'litro', 6.20, 'L'),
('ETANOL-L',   'Etanol',           'combustivel', 'Etanol para pane seca (por litro).',          'litro', 4.50, 'L'),
('DIESEL-L',   'Diesel S10',       'combustivel', 'Diesel S10 para pane seca (por litro).',      'litro', 6.30, 'L'),
-- Chaveiro
('CHAVE-SIMPLES',     'Chave Simples (cópia)',        'chaveiro', 'Cópia de chave sem eletrônica.',              'un',  90.00, 'un'),
('CHAVE-TRANSPONDER', 'Chave Codificada (transponder)','chaveiro', 'Chave com transponder codificada.',           'un', 280.00, 'un'),
('CHAVE-CANIVETE',    'Chave Canivete',               'chaveiro', 'Chave canivete com telecomando.',             'un', 450.00, 'un'),
('CONTROLE-ALARME',   'Controle/Telecomando',         'chaveiro', 'Controle de alarme/telecomando.',             'un', 320.00, 'un'),
-- Fluidos / mecânico
('OLEO-MOTOR-1L',   'Óleo de Motor (1L)',     'fluido', 'Óleo lubrificante de motor, 1 litro.',            '1L',    48.00, 'un'),
('ADITIVO-RADIADOR','Aditivo de Radiador (1L)','fluido', 'Aditivo de arrefecimento, 1 litro.',              '1L',    35.00, 'un'),
('FLUIDO-FREIO',    'Fluido de Freio (500ml)', 'fluido', 'Fluido de freio DOT, 500ml.',                     '500ml', 28.00, 'un');

-- ============================================================
-- 2) Associação serviço → produtos (pré-seleção do orçamento)
-- ============================================================
CREATE TABLE IF NOT EXISTS `service_type_produtos` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `service_type_id` INT NOT NULL,
    `produto_id`      INT NOT NULL,
    `sugerido`        TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = aparece pré-selecionado no orçamento',
    `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_stp` (`service_type_id`, `produto_id`),
    KEY `idx_stp_servico` (`service_type_id`),
    CONSTRAINT `fk_stp_service` FOREIGN KEY (`service_type_id`)
        REFERENCES `service_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_stp_produto` FOREIGN KEY (`produto_id`)
        REFERENCES `produtos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Elétrica (partida auxiliar, teste/troca de bateria, diagnóstico) → baterias + elétrica
INSERT IGNORE INTO `service_type_produtos` (`service_type_id`, `produto_id`)
SELECT st.id, p.id
FROM `service_types` st
JOIN `service_categories` c ON c.id = st.category_id AND c.code = 'ELECTRICAL_ASSISTANCE'
JOIN `produtos` p ON p.categoria IN ('bateria', 'eletrica') AND p.active = 1;

-- Pneu → pneus, kit reparo, válvula
INSERT IGNORE INTO `service_type_produtos` (`service_type_id`, `produto_id`)
SELECT st.id, p.id
FROM `service_types` st
JOIN `service_categories` c ON c.id = st.category_id AND c.code = 'TIRE_ASSISTANCE'
JOIN `produtos` p ON p.categoria = 'pneu' AND p.active = 1;

-- Combustível → gasolina/etanol/diesel
INSERT IGNORE INTO `service_type_produtos` (`service_type_id`, `produto_id`)
SELECT st.id, p.id
FROM `service_types` st
JOIN `service_categories` c ON c.id = st.category_id AND c.code = 'FUEL_ASSISTANCE'
JOIN `produtos` p ON p.categoria = 'combustivel' AND p.active = 1;

-- Chaveiro → chaves/controle
INSERT IGNORE INTO `service_type_produtos` (`service_type_id`, `produto_id`)
SELECT st.id, p.id
FROM `service_types` st
JOIN `service_categories` c ON c.id = st.category_id AND c.code = 'LOCKSMITH'
JOIN `produtos` p ON p.categoria = 'chaveiro' AND p.active = 1;

-- Socorro mecânico → fluidos + elétrica de consumo
INSERT IGNORE INTO `service_type_produtos` (`service_type_id`, `produto_id`)
SELECT st.id, p.id
FROM `service_types` st
JOIN `service_categories` c ON c.id = st.category_id AND c.code = 'ROADSIDE_ASSISTANCE'
JOIN `produtos` p ON p.categoria IN ('fluido', 'eletrica') AND p.active = 1;
