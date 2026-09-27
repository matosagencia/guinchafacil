-- Seed QA: fluxo Diego (assistencia vs reboque) — Gamboa / RJ.
-- Rodar no phpMyAdmin (XAMPP) apos as migrations de providers + workshop.
-- Idempotente: reexecutar nao duplica as oficinas de teste.

INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`)
VALUES
    ('habilitar_comparativo_assistencia', '1',
     '1 = mostra comparativo assistencia/reboque quando ha oficina no raio; 0 = so reboque.'),
    ('custo_saida_profissional_padrao', '80.00',
     'Valor base que o cliente paga pela saida de um profissional de assistencia.'),
    ('comissao_assistencia_percentual', '0.21',
     'Percentual de comissao da plataforma sobre a saida da assistencia.'),
    ('desconto_saida_oficina_percentual', '0.21',
     'Desconto aplicado ao converter assistencia em reboque.'),
    ('taxa_fixa', '150.00',
     'Valor base do reboque (bandeirada). Somado a tarifa por km.'),
    ('tarifa_por_km', '3.50',
     'Tarifa por km do reboque.')
ON DUPLICATE KEY UPDATE
    `valor` = VALUES(`valor`),
    `descricao` = VALUES(`descricao`);

-- Oficina Teste 1: Barão Car (Rua da Gamboa, 247)
INSERT INTO `providers`
    (`provider_type`, `legal_name`, `trade_name`, `document_type`, `document_number`,
     `approval_status`, `payment_recipient_type`, `active`, `created_at`, `updated_at`)
SELECT
    'WORKSHOP', 'Barao Car Oficina LTDA', 'Barao Car', 'CNPJ', '00000000000191',
    'APPROVED', 'INDIVIDUAL', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `providers` WHERE `document_number` = '00000000000191'
);

INSERT INTO `provider_workshop_settings`
    (`provider_id`, `taxa_indicacao_fixa`, `taxa_resgate_direto`, `permite_resgate_direto`,
     `recebe_veiculo_patio`, `faz_resgate_direto`, `regra_versao`, `status_parceria`,
     `raio_checkin_m`, `raio_resgate_direto_km`, `address`, `latitude`, `longitude`,
     `created_at`, `updated_at`)
SELECT
    p.id, 30.00, 80.00, 1, 1, 1, 'diego-v1', 'ATIVO',
    150, 10.00, 'Rua da Gamboa, 247', -22.89700000, -43.18700000,
    NOW(), NOW()
FROM `providers` p
LEFT JOIN `provider_workshop_settings` ws ON ws.provider_id = p.id
WHERE p.document_number = '00000000000191'
  AND ws.provider_id IS NULL;

UPDATE `provider_workshop_settings` ws
JOIN `providers` p ON p.id = ws.provider_id
SET
    ws.taxa_resgate_direto = 80.00,
    ws.permite_resgate_direto = 1,
    ws.faz_resgate_direto = 1,
    ws.recebe_veiculo_patio = 1,
    ws.status_parceria = 'ATIVO',
    ws.raio_resgate_direto_km = 10.00,
    ws.address = 'Rua da Gamboa, 247',
    ws.latitude = -22.89700000,
    ws.longitude = -43.18700000,
    ws.updated_at = NOW(),
    p.active = 1,
    p.approval_status = 'APPROVED',
    p.trade_name = 'Barao Car',
    p.updated_at = NOW()
WHERE p.document_number = '00000000000191';

-- Oficina Teste 2: Oficina Gamboa 199
INSERT INTO `providers`
    (`provider_type`, `legal_name`, `trade_name`, `document_type`, `document_number`,
     `approval_status`, `payment_recipient_type`, `active`, `created_at`, `updated_at`)
SELECT
    'WORKSHOP', 'Oficina Gamboa 199 LTDA', 'Oficina Gamboa 199', 'CNPJ', '00000000000272',
    'APPROVED', 'INDIVIDUAL', 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `providers` WHERE `document_number` = '00000000000272'
);

INSERT INTO `provider_workshop_settings`
    (`provider_id`, `taxa_indicacao_fixa`, `taxa_resgate_direto`, `permite_resgate_direto`,
     `recebe_veiculo_patio`, `faz_resgate_direto`, `regra_versao`, `status_parceria`,
     `raio_checkin_m`, `raio_resgate_direto_km`, `address`, `latitude`, `longitude`,
     `created_at`, `updated_at`)
SELECT
    p.id, 30.00, 80.00, 1, 1, 1, 'diego-v1', 'ATIVO',
    150, 10.00, 'Rua da Gamboa, 199', -22.89600000, -43.18600000,
    NOW(), NOW()
FROM `providers` p
LEFT JOIN `provider_workshop_settings` ws ON ws.provider_id = p.id
WHERE p.document_number = '00000000000272'
  AND ws.provider_id IS NULL;

UPDATE `provider_workshop_settings` ws
JOIN `providers` p ON p.id = ws.provider_id
SET
    ws.taxa_resgate_direto = 80.00,
    ws.permite_resgate_direto = 1,
    ws.faz_resgate_direto = 1,
    ws.recebe_veiculo_patio = 1,
    ws.status_parceria = 'ATIVO',
    ws.raio_resgate_direto_km = 10.00,
    ws.address = 'Rua da Gamboa, 199',
    ws.latitude = -22.89600000,
    ws.longitude = -43.18600000,
    ws.updated_at = NOW(),
    p.active = 1,
    p.approval_status = 'APPROVED',
    p.trade_name = 'Oficina Gamboa 199',
    p.updated_at = NOW()
WHERE p.document_number = '00000000000272';

-- Cenario B (sem oficina): desative com
-- UPDATE providers SET active = 0 WHERE document_number IN ('00000000000191','00000000000272');
