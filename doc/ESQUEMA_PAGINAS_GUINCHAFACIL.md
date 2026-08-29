# ESQUEMA DE PÁGINAS — GuinchaFácil

> Documento de engenharia reversa visual gerado a partir de 32 screenshots (mobile 700×900 e desktop 1280×720).
> Para cada tela: **estrutura HTML** (árvore semântica), **CSS** (tokens, layout, componentes) e **JS** (comportamentos, eventos, integrações prováveis).
> Convenção de nomenclatura: BEM simplificado (`bloco__elemento--modificador`).

---

## 0. DESIGN SYSTEM GLOBAL (compartilhado por todas as páginas)

### 0.1 Tokens CSS (`:root`)

```css
:root {
  /* Cores primárias */
  --color-primary: #F59E0B;        /* Amarelo/âmbar — botões CTA, destaques */
  --color-primary-dark: #D97706;   /* Hover do CTA */
  --color-dark: #111827;           /* Fundo do header/hero escuro */
  --color-dark-2: #1F2937;         /* Cards sobre fundo escuro */
  --color-bg: #F3F4F6;             /* Fundo geral das telas internas */
  --color-surface: #FFFFFF;        /* Cards, formulários */
  --color-text: #111827;
  --color-text-muted: #6B7280;
  --color-border: #E5E7EB;

  /* Feedback */
  --color-success: #10B981;        /* Status "concluída", online */
  --color-danger: #EF4444;         /* Cancelamento, logout */
  --color-info: #3B82F6;           /* Links, rota no mapa */
  --color-warning: #F59E0B;

  /* Tipografia */
  --font-family: 'Inter', 'Poppins', system-ui, sans-serif;
  --fs-h1: 1.75rem; --fs-h2: 1.375rem; --fs-h3: 1.125rem;
  --fs-body: 0.9375rem; --fs-small: 0.8125rem;

  /* Espaçamento e forma */
  --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --radius-full: 999px;
  --shadow-card: 0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.05);
  --spacing: 1rem;
}
```

### 0.2 Componentes globais

| Componente | Classe base | Descrição |
|---|---|---|
| Botão primário | `.btn.btn--primary` | Fundo âmbar, texto escuro/branco, `border-radius: var(--radius-md)`, altura ~48px, largura 100% em mobile |
| Botão secundário | `.btn.btn--outline` | Borda 1px, fundo transparente |
| Card | `.card` | Fundo branco, `--shadow-card`, `--radius-lg`, padding 1rem–1.5rem |
| Input | `.form-group > label + input.form-control` | Altura ~48px, borda `--color-border`, focus ring âmbar |
| Badge de status | `.badge--success/--warning/--danger` | Pílula `--radius-full`, fundo tonalizado 10% |
| Bottom nav (mobile) | `.bottom-nav` | Fixa, 4–5 ícones, item ativo em âmbar |
| Topbar mobile | `.topbar` | Logo à esquerda, avatar/menu à direita, fundo escuro ou branco conforme contexto |
| Sidebar (admin desktop) | `.sidebar` | Coluna fixa escura ~240px, itens com ícone+label |

### 0.3 JS global

```
/assets/js/
├── app.js            → bootstrap, listeners globais, toggle de menu
├── api.js            → wrapper fetch() com baseURL /api ou rotas MVC (?route=)
├── auth.js           → sessão, guarda de rotas por perfil (cliente|guincho|admin)
├── map.js            → Leaflet/Google Maps: init, markers, polyline (OSRM)
├── sse.js            → EventSource para tracking em tempo real
├── toast.js          → notificações flutuantes
└── masks.js          → máscaras (telefone, placa, CPF/CNPJ)
```

---

# PARTE 1 — PÁGINAS PÚBLICAS (LANDING + AUTH)

---

## Página 01 — Landing / Home (topo) `[screenshot 01]`

**Rota:** `/` · **Perfil:** público · **Viewport:** mobile

### HTML
```html
<body class="landing">
  <header class="topbar topbar--dark">
    <a class="topbar__logo" href="/">
      <img src="logo.svg" alt="GuinchaFácil"> <span>GuinchaFácil</span>
    </a>
    <button class="topbar__hamburger" aria-label="Menu">☰</button>
  </header>

  <section class="hero hero--dark">
    <h1 class="hero__title">Guincho rápido e confiável<br>na palma da sua mão</h1>
    <p class="hero__subtitle">Solicite um guincho em minutos, acompanhe em tempo real.</p>
    <figure class="hero__image"><img src="hero-guincho.png" alt=""></figure>
    <div class="hero__cta">
      <a href="/solicitar" class="btn btn--primary btn--lg">Solicitar guincho</a>
      <a href="/cadastro-guincheiro" class="btn btn--outline btn--lg">Sou guincheiro</a>
    </div>
  </section>

  <section class="features">
    <h2 class="section__title">Por que usar o GuinchaFácil?</h2>
    <div class="features__grid">
      <article class="feature-card"><span class="feature-card__icon">⚡</span>
        <h3>Atendimento rápido</h3><p>…</p></article>
      <article class="feature-card"><span class="feature-card__icon">📍</span>
        <h3>Rastreamento em tempo real</h3><p>…</p></article>
      <article class="feature-card"><span class="feature-card__icon">💰</span>
        <h3>Preço justo</h3><p>…</p></article>
    </div>
  </section>
</body>
```

### CSS
- `.hero--dark`: fundo `--color-dark`, texto branco, título com palavra-chave em `--color-primary`.
- `.hero__image`: imagem full-width com leve overlay/gradiente para legibilidade.
- `.hero__cta`: `display:flex; flex-direction:column; gap:.75rem` (mobile).
- `.features__grid`: `grid-template-columns: 1fr` (mobile) → `repeat(3,1fr)` em `min-width:768px`.
- `.feature-card`: card branco com ícone circular tonalizado em âmbar.

### JS
- `topbar__hamburger` → toggle de menu off-canvas (classe `.menu--open` no `body`).
- Smooth scroll para âncoras internas (`scroll-behavior` ou `scrollIntoView`).
- Sem chamadas de API.

---

## Página 02 — Landing / "Como funciona" + Footer `[screenshot 02]`

**Rota:** `/` (continuação do scroll) · **Perfil:** público

### HTML
```html
<section class="how-it-works">
  <h2 class="section__title">Como funciona</h2>
  <ol class="steps">
    <li class="step">
      <span class="step__number">1</span>
      <h3 class="step__title">Informe origem e destino</h3>
      <p class="step__desc">…</p>
    </li>
    <li class="step"><span class="step__number">2</span>
      <h3>Receba o preço na hora</h3><p>…</p></li>
    <li class="step"><span class="step__number">3</span>
      <h3>Acompanhe o guincho no mapa</h3><p>…</p></li>
  </ol>
</section>

<section class="cta-final">
  <h2>Pronto para começar?</h2>
  <a href="/cadastro" class="btn btn--primary btn--lg">Criar conta grátis</a>
</section>

<footer class="footer footer--dark">
  <div class="footer__brand"><img src="logo.svg"> GuinchaFácil</div>
  <nav class="footer__links">
    <a href="/sobre">Sobre</a><a href="/termos">Termos</a><a href="/privacidade">Privacidade</a>
  </nav>
  <p class="footer__copy">© 2026 GuinchaFácil</p>
</footer>
```

### CSS
- `.step__number`: círculo âmbar 40px, número em peso 700.
- `.steps`: coluna com linha vertical conectora (`::before` com `border-left` tracejada) em mobile.
- `.cta-final`: bloco de destaque com fundo escuro ou âmbar, centralizado.
- `.footer--dark`: fundo `--color-dark`, links `--color-text-muted` com hover branco.

### JS
- Nenhum específico (estático). Opcional: `IntersectionObserver` para animar entrada dos steps.

---

## Página 03 — Login `[screenshot 03]`

**Rota:** `/login` · **Perfil:** público

### HTML
```html
<main class="auth-page">
  <div class="auth-card card">
    <img class="auth-card__logo" src="logo.svg" alt="GuinchaFácil">
    <h1 class="auth-card__title">Entrar</h1>

    <form id="loginForm" method="post" action="?route=auth/login">
      <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" class="form-control" required>
      </div>
      <div class="form-group form-group--password">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" class="form-control" required>
        <button type="button" class="toggle-password" aria-label="Mostrar senha">👁</button>
      </div>
      <a class="auth-card__forgot" href="/recuperar-senha">Esqueci minha senha</a>
      <button type="submit" class="btn btn--primary btn--block">Entrar</button>
    </form>

    <p class="auth-card__footer">
      Não tem conta? <a href="/cadastro">Cadastre-se</a>
    </p>
  </div>
</main>
```

### CSS
- `.auth-page`: `min-height:100vh; display:grid; place-items:center;` fundo `--color-bg` (ou hero escuro atrás do card).
- `.auth-card`: largura máx. 400px, padding 2rem, logo centralizada.
- `.form-group--password`: `position:relative;` com `.toggle-password` absoluto à direita.
- Estados de erro: `.form-control--error` (borda vermelha) + `.form-error` (texto `--color-danger`, `--fs-small`).

### JS (`auth.js`)
```js
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const res = await api.post('auth/login', new FormData(loginForm));
  if (res.ok) location.href = res.redirect;    // /cliente, /guincho ou /admin conforme perfil
  else toast.error(res.message);               // "E-mail ou senha inválidos"
});
// toggle-password: alterna input.type password ↔ text
```

---

## Página 04 — Cadastro (Cliente) `[screenshot 04]`

**Rota:** `/cadastro` · **Perfil:** público

### HTML
```html
<main class="auth-page">
  <div class="auth-card card">
    <h1>Criar conta</h1>
    <form id="registerForm" action="?route=auth/register" method="post">
      <div class="form-group"><label>Nome completo</label>
        <input name="nome" class="form-control" required></div>
      <div class="form-group"><label>E-mail</label>
        <input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label>Telefone</label>
        <input type="tel" name="telefone" class="form-control" data-mask="phone" required></div>
      <div class="form-group"><label>Senha</label>
        <input type="password" name="senha" class="form-control" minlength="8" required></div>
      <div class="form-group"><label>Confirmar senha</label>
        <input type="password" name="senha_confirm" class="form-control" required></div>

      <label class="checkbox">
        <input type="checkbox" name="termos" required>
        <span>Li e aceito os <a href="/termos">Termos de uso</a></span>
      </label>

      <button type="submit" class="btn btn--primary btn--block">Cadastrar</button>
    </form>
    <p class="auth-card__footer">Já tem conta? <a href="/login">Entrar</a></p>
  </div>
</main>
```

### CSS
- Mesmo layout da página 03 (`.auth-page` / `.auth-card`).
- `.checkbox`: flex com input custom (accent-color âmbar).

### JS
- Máscara de telefone `(00) 00000-0000` via `masks.js`.
- Validação client-side: senha ≥ 8, `senha === senha_confirm` (erro inline).
- Submit AJAX → sucesso redireciona para `/login` ou já autentica e vai ao painel.

---

## Página 05 — Cadastro (Guincheiro) `[screenshot 05]`

**Rota:** `/cadastro-guincheiro` · **Perfil:** público

### HTML
Igual à pág. 04 acrescida dos campos do veículo/profissional:

```html
<fieldset class="form-section">
  <legend>Dados do veículo</legend>
  <div class="form-group"><label>Placa</label>
    <input name="placa" class="form-control" data-mask="plate" required></div>
  <div class="form-group"><label>Modelo do guincho</label>
    <input name="modelo" class="form-control" required></div>
  <div class="form-group"><label>Tipo de guincho</label>
    <select name="tipo" class="form-control">
      <option>Plataforma</option><option>Reboque</option><option>Pesado</option>
    </select></div>
  <div class="form-group"><label>CNH</label>
    <input name="cnh" class="form-control" required></div>
</fieldset>
```

### CSS
- `.form-section`: separador visual (`legend` em `--fs-small` uppercase, cor muted; `border-top: 1px solid var(--color-border)` entre seções).

### JS
- Máscara de placa (Mercosul `AAA0A00`).
- Mesmo fluxo AJAX; ao concluir, mensagem de "cadastro em análise" (aprovação pelo admin) se aplicável.

---

## Página 06 — Recuperar senha `[screenshot 06]`

**Rota:** `/recuperar-senha`

### HTML
```html
<div class="auth-card card">
  <h1>Recuperar senha</h1>
  <p class="auth-card__desc">Informe seu e-mail e enviaremos um link de redefinição.</p>
  <form id="forgotForm" action="?route=auth/forgot" method="post">
    <div class="form-group"><label>E-mail</label>
      <input type="email" name="email" class="form-control" required></div>
    <button class="btn btn--primary btn--block">Enviar link</button>
  </form>
  <a class="auth-card__back" href="/login">← Voltar para o login</a>
</div>
```

### CSS / JS
- Layout idêntico às demais telas de auth.
- Submit AJAX → substitui o formulário por um estado de sucesso (`.auth-card__success` com ícone ✓ e texto "Verifique seu e-mail").

---

## Página 07 — Redefinir senha / etapa complementar `[screenshot 07]`

**Rota:** `/redefinir-senha?token=…`

### HTML
```html
<form id="resetForm" action="?route=auth/reset" method="post">
  <input type="hidden" name="token" value="…">
  <div class="form-group"><label>Nova senha</label>
    <input type="password" name="senha" class="form-control" minlength="8" required></div>
  <div class="form-group"><label>Confirmar nova senha</label>
    <input type="password" name="senha_confirm" class="form-control" required></div>
  <button class="btn btn--primary btn--block">Redefinir senha</button>
</form>
```

### JS
- Indicador de força de senha opcional (`.password-strength` com barra colorida).
- Token inválido/expirado → estado de erro com link para pedir novo.

---

# PARTE 2 — PAINEL DO CLIENTE (mobile-first)

Layout base compartilhado:

```html
<body class="app app--cliente">
  <header class="topbar">
    <span class="topbar__title">…</span>
    <button class="topbar__avatar">…</button>
  </header>
  <main class="app__content"> … </main>
  <nav class="bottom-nav">
    <a class="bottom-nav__item is-active" href="/cliente"><i>🏠</i><span>Início</span></a>
    <a class="bottom-nav__item" href="/cliente/corridas"><i>🕓</i><span>Corridas</span></a>
    <a class="bottom-nav__item" href="/cliente/perfil"><i>👤</i><span>Perfil</span></a>
  </nav>
</body>
```

CSS base: `.app__content { padding: 1rem; padding-bottom: 72px; }` (espaço para a bottom-nav fixa); `.bottom-nav { position:fixed; inset: auto 0 0 0; height:64px; background:#fff; border-top:1px solid var(--color-border); display:flex; }`.

---

## Página 08 — Dashboard do Cliente (Solicitar guincho) `[screenshot 08]`

**Rota:** `/cliente` · **Perfil:** cliente

### HTML
```html
<main class="app__content dashboard-cliente">
  <section class="greeting">
    <h1>Olá, {nome} 👋</h1>
    <p class="text-muted">Precisa de um guincho agora?</p>
  </section>

  <section class="card request-card">
    <form id="requestForm">
      <div class="form-group form-group--icon">
        <i class="icon icon--origin">●</i>
        <input id="origem" class="form-control" placeholder="Origem (sua localização)"
               autocomplete="off">
        <button type="button" id="btnGps" class="input-action" aria-label="Usar GPS">📍</button>
      </div>
      <div class="form-group form-group--icon">
        <i class="icon icon--dest">▪</i>
        <input id="destino" class="form-control" placeholder="Destino" autocomplete="off">
      </div>
      <ul id="suggestions" class="autocomplete" hidden></ul>

      <div class="form-group"><label>Tipo de veículo</label>
        <select id="tipoVeiculo" class="form-control">
          <option>Carro de passeio</option><option>SUV / Caminhonete</option>
          <option>Moto</option><option>Utilitário</option>
        </select>
      </div>

      <button type="submit" class="btn btn--primary btn--block">Calcular preço</button>
    </form>
  </section>

  <section class="quick-status" id="activeRide" hidden>
    <!-- card de corrida ativa, se houver -->
  </section>
</main>
```

### CSS
- `.request-card`: card elevado, inputs com ícones de origem (ponto verde) e destino (quadrado vermelho) conectados por linha vertical pontilhada (`::after`).
- `.autocomplete`: dropdown absoluto sob o input, itens com `padding:.75rem`, hover `--color-bg`.

### JS (`cliente-dashboard.js`)
- `btnGps` → `navigator.geolocation.getCurrentPosition()` → reverse-geocoding (Nominatim) → preenche `#origem`.
- Autocomplete com debounce 300ms → `GET https://nominatim.openstreetmap.org/search?q=…&format=json`.
- Submit → `POST ?route=corrida/cotacao` `{origem_lat,lng, destino_lat,lng, tipo}` → redireciona para a tela de cotação/mapa (pág. 09).
- Ao carregar: `GET ?route=corrida/ativa` — se existir corrida em andamento, mostra `#activeRide` com link para o tracking.

---

## Página 09 — Cotação com mapa (origem → destino) `[screenshot 09]`

**Rota:** `/cliente/cotacao` · **Perfil:** cliente

### HTML
```html
<main class="ride-quote">
  <div id="map" class="map map--fullscreen"></div>

  <section class="sheet sheet--bottom card">
    <div class="route-summary">
      <div class="route-summary__row"><i class="icon--origin"></i><span>{endereco_origem}</span></div>
      <div class="route-summary__row"><i class="icon--dest"></i><span>{endereco_destino}</span></div>
    </div>
    <div class="quote">
      <div class="quote__item"><span>Distância</span><strong id="dist">12,4 km</strong></div>
      <div class="quote__item"><span>Tempo est.</span><strong id="eta">25 min</strong></div>
      <div class="quote__item quote__item--price"><span>Valor</span><strong id="price">R$ 189,90</strong></div>
    </div>
    <button id="btnConfirm" class="btn btn--primary btn--block">Confirmar solicitação</button>
    <button id="btnBack" class="btn btn--ghost btn--block">Alterar endereços</button>
  </section>
</main>
```

### CSS
- `.map--fullscreen`: `position:fixed; inset:0;` mapa ocupa a tela toda.
- `.sheet--bottom`: bottom-sheet fixo (`position:fixed; bottom:0; left:0; right:0`), `border-radius: var(--radius-lg) var(--radius-lg) 0 0`, sombra para cima, handle opcional.
- `.quote`: grid 3 colunas; `.quote__item--price strong` em `--color-primary`, `--fs-h2`.

### JS (`map.js` + `cotacao.js`)
```js
const map = L.map('map');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
// markers origem/destino + polyline da rota via OSRM:
// GET https://router.project-osrm.org/route/v1/driving/{lng1},{lat1};{lng2},{lat2}?geometries=geojson
map.fitBounds(routeBounds, { padding: [40, 160] }); // padding inferior p/ bottom-sheet
```
- Preço vem centralizado do backend (`TarifaService` — bandeirada + km + tipo de veículo).
- `btnConfirm` → `POST ?route=corrida/criar` → redireciona para "Procurando guincho" (pág. 14).

---

## Página 10 — Acompanhamento no mapa (guincho a caminho) `[screenshot 10]`

**Rota:** `/cliente/corrida/{id}` · **Perfil:** cliente

### HTML
```html
<main class="ride-tracking">
  <div id="map" class="map map--fullscreen"></div>

  <section class="sheet sheet--bottom card">
    <header class="driver-info">
      <img class="driver-info__avatar" src="{foto}" alt="">
      <div class="driver-info__meta">
        <strong>{nome_guincheiro}</strong>
        <span class="text-muted">{modelo} · {placa}</span>
        <span class="rating">★ {nota}</span>
      </div>
      <a href="tel:{telefone}" class="btn-icon" aria-label="Ligar">📞</a>
    </header>

    <div class="eta-banner">
      <span>Chegada em</span><strong id="etaMin">8 min</strong>
    </div>

    <div class="ride-status-steps">
      <span class="step is-done">Aceito</span>
      <span class="step is-active">A caminho</span>
      <span class="step">Chegou</span>
      <span class="step">Em transporte</span>
    </div>

    <button id="btnCancel" class="btn btn--danger-outline btn--block">Cancelar corrida</button>
  </section>
</main>
```

### CSS
- `.driver-info`: flex, avatar 48px circular.
- `.eta-banner`: faixa tonalizada âmbar com número grande.
- `.ride-status-steps`: stepper horizontal; `.is-done` verde, `.is-active` âmbar com pulso (`@keyframes pulse`).

### JS (`tracking.js` + `sse.js`)
```js
const es = new EventSource(`?route=corrida/stream&id=${rideId}`);
es.addEventListener('position', (e) => {
  const { lat, lng, eta } = JSON.parse(e.data);
  guinchoMarker.setLatLng([lat, lng]);
  etaMin.textContent = `${eta} min`;
  // IMPORTANTE: rota desenhada = guincho → ORIGEM (não origem → destino)
  drawRoute([lat, lng], origemLatLng);
});
es.addEventListener('status', (e) => updateStepper(JSON.parse(e.data).status));
```
- `btnCancel` → modal de confirmação → `POST ?route=corrida/cancelar` (com exibição de taxa de cancelamento conforme faixa/tempo).

---

## Página 11 — Em transporte / corrida em andamento `[screenshot 11]`

**Rota:** mesma da pág. 10, estado `em_transporte`

### HTML
Mesma estrutura da pág. 10 com diferenças:

```html
<div class="ride-status-steps">
  <span class="step is-done">Aceito</span>
  <span class="step is-done">A caminho</span>
  <span class="step is-done">Chegou</span>
  <span class="step is-active">Em transporte</span>
</div>
<!-- rota agora: posição atual do guincho → DESTINO -->
<div class="destination-banner">
  <span>Destino</span><strong>{endereco_destino}</strong>
</div>
```

### JS
- Mesmo SSE; ao evento `status: em_transporte`, a polyline passa a ligar guincho → destino.
- Evento `status: concluida` → redireciona para a tela de avaliação (pág. 18).

---

## Página 12 — Detalhes da corrida / recibo `[screenshot 12]`

**Rota:** `/cliente/corrida/{id}/detalhes`

### HTML
```html
<main class="app__content ride-details">
  <section class="card">
    <header class="ride-details__header">
      <span class="badge badge--success">Concluída</span>
      <time class="text-muted">{data} · {hora}</time>
    </header>
    <div class="route-summary"> … origem / destino … </div>
    <hr>
    <dl class="receipt">
      <div><dt>Bandeirada</dt><dd>R$ 80,00</dd></div>
      <div><dt>Distância (12,4 km × R$ 8,50)</dt><dd>R$ 105,40</dd></div>
      <div><dt>Adicional tipo de veículo</dt><dd>R$ 4,50</dd></div>
      <div class="receipt__total"><dt>Total</dt><dd>R$ 189,90</dd></div>
    </dl>
    <div class="driver-info"> … guincheiro + nota … </div>
  </section>
  <button class="btn btn--outline btn--block">Solicitar novamente</button>
</main>
```

### CSS
- `.receipt`: `display:grid`, linhas `dt`/`dd` justificadas; `.receipt__total` com `border-top`, `font-weight:700`, valor em âmbar.

### JS
- Estático (dados server-side). "Solicitar novamente" → pré-popula o formulário da pág. 08 via querystring/localStorage.

---

## Página 13 — Confirmação / feedback de sucesso `[screenshot 13]`

**Rota:** estado pós-ação (solicitação criada / cadastro concluído)

### HTML
```html
<main class="feedback-page">
  <div class="feedback">
    <span class="feedback__icon feedback__icon--success">✓</span>
    <h1>Solicitação enviada!</h1>
    <p class="text-muted">Estamos procurando o guincho mais próximo de você.</p>
    <button class="btn btn--primary btn--block" id="btnGo">Acompanhar</button>
  </div>
</main>
```

### CSS
- `.feedback__icon--success`: círculo 80px verde tonalizado, ícone check com animação `scale-in`.
- Página centralizada (`display:grid; place-items:center; min-height:100vh`).

### JS
- Redirecionamento automático após ~3s (`setTimeout`) ou clique no botão.

---

## Página 14 — Procurando guincho (aguardando aceite) `[screenshot 14]`

**Rota:** `/cliente/corrida/{id}` (estado `pendente`)

### HTML
```html
<main class="searching">
  <div class="searching__radar">
    <span class="radar__pulse"></span>
    <span class="radar__pulse radar__pulse--delay"></span>
    <i class="radar__icon">🚛</i>
  </div>
  <h1>Procurando guincho…</h1>
  <p class="text-muted">Notificando guincheiros próximos</p>
  <button id="btnCancelSearch" class="btn btn--danger-outline">Cancelar</button>
</main>
```

### CSS
```css
.radar__pulse {
  position:absolute; inset:0; border-radius:50%;
  border:2px solid var(--color-primary);
  animation: radar 2s ease-out infinite;
}
.radar__pulse--delay { animation-delay: 1s; }
@keyframes radar { from {transform:scale(.4); opacity:1} to {transform:scale(1.6); opacity:0} }
```

### JS
- Polling `GET ?route=corrida/status&id=…` a cada 3–5s **ou** SSE.
- `status: aceita` → redireciona ao tracking (pág. 10).
- Timeout sem aceite (ex.: 3 min) → mensagem "Nenhum guincho disponível" + opção de tentar novamente.
- Cancelar nesta fase = sem taxa.

---

## Página 15 — Histórico de corridas `[screenshot 15]`

**Rota:** `/cliente/corridas`

### HTML
```html
<main class="app__content history">
  <h1>Minhas corridas</h1>
  <div class="filter-tabs">
    <button class="tab is-active" data-filter="todas">Todas</button>
    <button class="tab" data-filter="concluida">Concluídas</button>
    <button class="tab" data-filter="cancelada">Canceladas</button>
  </div>

  <ul class="ride-list">
    <li class="ride-item card" data-status="concluida">
      <div class="ride-item__route">
        <span class="ride-item__origin">{origem}</span>
        <span class="ride-item__dest">{destino}</span>
      </div>
      <div class="ride-item__meta">
        <time>{data}</time>
        <strong class="ride-item__price">R$ 189,90</strong>
        <span class="badge badge--success">Concluída</span>
      </div>
    </li>
    <!-- … -->
  </ul>
</main>
```

### CSS
- `.filter-tabs`: pílulas horizontais scrolláveis (`overflow-x:auto`), `.is-active` fundo âmbar.
- `.ride-item`: card clicável (→ pág. 12), rota com ícones ponto/quadrado alinhados por linha vertical.

### JS
- Filtros client-side (`data-filter` → mostra/oculta itens) ou recarga AJAX `GET ?route=corridas/lista&status=…`.
- Paginação por "carregar mais" ou infinite scroll (`IntersectionObserver` no sentinel).

---

## Página 16 — Perfil do cliente `[screenshot 16]`

**Rota:** `/cliente/perfil`

### HTML
```html
<main class="app__content profile">
  <section class="profile__header card">
    <img class="profile__avatar" src="{foto}" alt="">
    <h1>{nome}</h1>
    <p class="text-muted">{email}</p>
  </section>

  <nav class="menu-list card">
    <a class="menu-list__item" href="/cliente/perfil/editar"><i>👤</i> Dados pessoais <span>›</span></a>
    <a class="menu-list__item" href="/cliente/veiculos"><i>🚗</i> Meus veículos <span>›</span></a>
    <a class="menu-list__item" href="/cliente/pagamento"><i>💳</i> Pagamento <span>›</span></a>
    <a class="menu-list__item" href="/cliente/configuracoes"><i>⚙️</i> Configurações <span>›</span></a>
    <a class="menu-list__item" href="/ajuda"><i>❓</i> Ajuda <span>›</span></a>
    <button class="menu-list__item menu-list__item--danger" id="btnLogout"><i>🚪</i> Sair</button>
  </nav>
</main>
```

### CSS
- `.profile__avatar`: 88px circular, borda âmbar 3px.
- `.menu-list__item`: linha com `border-bottom`, ícone à esquerda em círculo tonalizado, chevron à direita; `--danger` em vermelho.

### JS
- `btnLogout` → confirmação → `POST ?route=auth/logout` → `/login`.

---

## Página 17 — Editar dados / Configurações `[screenshot 17]`

**Rota:** `/cliente/perfil/editar`

### HTML
```html
<form id="profileForm" class="card" action="?route=perfil/atualizar" method="post">
  <div class="avatar-upload">
    <img src="{foto}" id="avatarPreview">
    <label class="avatar-upload__btn">📷
      <input type="file" name="foto" accept="image/*" hidden>
    </label>
  </div>
  <div class="form-group"><label>Nome</label>
    <input name="nome" class="form-control" value="{nome}"></div>
  <div class="form-group"><label>E-mail</label>
    <input type="email" name="email" class="form-control" value="{email}"></div>
  <div class="form-group"><label>Telefone</label>
    <input type="tel" name="telefone" class="form-control" value="{telefone}"></div>
  <button class="btn btn--primary btn--block">Salvar alterações</button>
</form>
```

### JS
- Preview de avatar: `FileReader.readAsDataURL` → `avatarPreview.src`.
- Submit AJAX `multipart/form-data` → toast de sucesso.

---

## Página 18 — Avaliação da corrida `[screenshot 18]`

**Rota:** `/cliente/corrida/{id}/avaliar` (modal ou página)

### HTML
```html
<main class="rating-page">
  <div class="card rating-card">
    <img class="driver-info__avatar" src="{foto}">
    <h1>Como foi sua corrida com {nome}?</h1>

    <div class="stars" role="radiogroup">
      <button class="star" data-value="1" aria-label="1 estrela">★</button>
      <button class="star" data-value="2">★</button>
      <button class="star" data-value="3">★</button>
      <button class="star" data-value="4">★</button>
      <button class="star" data-value="5">★</button>
    </div>

    <textarea class="form-control" name="comentario"
              placeholder="Deixe um comentário (opcional)"></textarea>

    <button id="btnSendRating" class="btn btn--primary btn--block" disabled>Enviar avaliação</button>
    <button class="btn btn--ghost btn--block">Pular</button>
  </div>
</main>
```

### CSS
- `.star`: 40px, cor `--color-border`; `.star.is-filled` em âmbar com transição; hover preenche até o índice.

### JS
```js
stars.forEach(s => s.onclick = () => {
  rating = +s.dataset.value;
  stars.forEach((x,i) => x.classList.toggle('is-filled', i < rating));
  btnSendRating.disabled = false;
});
// POST ?route=avaliacao/criar {corrida_id, nota, comentario} → volta ao dashboard
```

---

# PARTE 3 — PAINEL DO GUINCHEIRO (mobile-first)

Layout base igual ao do cliente (`.app--guincho`), bottom-nav com: **Início · Corridas · Ganhos · Perfil**.

---

## Página 21 — Dashboard do Guincheiro `[screenshot 21]`

**Rota:** `/guincho` · **Perfil:** guincho

### HTML
```html
<main class="app__content dashboard-guincho">
  <section class="status-toggle card">
    <div>
      <strong>Status</strong>
      <p class="text-muted" id="statusLabel">Você está online</p>
    </div>
    <label class="switch">
      <input type="checkbox" id="toggleOnline" checked>
      <span class="switch__slider"></span>
    </label>
  </section>

  <section class="stats-row">
    <div class="stat-card card"><span class="stat-card__value">R$ 480</span><span class="stat-card__label">Hoje</span></div>
    <div class="stat-card card"><span class="stat-card__value">6</span><span class="stat-card__label">Corridas</span></div>
    <div class="stat-card card"><span class="stat-card__value">★ 4.8</span><span class="stat-card__label">Nota</span></div>
  </section>

  <section id="incomingRides">
    <h2>Solicitações próximas</h2>
    <!-- cards injetados via JS (ver pág. 23) -->
    <p class="empty-state" hidden>Nenhuma solicitação no momento</p>
  </section>
</main>
```

### CSS
- `.switch`: toggle iOS-like; ligado = trilho verde/âmbar.
- `.stats-row`: `display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem;`
- `.stat-card__value`: `--fs-h2`, peso 700.

### JS (`guincho-dashboard.js`)
- `toggleOnline` → `POST ?route=guincho/status {online: bool}`; quando online, inicia:
  - `navigator.geolocation.watchPosition()` → `POST ?route=guincho/posicao` a cada N segundos;
  - SSE/polling de novas solicitações → injeta cards em `#incomingRides` + som/vibração (`navigator.vibrate`).

---

## Página 23 — Nova solicitação (aceitar/recusar) `[screenshot 23]`

**Rota:** card/modal sobre o dashboard

### HTML
```html
<article class="ride-offer card">
  <header class="ride-offer__header">
    <span class="badge badge--warning">Nova solicitação</span>
    <span class="ride-offer__timer" id="offerTimer">0:28</span>
  </header>
  <div class="route-summary">
    <div><i class="icon--origin"></i> {origem} <small>({dist_ate_origem} km de você)</small></div>
    <div><i class="icon--dest"></i> {destino}</div>
  </div>
  <div class="ride-offer__meta">
    <span>🚗 {tipo_veiculo}</span>
    <strong class="ride-offer__price">R$ 189,90</strong>
  </div>
  <div class="ride-offer__actions">
    <button class="btn btn--danger-outline" data-action="recusar">Recusar</button>
    <button class="btn btn--primary" data-action="aceitar">Aceitar</button>
  </div>
</article>
```

### CSS
- `.ride-offer__timer`: contagem regressiva em destaque; abaixo de 10s fica vermelho.
- Barra de progresso do tempo no topo do card (`::before` com `width` animada).
- `.ride-offer__actions`: grid 2 colunas.

### JS
- Timer 30s (`setInterval`); ao expirar, remove o card (oferta perdida).
- Aceitar → `POST ?route=corrida/aceitar {id}` → navega para pág. 24. Conflito (409, outro aceitou antes) → toast + remove card.

---

## Página 24 — Corrida aceita (detalhes p/ o guincheiro) `[screenshot 24]`

**Rota:** `/guincho/corrida/{id}`

### HTML
```html
<main class="ride-active">
  <div id="map" class="map map--fullscreen"></div>
  <section class="sheet sheet--bottom card">
    <header class="client-info">
      <img class="client-info__avatar" src="{foto_cliente}">
      <div><strong>{nome_cliente}</strong><span class="text-muted">{tipo_veiculo}</span></div>
      <a href="tel:{telefone}" class="btn-icon">📞</a>
      <a href="https://wa.me/{telefone}" class="btn-icon">💬</a>
    </header>
    <div class="route-summary"> … </div>
    <button id="btnNavigate" class="btn btn--outline btn--block">Abrir no Waze/Maps</button>
    <button id="btnArrived" class="btn btn--primary btn--block">Cheguei ao local</button>
  </section>
</main>
```

### JS
- `btnNavigate` → deep-link `geo:{lat},{lng}` / `https://waze.com/ul?ll={lat},{lng}&navigate=yes`.
- `btnArrived` → `POST ?route=corrida/status {status:'chegou'}` → botão muda para "Iniciar transporte".
- Envio contínuo de posição (mesmo `watchPosition` do dashboard) alimenta o SSE do cliente.

---

## Página 25 — Em transporte (visão do guincheiro) `[screenshot 25]`

**Rota:** mesma da pág. 24, estado `em_transporte`

### HTML
- Mesmo esqueleto; bottom-sheet agora exibe:

```html
<div class="eta-banner"><span>Destino em</span><strong>{eta} min</strong></div>
<button id="btnFinish" class="btn btn--success btn--block">Finalizar corrida</button>
```

### JS
- Rota no mapa: posição atual → destino.
- `btnFinish` → confirmação → `POST ?route=corrida/finalizar` → tela de resumo/ganho.

---

## Página 26 — Ganhos / extrato do guincheiro `[screenshot 26]`

**Rota:** `/guincho/ganhos`

### HTML
```html
<main class="app__content earnings">
  <section class="card earnings__summary">
    <span class="text-muted">Ganhos desta semana</span>
    <strong class="earnings__total">R$ 2.340,00</strong>
    <div class="earnings__chart"><canvas id="weekChart"></canvas></div>
  </section>

  <div class="filter-tabs">
    <button class="tab is-active">Semana</button>
    <button class="tab">Mês</button>
  </div>

  <ul class="earnings-list">
    <li class="card earning-item">
      <div><strong>{origem} → {destino}</strong><time class="text-muted">{data}</time></div>
      <span class="earning-item__value">+ R$ 189,90</span>
    </li>
  </ul>
</main>
```

### CSS
- `.earnings__total`: `--fs-h1` em verde/âmbar.
- Gráfico de barras por dia da semana (barras âmbar, dia atual destacado).

### JS
- Chart.js (ou canvas manual) com dados de `GET ?route=guincho/ganhos?periodo=semana`.
- Troca de período recarrega dataset sem reload.

---

## Página 27 — Histórico de corridas do guincheiro `[screenshot 27]`

**Rota:** `/guincho/corridas`

Estrutura idêntica à pág. 15 (mesmos componentes `.filter-tabs`, `.ride-list`), com valor exibido como ganho (`+ R$ …`) e badge de status. JS igual (filtro + paginação).

---

## Página 28 — Perfil do guincheiro `[screenshot 28]`

**Rota:** `/guincho/perfil`

Mesma estrutura da pág. 16, acrescida de:

```html
<section class="card vehicle-card">
  <h2>Meu guincho</h2>
  <dl>
    <div><dt>Modelo</dt><dd>{modelo}</dd></div>
    <div><dt>Placa</dt><dd>{placa}</dd></div>
    <div><dt>Tipo</dt><dd>{tipo}</dd></div>
  </dl>
  <a href="/guincho/veiculo/editar" class="btn btn--outline btn--block">Editar veículo</a>
</section>
<section class="card rating-summary">
  <strong>★ 4.8</strong><span class="text-muted">Baseado em {n} avaliações</span>
</section>
```

---

# PARTE 4 — PAINEL ADMIN (desktop, 1280×720)

Layout base:

```html
<body class="admin">
  <aside class="sidebar">
    <div class="sidebar__logo"><img src="logo.svg"> GuinchaFácil</div>
    <nav class="sidebar__nav">
      <a class="sidebar__item is-active" href="/admin"><i>📊</i> Dashboard</a>
      <a class="sidebar__item" href="/admin/corridas"><i>🚛</i> Corridas</a>
      <a class="sidebar__item" href="/admin/clientes"><i>👥</i> Clientes</a>
      <a class="sidebar__item" href="/admin/guincheiros"><i>🪪</i> Guincheiros</a>
      <a class="sidebar__item" href="/admin/tarifas"><i>💰</i> Tarifas</a>
      <a class="sidebar__item" href="/admin/relatorios"><i>📈</i> Relatórios</a>
      <a class="sidebar__item" href="/admin/config"><i>⚙️</i> Configurações</a>
    </nav>
    <button class="sidebar__logout">🚪 Sair</button>
  </aside>

  <div class="admin__main">
    <header class="admin__topbar">
      <input class="search" placeholder="Buscar…">
      <div class="admin__user"><img src="{avatar}"> {nome}</div>
    </header>
    <main class="admin__content"> … </main>
  </div>
</body>
```

CSS base:
```css
.admin { display:grid; grid-template-columns:240px 1fr; min-height:100vh; }
.sidebar { background:var(--color-dark); color:#fff; padding:1rem 0; position:sticky; top:0; height:100vh; }
.sidebar__item { display:flex; gap:.75rem; padding:.75rem 1.25rem; color:#9CA3AF; border-left:3px solid transparent; }
.sidebar__item.is-active { color:#fff; background:rgba(255,255,255,.06); border-left-color:var(--color-primary); }
.admin__content { padding:1.5rem; background:var(--color-bg); }
```

---

## Página 19 — Admin: Dashboard (KPIs + gráficos) `[screenshot 19]`

**Rota:** `/admin` · **Perfil:** admin

### HTML
```html
<main class="admin__content">
  <h1>Dashboard</h1>

  <section class="kpi-grid">
    <div class="kpi-card card">
      <span class="kpi-card__label">Corridas hoje</span>
      <strong class="kpi-card__value">42</strong>
      <span class="kpi-card__delta kpi-card__delta--up">▲ 12%</span>
    </div>
    <div class="kpi-card card"><span>Faturamento</span><strong>R$ 7.890</strong>…</div>
    <div class="kpi-card card"><span>Guincheiros online</span><strong>18</strong>…</div>
    <div class="kpi-card card"><span>Ticket médio</span><strong>R$ 187,80</strong>…</div>
  </section>

  <section class="charts-row">
    <div class="card chart-card chart-card--wide">
      <h2>Corridas por dia</h2>
      <canvas id="ridesChart"></canvas>
    </div>
    <div class="card chart-card">
      <h2>Status das corridas</h2>
      <canvas id="statusChart"></canvas> <!-- donut -->
    </div>
  </section>

  <section class="card">
    <h2>Últimas corridas</h2>
    <table class="table"> … (ver pág. 20) … </table>
  </section>
</main>
```

### CSS
- `.kpi-grid`: `grid-template-columns:repeat(4,1fr); gap:1rem;`
- `.kpi-card__value`: `--fs-h1` (2rem), peso 700; delta verde/vermelho.
- `.charts-row`: `grid-template-columns:2fr 1fr;`

### JS
- Chart.js: linha/barra (corridas por dia) + doughnut (concluídas / canceladas / em andamento) com paleta âmbar/verde/vermelho.
- Auto-refresh dos KPIs a cada 60s (`GET ?route=admin/kpis`).

---

## Página 20 — Admin: Tabela de corridas `[screenshot 20]`

**Rota:** `/admin/corridas`

### HTML
```html
<main class="admin__content">
  <header class="page-header">
    <h1>Corridas</h1>
    <div class="page-header__actions">
      <select class="form-control" id="filterStatus">
        <option value="">Todos os status</option>
        <option>Pendente</option><option>Em andamento</option>
        <option>Concluída</option><option>Cancelada</option>
      </select>
      <input type="date" class="form-control" id="filterDate">
      <button class="btn btn--outline" id="btnExport">Exportar CSV</button>
    </div>
  </header>

  <div class="card table-card">
    <table class="table">
      <thead>
        <tr><th>#</th><th>Cliente</th><th>Guincheiro</th><th>Origem → Destino</th>
            <th>Valor</th><th>Status</th><th>Data</th><th></th></tr>
      </thead>
      <tbody id="ridesTbody">
        <tr>
          <td>#1042</td><td>{cliente}</td><td>{guincheiro}</td>
          <td class="table__route">{origem} → {destino}</td>
          <td>R$ 189,90</td>
          <td><span class="badge badge--success">Concluída</span></td>
          <td>{data}</td>
          <td><button class="btn-icon" data-action="ver">👁</button></td>
        </tr>
      </tbody>
    </table>
    <footer class="table-pagination">
      <span>Mostrando 1–20 de 356</span>
      <div class="pagination"><button>‹</button><button class="is-active">1</button>
        <button>2</button><button>3</button><button>›</button></div>
    </footer>
  </div>
</main>
```

### CSS
- `.table`: largura 100%, `th` em `--fs-small` uppercase muted, linhas com `border-bottom` e hover `--color-bg`.
- `.table__route`: `max-width` com `text-overflow:ellipsis`.

### JS
- Filtros → `GET ?route=admin/corridas?status=…&data=…&page=…` (preservando parâmetros na paginação).
- `btnExport` → download CSV do dataset filtrado.
- 👁 → modal/rota de detalhe com mapa da corrida.

---

## Página 22 — Admin: Gestão de usuários (clientes/guincheiros) `[screenshot 22]`

**Rota:** `/admin/guincheiros` (e análoga `/admin/clientes`)

### HTML
```html
<main class="admin__content">
  <header class="page-header">
    <h1>Guincheiros</h1>
    <div class="page-header__actions">
      <input class="form-control search" placeholder="Buscar por nome, placa…">
      <button class="btn btn--primary">+ Novo guincheiro</button>
    </div>
  </header>

  <div class="card table-card">
    <table class="table">
      <thead><tr>
        <th>Nome</th><th>Telefone</th><th>Veículo/Placa</th>
        <th>Nota</th><th>Corridas</th><th>Status</th><th>Ações</th>
      </tr></thead>
      <tbody>
        <tr>
          <td class="table__user"><img src="{avatar}"> {nome}</td>
          <td>{telefone}</td><td>{modelo} · {placa}</td>
          <td>★ 4.8</td><td>132</td>
          <td><span class="badge badge--success">Ativo</span></td>
          <td class="table__actions">
            <button class="btn-icon" data-action="editar">✏️</button>
            <button class="btn-icon" data-action="bloquear">🚫</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</main>
```

### JS
- Busca com debounce → filtro server-side.
- Bloquear/aprovar → `POST ?route=admin/guincheiro/status {id, ativo}` com confirmação; atualiza badge sem reload.
- Modal de edição reutilizando os campos do cadastro (pág. 05).

---

# PARTE 5 — TELAS COMPLEMENTARES `[screenshots 29–32]`

## Página 29 — Detalhe de corrida com mapa (visão expandida)
- Mesmo esqueleto das págs. 10/12: `#map` no topo (não fullscreen, altura ~40vh, `--radius-lg`), card de resumo abaixo com recibo (`.receipt`), stepper de status e dados do guincheiro/cliente.
- JS: mapa estático (polyline da rota percorrida) sem SSE — corrida já finalizada.

## Página 30 — Notificações / atividade
```html
<ul class="notif-list">
  <li class="notif card is-unread">
    <i class="notif__icon">🚛</i>
    <div><strong>Guincho a caminho</strong>
      <p class="text-muted">João aceitou sua solicitação</p></div>
    <time>há 2 min</time>
  </li>
</ul>
```
- `.is-unread`: borda esquerda âmbar + fundo levemente tonalizado.
- JS: marcar como lida no clique (`POST ?route=notificacao/lida`), badge de contagem na topbar.

## Página 31 — Configurações / preferências
```html
<section class="card settings">
  <div class="settings__row"><span>Notificações push</span>
    <label class="switch"><input type="checkbox" checked><span class="switch__slider"></span></label></div>
  <div class="settings__row"><span>Notificações por e-mail</span>
    <label class="switch"><input type="checkbox"><span class="switch__slider"></span></label></div>
  <div class="settings__row"><span>Alterar senha</span><a href="…">›</a></div>
</section>
<button class="btn btn--danger-outline btn--block">Excluir minha conta</button>
```
- JS: toggles persistem via `POST ?route=config/salvar`; exclusão exige modal de confirmação com input de senha.

## Página 32 — Ajuda / FAQ
```html
<section class="faq">
  <details class="faq__item card">
    <summary>Como é calculado o preço?</summary>
    <p>…</p>
  </details>
  <details class="faq__item card"><summary>Posso cancelar uma corrida?</summary><p>…</p></details>
</section>
<a href="https://wa.me/…" class="btn btn--primary btn--block">Falar com o suporte</a>
```
- CSS: `summary` com chevron rotacionado quando `details[open]`; nativo, sem JS obrigatório.

---

# APÊNDICE A — Mapa de rotas × arquivos (MVC sugerido)

| Rota | Controller | View | JS |
|---|---|---|---|
| `/` | `HomeController::index` | `views/home/index.php` | `landing.js` |
| `/login`, `/cadastro`, `/recuperar-senha` | `AuthController` | `views/auth/*` | `auth.js`, `masks.js` |
| `/cliente` | `ClienteController::dashboard` | `views/cliente/dashboard.php` | `cliente-dashboard.js`, `map.js` |
| `/cliente/cotacao` | `CorridaController::cotacao` | `views/corrida/cotacao.php` | `cotacao.js`, `map.js` |
| `/cliente/corrida/{id}` | `CorridaController::tracking` | `views/corrida/tracking.php` | `tracking.js`, `sse.js` |
| `/cliente/corridas` | `CorridaController::historico` | `views/corrida/historico.php` | `historico.js` |
| `/cliente/perfil` | `PerfilController` | `views/perfil/*` | `perfil.js` |
| `/guincho` | `GuinchoController::dashboard` | `views/guincho/dashboard.php` | `guincho-dashboard.js` |
| `/guincho/corrida/{id}` | `GuinchoController::corrida` | `views/guincho/corrida.php` | `guincho-corrida.js`, `map.js` |
| `/guincho/ganhos` | `GuinchoController::ganhos` | `views/guincho/ganhos.php` | `ganhos.js` (Chart.js) |
| `/admin` | `AdminController::dashboard` | `views/admin/dashboard.php` | `admin-dashboard.js` (Chart.js) |
| `/admin/corridas` | `AdminController::corridas` | `views/admin/corridas.php` | `admin-tables.js` |
| `/admin/guincheiros`, `/admin/clientes` | `AdminController::usuarios` | `views/admin/usuarios.php` | `admin-tables.js` |

# APÊNDICE B — Contratos de API (resumo)

| Endpoint | Método | Payload | Resposta |
|---|---|---|---|
| `?route=auth/login` | POST | `{email, senha}` | `{ok, redirect}` |
| `?route=corrida/cotacao` | POST | `{origem, destino, tipo}` | `{distancia_km, eta_min, valor, geojson_rota}` |
| `?route=corrida/criar` | POST | cotação confirmada | `{id, status:'pendente'}` |
| `?route=corrida/stream&id=` | SSE | — | eventos `position`, `status` |
| `?route=corrida/aceitar` | POST | `{id}` | `200` ou `409` (já aceita) |
| `?route=corrida/status` | POST | `{id, status}` | transições: aceita→chegou→em_transporte→concluida |
| `?route=corrida/cancelar` | POST | `{id, motivo}` | `{taxa}` conforme faixa de tempo |
| `?route=guincho/posicao` | POST | `{lat, lng}` | `204` |
| `?route=avaliacao/criar` | POST | `{corrida_id, nota, comentario}` | `201` |
| `?route=admin/kpis` | GET | — | `{corridas_hoje, faturamento, online, ticket}` |

# APÊNDICE C — Pontos de atenção (do audit ADITIVO_01)

1. **Tracking:** a polyline em `/cliente/corrida/{id}` deve ligar **guincho → origem** na fase "a caminho" (bug conhecido: desenhava origem → destino).
2. **Tarifa centralizada:** o valor exibido nas págs. 09, 12, 23 e 26 deve vir de um único `TarifaService` (evitar as 5 duplicações mapeadas).
3. **Contraste:** badges e textos muted sobre fundo escuro devem respeitar WCAG AA (mín. 4.5:1) — atenção especial no sidebar do admin e no hero da landing.
4. **SSE:** garantir fechamento do `EventSource` ao sair da página (`beforeunload`) e reconexão com backoff.
5. **Bottom-sheet:** reservar padding-bottom no `fitBounds` do mapa para o sheet não cobrir os markers.
