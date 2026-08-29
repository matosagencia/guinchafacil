# Financeiro, atribuição e margem — entrega de 03/08/2026

## Entregue

- Migration `migration_financeiro_atribuicao_v1.sql`: UTM/first-touch em pedidos e cadastro de usuários, além de `gastos_marketing` com hash único de idempotência.
- Migration `migration_financeiro_atribuicao_v2_cidade.sql`: vínculo opcional do pedido à cidade resolvida no momento da criação.
- `MarketingAttributionService`: captura a primeira origem em sessão/cookie de 90 dias; ausência de origem vira `organico`.
- `MarketingSpendService`: lançamento manual e importação CSV idempotentes; exclusão é lógica e auditada.
- `/admin/financeiro/visao-unificada`: resumo de pagamentos aprovados, liquidações de gateway, ledger, repasses, estornos, gastos, margem, CAC e células canal/cidade/serviço/categoria.
- `/admin/financeiro/visao-unificada/csv`: exportação das células.
- Rotas de gasto manual, importação CSV e exclusão lógica, todas protegidas por admin + CSRF e auditadas em `app_logs`.
- `tools/reconciliar_divergencia_financeira.php`: reconciliação CLI linha a linha, sem escrita.

## Tarefa 0 — divergência encontrada

O total local continua sendo R$ 5.215,50 em pagamentos aprovados, R$ 3.900,05 em créditos de guincho e R$ 1.040,55 em créditos de plataforma. A diferença de R$ 274,90 é explicada por dois pagamentos aprovados sem qualquer lançamento no ledger:

- pedido 1515 / pagamento 1119: R$ 89,90;
- pedido 1543 / pagamento 1162: R$ 185,00.

O relatório não corrige esses registros automaticamente: são históricos e precisam de decisão contábil antes de um backfill.

## Teste manual

1. Execute `C:\xampp\php\php.exe install\migrate.php`.
2. Acesse `/admin/financeiro/visao-unificada` como admin.
3. Lance um gasto manual e repita o mesmo lançamento; o hash impede duplicação.
4. Importe CSV com cabeçalho `data;campanha;canal;valor_gasto`.
5. Confira os agrupamentos e use “Exportar CSV”.
6. Rode `C:\xampp\php\php.exe tools\reconciliar_divergencia_financeira.php`.

## Validação

- `php -l` limpo nos arquivos PHP tocados.
- Testes direcionados de tarifa e ledger: 12 testes, 27 asserções, OK.
- A suíte completa atual reporta 27 erros em testes que usam SQLite sem as migrations históricas de `cidades`, `cidade_id` e `pricing_zones`; não são erros introduzidos pela tela nova, mas a suíte completa não fica verde até o bootstrap de testes aplicar o schema atualizado.

## Limitações conscientes

- O histórico anterior à captura não pode ser atribuído retroativamente sem evidência.
- `pagamento_liquidacoes` está vazio no banco atual; portanto, líquido de gateway aparece como “não liquidado” e não é inferido como caixa confirmado.
- A margem por célula usa o crédito de plataforma do ledger, não o campo operacional do pagamento, para respeitar a reconciliação contábil.
