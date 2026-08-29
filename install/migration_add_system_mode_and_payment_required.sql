-- migration_add_system_mode_and_payment_required.sql
-- Adiciona configurações de sistema para modo 'freeflow'

-- Configuração 'system_mode'
INSERT INTO configuracoes (chave, valor, descricao, criado_em, atualizado_em)
SELECT 'system_mode', 'production', 'Modo de operação do sistema (production, sandbox, freeflow)', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracoes WHERE chave = 'system_mode');

-- Configuração 'payment_required'
INSERT INTO configuracoes (chave, valor, descricao, criado_em, atualizado_em)
SELECT 'payment_required', '1', 'Exigir pagamento antecipado (1=Sim, 0=Não)', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM configuracoes WHERE chave = 'payment_required');
