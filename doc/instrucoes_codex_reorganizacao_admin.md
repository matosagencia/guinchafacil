# Instruções para o Codex — Reorganização do Admin (bloco Financeiro / Qualidade / Sistema / Catálogo)

## Contexto

O painel admin do GuinchaFácil (`C:\xampp\htdocs\guinchafacil`) está passando por uma reorganização de navegação, baseada num relatório de auditoria que identificou páginas redundantes e mal agrupadas. O trabalho foi dividido em dois blocos pra evitar dois agentes editando os mesmos arquivos ao mesmo tempo:

- **Bloco Claude** (em andamento, não mexer): Central Operacional (fusão de Dashboard + Central + Despacho), módulo Prestadores (Guinchos + Especialistas + Pendentes + Documentos), módulo Pessoas (Usuários com abas), e a reescrita de `src/Views/components/admin_nav_operacional.php`.
- **Bloco Codex (este documento)**: Financeiro, Qualidade, Sistema/SRE, Catálogo de Serviços, e limpeza de rotas técnicas da navegação.

**Regra de convivência**: não edite `src/Views/components/admin_nav_operacional.php` nem `src/Controllers/AdminController.php` nas seções de rotas do bloco Claude (dashboard/central/despacho/guinchos/usuarios) até receber sinal verde. Se precisar adicionar um link de nav, adicione apenas dentro das seções "Financeiro", "Qualidade e SRE" e "Serviços e planejamento" já existentes nesse arquivo — não reordene nem remova as outras seções.

## Padrão arquitetural do projeto (siga à risca)

Todas as páginas de listagem do admin foram convertidas pro padrão **shell-ops** (3 colunas): sidebar + worklist clicável + workspace de detalhe, sem reload de página. Antes de mexer em qualquer view, olhe um exemplo já pronto pra copiar o padrão:

- `src/Views/admin/documentos.php` — worklist + workspace preenchido via **JSON embutido** (`window.__xData`), sem round-trip — use esse padrão quando os dados já vêm inteiros do servidor (dataset pequeno, sem paginação pesada).
- `src/Views/admin/guinchos.php` + `src/Views/admin/partials/guincho_detalhe_workspace.php` — workspace preenchido via **fetch AJAX** a um endpoint `*-fragmento/{id}` que devolve `{ok, html}` — use esse padrão quando o detalhe é caro/grande (documentos, pedidos vinculados etc.) e não vale a pena pré-renderizar tudo de uma vez.
- `src/Views/admin/financeiro.php` — exemplo de página que também tem gráficos/KPIs/filtros acima do shell-ops, preservados, com a lista principal (pagamentos) convertida em worklist+workspace.
- `src/Views/admin/saques.php`, `src/Views/admin/pedidos.php` — outros exemplos prontos.

Estrutura HTML mínima de uma página shell-ops (não usa `layouts/footer.php`):

```php
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">...</div>
    <div class="ops-topbar__meta">...</div>
</div>

<section class="ops-summary" aria-label="...">
    <article class="ops-metric">...</article>
</section>

<div class="shell-ops" id="xShell">
    <aside class="shell-ops-sidebar" id="xSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>
    <section class="shell-ops-worklist" aria-label="...">...</section>
    <section class="shell-ops-workspace" id="xWorkspace" aria-live="polite">...</section>
</div>

<script ...>/* JS de seleção/filtro, ver documentos.php como referência */</script>
<script src=".../bootstrap.bundle.min.js"></script>
</body>
</html>
```

Se a página não tem uma entidade única pra virar worklist (ex.: uma calculadora ou dashboard puro), use a variante de 2 colunas `class="shell-ops shell-ops--no-worklist"` (ver `src/Views/admin/dashboard.php` e `src/Views/admin/planejamento.php`).

Cada seção de nav em `admin_nav_operacional.php` já é colapsável (accordion) e a sidebar inteira colapsa/expande — não precisa mexer nisso.

## Verificação obrigatória antes de considerar qualquer página pronta

Não há PHP-CLI disponível neste ambiente pra rodar `php -l` diretamente em alguns setups — se disponível no seu ambiente, rode. Se não, use no mínimo este check de balanceamento (adapte o caminho):

```python
import re
src = open("caminho/do/arquivo.php", encoding="utf-8").read()
blocks = re.findall(r'<\?php.*?\?>', src, re.S)
joined = "\n".join(blocks)
for a, b, name in [('{','}','brace'), ('(',')','paren'), ('[',']','bracket')]:
    print(name, joined.count(a), joined.count(b))  # devem bater
print('div', src.count('<div'), src.count('</div>'))
print('script', src.count('<script'), src.count('</script>'))
```

E valide todo `<script>` novo com `node --check arquivo.js` (extraia o bloco JS puro antes, sem tags PHP).

## Tarefa 1 — Unificar Financeiro + Carteiras + Saques

Hoje são 3 páginas/links separados: `/admin/financeiro`, `/admin/carteiras`, `/admin/saques`.

Objetivo: um módulo único "Financeiro" com abas:
- Visão geral (KPIs + gráficos que já existem em `financeiro.php`)
- Pagamentos (worklist já existente em `financeiro.php`)
- Carteiras dos prestadores (conteúdo de `carteiras.php`)
- Saques (conteúdo de `saques.php`)
- Jobs e falhas (fila de repasse PIX, já existe em `financeiro.php`)
- Exportações (link pro CSV existente)

Como implementar sem reescrever tudo do zero: as 3 páginas já estão no padrão shell-ops. A forma mais segura é manter os 3 controllers/rotas como estão (não quebrar nada que já funciona), e criar uma camada de abas por cima — ex.: um novo `src/Views/admin/financeiro_hub.php` (ou adaptar `financeiro.php` como página "mãe") com navegação por abas no topo do `.shell-ops-workspace`, carregando o conteúdo de cada aba via `fetch()` nos endpoints que já existem hoje (ou via `<iframe>` só como último recurso — prefira fetch). Se preferir uma rota nova `/admin/financeiro` como hub e mover as rotas antigas para `/admin/financeiro/pagamentos`, `/admin/financeiro/carteiras`, `/admin/financeiro/saques`, tudo bem — só **mantenha redirects das URLs antigas** (`/admin/carteiras`, `/admin/saques`) apontando pras novas, pra não quebrar links salvos/histórico.

Atualize o link de nav (dentro da seção "Financeiro" já existente em `admin_nav_operacional.php`) pra apontar só pro hub, removendo os 2 links extras (Carteiras, Saques) da sidebar — eles continuam acessíveis como abas dentro do hub.

## Tarefa 2 — Unificar Avaliações + Proof-of-Road + Checklists em "Qualidade Operacional"

Páginas hoje: `/admin/avaliacoes`, `/admin/proof-of-road`, `/admin/checklists-incompletos` (esta já está no padrão shell-ops, ver `src/Views/admin/proof_of_service_incompletos.php` — use como referência de worklist+workspace pras outras duas, se ainda não estiverem).

Objetivo: módulo "Qualidade Operacional" com abas Avaliações / Proof-of-Road / Checklists incompletos / Revisões manuais (esta última pode ficar como placeholder "em breve" se não existir ainda).

Mesma estratégia de hub por abas da Tarefa 1. Mantenha as URLs antigas funcionando (redirect ou abas internas via `?aba=`).

## Tarefa 3 — Separar "Sistema e SRE"

Páginas hoje misturadas na navegação: `/admin/health`, `/admin/logs`, `/admin/simulador` (QA Playwright), `/admin/env`, `/admin/env/auditoria`.

Objetivo: agrupar essas 5 num único bloco de nav "Sistema" (pode ser hub com abas ou simplesmente uma seção de nav própria, já que são todas ferramentas técnicas de baixa frequência de uso — não precisa forçar shell-ops nelas se já funcionam bem como estão). O importante aqui é a **navegação**: essas rotas não devem aparecer misturadas com tarefas operacionais do dia a dia (Central, Pessoas, Financeiro) — devem ficar visualmente/estruturalmente separadas, no fim do menu, como uma seção "Sistema" clara.

Se `/admin/logs` tiver uma versão legada e uma `logs_v2` ativa, mantenha só a ativa na nav e redirecione a antiga.

## Tarefa 4 — Unificar Catálogo de Serviços

Hoje existem duas estruturas:
- `/admin/servicos` — catálogo antigo de atalhos do cliente (`AdminController::servicos()`)
- `/admin/catalogo-servicos/tipos` — catálogo operacional estruturado (já convertido pro padrão shell-ops, ver `src/Views/admin/catalogo_servicos_tipos.php`)

Objetivo: um único "Catálogo de Serviços" com abas:
- Serviços exibidos ao cliente (conteúdo de `/admin/servicos`)
- Tipos operacionais (já existe)
- Capacidades dos prestadores (já existe, `/admin/catalogo-servicos/capacidades`)
- Compatibilidade veicular (já existe, `/admin/catalogo-servicos/compatibilidade`)
- Tarifas (já existe, `/admin/catalogo-servicos/tarifas`)
- Produtos e peças (já existe, `/admin/produtos`)

`/admin/servicos` deve **continuar funcionando** (compatibilidade), mas sem aparecer mais como link independente na sidebar — vira só uma aba dentro do hub novo, ou um redirect pra aba correspondente do hub.

## Tarefa 5 — Tirar rotas técnicas da navegação

Estas rotas devem continuar funcionando (não remover, não quebrar nada que as chame), só não devem ter link visível em nenhuma sidebar/menu:

```
/admin/servicos                (vira aba, não link — ver Tarefa 4)
/admin/chat
/admin/dashboard/json
/admin/dashboard/mapa-json
/admin/pedidos/json
/admin/financeiro/csv
/admin/veiculos/ajax
/admin/clientes/ajax
/admin/oficinas/ajax
/admin/usuario/suspender          (GET, é só o redirect helper — ação real é POST)
/admin/pedido/criar
/admin/logs (legado, se logs_v2 for a versão ativa)
```

Audite `src/Views/components/admin_nav_operacional.php` e qualquer outro lugar que gere link de menu (breadcrumbs, botões "voltar", etc.) e confirme que nenhuma dessas rotas técnicas aparece como link clicável fora de contexto (ex.: um botão "Exportar CSV" dentro da página de Financeiro pode continuar linkando pra `/admin/financeiro/csv` — isso não é nav, é uma ação dentro do conteúdo, tudo bem manter).

## Ordem sugerida de execução

1. Tarefa 5 primeiro (rápida, baixo risco, define o que vai sumir da nav).
2. Tarefa 4 (Catálogo) — menor, bom aquecimento pro padrão de hub por abas.
3. Tarefa 1 (Financeiro) — maior, mas já tem as 3 páginas prontas no padrão shell-ops.
4. Tarefa 2 (Qualidade).
5. Tarefa 3 (Sistema) por último — é só reorganização de nav, não exige reescrever views.

## Checklist final antes de entregar

- [ ] Balance-check (chaves/parênteses/colchetes/divs/scripts) em todo arquivo `.php` tocado.
- [ ] `node --check` em todo bloco `<script>` novo/editado.
- [ ] Nenhuma URL antiga quebrou (teste manual ou grep por `href="..."` apontando pras rotas antigas em outras views do projeto, pra garantir que outros lugares que linkam pra `/admin/carteiras`, `/admin/saques`, `/admin/servicos` etc. continuam funcionando).
- [ ] `admin_nav_operacional.php` só foi editado dentro das seções "Financeiro", "Qualidade e SRE" e "Serviços e planejamento" — não tocou nas seções do bloco Claude (Atendimento, Clientes, Guinchos e especialistas).
