-- migration_add_tariff_configs.sql
-- Adiciona chaves de configuração para tarifas dinâmicas

INSERT INTO configuracoes (chave, valor, descricao, criado_em, atualizado_em)
VALUES
('tarifa_noturna_km', '5.50', 'Tarifa por KM (Turno Noturno)', NOW(), NOW()),
('tarifa_noturna_fixa', '15.00', 'Taxa fixa (Turno Noturno)', NOW(), NOW()),
('taxa_prioridade', '20.00', 'Taxa extra para pedidos prioritários', NOW(), NOW()),
('turno_noturno_inicio', '20:00', 'Início do turno noturno (HH:MM)', NOW(), NOW()),
('turno_noturno_fim', '06:00', 'Fim do turno noturno (HH:MM)', NOW(), NOW())
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
