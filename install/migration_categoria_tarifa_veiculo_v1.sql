-- install/migration_categoria_tarifa_veiculo_v1.sql
-- §A6 (auditoria 21/07): TarifaService::categoriaDeVeiculo() já lia
-- $veiculo['categoria_tarifa'] preferencialmente, mas a coluna nunca
-- existiu na tabela veiculos — isset() sempre dava falso, então a
-- categoria sempre vinha do ENUM `tipo` (carro/moto/caminhao/van/onibus/
-- outro), tornando as categorias 'suv' e 'eletrico' inalcançáveis na
-- prática. Coluna separada do ENUM `tipo` (usado em outros lugares, ex.
-- compatibilidade de reboque) para não misturar os dois conceitos.

ALTER TABLE veiculos
    ADD COLUMN IF NOT EXISTS categoria_tarifa VARCHAR(20) NULL AFTER tipo;
