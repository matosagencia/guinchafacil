# Briefing para Codex — Seletor Cidade/Célula + Mapa de Leitura + Gráficos em Disco no painel "Metas & Território" (GuinchaFácil)

**Contexto do projeto:** GuinchaFácil, plataforma de intermediação de socorro/reboque (PHP puro, MySQL, sem framework). Convenções obrigatórias a seguir (já usadas em todo o projeto):

- Migrations em `install/*.sql`, sempre **idempotentes** (guard via `INFORMATION_SCHEMA.COLUMNS`/`TABLE_CONSTRAINTS` antes de `ALTER TABLE`), executadas em ordem alfabética por `install/migrate.php` — nomeie qualquer migration nova pra ordenar depois da mais recente hoje (`migration_pricing_zones_v5_metas.sql`).
- Telas admin seguem o padrão "shell-ops" ou reaproveitam cards Bootstrap simples quando a tela já é assim (caso do `/admin/dashboard`, ver abaixo).
- Sem evidência, não está pronto — todo número exibido precisa vir de dado real calculado ao vivo, nunca estimado/hardcoded. Se faltar dado, mostrar "sem dado", nunca 0% ou 100% por padrão.
- Ao final, rodar `php -l` em cada arquivo tocado (balance-check de sintaxe) e não quebrar nada que já funciona.

## O que já existe (não precisa recriar)

- `pricing_zones`: geometria real (`polygon_geojson`, desenhado a partir de fronteiras de bairro do OpenStreetMap), governança de expansão (`ordem_expansao`, `status_expansao`, `bairros_referencia`) e metas do piloto de 90 dias (`meta_prestadores_min/max`, `meta_disponibilidade_simultanea`, `meta_atendimentos_mes1/2/3`, `meta_margem_operacional_min_pct`, `meta_margem_pos_marketing_min_pct`, `meta_composicao_prestadores`, `meta_ciclo_inicio`), desde `migration_pricing_zones_v5_metas.sql`.
- `pricing_zones.cidade_id`: tag organizacional pra qual cidade (tabela `cidades`) cada célula pertence.
- `pedidos.pricing_zone_id`: resolvido automaticamente por point-in-polygon real (`ZonePricingService::resolverZonaPorCoordenada()`) na criação do pedido.
- `src/Services/TerritorioMetasService.php::painel()`: retorna, por célula (array indexado 0..N, ordenado por `ordem_expansao`), tudo que o painel precisa — `id`, `code`, `name`, `status_expansao`, `ordem_expansao`, `tem_poligono`, `ciclo_dias_decorridos/restantes`, metas (`meta_*`), realizado (`pedidos_pagos`, `receita_bruta`, `repassado_prestadores`, `comissao_plataforma`, `perdas_estorno_valor/qtd`, `margem_operacional`, `margem_operacional_pct`, `prestadores_homologados`, `progresso_pedidos_pct`, `progresso_prestadores_pct`).
- `/admin/dashboard` (`AdminController::dashboard()` + `src/Views/admin/dashboard.php`): já renderiza dois cards — "Metas & Território" (um bloco por célula, lado a lado, sem seletor — mostra TODAS as células de uma vez) e "As 7 perguntas do marketing" (resumo financeiro + tabela por canal, via `FinancialAttributionReportService`).
- Mapa Leaflet **já implementado e funcionando** em `/admin/precificacao/zonas` (`src/Views/admin/precificacao_zonas.php`, linhas ~206-390): usa Leaflet vendorizado localmente (`/public/assets/vendor/leaflet/leaflet.{css,js}`, não CDN), tile OpenStreetMap padrão, desenha `polygon_geojson` de cada zona, e tem modo de desenho/edição (que aqui NÃO deve ser reaproveitado — o mapa pedido agora é só leitura).
- Chart.js **já carregado** em `/admin/dashboard` via CDN (`https://cdn.jsdelivr.net/npm/chart.js`, com nonce CSP — ver `csp_script_nonce_attr()`) e já usado para `chartStatus` (pizza) e outros gráficos de linha/barra nessa mesma página. Reaproveitar a mesma tag de script, não duplicar o `<script src>`.

## O que falta (objeto deste briefing)

Hoje o card "Metas & Território" mostra **todas as 5 células ao mesmo tempo**, em texto/números com barra de progresso linear, sem mapa e sem gráfico. O pedido é:

### Tarefa 1 — Seletor de cidade → célula

- Acima do grid de células, adicionar dois `<select>` encadeados: primeiro **Cidade** (fonte: `Cidade::listarAtivas()`, já usado em outras telas admin, ex. `/admin/cidades`), depois **Célula/Zona** (fonte: células daquela cidade, filtrando o array já retornado por `TerritorioMetasService::painel()` — não precisa de nova query, o `cidade_id` já vem dentro de cada zona via `PricingZone::listarPorOrdemExpansao()`, então só passar esse campo adiante no service).
- Comportamento: ao trocar a cidade, a lista de células recarrega (filtrada); ao selecionar uma célula, o resto da tela (mapa + cards) atualiza pra mostrar só aquela célula. Estado inicial: primeira célula com `ordem_expansao` mais baixa (célula 1) pré-selecionada.
- Decisão de implementação: pode ser feito 100% client-side (o controller já entrega o array completo de todas as células no `$territorioPainel` — não precisa de endpoint novo nem AJAX; o seletor só mostra/esconde blocos já renderizados via JS, ou usa Alpine/vanilla JS simples, igual ao padrão já usado nos toggles Bootstrap de `precificacao_zonas.php`). Preferir essa abordagem a criar rota nova, já que o volume de dados (5 células) é pequeno.

### Tarefa 2 — Mapa de leitura (read-only) da célula selecionada

- Adicionar um mapa Leaflet **somente leitura** (sem controles de desenho/edição) mostrando o `polygon_geojson` da célula selecionada, reaproveitando a mesma stack já vendorizada (`/public/assets/vendor/leaflet/leaflet.{css,js}`) usada em `precificacao_zonas.php` — não adicionar Leaflet via CDN nem duplicar a lib.
- Ao trocar a célula no seletor, o mapa deve recentralizar/redesenhar o polígono da célula recém-selecionada (destruir/recriar o layer do polígono, manter a instância do mapa se possível — ver `map.setView()` + remover layer anterior antes de adicionar o novo).
- Se a célula não tiver `polygon_geojson` ainda (`tem_poligono` = false), mostrar mensagem "Sem polígono desenhado ainda" no lugar do mapa (mesma mensagem já usada nos cards atuais) em vez de renderizar um mapa vazio ou quebrar.
- Não adicionar nenhum controle de edição/desenho — é só visualização. Não é pra reaproveitar o modo de desenho que já existe em `/admin/precificacao/zonas`; se o admin quiser editar o polígono, o link "Gerenciar células e polígonos →" (já existente no card atual) já leva pra lá.

### Tarefa 3 — Cards com gráfico em formato de disco (donut/anel)

Trocar (ou complementar — decisão do Codex, mas preferir substituir a barra de progresso linear atual por gráficos em anel, que é o que foi pedido) as métricas de progresso da célula selecionada por gráficos Chart.js tipo `doughnut`, usando os campos que `TerritorioMetasService::painel()` já calcula — não inventar métrica nova nesta tarefa:

1. **Disco "Pedidos pagos vs meta (90 dias)"** — `pedidos_pagos` / `meta_atendimentos_90d`, com o restante (não atingido) em cinza claro. No centro do anel (texto sobreposto via plugin ou `<div>` posicionado), mostrar `pedidos_pagos` grande + "de `meta_atendimentos_90d`" pequeno. Se `meta_atendimentos_90d` for `null` (célula sem meta ainda), não renderizar o gráfico — mostrar a mesma mensagem já usada hoje ("Sem meta de atendimentos definida ainda para esta célula").
2. **Disco "Prestadores homologados vs meta"** — mesma lógica, usando `prestadores_homologados` / `meta_prestadores_min` (usar `meta_prestadores_min` como alvo do anel; se quiser, mostrar `meta_prestadores_max` como um segundo marcador/anel externo — opcional, não obrigatório).
3. **Disco "Composição financeira"** — um doughnut com 3 fatias: `repassado_prestadores`, `comissao_plataforma` e `perdas_estorno_valor`, somando visualmente a `receita_bruta`. Cores sugeridas: repasse em azul, comissão em verde, perdas em vermelho — reaproveitar a paleta já usada nos outros gráficos da página (ver `chartStatus` em `dashboard.php` pra manter consistência visual).
4. Manter, abaixo de cada disco, os mesmos números em texto que já existem hoje (receita bruta, repassado, comissão, perdas, margem) — o disco é complemento visual, não substitui a leitura numérica exata.

Implementação: um `<canvas>` por disco, um `new Chart(ctx, {type:'doughnut', ...})` por célula selecionada. Como só uma célula é exibida por vez (por causa do seletor da Tarefa 1), destruir (`chart.destroy()`) e recriar os 3 gráficos ao trocar de célula, em vez de manter 5×3 instâncias de Chart.js vivas ao mesmo tempo (desperdício e risco de memory leak).

## Fora de escopo (não fazer neste briefing)

- Gate de expansão (os 9 indicadores operacionais: taxa de aceite, mediana de chegada, cobertura, etc.) — isso é objeto de outro briefing já existente (`doc/BRIEFING-CODEX-GATE-EXPANSAO-CELULAS.md`), não duplicar trabalho.
- Vincular guincheiro a uma célula específica (`guinchos.pricing_zone_id`) — também já é o Gap 2 do briefing acima; a contagem de prestadores continua sendo a aproximação por cidade até aquele briefing ser executado.
- Edição de polígono nesta tela — o mapa aqui é só leitura, edição continua exclusiva de `/admin/precificacao/zonas`.

## Critérios de entrega

1. Nenhuma rota nova nem query nova além do que já existe em `TerritorioMetasService::painel()` — Tarefa 1 e 2 são client-side sobre dado já carregado; se for estritamente necessário algum dado adicional (ex. lista de cidades pra popular o primeiro `<select>`), usar model já existente (`Cidade::listarAtivas()`), sem criar tabela/serviço novo.
2. Leaflet e Chart.js reaproveitados das instâncias/arquivos já vendorizados/carregados — não duplicar `<script src>` nem baixar outra versão da lib.
3. Mapa é somente leitura — sem `L.Draw` nem qualquer handler de clique que desenhe/edite.
4. Nenhum gráfico renderizado com dado ausente mostrando 0% enganoso — seguir a mesma regra já aplicada no card atual ("sem meta definida" em vez de barra vazia).
5. `php -l` limpo em todo arquivo `.php` tocado; revisar o HTML/JS gerado (balance de tags, sem erro de console) antes de entregar.
6. Entregar um resumo final indicando: quais arquivos foram alterados (deve ser essencialmente só `src/Views/admin/dashboard.php`, e possivelmente `src/Controllers/AdminController.php` se precisar passar `Cidade::listarAtivas()` pra view), e como testar manualmente (trocar cidade → célula → conferir mapa e os 3 discos atualizando).
