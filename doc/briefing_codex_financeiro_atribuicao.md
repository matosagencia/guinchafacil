# Briefing para Codex — Atribuição de Marketing e Fechamento Financeiro (GuinchaFácil)

**Contexto do projeto:** GuinchaFácil, plataforma de intermediação de socorro/reboque (PHP puro, MySQL, sem framework). Convenções obrigatórias a seguir (já usadas em todo o projeto):

- Migrations em `install/*.sql`, sempre **idempotentes** (guard via `INFORMATION_SCHEMA.COLUMNS`/`TABLE_CONSTRAINTS` antes de `ALTER TABLE`), executadas em ordem alfabética por `install/migrate.php` — nomeie o arquivo para ordenar depois das últimas (`migration_vehicle_catalog_v3_ids.sql` é a mais recente hoje).
- Telas admin seguem o padrão "shell-ops" (3 colunas: `ops-topbar` com busca contextual + `ops-summary` com métricas + workspace). Reaproveitar `src/Views/components/admin_nav_operacional.php` para adicionar itens de navegação.
- Toda ação administrativa financeira precisa gravar auditoria (quem, quando, o quê, estado antes/depois, justificativa) — mesmo padrão já usado em carteiras/saques.
- Toda integração/gravação financeira deve ser **idempotente** (chave única / hash de idempotência), nunca duplicar efeito sob retry.
- Regra suprema já vigente: pagamento aprovado ≠ saldo liberado ≠ saque pago ≠ repasse concluído. Qualquer novo relatório deve respeitar essa separação, não fundir os conceitos.

**Diagnóstico atual (já levantado, serve de ponto de partida):**

| Pergunta | Situação hoje |
|---|---|
| Onde anunciamos? | Não há captura de UTM/campanha/canal/origem no pedido. |
| Quanto gastamos? | Não existe módulo/importação de custo de anúncio. |
| Quantos pedidos pagos vieram? | Sim, via `status_pagamento = aprovado` (hoje: 24 pagamentos aprovados). |
| Quanto recebemos? | Parcial — bruto aprovado registrado (R$ 5.215,50), sem reconciliação total com o gateway. |
| Quanto repassamos? | Parcial — ledger de crédito/débito ao guincho existe (R$ 3.900,05 em créditos). |
| Quanto perdemos? | Parcial — cancelamentos/estornos/taxas existem, sem relatório consolidado. |
| Qual célula gerou margem? | Só por serviço/cidade/categoria — não por canal de aquisição. |

**Divergência encontrada, precisa ser explicada antes de qualquer relatório novo:**
- Pagamentos aprovados: R$ 5.215,50
- Crédito guincho: R$ 3.900,05
- Crédito plataforma: R$ 1.040,55
- Diferença não reconciliada: **R$ 274,90**

---

## Tarefa 0 (fazer primeiro): Investigar a divergência de R$ 274,90

Antes de construir qualquer relatório novo sobre dados que podem estar errados, reconciliar linha a linha:
1. Somar `pagamentos.valor` (status aprovado) do período analisado.
2. Somar `guincheiro_movimentos`/ledger equivalente (créditos ao guincho) do mesmo período.
3. Somar comissão da plataforma (`comissao_plataforma` ou campo equivalente) do mesmo período.
4. Identificar pedidos onde `credito_guincho + comissao_plataforma != valor_pago` — listar por `pedido_id`/`payment_id`.
5. Causas prováveis a checar: taxas de gateway descontadas sem lançamento espelho; pedidos híbridos (pane→reboque) com cobrança complementar mal somada; estornos parciais não deduzidos de ambos os lados; arredondamento.
6. Entregar: script (`tools/reconciliar_divergencia_financeira.php`, seguindo o padrão CLI-only `if (PHP_SAPI !== 'cli') { http_response_code(403); die(...); }`) que imprime a lista de pedidos divergentes com a causa provável.

## Tarefa 1: Captura de origem/UTM/canal no pedido

- Migration idempotente adicionando em `pedidos` (ou tabela dedicada `pedido_atribuicao` referenciando `pedido_id`): `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `canal_aquisicao` (enum/string: orgânico, google_ads, meta_ads, indicação, whatsapp, etc.), `referrer_url`, `landing_page`.
- Capturar na primeira visita (cookie/sessão de curta duração, ex. `first_touch_utm`) e gravar no pedido no momento da criação — mesmo se o cliente demorar dias entre clicar no anúncio e pedir socorro.
- Se o cliente já tiver conta, também vale gravar UTM de origem no cadastro do usuário (`usuarios.utm_source_cadastro` etc.) para atribuição de cadastro vs. atribuição de pedido.
- Sem UTM disponível → `canal_aquisicao = 'organico'` (nunca null sem motivo).

## Tarefa 2: Cadastro/importação de gastos de anúncio

- Nova tabela `gastos_marketing`: `id`, `canal` (google_ads, meta_ads, etc.), `campanha`, `data`, `valor_gasto`, `cidade_id` (nullable, se a campanha for local), `origem_lancamento` (manual, import_csv), `criado_por_admin_id`, `criado_em`.
- Tela admin (padrão shell-ops) para lançar gasto manual e para importar CSV (Meta Ads Manager e Google Ads exportam CSV com data/campanha/valor — mapear colunas).
- Auditoria de toda alteração/exclusão de lançamento de gasto.

## Tarefa 3: Relatório unificado de receita/gateway/repasse/estorno/perda

- Uma tela admin nova (ex. `/admin/financeiro/visao-unificada`) que cruza, por período e opcionalmente por cidade/canal:
  - Receita bruta aprovada (pagamentos)
  - Valor líquido do gateway (após taxas, usando `pagamento_liquidacoes` se existir, ou a divergência apurada na Tarefa 0)
  - Repasse ao guincheiro (crédito de carteira)
  - Comissão da plataforma
  - Estornos e cancelamentos (valor perdido)
  - Gasto de marketing do mesmo período (da Tarefa 2)
  - Resultado líquido = comissão da plataforma − gasto de marketing − perdas
- Exportação CSV/PDF do período.

## Tarefa 4: Margem por canal, cidade, serviço e categoria

- Extensão do relatório da Tarefa 3 com agrupamento por `canal_aquisicao` (da Tarefa 1) cruzado com `gastos_marketing` (da Tarefa 2), para calcular CAC (custo de aquisição por canal) e margem líquida por canal — além dos agrupamentos que já existem hoje (serviço/cidade/categoria).
- Fórmula por canal: `receita_liquida_canal − gasto_marketing_canal = margem_canal`.

---

## Critérios de entrega (para todas as tarefas)

1. Migrations idempotentes, testadas contra banco já existente (não pode quebrar se rodar duas vezes).
2. Nenhuma tela nova sem `ops-topbar` + `ops-summary` no padrão shell-ops já estabelecido.
3. Toda gravação financeira com hash de idempotência.
4. Toda ação admin financeira com auditoria completa.
5. Ao final, rodar balance-check de sintaxe PHP (`php -l` em cada arquivo tocado) e a suíte PHPUnit existente — não pode haver regressão.
6. Entregar um resumo final indicando: migrations novas (na ordem em que devem rodar), rotas novas, e como testar manualmente cada tela.
