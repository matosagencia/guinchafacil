# Inventario da migracao Pedido Core

Gerado em 2026-09-25 durante a execucao do plano `doc/GUINCHAFACIL_PLANO_EXECUCAO_AI.md`.

## Buscas obrigatorias

| Padrao | Ocorrencias em `src public bin tools tests` | Observacao |
| --- | ---: | --- |
| `EspecialistaPricingService` | 18 | Ainda consumido por controllers/servicos ativos. Nao remover nesta fase. |
| `provider_quote_rules` | 4 | Consumido por `OrcamentoPrevioService`/`ProviderWorkshopService`; mantido como legado compatível. |
| `permite_resgate_direto` | 10 | Ainda consumido por oficina/resgate direto. |
| `taxa_indicacao_fixa` | 15 | Ainda consumido por oficina/indicacao. |
| `especialista` | 1250 | Perfil e rotas ainda ativos em controllers, views, JS, workers e testes. |
| `INSERT INTO pedidos` | 58 | Existem criacoes diretas antigas; novo caminho canonico foi adicionado em `/pedido/criar`. |
| `UPDATE pedidos` | 136 | Transicoes e scripts legados ainda fazem updates diretos. |
| `TarifaService` | 180 | Fonte de tarifa legada mantida como dependencia do `PedidoPricingService`. |
| `new PedidoService` | 10 | Consumidores legados preservados. |
| `PedidoService::` | 0 | Sem chamadas estaticas encontradas. |
| `OrcamentoPrevioService` | 6 | Refatorado com novos metodos provider-owned sem remover compatibilidade. |
| `ChargePolicyService` | 32 | Consolidado com constantes/metodos do plano. |
| `ResgateDiretoOficinaService` | 6 | Mantido. |

## Codigo ativo mapeado

- `src/Services/PedidoService.php`: facade legada de criacao/listagem/cancelamento.
- `src/Services/TarifaService.php`: tarifa oficial legada de reboque; agora usada pelo `PedidoPricingService`.
- `src/Services/Financial/ChargePolicyService.php`: politica financeira consolidada.
- `src/Services/OrcamentoPrevioService.php`: mantem regras antigas e adiciona orcamento real informado pelo provider.
- `src/Services/Pedido/PedidoTransitionService.php`: state/transition central existente, preservado.
- `src/Controllers/ClienteController.php` e `src/Controllers/AdminController.php`: ainda possuem logica de criacao/cotacao legada.
- `src/Controllers/PedidoController.php`: novo adaptador fino para `/pedido/cotar` e `/pedido/criar`.

## Rotas relevantes

- Novas rotas canonicas: `POST /pedido/cotar`, `POST /pedido/criar`.
- Rotas legadas de cliente/admin continuam existentes para compatibilidade.
- Rotas `especialista` continuam ativas e devem ser migradas somente depois de substituir consumidores.

## Tabelas relevantes

- `pedidos`: recebe contexto de modalidade quando as colunas existem.
- `pedido_financial_snapshots`: nova tabela de snapshot financeiro contratual.
- `pedido_eventos`: nova tabela simples para eventos auditaveis do core.
- `pedido_orcamentos_previos`: recebe colunas aditivas para mao de obra, pecas, total e abatimento.
- `provider_quote_rules`: mantida; nao e mais necessaria para novo metodo `criarOrcamentoProvider`, mas ainda tem consumidores legados.

## Decisao de seguranca

Nao houve remocao de tabelas, rotas ou servicos de especialista nesta fase porque ainda existem consumidores ativos. A migracao segura exige fases posteriores: substituir consumidores, testar, buscar residuos e so entao remover legado.
