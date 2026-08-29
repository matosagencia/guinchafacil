# Briefing para o Codex — aplicar o visual novo no restante do painel admin

## Contexto

Hoje só `/admin/central` (Central Operacional) usa o layout novo (shell de 3
colunas, `.ops-nav`, cards/badges do Pacote L2.1-L2.3). Todas as outras ~30
telas do admin (pedidos, guinchos, financeiro, configurações, carteiras,
saques, ocorrências, despacho, alertas etc.) usam o layout clássico:
`layouts/header.php` + `layouts/sidebar_admin.php` + `<main class="main-content">`
+ `layouts/footer.php`, estilizado por `public/assets/css/style.css` (antigo)
com uma camada de tema escuro já aplicada (`themes/admin.css`).

O usuário quer que o RESTANTE do painel (essas ~30 telas clássicas) receba a
mesma linguagem visual da Central Operacional — sem recriar cada tela do
zero.

## Estratégia obrigatória: CSS/partials compartilhados, NÃO página por página

**Toda tela clássica já inclui os mesmos 2 arquivos**:
`src/Views/layouts/header.php` (topo, `<head>`, CSS/JS carregados) e
`src/Views/layouts/sidebar_admin.php` (menu lateral). Isso significa que
restilizar esses 2 arquivos compartilhados + adicionar UM arquivo CSS novo
já muda o visual em TODAS as ~30 telas de uma vez, sem tocar em nenhuma
lógica de negócio, nenhum Controller, nenhuma query.

**Você deve seguir exatamente esta estratégia — não reescreva cada view
individual.** Reescrever view por view multiplicaria o risco de quebrar
algo (30 arquivos tocados em vez de ~3) sem necessidade.

## O que fazer

1. **Novo arquivo CSS**: `public/assets/css/pages/admin-classic-shell.css`.
   Ele deve restilizar, usando as MESMAS variáveis já definidas em
   `public/assets/css/tokens.css` e `public/assets/css/themes/admin.css`
   (não invente cores novas — reaproveite `--theme-*`, `--primary`, `--audit`
   etc. já existentes), as classes que TODA tela clássica usa:
   - `.sidebar` e `.sidebar-link` (menu lateral) → visual parecido com
     `.ops-nav`/`.ops-nav-link` de `public/assets/css/pages/admin-central-operacional.css`
     (mesmo espaçamento, mesmo estilo de hover/active, mesmos ícones
     alinhados). **Olhe esse arquivo como referência de linguagem visual.**
   - `.main-wrapper` / `.main-content` (área de conteúdo).
   - `.page-head` (cabeçalho de página — já existe `components/page-head.css`,
     confira antes de duplicar regra).
   - Componentes Bootstrap usados em quase toda tela: `.card`, `.table`,
     `.badge`, `.btn`, `.alert` — só ajuste de cor/borda/raio pra combinar
     com o tema escuro novo, SEM mudar estrutura HTML nem comportamento.
2. **Inclua o novo CSS em `header.php`** (`src/Views/Views/layouts/header.php`
   — depois do bloco que já carrega `tokens.css`/`base.css`/`shell.css`/
   `components/page-head.css`), só quando `$temaClass === 'admin'`
   (mesma lógica condicional que já existe pro `themes/{$temaArquivo}.css`
   — **não afete os temas de cliente/guincho/funcionário/gerente**, o
   pedido é só pro admin).
3. **`sidebar_admin.php`**: pode ajustar classes/estrutura HTML das `<a>` se
   precisar pra bater com o CSS novo (ex.: envolver ícone+texto em spans,
   como já é feito em `admin_nav_operacional.php`), mas **não remova nem
   renomeie nenhum `href`, nenhuma rota, nenhum texto de link** — é
   puramente visual.

## Regras de segurança (não negociáveis)

1. **Aditivo, nunca destrutivo.** Sempre que possível, adicione uma regra
   CSS nova em vez de apagar uma existente em `style.css`. Se precisar
   sobrescrever, sobrescreva com um seletor mais específico — não edite
   `style.css` diretamente (é usado por cliente/guincho também).
2. **Zero mudança de lógica.** Não toque em nenhum Controller, nenhum
   Model, nenhuma Service, nenhuma query SQL. Isso é 100% CSS + ajuste
   estrutural de 2 arquivos de layout compartilhado.
3. **Não toque nas 4 telas que já usam o shell novo**
   (`central_operacional.php`, `despacho.php`, `ocorrencias.php`,
   `alertas_operacionais.php` — reconheça pelo `<div class="shell-ops">`
   ou ausência de `main-wrapper`) nem nos arquivos CSS delas
   (`shell.css`, `admin-central-operacional.css`).
4. **Teste visualmente em pelo menos 5 telas variadas** antes de terminar:
   `/admin/dashboard`, `/admin/pedidos`, `/admin/guinchos`,
   `/admin/carteiras` (tem cards de resumo + tabela), `/admin/financeiro`.
   Se não tiver como abrir o navegador, pelo menos releia o CSS gerado
   contra o HTML real de cada uma dessas views pra garantir que os
   seletores existem e não vão colidir com nada.
5. **`php -l` não se aplica** (é CSS/HTML), mas rode
   `npx tsc --noEmit` mesmo assim no final por segurança (nenhum `.ts`
   deveria ser tocado, então deve continuar limpo).

## Entregável

Log em `qa/logs/admin-layout-restante-<data>.log` com: lista de arquivos
tocados, um resumo de quais classes/seletores foram adicionados/alterados,
confirmação de que as 5 telas de teste acima foram olhadas (visualmente ou
por leitura de HTML+CSS) e não quebraram, e qualquer limitação/pendência
conhecida (ex.: alguma tela com HTML muito específico que não respondeu bem
ao CSS genérico).
