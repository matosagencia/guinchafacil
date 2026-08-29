<!-- ============================================================
     CAPA — inspirada no template CabME (Customer App / Driver App)
     ============================================================ -->

<div align="center">

# 🛻 GuinchaFácil
## Guia de Design & Configuração dos Painéis

**Sistema de Design v1.0 — Layout, Cores e Experiência por Perfil**

*Socorro na estrada em segundos. Painéis pensados para quem está em um momento difícil.*

| 🟢 Painel do Cliente | 🌲 Painel do Guincho | ⬛ Painel do Admin |
|:---:|:---:|:---:|
| Tema claro, acolhedor | Tema verde-escuro operacional | Tema preto de controle |
| "Pedir Socorro" em 1 toque | Aceitar corrida em 1 toque | Visão total da operação |

**Stack visual:** Bootstrap 5.3 · Font Awesome 6 · Leaflet + LRM · CSS Custom Properties
**Arquivos-matriz:** `public/assets/css/style.css` · `src/Views/layouts/*` · `src/Views/{cliente,guincho,admin}/*`

---

</div>

## Índice

1. [Filosofia de design](#1-filosofia-de-design)
2. [Fundamentos globais (tokens)](#2-fundamentos-globais-tokens)
3. [Paleta — Perfil Cliente](#3-paleta--perfil-cliente)
4. [Paleta — Perfil Guincho](#4-paleta--perfil-guincho)
5. [Paleta — Perfil Admin](#5-paleta--perfil-admin)
6. [Cores semânticas de status do pedido](#6-cores-semânticas-de-status-do-pedido)
7. [Tipografia](#7-tipografia)
8. [Anatomia do layout (shell)](#8-anatomia-do-layout-shell)
9. [Biblioteca de componentes](#9-biblioteca-de-componentes)
10. [Blueprint das telas — Cliente](#10-blueprint-das-telas--cliente)
11. [Blueprint das telas — Guincho](#11-blueprint-das-telas--guincho)
12. [Mapeamento template CabME → GuinchaFácil](#12-mapeamento-template-cabme--guinchafácil)
13. [Responsividade](#13-responsividade)
14. [Acessibilidade e contraste](#14-acessibilidade-e-contraste)
15. [Checklist de configuração de um painel novo](#15-checklist-de-configuração-de-um-painel-novo)

---

## 1. Filosofia de design

O GuinchaFácil atende pessoas **em situação de estresse**: carro quebrado, acidente, chuva, madrugada. Todo o design parte de três princípios não negociáveis:

**1.1 — Uma ação óbvia por tela.**
Cada tela do cliente tem um único CTA dominante (`.btn-socorro`, pílula verde com sombra colorida). O usuário nunca deve pensar "onde clico?". No guincho, o equivalente é o botão de avanço de status (`Cheguei ao Local → Iniciar Reboque → Finalizar Corrida`), que é sempre o maior elemento acionável da tela de atendimento.

**1.2 — Estado sempre visível.**
Inspirado no card *"Ride Arriving in 5 mins"* do template CabME: o cliente com pedido ativo vê um **banner vivo** (`.status-banner-live`) fixado no topo do dashboard com status, ETA e atalho para o acompanhamento. O guincho vê o badge de status e o badge GPS no `page-header` do atendimento. Ninguém precisa procurar "em que pé está".

**1.3 — Identidade por perfil, marca única.**
Os três painéis compartilham o mesmo verde-marca `#2fb34a`, os mesmos componentes e o mesmo shell (navbar + sidebar + main). O que muda é o **tema de fundo**, controlado por uma única classe no `<body>` (`cliente`, `guincho`, `admin`/vazio). Isso permite que o cliente sinta leveza (claro), o operador de guincho tenha conforto noturno (verde-escuro, muitos trabalham à noite na cabine) e o admin tenha densidade de informação (preto).

> **Regra de ouro:** nenhuma tela define cores fixas de tema. Toda cor de superfície, texto e borda vem de `var(--theme-*)`. Cores fixas só são permitidas para semântica (status, sucesso, perigo) e para a marca.

---

## 2. Fundamentos globais (tokens)

Definidos em `:root` no `style.css`. São a **única fonte de verdade** — qualquer painel novo consome esses tokens, nunca valores hardcoded.

| Token | Valor | Uso |
|---|---|---|
| `--primary` | `#2fb34a` | Verde-marca. CTAs, ícones ativos, links, borda de foco |
| `--primary-dark` | `#1f8a36` | Hover/pressed de botões primários |
| `--primary-light` | `#d4f5dc` | Fundos suaves de destaque (chips, hover de linha) |
| `--danger` | `#dc2626` | Cancelamentos, erros, ações destrutivas |
| `--warning` | `#d97706` | Pagamento pendente, alertas |
| `--success` | `#16a34a` | Confirmações, conclusão |
| `--shadow` | `0 2px 10px rgba(0,0,0,.13)` | Sombra padrão de cards e navbar |
| `--radius` | `12px` | Raio padrão (cards, mapas, alertas) |
| `--trans` | `.2s ease` | Duração/curva padrão de transições |

**Escala de raios usada no projeto** (padrão observado nas views):

| Raio | Onde |
|---|---|
| `8px` | Botões, inputs, mapa pequeno (`.map-container-sm`) |
| `12px` (`--radius`) | Cards clássicos, alertas, chat, mapas |
| `14–16px` | Ícones de stat (`.stat-icon` 14px, `.dash-stat-icon` 16px) |
| `20px` | Badges-pílula de status |
| `24px` | Stat-cards novos (`.dash-stat`, `.tow-stat`) |
| `30px` | Heros e cards premium (`.dash-hero`, `.tow-hero`, `.dash-card`, `.tow-card`) |
| `50px / 999px` | `.btn-socorro`, chips (`.dash-chip`, `.tow-chip`), toggle |

**Sombras em camadas (linguagem "premium" das telas novas):**

```css
/* Card premium — dashboards redesenhados */
box-shadow: 0 28px 80px rgba(15,23,42,.08);

/* Hover de stat-card clássico */
box-shadow: 0 8px 22px rgba(0,0,0,.18);

/* CTA de socorro (sombra colorida, chama atenção) */
box-shadow: 0 6px 20px rgba(47,179,74,.4);      /* repouso */
box-shadow: 0 10px 26px rgba(47,179,74,.5);     /* hover  */
```

**Efeito de profundidade dos dashboards** — dois círculos desfocados atrás do conteúdo (`::before/::after` do wrapper `.client-dash` / `.tow-dash`):

```css
width/height: 340px; border-radius: 999px;
filter: blur(95px); opacity: .15; z-index: -1;
/* Cliente: verde rgba(47,179,74,.30) + âmbar rgba(245,158,11,.22) */
/* Guincho: verde rgba(47,179,74,.28) + vermelho rgba(239,68,68,.22) */
```

Esse detalhe replica o clima "soft gradient" da capa do template CabME sem custo de imagem — é puro CSS e deve ser mantido em qualquer nova tela de dashboard.

---

## 3. Paleta — Perfil Cliente

Tema **claro e acolhedor** (`body.cliente`). É a pessoa em apuros: o painel precisa parecer limpo, calmo e confiável — nunca "técnico".

| Variável | Hex | Papel no layout |
|---|---|---|
| `--theme-bg` | `#f3f6f4` | Fundo da página (branco-esverdeado, mais quente que cinza puro) |
| `--theme-surf` | `#ffffff` | Superfície de cards |
| `--theme-surf2` | `#edf5ef` | Superfície secundária: inputs, thead, card-footer |
| `--theme-border` | `#cde5d1` | Bordas (verde dessaturado — coesão com a marca) |
| `--theme-text` | `#152218` | Texto principal (verde-quase-preto, não `#000`) |
| `--theme-muted` | `#4e7257` | Texto secundário/labels |
| `--theme-nav-bg` | `#ffffff` | Navbar branca |
| `--theme-nav-txt` | `#152218` | Texto da navbar |
| `--theme-card-hd` | `#2fb34a` | **Header de card verde-marca com texto branco** — assinatura visual do painel cliente |
| `--theme-sidebar` | `#edf5ef` | Sidebar levemente verde |

**Decisões de designer neste tema:**
- O header de card verde cheio (`--theme-card-hd: #2fb34a` + `color:#fff`) é o elemento que dá "cara de app" ao painel — equivalente aos cards coloridos de sugestão do CabME (*Ride Booking* lilás / *Parcel Delivery* verde).
- Hover/active de navegação usa `--primary-dark` (`#1f8a36`) em vez de `--primary`, porque o verde puro sobre fundo claro perde contraste (regra já aplicada em `body.cliente .nav-link.active`).
- O badge de perfil do cliente é verde cheio com texto branco: `.badge-perfil.cliente { background:#2fb34a; color:#fff; }`.
- Superfícies premium do dashboard usam branco translúcido sobre o fundo: `linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.92))` — mantém o glow verde/âmbar do fundo aparecendo sutilmente nas bordas.

---

## 4. Paleta — Perfil Guincho

Tema **verde-escuro operacional** (`body.guincho`). Pensado para uso prolongado, muitas vezes à noite dentro da cabine: baixa luminância, alto contraste no que importa (pedido, rota, botão de avanço).

| Variável | Hex | Papel no layout |
|---|---|---|
| `--theme-bg` | `#071209` | Fundo (verde quase preto) |
| `--theme-surf` | `#0e2412` | Cards |
| `--theme-surf2` | `#163319` | Inputs, thead, superfícies elevadas |
| `--theme-border` | `#264d2c` | Bordas |
| `--theme-text` | `#ffffff` | Texto principal |
| `--theme-muted` | `#aaddb5` | Texto secundário (verde-claro legível) |
| `--theme-nav-bg` | `#050f06` | Navbar |
| `--theme-nav-txt` | `#a3f7b5` | Texto da navbar (menta) |
| `--theme-card-hd` | `#163d1c` | Header de card |
| `--theme-sidebar` | `#060d07` | Sidebar |

**Ajustes de contraste obrigatórios no tema escuro** (já implementados — replicar em telas novas):

```css
/* Labels e textos auxiliares nunca somem no escuro */
body.guincho .text-muted, .sidebar-title, .form-label, .stat-label
    → rgba(255,255,255,.76) !important;

/* Links de navegação em menta suave */
body.guincho .sidebar-link → rgba(207,245,215,.9);

/* Links dentro de cards */
body.guincho .card a:not(.btn) → #6ee58a (hover #9bf3ad);

/* Semânticas Bootstrap recalibradas p/ fundo escuro */
.text-success → #35d565   .text-danger → #ff6b6b
.text-warning → #fbbf24   .text-info   → #60a5fa
```

**Superfícies claras dentro do tema escuro.** As telas redesenhadas (`.tow-hero`, `.tow-card`, `.dash-*`, `.profile-*`, `.fin-*`, `.history-*`, `.ops-*`) usam cards **brancos** flutuando sobre o fundo escuro — mesmo padrão dos cards do app do motorista no CabME. Para isso existe o par de tokens de contraste local:

```css
--surface-contrast-text:  #142018;  /* texto sobre superfície clara */
--surface-contrast-muted: #5a6b5f;  /* secundário sobre superfície clara */
```

Inputs dentro dessas superfícies: `background: rgba(255,255,255,.96)`, borda `rgba(15,23,42,.12)`, placeholder `#78867c`. Tabelas: thead `#eef3ef`, bordas `rgba(15,23,42,.08)`.

- Badge de perfil: `.badge-perfil.guincho { background:#0a2e0e; color:#7dff96; border:1px solid #2fb34a; }` — pílula "neon contido".
- Estados de disponibilidade (equivalente ao online/offline do driver app CabME):

| Classe | Fundo | Texto | Borda |
|---|---|---|---|
| `.guincho-status-online` | `rgba(34,197,94,.15)` | `#22c55e` | `rgba(34,197,94,.3)` |
| `.guincho-status-ocupado` | `rgba(251,191,36,.15)` | `#fbbf24` | `rgba(251,191,36,.3)` |
| `.guincho-status-offline` | `rgba(156,163,175,.15)` | `#9ca3af` | `rgba(156,163,175,.3)` |

---

## 5. Paleta — Perfil Admin

Tema **preto neutro** (`body` sem classe extra / `body.admin`). Densidade e neutralidade: o admin olha números, logs, saúde do sistema.

| Variável | Hex | Papel |
|---|---|---|
| `--theme-bg` | `#0d0d0d` | Fundo |
| `--theme-surf` | `#1a1a1a` | Cards |
| `--theme-surf2` | `#252525` | Inputs, thead |
| `--theme-border` | `#333333` | Bordas |
| `--theme-text` | `#ffffff` | Texto |
| `--theme-muted` | `#bbbbbb` | Secundário |
| `--theme-nav-bg` | `#000000` | Navbar |
| `--theme-nav-txt` | `#ffffff` | Texto navbar |
| `--theme-card-hd` | `#252525` | Header de card (neutro — só o verde da marca pontua) |
| `--theme-sidebar` | `#111111` | Sidebar |

Badge de perfil: `.badge-perfil.admin { background:#2fb34a; color:#000; }`. As mesmas regras de contraste do tema guincho (`body.admin .card ...`) se aplicam — os dois temas escuros compartilham o bloco de correções.

---

## 6. Cores semânticas de status do pedido

O coração do produto. O ciclo de vida do pedido é `aguardando_pagamento → aguardando_guincho → a_caminho → no_local → em_reboque → concluido` (ou `cancelado`), e **cada status tem uma cor própria em formato pílula translúcida** — mesma linguagem em qualquer perfil, qualquer tema:

| Status | Classe | Texto | Fundo | Semântica |
|---|---|---|---|---|
| Aguardando Pagamento | `.badge-aguardando_pagamento` | `#d97706` | `rgba(217,119,6,.15)` | Âmbar — ação pendente do cliente |
| Buscando Guincho | `.badge-aguardando_guincho` | `#60a5fa` | `rgba(59,130,246,.15)` | Azul — sistema trabalhando |
| A Caminho | `.badge-a_caminho` | `#38bdf8` | `rgba(14,165,233,.15)` | Azul-céu — movimento |
| No Local | `.badge-no_local` | `#34d399` | `rgba(52,211,153,.15)` | Verde-água — chegada |
| Em Reboque | `.badge-em_reboque` | `#4ade80` | `rgba(74,222,128,.15)` | Verde — progresso |
| Concluído | `.badge-concluido` | `#22c55e` | `rgba(34,197,94,.15)` | Verde pleno — sucesso |
| Cancelado | `.badge-cancelado` | `#f87171` | `rgba(248,113,113,.15)` | Vermelho suave |

**Padrão da pílula:** `border-radius:20px; font-weight:700; font-size:.7rem; padding:4px 10px; border:1px solid <cor a 30%>`. A progressão intencional de azul → verde comunica "aproximando-se da resolução" sem que o usuário leia nada — exatamente o papel dos steps coloridos do CabME.

O mesmo mapa alimenta os **ícones dos steps** (tela `pedidostatus.php`):

| Step | Ícone FA | Status |
|---|---|---|
| Pedido Criado | `fa-check` | `aguardando_pagamento` |
| Buscando Guincho | `fa-magnifying-glass` | `aguardando_guincho` |
| A Caminho | `fa-route` | `a_caminho` |
| No Local | `fa-map-pin` | `no_local` |
| Em Reboque | `fa-truck-ramp-box` | `em_reboque` |
| Concluído | `fa-flag-checkered` | `concluido` |

---

## 7. Tipografia

Fonte de sistema — carregamento zero, aparência nativa em qualquer dispositivo (crítico para quem está no acostamento com 3G):

```css
font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
```

**Escala tipográfica do projeto:**

| Papel | Tamanho | Peso | Exemplo de classe |
|---|---|---|---|
| Valor de stat (destaque) | `1.9rem` (`1.6rem` mobile) | 800 | `.stat-value` |
| Valor de stat premium | `1.6–1.65rem` | 800 | `.dash-stat-value`, `.tow-stat-value` |
| Título de página | `1.45rem` | 800 | `.page-title` |
| Brand da navbar | `1.25rem` | 800 | `.navbar-brand` |
| CTA de socorro | `1.15rem` | 800 | `.btn-socorro` |
| Corpo / links de menu | `.88rem` | 500–600 | `.sidebar-link`, `.chat-msg .bubble` |
| Subtítulo, labels | `.85–.86rem` | 400–600 | `.page-subtitle`, `.form-label` |
| Rótulo de stat | `.78rem` | 400 | `.stat-label` |
| Thead de tabela | `.74rem` | 700 UPPERCASE, `letter-spacing:.6px` | `.table thead th` |
| Badge de status | `.7rem` | 700 | `.badge-*` |
| Título de seção da sidebar | `.63–.68rem` | 700–800 UPPERCASE, `letter-spacing:1.2–1.4px` | `.sidebar-title`, `.sidebar-section-header` |
| Micro (label de step, badge preview) | `.55–.68rem` | 700–800 | `.step-label`, `.sidebar-preview-badge` |

**Regras:**
- Peso 800 é reservado a números e títulos — hierarquia por peso, não por tamanho exagerado.
- UPPERCASE + letter-spacing só em micro-rótulos estruturais (seções, thead). Nunca em conteúdo.
- Números financeiros e ETAs sempre em 800 com `line-height:1` (padrão `.stat-value`), como o "$1200.98" do wallet do CabME.

---

## 8. Anatomia do layout (shell)

Todos os painéis usam o mesmo esqueleto, montado por `layouts/header.php` + sidebar do perfil + `layouts/footer.php`:

```
┌──────────────────────────────────────────────────────────┐
│ NAVBAR fixa (64px, z-index 1030)                          │
│  [logo-48 + wordmark]        [links]   [badge-perfil][sair]│
├───────────┬──────────────────────────────────────────────┤
│ SIDEBAR   │  MAIN-CONTENT (flex:1, padding 2rem)          │
│ 230px     │  ┌ page-header ──────────────────────────┐    │
│ sticky    │  │ page-title + page-subtitle │ ações    │    │
│ top:64px  │  └────────────────────────────────────────┘    │
│           │  [status-banner-live se houver pedido ativo]  │
│ .sidebar- │  [grid de cards / stats / mapa ...]           │
│  title    │                                               │
│ .sidebar- │                                               │
│  link     │                                               │
├───────────┴──────────────────────────────────────────────┤
│ FOOTER (fundo = --theme-sidebar)                          │
└──────────────────────────────────────────────────────────┘
```

**Regras do shell:**
- `body { padding-top: 64px }` compensa a navbar fixa (60px em mobile).
- A classe do `<body>` **é o tema**: `class="cliente"`, `"guincho"` ou `"admin"` — vem de `$_SESSION['user']['tipo']` no header. Nunca aplicar tema por página.
- `.main-wrapper { display:flex; min-height: calc(100vh - 64px) }` — sidebar e conteúdo lado a lado.
- Sidebar: links com `border-left: 3px solid transparent`, ativo/hover = borda verde + fundo `rgba(47,179,74,.1)` + texto `--primary`. Estado ativo detectado por `strpos($cur, ...)` no PHP.
- Espaçamento vertical entre blocos do main: `1.75rem` após o `page-header`, `g-4` (1.5rem) nos grids Bootstrap.

**Navegação por perfil (matriz atual):**

| Cliente (`sidebar_cliente.php`) | Guincho (`sidebar_guincho.php`) | Admin (`sidebar_admin.php`) |
|---|---|---|
| 🧭 Painel (`fa-gauge`) | **Seção "Operações"** | Dashboard, Pedidos, Guinchos |
| ➕ Pedir Socorro (`fa-circle-plus`) | 🧭 Painel (`fa-gauge`) | Usuários, Pendentes, Financeiro |
| 🕓 Histórico (`fa-clock-rotate-left`) | 🕓 Histórico | Configurações, Logs, Health |
| 👛 Financeiro (`fa-wallet`) | 🪙 Financeiro (`fa-coins`) | Simulador, Env, previews "ver como" |
| 🚗 Veículos (`fa-car`) | **Seção "Conta"** | |
| 🔧 Oficinas (`fa-wrench`) | 👤 Meu Perfil (`fa-user-pen`) | |
| 👤 Meu Perfil (`fa-user-pen`) | | |

- Seções da sidebar usam `.sidebar-title` (ou `.sidebar-section-header` com ícone). Links de criação usam `.sidebar-link-action` (recuado, ícone verde). Links de "ver como" do admin usam `.sidebar-link-preview` (borda tracejada verde + `.sidebar-preview-badge`).

---

## 9. Biblioteca de componentes

### 9.1 Botões

| Componente | Receita | Quando usar |
|---|---|---|
| `.btn-primary` | verde `--primary`, hover `--primary-dark` + `translateY(-1px)` + sombra verde | Ação principal comum |
| `.btn-socorro` | pílula `border-radius:50px`, `1rem 2.5rem`, peso 800, sombra `rgba(47,179,74,.4)` | **Só** para o CTA de emergência ("Solicitar Socorro") — 1 por tela, `w-100` no mobile |
| `.btn-outline-primary` | contorno verde, preenche no hover | Ações secundárias positivas |
| `.btn-outline-danger` | contorno vermelho | Cancelar atendimento/pedido |
| `.btn-secondary` | `--theme-surf2` + borda do tema | Ações neutras (voltar, filtros) |

### 9.2 Cards

- **Card clássico** (`.card`): raio 12px, header colorido pelo tema (`--theme-card-hd`, texto branco), body `1.2rem`, footer em `--theme-surf2`. Header sempre com ícone FA + título curto: `<i class="fas fa-map-location-dot me-2"></i>Rota`.
- **Card premium** (`.dash-card` / `.tow-card` / heros): raio 30px, gradiente branco translúcido, sombra `0 28px 80px rgba(15,23,42,.08)`. Hero leva `::before` com dois radial-gradients (verde no topo-esquerdo + âmbar no rodapé-direito, ~14% de opacidade).
- **Stat-card clássico** (`.stat-card`): centralizado, ícone 50×50 raio 14 em `rgba(47,179,74,.15)`, valor 1.9rem/800, hover `translateY(-3px)`.
- **Stat premium** (`.dash-stat`/`.tow-stat`): raio 24, fundo `rgba(255,255,255,.84)`, ícone 48×48 raio 16, envolvido por `.dash-stat-link` (card inteiro clicável, hover `translateY(-2px)`), como os atalhos *Suggestions* do CabME.
- **Chip** (`.dash-chip`/`.tow-chip`): pílula 999px com ícone verde — para metadados do hero (ex.: "3 veículos cadastrados", "Online há 2h").
- **`.guincho-card`**: card de guincho candidato com `border-left: 4px solid var(--primary)`, clicável, com `.guincho-score` verde.

### 9.3 Banner de pedido ativo (`.status-banner-live`)

Equivalente direto ao card "Ride Arriving in 5 mins" do template:

```
┌─────────────────────────────────────────────────────────┐
│ ● Pedido #123 em andamento   [a_caminho] [ETA 7min] [💬2] │  [Acompanhar →]
└─────────────────────────────────────────────────────────┘
```

Receita: borda `rgba(47,179,74,.35)` + `border-left: 4px solid var(--primary)`, fundo `color-mix(in srgb, var(--theme-surf) 88%, var(--primary) 12%)`, título 800, metadados em pílulas `999px` (`.status-banner-extra` neutra, `.status-banner-chat` azul `#93c5fd` para mensagens não lidas). No mobile vira coluna e o botão ocupa 100%.

### 9.4 Timeline de status (`.status-steps`)

Steps horizontais conectados por linha de 3px. Estados: `done` (círculo verde cheio + linha verde), `active` (borda e texto verdes, label 700), futuro (cinza do tema). Círculo 42×42, label `.68rem`. **No mobile (<768px) vira coluna e as linhas somem** — cada step é uma linha com ícone + label.

### 9.5 Toggle de disponibilidade (`.toggle-disponivel`)

Switch 52×28 customizado: trilho `--theme-border` → `--primary` quando ligado, knob branco 22px com `translateX(24px)`. Sempre acompanhado do rótulo textual **Online/Offline** e de um badge (`text-bg-success`/`text-bg-secondary`) — nunca cor sozinha. É o coração do dashboard do guincho (mesmo papel do status do driver no CabME).

### 9.6 Chat (`.chat-box`)

Caixa 300px com bolhas: minhas = verde `--primary` texto branco, canto inferior-direito 4px; do outro = `--theme-surf2`, canto inferior-esquerdo 4px. Remetente em `.7rem` muted. Máx. 75% de largura por bolha.

### 9.7 Mapa

`.map-container` (70vh, mín. 400px, raio 12, borda do tema) para telas de acompanhamento/atendimento; `.map-container-sm` (260px, raio 8) para resumos e formulários. Leaflet + Leaflet Routing Machine com formatter pt-BR (`assets/js/routing/formatter-pt-br.js`).

### 9.8 Formulários, tabelas, alertas, paginação, estrelas

- Inputs: fundo `--theme-surf2`, raio 8, foco = borda verde + anel `0 0 0 3px rgba(47,179,74,.2)`.
- Tabelas: thead UPPERCASE `.74rem` sobre `--theme-surf2`; hover de linha `rgba(47,179,74,.06)`.
- Alertas: sem borda, raio 12, fundo translúcido 15% da cor semântica (verde/vermelho/âmbar/azul).
- Paginação: página ativa verde cheia.
- Avaliação (`.star-rating`): estrelas 2rem, hover/checked `#f59e0b` (âmbar — única cor "quente" de interação do sistema).

---

## 10. Blueprint das telas — Cliente

### 10.1 Dashboard (`cliente/dashboard.php`) — wrapper `.client-dash`

```
[status-banner-live]                     ← só se houver pedido ativo
┌ dash-hero (raio 30) ───────────────────────────────────────┐
│ "Olá, {nome} 👋"  subtítulo acolhedor                        │
│ [dash-chip veículos] [dash-chip últimos pedidos]             │
│                    [ .btn-socorro  🚨 PEDIR SOCORRO ]        │
└─────────────────────────────────────────────────────────────┘
┌dash-stat┐ ┌dash-stat┐ ┌dash-stat┐ ┌dash-stat┐   ← linkáveis
│Pedidos  │ │Concluídos│ │Gasto    │ │Veículos │
└─────────┘ └─────────┘ └─────────┘ └─────────┘
┌ dash-card: últimos pedidos (tabela + badges de status) ─────┐
```

Regras: o `.btn-socorro` é o elemento de maior peso visual; glow verde+âmbar ao fundo; cada `.dash-stat` embrulhado em `.dash-stat-link` levando à página correspondente (Histórico, Financeiro, Veículos).

### 10.2 Nova Solicitação (`cliente/pedidonovo.php`)

Duas colunas em desktop: **esquerda = mapa** (seleção de origem/destino, card com header verde "Localização"), **direita = card "Detalhes do Pedido"** (`fa-file-alt`): veículo, tipo de socorro, observações, resumo de custo estimado. CTA final: `.btn-socorro w-100` **desabilitado até o formulário validar** (`id="btnSubmit" disabled`) — o botão só "acende" quando dá para agir, eliminando erro em momento de estresse. Equivale à tela "Select your preferences" do CabME (origem/destino + veículo + CTA amarelo).

### 10.3 Acompanhamento (`cliente/pedidostatus.php`) — a tela mais crítica

```
page-header: "Pedido #{id}" + badge de status
[.status-steps: 6 steps do ciclo de vida]
┌ col-lg-7: mapa 70vh com rota viva ──┐ ┌ col-lg-5 ─────────────┐
│ marcador guincho + origem + destino │ │ card Guincho designado │
│ trilha percorrida (routingSnapshot) │ │ (foto, placa/UF, ⭐,    │
│                                     │ │  ligar)                │
│                                     │ │ card Chat              │
│                                     │ │ card Cancelamento      │
│                                     │ │ (preview de taxa!)     │
└─────────────────────────────────────┘ └───────────────────────┘
```

Detalhes de UX já implementados que **devem ser preservados**:
- Steps atualizados via polling sem reload (`atendimento-status.js` re-classifica `done/active` pelo `STATUS_ORDER`).
- Enquanto não há guincho: mensagem calma *"Aguardando atribuição de guincho..."*; se o guincho cancelar: *"...Estamos buscando outro guincho para você, sem custo adicional"* — **linguagem sempre tranquilizadora, nunca técnica**.
- Cancelamento mostra `cancelPreview` (taxa, bloqueio, isenção) **antes** de confirmar — transparência de preço, princípio herdado do wallet do CabME.
- `routingSnapshot` alimenta rua atual, distância restante, ETA e % de progresso — o cliente vê o guincho "andando".

### 10.4 Demais telas

- **Histórico**: tabela com badges de status + paginação; filtros no `page-header`.
- **Financeiro**: stat-cards (gasto total, pedidos pagos, taxas de cancelamento) + extrato em tabela — espelho do "Transaction History" do CabME.
- **Veículos/Oficinas**: grids de cards com `.sidebar-link-action`/botão "+ Adicionar"; formulários em card único centralizado (`veiculoform`, `oficinaform`).
- **Avaliação**: `.star-rating` âmbar + textarea + `.btn-primary`.
- **Perfil**: cards `profile-*` claros (padrão premium).

---

## 11. Blueprint das telas — Guincho

### 11.1 Dashboard (`guincho/dashboard.php`) — wrapper `.tow-dash`

```
┌ tow-hero ───────────────────────────────────────────────────┐
│ "Olá, {operador}"          [Online/Offline + toggle 52×28]   │
│ [tow-chip status] [tow-chip corridas hoje]                   │
└──────────────────────────────────────────────────────────────┘
┌tow-stat┐ ┌tow-stat┐ ┌tow-stat┐ ┌tow-stat┐
┌ tow-panel: "🔔 Novo Pedido Disponível" / "Fila de Pedidos" ──┐
│ se offline → aviso para ficar online                         │
│ se pedido pendente → card do pedido com [Recusar][Aceitar]   │
└──────────────────────────────────────────────────────────────┘
```

O par **Recusar (neutro/outline) / Aceitar (verde cheio)** replica o Reject/Accept do driver app CabME: o positivo sempre à direita, cheio e maior. O toggle atualiza via AJAX (`fd.append('disponivel', ...)`) e o rótulo textual acompanha (`Online`/`Offline`).

### 11.2 Atendimento (`guincho/atendimento.php`)

```
page-header: "🚚 Atendimento #{id}"  [badge status][badge GPS]   [Cancelar Atendimento]*
                                                    (*só em a_caminho)
┌ col-lg-7: card "Rota" (mapa + legenda) ┐ ┌ col-lg-5 ────────────────┐
│                                         │ │ card Cliente (nome, ☎)   │
│                                         │ │ card Endereços (orig/dest)│
│                                         │ │ card Chat                 │
│                                         │ │ [BOTÃO DE AVANÇO]         │
└─────────────────────────────────────────┘ └──────────────────────────┘
```

Máquina de estados do botão de avanço (um único botão, texto e ícone mudam):

| Status atual | Botão | Ícone | Cor do badge |
|---|---|---|---|
| `a_caminho` | **Cheguei ao Local** | `fa-map-pin` | `warning` |
| `no_local` | **Iniciar Reboque** | `fa-truck-ramp-box` | `info` |
| `em_reboque` | **Finalizar Corrida** | `fa-flag-checkered` | `primary` |
| `concluido` | — | `fa-circle-check` | `success` |

O badge GPS (`fa-location-crosshairs`) indica envio de localização ao cliente. Cancelar exibe a penalidade de reputação (`penalidade_reputacao_cancelamento`, padrão 0.25) antes de confirmar.

### 11.3 Perfil em três abas + financeiro

- Perfil dividido em **Conta** (`perfil_conta`), **Operação** (`perfil_operacao` — dados do veículo `fa-truck-pickup`, raio de atuação, disponibilidade) e **Bancário** (`perfil_bancario` — dados de recebimento). Cards `profile-*`/`ops-*` claros sobre o tema escuro, com inputs brancos (seção 4).
- **Financeiro** (`fin-*`): stat-cards de ganhos + extrato, espelho do "My Wallet / Withdraw" do CabME.

---

## 12. Mapeamento template CabME → GuinchaFácil

Guia rápido para "traduzir" qualquer padrão do template para a matriz do projeto:

| Padrão no template CabME | Equivalente GuinchaFácil | Observação |
|---|---|---|
| Card "Ride Arriving in 5 mins" + Cancel Ride | `.status-banner-live` + acompanhar/cancelar | ETA vem do `routingSnapshot` |
| Cards *Suggestions* (Ride Booking / Parcel) | `.dash-stat` linkáveis do dashboard | atalhos coloridos → chips/ícones verdes |
| "Select your preferences" (origem/destino/veículo) | `cliente/pedidonovo.php` | CTA amarelo do template → `.btn-socorro` verde |
| Lista "Select payment method" | fluxo de pagamento PIX (`src/Views/pagamento/`) | radio list em card claro |
| My Wallet $1200.98 / Withdraw | `financeiro.php` (cliente e guincho) | valor em `.stat-value` 800 |
| Driver Accept/Reject com detalhes do parcel | `guincho/pedidoaceitar.php` + painel de fila | Aceitar verde cheio à direita |
| "Reached the Location" (botão verde do driver) | botão de avanço de status do atendimento | máquina de estados da seção 11.2 |
| Bottom-tabs Home/Bookings/Wallet/Profile | sidebar 230px (desktop) que vira barra horizontal de pílulas no mobile | mesma função de navegação primária |
| Badge de capa "Customer App" / "Driver App" | `.badge-perfil` cliente/guincho/admin na navbar | identifica o contexto logado |
| Choose Your Business Plan (comissão) | Configurações do admin (comissão/taxas) | admin, não guincho |
| Ratings ★ | `.star-rating` + `guincho-score` | âmbar `#f59e0b` |

---

## 13. Responsividade

Breakpoints Bootstrap com três degraus de adaptação:

**≤ 991.98px (tablet):**
- Sidebar deixa de ser coluna: vira **faixa horizontal de pílulas** no topo (`position:static`, `flex-wrap`, links com raio 8, sem borda-esquerda; ativo = fundo verde 15%). Títulos de seção e separadores somem.
- `main-content` reduz para `1.25rem` de padding.

**≤ 767.98px (celular — o caso real do cliente no acostamento):**
- `padding-top: 60px`; mapa cai para `50vh` (mín. 250px).
- `.status-steps` empilha em coluna, linhas conectoras somem.
- `.btn-socorro` e o botão do `status-banner-live` viram `width:100%`.
- `page-header` empilha (título em cima, ações embaixo); `stat-value` reduz a 1.6rem.

**≤ 575.98px:** paddings de card/stat caem para `1rem`; brand reduz a `1.05rem`.

> Projete **mobile-first para o cliente** (ele estará no celular, possivelmente com sol na tela e mãos trêmulas: alvos de toque ≥ 44px, CTA full-width) e **desktop/tablet-first para admin** (densidade). O guincho fica no meio: tablet fixado na cabine é o cenário típico.

---

## 14. Acessibilidade e contraste

1. **Cor nunca é o único canal.** Todo status tem ícone + texto (badges, steps, toggle Online/Offline com rótulo). 
2. **Contraste mínimo AA.** Já garantido pelos ajustes do CSS: `--theme-muted` foi elevado para `#bbbbbb` (admin) e `#aaddb5` (guincho); labels em temas escuros forçadas a `rgba(255,255,255,.76)`. Ao criar componente novo em tema escuro, testar texto secundário contra `--theme-surf` (alvo ≥ 4.5:1).
3. **Superfícies claras em tema escuro** usam obrigatoriamente `--surface-contrast-text`/`--surface-contrast-muted` — nunca herdar o branco do tema (é a causa nº 1 de "texto invisível" em telas novas).
4. **Foco visível:** anel verde `0 0 0 3px rgba(47,179,74,.2)` em qualquer elemento interativo customizado.
5. **Linguagem:** mensagens ao cliente sempre em tom calmo e sem jargão ("Estamos buscando outro guincho para você, sem custo adicional"), erros nunca culpabilizam.
6. **Sessão expirada** abre modal com ação única "Entrar novamente" que devolve à mesma página — não perder o contexto de um pedido em andamento.

---

## 15. Checklist de configuração de um painel novo

Ao criar/redesenhar qualquer tela, seguir nesta ordem:

- [ ] `<body>` recebe a classe do perfil via `header.php` (nunca hardcode de tema na view).
- [ ] Estrutura: `header.php` → `.main-wrapper` → `sidebar_{perfil}.php` → `<main class="main-content">` → `footer.php`.
- [ ] `page-header` com `page-title` (ícone FA + `me-2 text-primary-custom`) e `page-subtitle`; ações à direita.
- [ ] Toda cor via `var(--theme-*)` ou `var(--primary*)`; semânticas apenas pelas classes de badge/alert existentes.
- [ ] Dashboard novo? Adotar o padrão premium: wrapper `*-dash` com glow blur 95px, hero raio 30, stats raio 24 clicáveis, chips 999px.
- [ ] Card comum? `.card` clássico com header ícone+título.
- [ ] Tema escuro + superfície clara? Prefixar classes com `profile-|ops-|dash-|history-|tow-|fin-` para herdar o bloco de contraste automático do `style.css`.
- [ ] Um único CTA dominante por tela; destrutivos sempre outline-danger com confirmação e preview de custo/penalidade.
- [ ] Estados vazios com mensagem + ação (padrão "No parcel requests... [Search Parcel]" do template → "Nenhum pedido na sua região. [Atualizar]").
- [ ] Testar nos três breakpoints e nos três temas (admin visita áreas de outros perfis mantendo o próprio tema — ver comentário no `header.php`).
- [ ] Ícones sempre Font Awesome 6 sólido, 1 por conceito, reutilizando o vocabulário existente (`fa-gauge`=painel, `fa-truck-fast`=a caminho, `fa-wallet`/`fa-coins`=financeiro...).

---

<div align="center">

**GuinchaFácil — Design System v1.0** · Documento gerado a partir da matriz real do projeto (`style.css` 778 linhas + views de produção) · Referência visual de capa: template CabME (Customer App / Driver-Owner App)

</div>
