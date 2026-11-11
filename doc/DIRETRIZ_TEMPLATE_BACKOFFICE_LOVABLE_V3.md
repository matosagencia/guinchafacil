# Diretriz v3 — Template Administrativo Lovable para os 6 perfis do GuinchaFácil

**Projeto:** GuinchaFácil
**Data:** 2026-11-10
**Status:** Normativa — substitui todas as regras visuais de backoffice anteriores (ver §2.3)
**Escopo:** áreas autenticadas `/admin`, `/funcionario`, `/gerente`, `/guincho`, `/oficina`, `/cliente` (e os aliases `/especialista`, `/parceiro`)
**Fora de escopo:** site público, funil de pré-cotação, checkout público, templates de e-mail (estes seguem a Constituição §18.2)
**Destino no repositório:** `doc/DIRETRIZ_TEMPLATE_BACKOFFICE_LOVABLE_V3.md`

---

## 1. Diagnóstico: por que o backoffice virou uma quimera

A revisão do pacote (`guinchafacil.zip`) mostra que o tema Lovable foi *adicionado* ao projeto, mas nunca *substituiu* nada. Hoje convivem cinco sistemas visuais, e cada página pega um pedaço de cada um.

### 1.1 Números do estado atual

| Medida | Valor | Leitura |
|---|---|---|
| Views de backoffice (8 perfis) | 116 | — |
| Views que usam `backoffice_shell.php` | **1** (`admin/catalogo_servicos_capacidades.php`) | 115 ainda passam pelo `header.php` legado |
| A única view no shell novo usa | `ops-*`, `style=""` com `--theme-nav`, Font Awesome, `alert` Bootstrap | nem a referência está limpa |
| Folhas CSS carregadas pelo `header.php` para admin | ~16 (bootstrap, style, tokens, base, shell, gf-layout, backoffice-themes, themes/admin, admin-themes, backoffice-layout, admin-catalogo-shell, admin-central-operacional, admin-classic-shell, admin-reference, gf-admin-topbar-crumb, gf-admin-shell-unification) | camadas brigando por especificidade |
| Sistemas de tokens em paralelo | 5: `--theme-*` (726 usos), `--gf-*` (442), `--bo-*` (67), `--gf-bo-*` (45), `--admin-*` (32), mais os tokens shadcn do Lovable (`--primary`, `--card`…) | nenhum é fonte única |
| Classes Bootstrap nas views (`card`, `btn`, `row`, `col-*`) | 2.281 | o Lovable não usa Bootstrap |
| Ícones Font Awesome nas views | 1.312 | o Lovable usa Lucide (SVG inline) |
| Atributos `style=""` inline | 325 | cor e espaçamento fora do sistema |
| Views com `<style>` próprio | 13 | — |
| Famílias de classe de página | `ops-*` (1.121), `tow-*` (194), `gf-f18c-*` (123), `gf-ap-*`, `fin-*`, `dash-*`, `admin-*`… | cada rodada de agente criou a sua |

### 1.2 As contradições de tema (a causa raiz)

O mesmo perfil recebe temas diferentes conforme o arquivo que você lê:

| Perfil | `backoffice_shell.php` | `backoffice_contract.php` | `header.php` (`data-theme`) | Lovable original | Cauda de `gf-bo-lovable.css` | `backoffice-themes.css` | `admin-themes.css` / GUIA v1 |
|---|---|---|---|---|---|---|---|
| admin | `bo-operator` (verde) | `executive` | `executive` | executive = índigo | preto `#090d0b` + verde | marfim + tinta preta | **fundo preto** |
| guincho | `bo-driver` | `driver` | `driver` | lima `#d8cc20`, nav verde | **conteúdo verde-escuro** | conteúdo claro | **fundo verde-escuro** |
| oficina | `bo-workshop` | `workshop` | `workshop` | laranja, conteúdo claro | **conteúdo grafite** | conteúdo claro | **fundo marrom-escuro** |
| cliente | `bo-client` | `client` | `client` | *não existe no Lovable* | branco | branco | branco |
| funcionário | — (throw) | — | `dispatch` | operator | — | — | `--admin-*` preto |
| gerente | — (throw) | — | `dispatch` | — | — | — | `--admin-*` preto |

Ou seja: o shell aplica uma classe, o CSS posterior sobrescreve a cor de fundo para escuro, e outro CSS com outro prefixo de variável tenta clarear de novo. O resultado é exatamente o que você descreveu.

### 1.3 Outras falhas encontradas

1. **Sidebars híbridas.** `sidebar_cliente.php`, `sidebar_guincho.php` etc. declaram ao mesmo tempo `bo-sidebar sidebar gf-unified-sidebar gf-profile-sidebar gf-f18c-sidebar`. Cada classe puxa regras de uma geração diferente.
2. **Menus divergentes.** O menu do cliente dentro do shell não tem *Financeiro* nem *Oficinas*. O do guincho não tem *Ofertas*, *Operação* nem *Serviços que ofereço*. A sidebar legada tem esses itens.
3. **Funcionário e gerente não existem no shell.** O shell lança `RuntimeException('Papel invalido no shell')` para eles.
4. **Mojibake mascarado.** `backoffice_shell.php` aplica um `strtr()` com ~60 substituições sobre `$conteudo_pagina`, o que viola a própria regra do projeto (`LEITURA-OBRIGATORIA-MANUTENCAO-LAYOUT-E-MOJIBAKE.md`: "não mascarar"). Ele também troca `ï¿½` por `�`, o que *cria* mojibake.
5. **Contraste do próprio Lovable.** O Lovable usa `--primary` como cor de **texto** em `.bo-status`, `.bo-tabs .active`, `.bo-eta`, `.bo-net strong` e `.bo-segments .active`. Medido: verde sobre `--accent` = 2,47:1; lima do guincho sobre branco = 1,67:1; branco sobre verde no botão = 2,74:1. Tudo reprova no AA (4,5:1).

---

## 2. Fonte de verdade e precedência

### 2.1 O que o Lovable define (estrutura) — imutável

A fonte estrutural é o export em `lovable/` (arquivos *Monitoramento da Frota* e *Gestão financeira e repasses*), CSS `styles-SkD_E4XV.css`. Dele vêm:

- o shell: `.bo` > `.bo-sidebar` + `.bo-body` > `.bo-header` + `.bo-content` + `.bo-footer`;
- **superfícies sempre claras** (`--background` ≈ `#f5f7f9`, `--card` `#ffffff`), **sidebar escura** (`--bo-nav`) e **um acento por perfil** (`--primary` / `--accent`);
- a tipografia Manrope, base 12px, densidade compacta, raio de 5 a 6px e sombra `--bo-shadow` quase imperceptível;
- o catálogo de componentes `bo-*` (§7.1) e os breakpoints 1600/1250/950/700/370px.

> O Lovable **não tem tema escuro de conteúdo**. Toda regra que pinta `--background` ou `--card` de preto, verde-escuro ou grafite contradiz o template e está revogada.

### 2.2 O que o proprietário define (cor) — aplicada dentro do mecanismo Lovable

As "cores oficiais enviadas pelo usuário" (registradas na cauda de `gf-bo-lovable.css` e na DIRETRIZ v2) continuam válidas, mas **só nos dois pontos que o Lovable deixa variar**: a cor da sidebar (`--bo-nav`) e o acento (`--primary`/`--accent`). Elas nunca mais pintam o fundo do conteúdo.

### 2.3 Documentos e arquivos revogados para o backoffice

| Item | Situação |
|---|---|
| `GUIA_DESIGN_PAINEIS_GUINCHAFACIL.md` §2–§5, §7–§9, §13 (paletas, tipografia Segoe, componentes Bootstrap) | Revogado. Continuam valendo os princípios de UX: §1, §6 (semântica de status), §10.3 e §11.2 (fluxos), §14 itens 1, 5 e 6 |
| `ADITIVO_01_AJUSTES_PAINEIS.md` | Revogado no visual. Continuam valendo a regra A (sem texto técnico na UI), a D (um dado, um lugar) e a lista de IDs preservados |
| `doc/DIRETRIZ_NOVO_VISUAL_BACKOFFICE_GUINCHAFACIL.md` §2 (tokens `--bo-*` escuros) e §3 (tokens) | Revogado. §4–§8 (hubs, fluxos) continuam como especificação funcional |
| `PROTOTIPO_VISUAL_PERFIS_GUINCHAFACIL.html` | Referência histórica, sem valor normativo |
| `public/assets/css/admin-themes.css`, `backoffice-themes.css`, `themes/*.css`, `backoffice-layout.css`, `admin-catalogo-shell.css`, `pages/admin-classic-shell.css`, `pages/admin-reference.css`, `pages/gf-admin-*.css` | Proibidos no shell novo; serão removidos na onda final (§12) |
| Cauda de `gf-bo-lovable.css` (a partir de `/* Cores oficiais enviadas pelo usuario */`) | Migrada para `gf-bo-profiles.css` (§5) e apagada do arquivo canônico |

`doc/BACKOFFICE_SHELL_PARAMETROS.md` e `doc/LEITURA-OBRIGATORIA-MANUTENCAO-LAYOUT-E-MOJIBAKE.md` continuam válidos e são estendidos por este documento.

---

## 3. Regras de ouro

1. **Um shell.** Toda tela autenticada renderiza por `src/Views/layouts/backoffice_shell.php`. Nenhuma view inclui `header.php`, `footer.php` ou `sidebar_*.php`.
2. **Um namespace de tokens.** As views e os CSS de página consomem **apenas** os tokens do Lovable (`--background`, `--card`, `--foreground`, `--muted`, `--muted-foreground`, `--border`, `--primary`, `--primary-foreground`, `--accent`, `--bo-*`) e os 3 tokens de extensão desta diretriz (`--primary-ink`, `--primary-strong`, `--bo-touch`). Hex direto em view = reprovado.
3. **O perfil é decidido uma vez.** A sessão define o perfil; o contrato define o tema; o shell aplica a classe. Página nenhuma escolhe tema.
4. **Conteúdo sempre claro, acento por perfil.** A diferença entre perfis está na sidebar, no acento e na densidade. O fundo não muda.
5. **Componentes `bo-*`, não Bootstrap.** O shell não carrega `bootstrap.min.css`. Cada necessidade tem um componente `bo-*` (§7).
6. **Ícones Lucide via `gfBoIcon()`.** Font Awesome não é carregado no shell.
7. **Contratos de JS intocáveis.** `id`, `name`, `data-*`, CSRF, `action` e endpoints são preservados exatamente (lista mínima em §11.4).

---

## 4. Matriz oficial dos 6 perfis

### 4.1 Perfil → tema

| Perfil (UI) | `$_SESSION['user']['tipo']` | Preset Lovable de base | Classe aplicada | Sidebar `--bo-nav` | Acento `--primary` | Texto no acento `--primary-foreground` | Workspace (sidebar) | Rota inicial |
|---|---|---|---|---|---|---|---|---|
| **Admin** | `admin` | operator | `bo bo-operator bo-p-admin` | `#090d0b` preto | `#2fb34a` verde-marca | `#ffffff` | Central GuinchaFácil · Workspace operacional | `/admin/central` |
| **Empregado** | `funcionario` | finance | `bo bo-finance bo-p-funcionario` | `#111418` grafite (Lovable) | `#0284c7` azul (Lovable) | `#ffffff` | Atendimento · Solicitações e demandas | `/funcionario/dashboard` |
| **Gerente** | `gerente` | executive | `bo bo-executive bo-p-gerente` | `#111418` grafite (Lovable) | `#6366f1` índigo (Lovable) | `#ffffff` | Gerência · Aprovações e alçadas | `/gerente/dashboard` |
| **Guincho** | `guincho` | driver | `bo bo-driver bo-p-guincho` | `#0a1f11` verde-floresta | `#22c55e` verde-vivo | `#0a1f11` | Guincho parceiro · Operação do prestador | `/guincho/dashboard` |
| **Oficina** | `oficina` | workshop | `bo bo-workshop bo-p-oficina` | `#0f141c` grafite | `#f97316` laranja-mecânico | `#1c1008` | Oficina parceira · Pátio e orçamentos | `/oficina/dashboard` |
| **Cliente** | `cliente` | *(extensão GF)* | `bo bo-client bo-p-cliente` | `#ffffff` com borda | `#2fb34a` verde-marca | `#ffffff` | Área do cliente · Minhas solicitações | `/cliente/dashboard` |
| *alias* Especialista | `especialista` | driver | `bo bo-driver bo-p-especialista` | igual guincho | igual guincho | igual guincho | Especialista · Atendimento no local | `/especialista/dashboard` |
| *alias* Parceiro | `parceiro` | operator | `bo bo-operator bo-p-parceiro` | `#111418` | `#2fb34a` | `#ffffff` | Parceiro · Indicações | `/parceiro/dashboard` |

Por que essa distribuição:
- **Admin, guincho, oficina e cliente** mantêm as cores oficiais já enviadas pelo proprietário.
- **Empregado e gerente** usam presets que o próprio Lovable já entregou (*finance* azul, *executive* índigo). Isso evita inventar cores e dá uma pista visual de alçada: quem cria demanda (azul) e quem aprova (índigo) nunca confundem a tela em que estão.
- **Guincho com conteúdo claro** é intencional: sob sol forte, fundo claro com sidebar escura legível é mais seguro que fundo escuro. O conforto noturno vem da sidebar escura e dos alvos grandes (§9).

> Decisões a ratificar em `doc/DECISOES.md`: D-1, D-2, D-3 (§14).

### 4.2 Densidade por perfil

| Perfil | Densidade | Alvo de toque mínimo | Tamanho de texto base |
|---|---|---|---|
| Admin, Empregado, Gerente | Alta (Lovable padrão) | 36px | 12px |
| Oficina | Média | 40px | 12–13px |
| Cliente | Confortável | 44px | 13px |
| Guincho / Especialista | Operação em movimento | **52px** | 13px; CTA 16px |

---

## 5. Arquitetura de CSS

### 5.1 Arquivos permitidos no shell e ordem de carga (exaustiva)

```text
1. https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800
2. /public/assets/css/gf-bo-lovable.css        ← CANÔNICO: export Lovable + slots PHP. Congelado.
3. /public/assets/css/gf-bo-profiles.css       ← NOVO: tokens dos 6 perfis + correções AA.
4. /public/assets/css/gf-bo-components.css     ← NOVO: equivalentes PHP dos utilitários shadcn/Tailwind.
5. /public/assets/css/components/gf-footer-payments.css
6. /public/assets/css/pages/bo-<perfil>-<tela>.css   ← opcional, via $assets_css, só layout.
```

Nada além disso. Em especial, **proibido no shell**: `bootstrap.min.css`, `fontawesome/all.min.css`, `style.css`, `tokens.css`, `base.css`, `shell.css`, `gf-layout.css`, `backoffice-themes.css`, `admin-themes.css`, `backoffice-layout.css`, `themes/*.css`, `admin-catalogo-shell.css`, `pages/admin-central-operacional.css`, `pages/admin-classic-shell.css`, `pages/admin-reference.css`, `pages/gf-admin-*.css`, `pages/gf-f18*.css`.

### 5.2 Regras por arquivo

| Arquivo | Pode | Não pode |
|---|---|---|
| `gf-bo-lovable.css` | Ser regenerado a partir de um novo export Lovable. Conter os "slots PHP" (regras de `.bo-header-start`, `.bo-workspace-copy`, `.bo-notification-*`, `.bo-header-button`, `.bo-empty-state`) | Receber cor, override de perfil ou correção de página |
| `gf-bo-profiles.css` | Declarar variáveis por `.bo-p-*` e as 4 correções de contraste (§6) | Declarar layout, `display`, `grid`, medidas de componente |
| `gf-bo-components.css` | Definir `bo-btn`, `bo-field`, `bo-panel`, `bo-grid`, `bo-alert`, `bo-dialog`, `bo-kpis` etc. usando **só tokens** | Usar hex, `!important` ou seletor de perfil (exceto `.bo-p-guincho` para o alvo de toque) |
| `pages/bo-*.css` | Grid e posicionamento específicos da tela | Cor, fonte, raio, sombra, tokens novos, `!important` |

### 5.3 Ação imediata em `gf-bo-lovable.css`

Cortar tudo a partir da linha `/* Cores oficiais enviadas pelo usuario, sobrepondo SOMENTE variaveis de tema Lovable. */` até o fim do bloco de overrides de perfil (`.bo-operator`, `.bo-admin`, `.bo-driver`, `.bo-client`, `.bo-workshop`, `:hover`, `[aria-current]`). Manter apenas o bloco `/* Slots PHP */` com as regras estruturais. O conteúdo de cor passa para o arquivo da §5.4.

### 5.4 `public/assets/css/gf-bo-profiles.css` (conteúdo normativo)

```css
/* GuinchaFácil — tokens dos perfis do backoffice.
 * Diretriz v3 §4/§6. Só variáveis + correções AA. Nenhum layout aqui. */

/* ---- Extensões globais (valem para todo .bo) ---- */
.bo {
  --primary-ink: var(--primary);         /* acento usado como TEXTO (AA ≥ 4,5:1) */
  --primary-strong: var(--primary);      /* acento sob texto branco (botão, nav ativo) */
  --bo-touch: 36px;                      /* altura mínima de controle */
  --bo-danger-ink: #b42318;
  --bo-warning-ink: #8a5a00;
  --bo-info-ink: #0369a1;
}

/* ---- Admin: preto + verde-marca ---- */
.bo.bo-p-admin {
  --bo-nav: #090d0b;
  --bo-nav-fg: #aeb5bd;
  --bo-nav-bright: #f8f8f8;
  --primary: #2fb34a;
  --primary-foreground: #ffffff;
  --primary-strong: #1a7f37;             /* branco sobre ele = 5,08:1 */
  --primary-ink: #1f7a34;                /* sobre branco 5,40 · sobre accent 4,87 */
  --accent: #e8f7ec;
}

/* ---- Empregado (funcionario): preset Lovable "finance" ---- */
.bo.bo-p-funcionario {
  --primary: #0284c7;
  --primary-foreground: #ffffff;
  --primary-strong: #0369a1;             /* 5,93:1 */
  --primary-ink: #0369a1;                /* sobre accent 5,18 */
  --accent: #e1f2ff;
}

/* ---- Gerente: preset Lovable "executive" ---- */
.bo.bo-p-gerente {
  --primary: #6366f1;
  --primary-foreground: #ffffff;
  --primary-strong: #4f46e5;             /* 6,29:1 */
  --primary-ink: #4f46e5;                /* sobre accent 5,49 */
  --accent: #ebefff;
}

/* ---- Guincho e Especialista: verde-floresta + verde-vivo ---- */
.bo.bo-p-guincho,
.bo.bo-p-especialista {
  --bo-nav: #0a1f11;
  --bo-nav-fg: #c8e6d0;
  --bo-nav-bright: #ffffff;
  --primary: #22c55e;
  --primary-foreground: #0a1f11;         /* 7,57:1 */
  --primary-strong: #22c55e;             /* texto escuro sobre ele: dispensa escurecer */
  --primary-ink: #166534;                /* sobre accent 6,23 */
  --accent: #e3f4e7;
  --bo-touch: 52px;
}

/* ---- Oficina: grafite + laranja-mecânico ---- */
.bo.bo-p-oficina {
  --bo-nav: #0f141c;
  --bo-nav-fg: #c9ced6;
  --bo-nav-bright: #fff7ed;
  --primary: #f97316;
  --primary-foreground: #1c1008;         /* 6,65:1 */
  --primary-strong: #f97316;
  --primary-ink: #c2410c;                /* sobre branco 5,18 · sobre accent 4,63 */
  --accent: #fff0df;
  --bo-touch: 40px;
}

/* ---- Cliente: sidebar clara + verde-marca ---- */
.bo.bo-p-cliente {
  --background: #f8fafc;
  --bo-nav: #ffffff;
  --bo-nav-fg: #294536;
  --bo-nav-bright: #112820;
  --primary: #2fb34a;
  --primary-foreground: #ffffff;
  --primary-strong: #1a7f37;
  --primary-ink: #176c30;                /* sobre accent 5,88 */
  --accent: #e8f7ec;
  --bo-touch: 44px;
}
.bo.bo-p-cliente .bo-sidebar { border-right: 1px solid var(--border); }
.bo.bo-p-cliente .bo-nav.active,
.bo.bo-p-cliente .bo-nav[aria-current="page"] { background: var(--accent); color: var(--primary-ink); }

/* ---- Parceiro: grafite Lovable + verde ---- */
.bo.bo-p-parceiro {
  --primary: #2fb34a; --primary-foreground: #ffffff;
  --primary-strong: #1a7f37; --primary-ink: #1f7a34; --accent: #e8f7ec;
}

/* ---- Marca na sidebar segue o acento do perfil ---- */
.bo .bo-brand { color: var(--bo-nav-bright); }
.bo .bo-brand span,
.bo .bo-brand .brand-period { color: var(--primary); }
.bo.bo-p-cliente .bo-brand span,
.bo.bo-p-cliente .bo-brand .brand-period { color: var(--primary-ink); }
```

---

## 6. Correções de contraste sobre o Lovable (únicas permitidas)

O visual do Lovable é preservado. Só trocamos *qual* variável pinta texto nos 4 lugares que reprovam no AA. Estas regras vão no fim de `gf-bo-profiles.css`:

```css
/* AA-1: acento usado como texto → --primary-ink */
.bo .bo-status,
.bo .bo-eyebrow,
.bo .bo-eta,
.bo .bo-net strong,
.bo .bo-segments .active,
.bo .bo-tabs > button.active,
.bo .bo-tabs > a.active,
.bo .bo-inline-status,
.bo .bo-toast,
.bo .bo-icon-block,
.bo .bo-avatar { color: var(--primary-ink); }

/* AA-2: acento sob texto branco → --primary-strong */
.bo .bo-nav.active,
.bo .bo-nav[aria-current="page"] { background: var(--primary-strong); color: var(--primary-foreground); }

/* AA-3: status semânticos com tinta própria */
.bo .bo-status.danger  { color: var(--bo-danger-ink); }
.bo .bo-status.warning { color: var(--bo-warning-ink); }
.bo .bo-status.info    { color: var(--bo-info-ink); }

/* AA-4: o guincho mantém a regra Lovable de status sobre acento claro */
.bo.bo-p-guincho .bo-status { color: var(--primary-ink); }
```

Ícones, bordas, pontos de mapa, timeline e barras de progresso continuam usando `--primary` puro, porque são elementos gráficos (exigência 3:1, atendida).

---

## 7. Componentes

### 7.1 Componentes canônicos do Lovable (usar como estão)

| Componente | Classes | Uso |
|---|---|---|
| Título de página | `.bo-page-title` > `div` (`.bo-eyebrow`, `h1`, `p`) + `.bo-live` | Topo de toda tela. Eyebrow = módulo, h1 = tela, p = 1 linha voltada ao usuário |
| Faixa de KPIs | `.bo-metrics` > `.bo-metric` (`span` rótulo+ícone, `strong` valor, `small` detalhe); `.bo-alert-metric` | Indicadores. 8 colunas padrão; `.bo-finance-metrics` = 7 |
| Grade operacional | `.bo-operation-grid` (310px + 1fr) | Lista à esquerda + ficha à direita |
| Painéis | `.bo-orders`, `.bo-incident`, `.bo-ledger`, `.bo-ranking`, `.bo-statement` + `.bo-panel-heading` | Todo bloco de conteúdo |
| Busca | `.bo-search` (ícone + input) | Topo de listas |
| Segmentos | `.bo-segments` (botões, `.active`) | Filtros rápidos (Todos / Urgentes / Concluídos) |
| Item de lista | `.bo-order` (`.selected`), `.bo-order-top`, `.bo-order-bottom` | Fila de pedidos, de ofertas, de demandas |
| Ficha | `.bo-incident-heading`, `.bo-incident-id`, `.bo-icon-block`, `.bo-customer-bar` | Cabeçalho do detalhe |
| Abas | `.bo-tabs` (`button.active`, `span` à direita) | Navegação interna da ficha ou do hub |
| Mapa | `.bo-map-area` > `.bo-incident-map` + `.bo-map-overlay` + `.bo-map-key`; marcadores `.bo-map-marker`, `.bo-truck-marker` | Leaflet. O `id="map"` existente vai no `.bo-incident-map` |
| Rota | `.bo-route-details` + `.bo-route-dot` (`.destination`) | Origem/destino |
| Despacho / ações | `.bo-dispatch`, `.bo-provider`, `.bo-eta`, `.bo-dispatch-actions` (primeiro botão = principal), `.bo-cancel` | Barra de ações da ficha |
| Linha do tempo | `.bo-timeline` > `div` (`.bo-timeline-dot`, `small`, `p`) | Histórico de eventos |
| Chat | `.bo-chat`, `.bo-chat-person`, `.bo-chat-messages`, `.bo-message.incoming/.outgoing`, `.bo-chat-form` | Chat do pedido |
| Filtros | `.bo-filters` > `label` (`span` + `select`) + `button` | Barra de filtros de tabela |
| Tabela | `.bo-ledger` > `.bo-table-scroll` > `table`; `.bo-number`; `tr.selected` | Toda listagem tabular |
| Extrato | `.bo-statement-client`, `.bo-statement-total`, `.bo-breakdown` (`dl`), `.bo-breakdown-subtotal`, `.bo-net`, `.bo-payout`, `.bo-statement-note` | Detalhe financeiro e repasse |
| Ranking | `.bo-ranking-grid`, `.bo-rank-row`, `.bo-rank-number` | Top prestadores, top oficinas |
| Avisos | `.bo-toast` (com botão de fechar), `.bo-inline-status` | Flash e status inline |
| Status | `.bo-status` (+ `.danger`, `.info`, `.warning`) | Pílulas de estado |
| Vazio | `.bo-empty` | Listas vazias |

### 7.2 Componentes de extensão — `public/assets/css/gf-bo-components.css`

O Lovable desenha botões, campos e diálogos com utilitários Tailwind/shadcn que não existem no PHP. Estes componentes os reproduzem com os mesmos valores (raio `--radius`, `h-9`, `text-sm`, `font-medium`). Especificação normativa:

```css
/* Botão (shadcn Button) */
.bo-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:var(--bo-touch);
  padding:0 16px;border-radius:5px;border:1px solid transparent;font-family:inherit;font-size:11px;font-weight:600;line-height:1;cursor:pointer;
  white-space:nowrap;text-decoration:none;transition:background-color .15s,color .15s,border-color .15s}
.bo-btn svg{width:16px;height:16px;flex-shrink:0}
.bo-btn:focus-visible{outline:2px solid var(--ring);outline-offset:2px}
.bo-btn:disabled,.bo-btn[aria-disabled="true"]{opacity:.5;pointer-events:none}
.bo-btn--primary{background:var(--primary-strong);color:var(--primary-foreground)}
.bo-btn--primary:hover{filter:brightness(.94)}
.bo-btn--outline{background:var(--card);border-color:var(--border);color:var(--foreground)}
.bo-btn--outline:hover{background:var(--accent);color:var(--primary-ink)}
.bo-btn--ghost{background:transparent;color:var(--foreground)}
.bo-btn--ghost:hover{background:var(--accent)}
.bo-btn--danger{background:var(--card);border-color:var(--border);color:var(--bo-danger-ink)}
.bo-btn--danger:hover{background:var(--bo-danger-soft)}
.bo-btn--sm{min-height:28px;padding:0 10px;font-size:10px}
.bo-btn--block{width:100%}
.bo-btn--cta{min-height:max(var(--bo-touch),48px);font-size:14px;font-weight:800;padding:0 24px}

/* Campos (shadcn Input/Select/Textarea/Label) */
.bo-field{display:flex;flex-direction:column;gap:6px;min-width:0}
.bo-field > span,.bo-field > label{color:var(--muted-foreground);font-size:9px;font-weight:600}
.bo-input,.bo-select,.bo-textarea{width:100%;min-height:var(--bo-touch);padding:0 10px;border:1px solid var(--input);
  border-radius:5px;background:var(--card);color:var(--foreground);font-family:inherit;font-size:11px;font-weight:500;line-height:1.4}
.bo-textarea{padding:10px;min-height:96px;resize:vertical}
.bo-input:focus,.bo-select:focus,.bo-textarea:focus{outline:none;border-color:var(--ring);
  box-shadow:0 0 0 3px color-mix(in oklab,var(--ring) 18%,transparent)}
.bo-field.is-invalid .bo-input,.bo-field.is-invalid .bo-select{border-color:var(--bo-danger)}
.bo-field-hint{color:var(--muted-foreground);font-size:9px}
.bo-field-error{color:var(--bo-danger-ink);font-size:9px}
.bo-check{display:flex;align-items:center;gap:8px;font-size:11px}

/* Painel genérico (mesmo visual de .bo-orders/.bo-ledger) */
.bo-panel{background:var(--card);border:1px solid var(--border);border-radius:6px;box-shadow:var(--bo-shadow);min-width:0;overflow:hidden}
.bo-panel-body{padding:20px}
.bo-panel-footer{border-top:1px solid var(--border);padding:15px 20px;display:flex;gap:9px;justify-content:flex-end}

/* Grades */
.bo-grid{display:grid;gap:20px;align-items:start}
.bo-grid--2{grid-template-columns:repeat(2,minmax(0,1fr))}
.bo-grid--3{grid-template-columns:repeat(3,minmax(0,1fr))}
.bo-grid--4{grid-template-columns:repeat(4,minmax(0,1fr))}
.bo-grid--main-side{grid-template-columns:minmax(0,1fr) 340px}
.bo-stack{display:flex;flex-direction:column;gap:20px}
.bo-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px}
.bo-form-grid .bo-span-2{grid-column:1/-1}
@media (width<=950px){.bo-grid--3,.bo-grid--4{grid-template-columns:repeat(2,minmax(0,1fr))}.bo-grid--main-side{grid-template-columns:minmax(0,1fr)}}
@media (width<=700px){.bo-grid--2,.bo-grid--3,.bo-grid--4,.bo-form-grid{grid-template-columns:minmax(0,1fr)}}

/* KPIs com número de colunas variável (mantém visual .bo-metrics) */
.bo-metrics[style*="--bo-cols"]{grid-template-columns:repeat(var(--bo-cols),minmax(0,1fr))}

/* Alertas (variação do .bo-inline-status) */
.bo-alert{display:flex;gap:9px;align-items:flex-start;padding:12px 16px;border:1px solid var(--border);border-radius:5px;font-size:11px;margin-bottom:15px}
.bo-alert--info{background:var(--bo-info-soft);color:var(--bo-info-ink)}
.bo-alert--success{background:var(--accent);color:var(--primary-ink)}
.bo-alert--warning{background:var(--bo-warning-soft);color:var(--bo-warning-ink)}
.bo-alert--danger{background:var(--bo-danger-soft);color:var(--bo-danger-ink)}

/* Diálogo nativo (substitui o modal Bootstrap) */
.bo-dialog{border:1px solid var(--border);border-radius:8px;padding:0;width:min(520px,calc(100vw - 24px));
  background:var(--card);color:var(--foreground);box-shadow:0 20px 60px oklch(15% 0 0/.18)}
.bo-dialog::backdrop{background:oklch(15% .01 255/.45)}
.bo-dialog-head{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;border-bottom:1px solid var(--border)}
.bo-dialog-head h2{font-size:13px;font-weight:750}
.bo-dialog-body{padding:20px;font-size:11px;line-height:1.6}
.bo-dialog-foot{display:flex;gap:9px;justify-content:flex-end;padding:15px 20px;border-top:1px solid var(--border)}

/* Utilitários mínimos (lista fechada) */
.bo-muted{color:var(--muted-foreground)}
.bo-ink{color:var(--primary-ink)}
.bo-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.bo-row--between{justify-content:space-between}
.bo-sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
```

Utilitários fora dessa lista fechada não podem ser criados. Se faltar algo, abre-se um item em `doc/DECISOES.md` antes.

### 7.3 Tabela de tradução: legado → `bo-*`

| Legado (remover) | Substituto |
|---|---|
| `.main-wrapper`, `.main-content`, `nav.navbar`, `.gf-unified-topbar`, `.gf-admin-topbar`, `.ops-topbar` | Shell (`backoffice_shell.php`) |
| `.page-header`, `.page-head`, `.page-title`, `.page-subtitle`, `.eyebrow` | `.bo-page-title` + `.bo-eyebrow` |
| `.row` / `.col-*` / `g-*` | `.bo-grid--N`, `.bo-grid--main-side`, `.bo-operation-grid`, `.bo-stack` |
| `.card`, `.card-header`, `.card-body`, `.dash-card`, `.tow-card`, `.ops-panel`, `.fin-*` | `.bo-panel` + `.bo-panel-heading` + `.bo-panel-body` |
| `.stat-card`, `.dash-stat`, `.tow-stat`, `.ops-summary`/`.ops-metric` | `.bo-metrics` > `.bo-metric` (via `components/bo_metrics.php` genérico, §8.4) |
| `.btn .btn-primary` | `.bo-btn .bo-btn--primary` |
| `.btn-outline-*`, `.btn-secondary`, `.btn-light` | `.bo-btn--outline` |
| `.btn-outline-danger`, `.btn-danger` | `.bo-btn--danger` (+ `.bo-dialog` de confirmação) |
| `.btn-socorro` | `.bo-btn .bo-btn--primary .bo-btn--cta` (1 por tela) |
| `.form-control`, `.form-select`, `.form-label`, `.mb-3` | `.bo-field` > `span` + `.bo-input`/`.bo-select`/`.bo-textarea` dentro de `.bo-form-grid` |
| `.table`, `.table-hover`, `.table-responsive` | `.bo-ledger` > `.bo-table-scroll` > `table` |
| `.badge`, `.badge-<status>`, `.ops-badge--*`, `text-bg-*` | `.bo-status` + variante (§8.3) |
| `.alert alert-*` | `.bo-alert--*` ou `.bo-toast` (flash) |
| `.modal` + `data-bs-toggle` | `<dialog class="bo-dialog">` + `data-bo-open="#id"` / `data-bo-close` |
| `.nav-tabs`, `admin_module_tabs`, `.gf18e-*` tabs | `.bo-tabs` |
| `.status-steps` | `.bo-timeline` (vertical) ou `.bo-segments` não clicáveis (horizontal) |
| `.empty-state` | `.bo-empty` |
| `<i class="fas fa-*">` | `gfBoIcon('nome')` (§10) |
| `style="color:#…"`, `style="padding:…"` | componente ou `pages/bo-*.css` |

---

## 8. Shell e contrato

### 8.1 Novo `src/Views/layouts/backoffice_contract.php`

```php
<?php
/** Contrato único de apresentação dos backoffices. Diretriz v3 §4. */
return [
  'admin'        => ['preset'=>'operator',  'workspace'=>['Central GuinchaFácil','Workspace operacional'], 'home'=>'/admin/central'],
  'funcionario'  => ['preset'=>'finance',   'workspace'=>['Atendimento','Solicitações e demandas'],        'home'=>'/funcionario/dashboard'],
  'gerente'      => ['preset'=>'executive', 'workspace'=>['Gerência','Aprovações e alçadas'],              'home'=>'/gerente/dashboard'],
  'guincho'      => ['preset'=>'driver',    'workspace'=>['Guincho parceiro','Operação do prestador'],     'home'=>'/guincho/dashboard'],
  'oficina'      => ['preset'=>'workshop',  'workspace'=>['Oficina parceira','Pátio e orçamentos'],        'home'=>'/oficina/dashboard'],
  'cliente'      => ['preset'=>'client',    'workspace'=>['Área do cliente','Minhas solicitações'],        'home'=>'/cliente/dashboard'],
  'especialista' => ['preset'=>'driver',    'workspace'=>['Especialista','Atendimento no local'],          'home'=>'/especialista/dashboard'],
  'parceiro'     => ['preset'=>'operator',  'workspace'=>['Parceiro','Indicações'],                        'home'=>'/parceiro/dashboard'],
];
```

### 8.2 Alterações obrigatórias em `backoffice_shell.php`

1. Trocar o mapa `$classes` pelo contrato acima. Classe do root: `bo bo-{preset} bo-p-{perfil}`, mais `data-gf-profile="{perfil}"`.
2. O perfil vem **da sessão**. `$perfil` passado pela view só é aceito se for igual ao da sessão, ou se a sessão for `admin` (preview, §8.5).
3. **Remover o `strtr()` de mojibake.** Corrigir a string na origem (arquivo salvo em UTF-8 sem BOM). O gate da §13 bloqueia qualquer ocorrência.
4. `<link>`s exatamente como a §5.1.
5. Menus: mover `$menuBase` para `src/Views/layouts/backoffice_menus.php`, com os 6 perfis completos (§8.3).
6. Atualizar o `<select data-bo-theme>`: só aparece para admin, lista os 6 perfis e troca apenas a classe `bo-p-*` (e o preset) no root. O rótulo é "Pré-visualizar perfil".
7. Ativo do menu: comparar o prefixo da rota, não a igualdade exata (hoje `/admin/pedido/123` não acende "Pedidos").
8. Acrescentar o link "Sair" (`/logout`, `data-confirm-logout`) ao lado do avatar, o que elimina o script injetado pelo `header.php`.
9. Incluir o modal de sessão expirada (`#sessionExpiredModal`) reescrito como `<dialog class="bo-dialog" id="sessionExpiredModal">`, preservando `data-session-login`.
10. Carregar só `gf-bo-shell.js`, `gf-bo-dialog.js` (novo, ~40 linhas: `data-bo-open`, `data-bo-close`, `Esc`, foco de retorno), `session-manager.js` e os scripts que a view pedir em `$assets_js`.

### 8.3 Menus por perfil (`backoffice_menus.php`)

A sidebar segue o padrão Lovable: rótulo de seção (`.bo-nav-label`) e itens `.bo-nav` com ícone Lucide. Cada item tem `match` (prefixos que acendem o item).

| Perfil | Seção PRINCIPAL | Seção CONTA / WORKSPACE |
|---|---|---|
| Admin | Criar pedido (`plus-circle`) · Operação `/admin/central` (`radio-tower`) · Pessoas e parceiros `/admin/usuarios` (`users`) · Financeiro `/admin/financeiro` (`wallet`) · Catálogo e preços `/admin/catalogo-servicos/tipos` (`wrench`) · Território `/admin/cidades` (`map`) · Marketing `/admin/marketing` (`megaphone`) · Qualidade `/admin/avaliacoes` (`shield-check`) | Sistema `/admin/configuracoes` (`settings`) · Pré-visualizar: Guincho / Oficina / Cliente (`eye`, com selo "Preview") |
| Empregado | Painel `/funcionario/dashboard` · Pedidos `/funcionario/pedidos` · Financeiro `/funcionario/financeiro` · Minhas demandas `/funcionario/demandas` (`send`) | Nova demanda `/funcionario/demandas/nova` |
| Gerente | Painel `/gerente/dashboard` · Demandas pendentes `/gerente/demandas` (`clipboard-check`) · Decisões de repasse (rota já servida pelo `GFPayoutDecisionController`; confirmar caminho no roteador) | — |
| Guincho | Painel · Ofertas (`bell`, âncora `#fila-ofertas`) · Histórico · Financeiro | Operação `/guincho/operacao` (`truck`) · Serviços que ofereço `/guincho/capacidades` (`toolbox`) · Tornar-se guincheiro (condicional, mesma regra da sidebar atual) · Meu perfil |
| Oficina | Painel · Ofertas `/oficina/pedidos` · Histórico · Financeiro | Meu perfil |
| Cliente | Painel · Pedir socorro `/cliente/pedido/novo` · Histórico · Financeiro | Veículos · Oficinas · Meu perfil |

Os prefixos de ativação do admin são os mesmos de `components/admin_nav_operacional.php` (`$hubs`). Copiar, não reinventar.

### 8.4 Padrão obrigatório de view

```php
<?php
/** @var array $pedidos  dados vindos do controller */
$perfil          = 'guincho';                                   // conferido contra a sessão no shell
$titulo_pagina   = 'Histórico';
$breadcrumb      = ['Guincho', 'Histórico'];
$assets_css      = ['/public/assets/css/pages/bo-guincho-historico.css']; // opcional
$assets_js       = ['/public/assets/js/atendimento-status.js'];           // opcional
ob_start(); ?>
<div class="bo-page-title">
  <div><p class="bo-eyebrow">OPERAÇÃO</p><h1>Histórico de atendimentos</h1>
       <p>Todos os atendimentos que você concluiu ou cancelou.</p></div>
</div>
<?php $metricas = [/* chave => ['rotulo','icone','valor','detalhe'] */];
      require __DIR__ . '/../components/bo_kpis.php'; ?>
<section class="bo-ledger"> … </section>
<?php $conteudo_pagina = ob_get_clean();
require __DIR__ . '/../layouts/backoffice_shell.php';
```

`components/bo_metrics.php` continua exclusivo da Central (8 métricas fixas). Para as demais telas, criar `components/bo_kpis.php`, genérico, que recebe a lista e aplica `style="--bo-cols:N"`. Mesma regra: valor ausente vira "—", nunca 0 inventado.

### 8.5 Admin visitando áreas de outros perfis

- **Navegação real** (admin abre `/guincho/dashboard`): mantém `bo-p-admin`. O tema segue o autenticado (regra já existente no `header.php`).
- **Pré-visualização** (seletor do header): muda só a classe visual no cliente. Nenhuma permissão muda; um selo "Pré-visualização" fica visível no `.bo-header`.

---

## 9. Blueprint por perfil (composição com componentes Lovable)

Todas as telas seguem a mesma anatomia: `.bo-page-title` → (opcional) `.bo-toast` de flash → `.bo-metrics` → conteúdo em `.bo-grid*` ou `.bo-operation-grid`. As regras de UX do GUIA v1 continuam valendo: **uma ação dominante por tela, estado sempre visível, linguagem calma e sem termos técnicos**.

### 9.1 Admin (`bo-p-admin`)
- **Central `/admin/central`:** referência exata do Lovable *Monitoramento*: `bo_metrics.php` com 8 métricas + `.bo-operation-grid` (fila `.bo-orders` / ficha `.bo-incident` com abas Mapa · Linha do tempo · Chat) + `.bo-dispatch-actions`.
- **Financeiro `/admin/financeiro`:** referência exata do Lovable *Gestão financeira*: `.bo-finance-metrics` (7), `.bo-filters`, `.bo-ranking-grid`, `.bo-finance-grid` (`.bo-ledger` + `.bo-statement` com `.bo-breakdown` e `.bo-payout`).
- **Listas** (pedidos, usuários, guinchos, faturas, saques, logs): `.bo-filters` + `.bo-ledger`. Detalhe na mesma página por `.bo-operation-grid`, ou em `.bo-statement` lateral (`.bo-grid--main-side`).
- **Formulários** (usuarioform, guinchoform, servicoform, configurações, env): `.bo-panel` > `.bo-panel-heading` + `.bo-panel-body` > `.bo-form-grid` + `.bo-panel-footer`. Ações sensíveis (cancelar, conclusão manual) abrem `.bo-dialog` com o campo de senha do admin, preservando `name`/`id`.

### 9.2 Empregado (`bo-p-funcionario`)
- **Painel:** `.bo-metrics` com 5 colunas (Pendentes, Aguardando 2ª aprovação, Aprovadas, Executadas, Rejeitadas) + `.bo-alert--info` "Nada é executado na hora: toda demanda passa por um gerente." + CTA `.bo-btn--primary` "Nova demanda".
- **Demandas:** `.bo-operation-grid`: lista `.bo-order` com `.bo-status` do estado; ficha à direita com `.bo-timeline` das decisões.
- **Pedidos e financeiro:** padrão de lista do admin, só leitura.

### 9.3 Gerente (`bo-p-gerente`)
- **Painel:** `.bo-metrics` com 3–4 colunas (Pendentes, Aguardando 2ª aprovação, Pendentes há +24h com `.bo-alert-metric`).
- **Demanda (detalhe):** `.bo-incident` com cabeçalho (`.bo-incident-id`, solicitante, valor em `.bo-customer-bar strong`), `.bo-timeline` de alçadas e `.bo-dispatch-actions` com **Aprovar** (`--primary`, à direita) e **Rejeitar** (`--danger`). Quando o gerente é o próprio criador, ou quando já decidiu a 1ª alçada, os botões ficam desabilitados com `.bo-field-hint` explicando.

### 9.4 Guincho (`bo-p-guincho`, alvo de toque 52px)
- **Painel:** `.bo-page-title` com o **toggle Online/Offline** à direita (preservar `id="toggleDisponivel"`; ver §9.7) + `.bo-metrics` com 4 colunas (Atendimentos hoje, Ganho hoje, Nota média, Km hoje) + `.bo-operation-grid`: fila de ofertas `.bo-orders` (preservar `#pedidosDisponiveisContainer`) e mapa `.bo-incident` (preservar `#map`).
- **Oferta:** `.bo-order` com valor líquido em destaque; ações `.bo-dispatch-actions`: Recusar (`--outline`) à esquerda, **Aceitar** (`--primary --cta`) à direita.
- **Atendimento:** `.bo-incident` com `.bo-customer-bar` (cliente, telefone), `.bo-route-details`, mapa e **um** botão de avanço em `.bo-dispatch-actions` (Cheguei ao local → Iniciar reboque → Finalizar corrida). A máquina de estados do GUIA v1 §11.2 é mantida.
- **Financeiro:** carteira da Constituição §12 em `.bo-finance-metrics` (Em compensação · Disponível · Reservado · Pago) + `.bo-ledger` de movimentos + `.bo-statement` com `.bo-payout` "Solicitar saque via Pix". Os textos constitucionais §12.3 aparecem em `.bo-statement-note`.

### 9.5 Oficina (`bo-p-oficina`)
- **Painel / Pátio:** `.bo-metrics` (A caminho, No pátio, Em orçamento, Prontos) + `.bo-grid--4` com uma `.bo-panel` por coluna do pátio (kanban da DIRETRIZ v2 §6), itens `.bo-order`.
- **Ofertas:** `.bo-operation-grid` (lista / detalhe com Aceitar orçamento).
- **Financeiro:** mesmo padrão do guincho.

### 9.6 Cliente (`bo-p-cliente`, alvo de toque 44px)
- **Painel:** se houver pedido ativo, `.bo-toast` vivo no topo ("Guincho a caminho — chegada em 12 min" + Acompanhar), preservando `#statusBannerCliente`. Depois o `.bo-page-title` com o **único** CTA `.bo-btn--primary --cta` "Pedir socorro". Por fim `.bo-metrics` com 4 colunas clicáveis (Pedidos, Concluídos, Veículos, Oficinas) e `.bo-grid--main-side` (últimos pedidos `.bo-ledger`, mapa).
- **Acompanhamento `pedidostatus`:** `.bo-incident` completo: status, `.bo-map-area` com rota viva, `.bo-provider` (guincheiro, placa, nota, ligar), `.bo-chat`, `.bo-timeline` e cancelamento em `.bo-dialog` **com preview da taxa antes de confirmar**.
- **Formulários** (veículo, oficina, perfil): `.bo-panel` centralizada com largura máxima de 720px.

### 9.7 Componentes de domínio que precisam ser recriados em `bo-*`

| Componente | Implementação Lovable-compatível |
|---|---|
| Toggle disponibilidade | `<button class="bo-btn bo-btn--outline" role="switch" aria-checked="…" id="toggleDisponivel">` + `.status-dot` + texto Online/Offline. Ligado: `--primary`. Desligado: `--muted` |
| Banner de pedido ativo | `.bo-toast` com `.bo-status`, ETA e botão "Acompanhar" |
| Steps do pedido | `.bo-timeline` (o `atendimento-status.js` re-classifica `done/active` — manter os `data-*`) |
| Estrelas de avaliação | `.bo-field` com `radio` + ícone `star` do Lucide; marcado: `--bo-warning` |
| Selo GPS | `.bo-status.info` com ícone `locate` |

---

## 10. Ícones (Lucide via `gfBoIcon`)

Ampliar o mapa de `gfBoIcon()` em `backoffice_helpers.php` com os SVGs oficiais do Lucide (mesmo traço 2px usado no export). Tradução das principais ocorrências de Font Awesome:

| Font Awesome | Lucide |
|---|---|
| `fa-gauge`, `fa-gauge-high` | `layout-dashboard` |
| `fa-tower-broadcast` | `radio-tower` |
| `fa-list`, `fa-list-check` | `list-checks` |
| `fa-circle-plus` | `plus-circle` |
| `fa-truck-pickup`, `fa-truck-fast`, `fa-truck-ramp-box` | `truck` |
| `fa-wallet`, `fa-coins` | `wallet` |
| `fa-clock-rotate-left` | `history` |
| `fa-user-pen`, `fa-user` | `user-round` |
| `fa-users` | `users` |
| `fa-car` | `car` |
| `fa-wrench`, `fa-screwdriver-wrench`, `fa-toolbox` | `wrench` |
| `fa-bell` | `bell` |
| `fa-gear` | `settings` |
| `fa-shield-halved` | `shield-check` |
| `fa-map-location-dot`, `fa-map-pin` | `map-pin` |
| `fa-route` | `route` |
| `fa-flag-checkered` | `flag` |
| `fa-magnifying-glass` | `search` |
| `fa-hourglass-half`, `fa-user-clock` | `hourglass` |
| `fa-triangle-exclamation` | `triangle-alert` |
| `fa-check`, `fa-circle-check` | `check`, `circle-check` |
| `fa-xmark` | `x` |
| `fa-paper-plane` | `send` |
| `fa-clipboard-check` | `clipboard-check` |
| `fa-bullhorn` | `megaphone` |
| `fa-eye` | `eye` |
| `fa-right-from-bracket` | `log-out` |
| `fa-location-crosshairs` | `locate` |
| `fa-star` | `star` |
| `fa-headset` | `headset` |
| `fa-comment` | `message-circle` |
| `fa-phone` | `phone` |
| `fa-angles-left` | `chevron-left` |

Ícone sem tradução: adicionar o SVG do Lucide ao mapa. Nunca reintroduzir Font Awesome.

---

## 11. Regras transversais

### 11.1 Status do pedido → `.bo-status`

| Status | Rótulo | Classe |
|---|---|---|
| `aguardando_pagamento` | Aguardando pagamento | `.bo-status.warning` |
| `aguardando_guincho` | Buscando guincho | `.bo-status.info` |
| `a_caminho` | A caminho | `.bo-status.info` |
| `no_local` | No local | `.bo-status` (acento) |
| `em_reboque` | Em reboque | `.bo-status` (acento) |
| `concluido` | Concluído | `.bo-status` (acento) + ícone `circle-check` |
| `encerrado_financeiro` | Encerrado | `.bo-status` (acento) + ícone `flag` |
| `cancelado` | Cancelado | `.bo-status.danger` |

Estados de carteira e saque (Constituição §9.3): `em_compensacao` → warning · `liberado` → acento · `reservado_saque` → info · `pago` → acento + `check` · `bloqueado`/`falha` → danger. Sempre com texto, nunca só cor.

Um helper único `gfBoStatus(string $status): string` em `backoffice_helpers.php` gera o HTML. As views não montam badge à mão.

### 11.2 Responsividade (breakpoints do Lovable, não do Bootstrap)

| Largura | Comportamento |
|---|---|
| ≥ 1600px | `.bo-content` 32/40px, grade operacional 350px, mapa 440px |
| ≤ 1250px | Sidebar 190px, KPIs em 4 colunas |
| ≤ 950px | Sidebar recolhida em 68px (só ícones, brand "GF"), grade operacional 245px |
| ≤ 700px | Tudo em 1 coluna, KPIs em 2 colunas, header quebra linha, rodapé enxuto |
| ≤ 370px | Ajustes finos do export |

No guincho e no cliente, abaixo de 700px, `.bo-dispatch-actions` e o CTA ocupam 100% da largura.

### 11.3 Acessibilidade
- Texto ≥ 4,5:1 (tokens `--primary-ink` e `--*-ink`); elementos gráficos ≥ 3:1.
- Foco visível em todo `.bo-btn`, `.bo-input`, `.bo-nav` (`outline 2px var(--ring)`).
- `aria-current="page"` no item ativo; `role="switch"` no toggle; `<dialog>` com `aria-labelledby`.
- `prefers-reduced-motion`: sem transição na sidebar (já está no export).

### 11.4 Contratos que não podem quebrar (mínimo)
`#toggleDisponivel`, `#statusBannerCliente`, `#pedidosDisponiveisContainer`, `#pedidoAtivoClienteCard`, `#pedidoAtivoGuinchoCard`, `#map`, `#btnSubmit`, `#sessionExpiredModal`, `[data-session-login]`, `[data-confirm-logout]`, todo `name=` de formulário, todo `csrf_token`, todo `data-*` lido por `public/assets/js/*`. Antes de migrar uma view: `grep -o 'id="[^"]*"\|data-[a-z-]*' view.php > antes.txt`. Depois: o mesmo comando deve dar um superconjunto.

---

## 12. Plano de migração em ondas

Cada onda termina com o gate da §13 verde e passagem registrada em `doc/lanes/B.md`.

| Onda | Entrega | Arquivos |
|---|---|---|
| **0 — Fundação** | `gf-bo-profiles.css`, `gf-bo-components.css`, poda de `gf-bo-lovable.css`, contrato, menus, shell atualizado, `gf-bo-dialog.js`, `bo_kpis.php`, `gfBoStatus()`, ícones Lucide ampliados. Nenhuma view muda ainda. | `layouts/*`, `components/bo_kpis.php`, `css/gf-bo-*.css`, `js/gf-bo-*.js` |
| **1 — Perfis pequenos** | gerente (3), funcionário (5). Validam o padrão de view, de diálogo e de tabela com pouco risco. | `Views/gerente/*`, `Views/funcionario/*` |
| **2 — Oficina** | 7 views | `Views/oficina/*` |
| **3 — Cliente** | 16 views (sem `pre_cotacao`/`triagem` se forem funil público; confirmar rota) | `Views/cliente/*` |
| **4 — Guincho** | 14 views; atenção a SSE, toggle e mapa | `Views/guincho/*` |
| **5 — Admin, núcleo** | central, pedidos, pedidodetalhe, pedidocriar, despacho, alertas, ocorrências, financeiro, faturas, saques, carteiras | ~20 views |
| **6 — Admin, cadastros e catálogo** | usuários, guinchos, prestadores, oficinas, especialistas, documentos, catálogo de serviços e veículos, produtos, preços, feriados | ~30 views |
| **7 — Admin, sistema e crescimento** | configurações, env, health, logs, simulação, testes, cidades, demanda, planejamento, marketing, qualidade | ~23 views |
| **8 — Remoção** | Apagar `header.php` (ou reduzir a `throw`), `footer.php`, `sidebar_*.php`, `admin_nav_operacional.php`, CSS proibidos da §5.1, `pages/*` sem uso. Aliases especialista/parceiro migrados. | — |

**Procedimento por view (obrigatório):**
1. Ler controller, view e JS associado. Salvar `antes.txt` (§11.4) e um screenshot de referência.
2. Reescrever o miolo com o padrão da §8.4 e a tabela da §7.3. Sem `style=""`, sem `<style>`, sem `fa-`, sem classe Bootstrap.
3. Mover estilos específicos que sobrarem para `pages/bo-<perfil>-<tela>.css`, só com layout.
4. `php -l`, gate da §13 e fluxo real no navegador em 1440px e 390px.

---

## 13. Gates de aceite (automatizar em `tools/agents/gf-layout-encoding-agent.ps1` e em Playwright)

### 13.1 Estático (por arquivo alterado em `src/Views/{admin,funcionario,gerente,guincho,oficina,cliente}`)

| Verificação | Comando de referência | Esperado |
|---|---|---|
| Usa o shell | `grep -L 'backoffice_shell.php' <view>` | vazio |
| Não usa legado | `grep -E "layouts/(header|footer|sidebar_)" <view>` | vazio |
| Sem Bootstrap | `grep -E 'class="[^"]*\b(row|col-[a-z0-9-]+|card|btn|form-control|form-select|badge|alert|modal|table)\b' <view>` | vazio |
| Sem Font Awesome | `grep -E '\bfa[srb]? fa-|\bfa-[a-z]' <view>` | vazio |
| Sem cor inline | `grep -E 'style="[^"]*(#[0-9a-fA-F]{3,6}|rgb|color|background)' <view>` | vazio |
| Sem `<style>` | `grep '<style' <view>` | vazio |
| Sem token legado | `grep -E -- '--(theme|admin|gf-bo|gf)-' <view> public/assets/css/pages/bo-*.css` | vazio |
| Sem mojibake | `grep -P 'ï¿½|\x{FFFD}|Ã[\x{80}-\x{BF}]|â€|ðŸ' <view>` | vazio |
| Contratos preservados | `diff` de `antes.txt` com `depois.txt` | só adições |

### 13.2 Renderizado (Playwright, por rota, nos 6 perfis)

1. Exatamente 1 `.bo`, 1 `.bo-sidebar`, 1 `.bo-header`, 1 `.bo-content`, 1 `.bo-footer`.
2. Root com `bo-p-{perfil da sessão}`; `data-gf-profile` igual ao `tipo` da sessão.
3. Nenhum `<link>` fora da lista da §5.1 (`document.styleSheets`).
4. `getComputedStyle(.bo).backgroundColor` claro: luminância > 0,85 em **todos** os perfis.
5. `getComputedStyle(.bo-sidebar).backgroundColor` igual ao `--bo-nav` da matriz §4.1.
6. Nenhum `nav.navbar`, `.main-wrapper`, `.gf-legacy-bo-header`.
7. axe-core: zero violações de `color-contrast`.
8. Sem overflow horizontal em 1440px e 390px.
9. Console sem erro; respostas HTTP do fluxo 2xx/3xx.
10. Screenshot comparado ao da onda anterior (diff visual revisado por humano na primeira vez).

---

## 14. Decisões a ratificar em `doc/DECISOES.md`

| ID | Decisão proposta | Alternativa |
|---|---|---|
| **D-1** | Admin = sidebar preta `#090d0b` + verde `#2fb34a` (cores oficiais do proprietário), sobre o preset Lovable *operator* | Usar o preset Lovable *executive* (índigo) para o admin |
| **D-2** | Empregado = preset Lovable *finance* (azul `#0284c7`). Gerente = preset Lovable *executive* (índigo `#6366f1`) | Os dois em verde (*operator*), sem distinção visual de alçada |
| **D-3** | Guincho e oficina com **conteúdo claro** (padrão Lovable). Escuro só na sidebar | Manter conteúdo escuro (exige um modo escuro completo, que o Lovable não especifica) |
| **D-4** | Bootstrap CSS e Font Awesome saem do backoffice ao fim da onda 8 | Manter Bootstrap só para grid (não recomendado: reintroduz a quimera) |

Até a ratificação, os agentes seguem a coluna "Decisão proposta".

---

## 15. Proibições explícitas

- Criar novo prefixo de classe (`gf-f18x-*`, `ops-*`, `tow-*`, `dash-*`…) no backoffice.
- Criar variável CSS fora das listadas na §3, regra 2.
- Usar `!important` em CSS de backoffice (exceção: nenhuma).
- Sobrescrever `--background` ou `--card` por perfil (exceto o `--background` do cliente, já definido na §5.4).
- Corrigir mojibake com `strtr`, `str_replace` ou CSS.
- Criar uma segunda topbar ou sidebar "para corrigir" uma tela.
- Escrever texto técnico na interface (SSE, polling, render, view, container, refactor).
- Declarar uma onda pronta sem o gate da §13 executado e anexado em `evidencias/`.

---

*Diretriz v3 · gerada a partir da análise do pacote `guinchafacil.zip` (116 views, 115 arquivos CSS, export Lovable `styles-SkD_E4XV.css`) em 2026-11-10.*
