-- Flag do comparativo assistencia vs reboque (fluxo Diego).
-- Idempotente.

INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`)
VALUES
    ('habilitar_comparativo_assistencia', '1',
     '1 = mostra comparativo assistencia/reboque quando ha oficina no raio; 0 = so reboque.')
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
