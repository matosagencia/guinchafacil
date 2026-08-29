-- Governança de preços dos especialistas (aditiva e idempotente).
-- A plataforma é a única fonte de preço; o especialista não pode publicar
-- ou alterar valores próprios. O campo legado é neutralizado para não ser
-- usado por versões antigas do painel.
UPDATE especialista_servicos
   SET preco_pretendido = NULL
 WHERE preco_pretendido IS NOT NULL;

-- Peças/materiais continuam apenas como referência administrativa. A cobrança
-- livre pelo especialista é bloqueada no serviço de atendimento até existir
-- checkout de catálogo central com aprovação e pagamento do cliente.
UPDATE especialista_servico_produtos
   SET ativo = 0
 WHERE ativo = 1;
