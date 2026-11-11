# Linha de produção — Backoffice Lovable V3

## Objetivo

Levar todas as telas HTML autenticadas do backoffice para um único shell visual, corrigindo mojibake, sobreposição, barras superiores legadas, tabelas ilegíveis, overflow e divergências de perfil sem alterar endpoints, CSRF, ações ou o frontend público.

## Contrato obrigatório por tela HTML

Cada rota de tela deve:

1. Renderizar `src/Views/layouts/backoffice_shell.php`.
2. Ter exatamente um `.bo`, `.bo-sidebar`, `.bo-header`, `.bo-content` e `.bo-footer`.
3. Usar `gf-panel-topbar` como alias do único header visual, sem criar um segundo header.
4. Não carregar `header.php`, `footer.php`, `sidebar_admin.php` ou parciais de shell legado.
5. Não usar `ops-topbar`; buscas ficam dentro de `.shell-ops-worklist` quando houver worklist.
6. Não usar Bootstrap CSS/JS, Font Awesome, `style=""`, `gf-f18*`, `admin-shell`, `main-wrapper` ou `shell-*` fora dos identificadores de worklist aprovados durante a transição.
7. Corrigir o texto na origem; nenhum `strtr()` ou máscara CSS de mojibake.
8. Preservar IDs, `name`, `data-*`, métodos HTTP, URLs, CSRF, scripts de busca, seleção, fetch e modais.

## Linha de produção por lote

### 0. Inventário

- Fonte de verdade: rotas GET do `index.php`.
- Separar tela HTML, JSON/AJAX, POST, download e redirecionamento.
- Registrar controller, action, perfil, view efetiva e dependências CSS/JS.
- Não considerar HTTP 200 suficiente: uma tela redirecionada, vazia ou com erro de autorização fica pendente.

Saída: `qa/backoffice-route-inventory.json`.

### 1. Auditoria estática

Para cada view HTML, procurar:

- includes legados;
- classes `ops-topbar`, `main-wrapper`, `admin-shell`, `gf-f18*`;
- Bootstrap, Font Awesome e CSS proibido;
- `style=`, `<style>`, handlers inline e scripts sem nonce;
- mojibake (`Ã`, `Â`, `â€`, `ï¿½`, `�`);
- ausência de `backoffice_shell.php`.

Saída: lista de desvios por rota, sem editar ainda.

### 2. Classificação e ordem de migração

Migrar nesta ordem:

1. Shell e componentes comuns.
2. Qualidade: avaliações, proof-of-road, checklists e revisões.
3. Operação: central, alertas, despacho e ocorrências.
4. Financeiro: carteiras, saques, faturas e visão unificada.
5. Catálogos: serviços, capacidades, tarifas, compatibilidade e veículos.
6. Cadastros: usuários, prestadores, guinchos, oficinas e especialistas.
7. Sistema: logs, configurações, health, env e comunicados.
8. Marketing, planejamento, territórios e telas auxiliares.

Endpoints JSON, exports e ações POST não entram no shell, mas recebem teste de contrato próprio.

### 3. Refatoração da view

Aplicar o padrão:

```php
$perfil = 'admin';
$titulo_pagina = 'Título';
$breadcrumb = ['Seção', 'Título'];
$assets_css = ['/public/assets/css/pages/bo-admin-tela.css'];
ob_start();
// conteúdo da tela
$conteudo_pagina = ob_get_clean();
require __DIR__ . '/../layouts/backoffice_shell.php';
```

O conteúdo deve usar `bo-page`, `bo-page-title`, `bo-panel`, `bo-metrics`, `bo-grid`, `bo-field`, `bo-btn`, `bo-ledger`, `bo-status`, `bo-empty` e, quando aplicável, `.shell-ops-worklist`/workspace sem `ops-topbar`.

### 4. Refatoração CSS

- O conteúdo padrão é branco com texto escuro.
- O header `gf-panel-topbar` também é branco com texto escuro.
- Admin usa sidebar preta e destaque verde.
- Funcionário usa azul; gerente, índigo.
- Guincho usa identidade verde; oficina, laranja.
- Nenhuma página cria uma paleta independente.
- CSS de página contém somente layout específico; tokens ficam em `gf-bo-profiles.css` e componentes em `gf-bo-components.css`.

### 5. Verificação funcional

Para cada tela, testar:

- carregamento autenticado;
- filtros e busca;
- links e breadcrumbs;
- seleção de item/workspace;
- modais;
- formulários e CSRF;
- fetch/AJAX;
- paginação;
- exportação/download, quando houver;
- autorização de perfil.

### 6. Gate Playwright

Executar em 1440×900 e 390×844:

- HTTP 2xx/3xx esperado;
- exatamente um shell/header/sidebar/content/footer;
- header visível e sem faixa superior residual;
- `scrollWidth <= innerWidth`;
- nenhuma ocorrência de mojibake no texto renderizado;
- nenhum CSS proibido;
- nenhum console error não justificado;
- contraste legível em títulos, tabelas, placeholders e estados;
- busca dentro de `.shell-ops-worklist` quando existir.

### 7. Gate final do lote

Um lote só é concluído quando:

- todas as rotas do lote foram auditadas;
- todas as views efetivas foram identificadas;
- PHP lint passou;
- Playwright passou nos dois breakpoints;
- contratos de formulário e endpoints foram comparados antes/depois;
- desvios remanescentes foram registrados em `evidencias/`;
- não há regressão nas rotas já migradas.

## Matriz das rotas admin

As rotas fornecidas pelo `index.php` serão agrupadas por família e não por arquivo isolado. Rotas com a mesma tela base, como `/admin/logs` e `/admin/logs/export`, compartilham o mesmo contrato, mas o export continua sendo validado como download separado.

## Critério de parada

Não declarar o backoffice concluído por ter alguns shells corretos. A conclusão exige que todas as rotas HTML autenticadas da matriz estejam no shell único ou estejam explicitamente classificadas como endpoint/download, com evidência de teste.
