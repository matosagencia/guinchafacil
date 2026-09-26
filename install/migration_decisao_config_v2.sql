-- Motor de decisao assistencia vs reboque.
-- Usa a chave existente taxa_fixa como base do reboque (nao cria duplicada).
-- Idempotente.

INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`)
VALUES
    ('custo_saida_profissional_padrao', '80.00',
     'Valor base que o cliente paga pela saida de um profissional de assistencia.'),
    ('comissao_assistencia_percentual', '0.21',
     'Percentual de comissao da plataforma sobre a saida da assistencia.')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Atualiza a base do reboque para R$ 150 somente se ainda estiver no valor
-- antigo (< R$ 100). Se o admin ja ajustou, nao sobrescreve.
UPDATE `configuracoes`
   SET `valor` = '150.00',
       `descricao` = 'Valor base do reboque (bandeirada). Somado a tarifa por km.'
 WHERE `chave` = 'taxa_fixa'
   AND CAST(`valor` AS DECIMAL(10,2)) < 100.00;
