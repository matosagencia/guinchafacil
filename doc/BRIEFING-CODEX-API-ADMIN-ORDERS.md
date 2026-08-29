# Briefing para Codex — Fase 2: API Admin de Pedidos (Worklist)

## Contexto

O GuinchaFácil está remodelando o backoffice admin para um modelo de central operacional (navegação fixa + fila de pedidos + painel de contexto), substituindo o dashboard atual de cards soltos. Claude (Sonnet) está construindo em paralelo o shell visual (header, sidebar, grid, design system CSS) em `src/Views/admin/`. Esta frente é independente: a camada de API que alimentará essa fila de pedidos.

**Trabalhe só nos arquivos abaixo. Não toque em `src/Views/admin/*.php` (dashboard.php, pedidodetalhe.php, pedido_trilha.php) — isso está sendo mexido em paralelo e qualquer edição aí vai gerar conflito.**

## O que construir

### 1. `src/Api/Admin/OrdersApiController.php` (novo)

Endpoints REST que devolvem JSON (nunca HTML), seguindo o padrão de resposta já usado no projeto (`{"sucesso": bool, ...}` ou o padrão `{"ok": bool, "data": ..., "meta": ...}` — verifique como os controllers existentes em `src/Controllers/` respondem JSON hoje, ex. `GuinchoController`, `PagamentoController`, e siga o MESMO padrão ao invés de inventar um novo formato).

Rotas a implementar (ver `index.php` para o padrão de roteamento usado no projeto):

- `GET /api/admin/orders` — lista paginada/filtrada de pedidos para a fila operacional.
  - Parâmetros de query: `q` (busca por código/cliente/placa/telefone), `status[]`, `priority[]`, `service_id`, `provider_id`, `region`, `created_from`, `created_to`, `has_alert`, `page`, `per_page`, `sort`.
  - Cada item da lista precisa ter: id, código do pedido, status (raw + label + classe css), prioridade (derive de: tempo sem aceite, alerta de GPS ativo, cancelamento em análise — ver `PorThresholds`/`ProofOfRoadService` pra critérios já existentes de "alerta"), nome do cliente, resumo do veículo (marca/modelo/placa), nome do serviço, nome do prestador (se atribuído), tempo decorrido no status atual, resumo de alerta (texto curto, ex. "Sem GPS há 10 min"), contagem de mensagens de chat não lidas.
- `GET /api/admin/orders/{id}` — detalhe completo de um pedido (cliente, veículo, prestador, serviço, preço, pagamento, SLA/tempo, localização atual, distância, ETA).
- `GET /api/admin/orders/{id}/tracking` — pontos de GPS/rota real e planejada (reaproveitar o que já existe em `pedido_trilha.php`/`pedidodetalhe.php` para trilha POR — NÃO duplicar lógica, extrair pra um Service se necessário).
- `GET /api/admin/orders/{id}/timeline` — eventos cronológicos do pedido (criação, pagamento, aceite, mudanças de status, alertas).
- `GET /api/admin/orders/{id}/messages` e `POST /api/admin/orders/{id}/messages` — chat administrativo (reaproveitar tabela/lógica de chat já existente no projeto, se houver).

### 2. `src/Services/Admin/OrderWorklistService.php` (novo)

Toda a lógica de query/filtro/paginação da listagem fica aqui, não no controller. O controller só valida input e delega.

### 3. Segurança (obrigatório, não opcional)

- Autenticação de sessão admin já existente no projeto (ver como `src/Controllers/` de admin fazem isso hoje, ex. `AdminController` ou similar) — reaproveitar o mesmo guard, não inventar um novo.
- CSRF: GETs não precisam, mas o `POST /messages` precisa validar `X-CSRF-Token` do jeito que o resto do projeto já faz.
- Nunca usar SQL concatenado — sempre prepared statements (`getPDO()->prepare(...)`), igual ao resto do código (ver `Configuracao.php`, `PedidoLocalizacao.php` como referência de estilo).
- Escapar/validar todo parâmetro de busca antes de ir pro SQL.

### 4. Validação

Antes de considerar pronto:

```bash
php -l src/Api/Admin/OrdersApiController.php
php -l src/Services/Admin/OrderWorklistService.php
```

Depois, testar manualmente com curl contra o servidor local (autenticando como admin primeiro, igual QA já faz em outros lugares — ver `tools/prepare_*_qa.php` pra exemplos de como logar como admin em scripts de teste), confirmando:
- paginação funciona;
- busca por código/placa/telefone funciona;
- filtro por status funciona;
- pedido sem prestador aparece com `provider_name: null`, não quebra;
- pedido sem alerta não mostra `alert_summary` inventado (deve ser string vazia ou null).

### 5. O que NÃO fazer

- Não mexer nas regras de negócio de antifraude (Proof-of-Road) — essa API é só LEITURA/exibição de dados que já existem, não deve relaxar nem recalcular nada do POR.
- Não criar uma nova forma de autenticação — reaproveitar a existente.
- Não tocar nos arquivos de `src/Views/admin/*.php` (conflito com o trabalho em paralelo).
- Não implementar ainda WebSocket/SSE/tempo real — isso é fase posterior. Por ora, os endpoints são request/response simples (o frontend vai fazer polling).

### 6. Entregável

Ao terminar, salvar em `qa/logs/admin-orders-api-<data>.log` (mesmo formato já usado pros runs de stress/homologação) um registro completo para revisão posterior, incluindo:

- lista dos arquivos criados/alterados (path completo);
- para cada endpoint: o comando curl exato usado, o payload/query enviado, e a resposta HTTP completa recebida (status + corpo JSON) — não só "passou/falhou", a evidência real, porque quem vai revisar (Claude) audita pelo log, sem re-executar nada;
- resultado de `php -l` de cada arquivo tocado;
- quaisquer decisões de design tomadas que não estavam explícitas no briefing (ex.: formato de resposta escolhido, nome de tabela usada pro chat, como foi derivada a "prioridade" do pedido) — isso é o que mais importa pra revisão, porque é onde mais provavelmente há divergência do que foi pedido;
- qualquer coisa que não deu pra terminar ou ficou incerta, marcada explicitamente como pendente.

Não é necessário reportar passo a passo durante a execução — só esse log consolidado ao final, para eu revisar o trabalho depois sem precisar pedir mais contexto.
