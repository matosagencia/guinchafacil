# ADITIVO 01 — Ajustes pós-implementação dos Painéis
## Revisão de QA visual sobre `cliente/dashboard.php` e `guincho/dashboard.php`

> **⚠️ STATUS ATUALIZADO (21/07/2026) — reverificado contra o código real, não apenas herdado.**
> Este documento nunca tinha sido reauditado desde 13/07 (a auditoria de 14/07 e os relatórios
> de 18-20/07 citam a checklist abaixo como "não reavaliada"). Ao ler o código atual
> (`src/Views/cliente/dashboard.php`, `src/Views/guincho/dashboard.php`,
> `public/assets/js/app.js`) diretamente para o pacote §A4, a maior parte dos itens **não
> reproduz mais**:
> - **Item A (texto de dev vazando pra UI)**: nenhuma das frases citadas abaixo existe mais
>   em `cliente/dashboard.php`/`guincho/dashboard.php` — grep dedicado não encontrou "SSE",
>   "polling", "render", "container" em texto visível nesses dois arquivos.
> - **IDs duplicados** (`#map`, `#pedidoAtivoClienteCard`): não existem — os elementos são
>   renderizados condicionalmente (`if/elseif/else` do PHP), só um por vez no DOM.
> - **CTAs duplicados, "Ações rápidas" redundante, "Online" triplicado, card sobrepondo
>   footer**: nenhum desses elementos/strings existe no código atual.
> - **Formulário rápido do cliente**: já intercepta o submit via JS, geocodifica e faz POST
>   JSON pro endpoint certo (`/cliente/pedido/rascunho`) — não está quebrado.
>
> **Continua sem verificação visual dedicada** (não é código morto, é um julgamento de
> design que precisa de olho humano/screenshot, não só grep): hero compacto vs. vazio,
> contraste dos stat-cards do guincho (Item C), ~100 `style=""` inline espalhados (maioria
> em `auth/`, cosmético). Recomendação: próxima auditoria visual deve confirmar isso com
> screenshot antes de reabrir como pendência real — não repetir a checklist abaixo sem
> reverificar contra o código, como aconteceu aqui.
>
> Dois bugs reais (não listados abaixo) foram encontrados nesta mesma verificação e já
> corrigidos: vazamento de listener/ResizeObserver no mapa do guincho
> (`MapManager.destroy()` em `app.js`) e escape de HTML incompleto no chat
> (`guincho/atendimento.php`, só escapava `<`).

> **Documento complementar ao** `GUIA_DESIGN_PAINEIS_GUINCHAFACIL.md`.
> Baseado nos screenshots renderizados de 13/07/2026 (`localhost:8080/cliente/dashboard` e `/guincho/dashboard`).
> A implementação seguiu os blocos do guia, mas divergiu em **composição, hierarquia e contraste**. Este aditivo lista cada desvio com causa-raiz e correção obrigatória. Nenhum item altera contratos de JS (IDs, `data-*`, SSE, polling).

---

## RESUMO EXECUTIVO — por que "ficou diferente"

O guia foi executado como **inventário de componentes** (os blocos existem), mas não como **sistema de composição** (os blocos não estão nos lugares, tamanhos e cores certos). Os 4 erros estruturais:

| # | Erro | Onde aparece | Gravidade |
|---|---|---|---|
| A | Texto de desenvolvedor vazou para a interface do usuário | Subtítulos e descrições nos 2 painéis | 🔴 Crítica |
| B | Hero com vazio gigante (composição de colunas errada) | Os 2 painéis | 🔴 Crítica |
| C | Contraste quebrado nos stat-cards do guincho (texto cinza sobre cinza) | Painel guincho | 🔴 Crítica |
| D | CTAs e informações duplicadas competindo entre si | Os 2 painéis | 🟠 Alta |

---

## A. LINGUAGEM: remover texto de dev da UI

**Evidência nos screenshots.** Os usuários finais estão lendo notas de implementação:

- Cliente: *"Área do cliente com o mesmo idioma visual dos novos perfis."*
- Guincho: *"Dashboard operacional redesenhado sem alterar SSE, mapa, toggle ou atendimento."*
- Guincho / mapa: *"Mesmo container e mesma inicialização JavaScript já usados pela operação."*
- Guincho / fila: *"A mesma lógica de render e polling permanece ativa aqui."*
- Cliente / mapa: *"Mapa principal mantido com a mesma lógica já usada pelo painel."*

Isso viola o princípio 1.2 do guia (linguagem calma, voltada ao usuário) e expõe detalhes técnicos. **Ninguém em pane na estrada precisa saber o que é SSE.**

**Correção — substituir 1:1:**

| Local | Texto atual (remover) | Texto novo |
|---|---|---|
| Cliente `page-subtitle` | "Área do cliente com o mesmo idioma visual..." | "Peça socorro, acompanhe seu pedido e gerencie seus veículos." |
| Guincho `page-subtitle` | "Dashboard operacional redesenhado sem alterar SSE..." | "Fique online, receba pedidos e acompanhe seus ganhos." |
| Cliente card mapa | "Mapa principal mantido com a mesma lógica..." | "Sua posição atual. Ao pedir socorro, usamos este ponto como origem." |
| Guincho card mapa | "Mesmo container e mesma inicialização JavaScript..." | "Sua área de cobertura e pedidos próximos em tempo real." |
| Guincho fila | "A mesma lógica de render e polling permanece ativa aqui." | "Novos pedidos da sua região aparecem aqui automaticamente." |

**Regra permanente (adicionar ao checklist da seção 15 do guia):**
- [ ] Nenhum subtítulo, tooltip ou empty-state menciona termos técnicos (SSE, polling, JS, container, render, refactor, view).

---

## B. HERO: eliminar o vazio gigante

**Evidência.** Nos dois painéis o hero ocupa ~700px de altura com uma área branca/verde enorme sem conteúdo. Causa-raiz (visível na estrutura): o painel de **"Ações rápidas" / "Atalhos operacionais" foi colocado DENTRO do hero** como `col-lg-4`. Como esse painel é muito alto (4 cards + mini-métricas), a `row` com `align-items-center` estica a coluna esquerda, que só tem título + parágrafo — o resto vira vácuo.

O blueprint do guia (10.1 e 11.1) define o hero como uma **faixa horizontal compacta**:

```
┌ dash-hero ─────────────────────────────────────────────┐
│ Olá, {nome} 👋            [chips]     [ CTA principal ] │   ← altura ~180–220px
└─────────────────────────────────────────────────────────┘
```

**Correção estrutural (os dois dashboards):**

1. **Tirar o painel de atalhos de dentro do hero.** Ele vira uma seção própria abaixo da linha de stats (ou é removido — ver item D sobre duplicação).
2. Hero passa a conter apenas, em uma coluna única (ou `col-lg-8` + CTA à direita verticalmente centrado):
   - Saudação (`Olá, Maria Eliana.` / `Bem-vindo, Matos Agencia.`) — **título primeiro, chips depois** (nos screenshots os chips estão acima do H1, invertendo a hierarquia de leitura);
   - Subtítulo de 1 linha;
   - Chips (máx. 3);
   - **Cliente:** `.btn-socorro` dentro do hero (hoje ele está no `page-header`, fora do hero — ver item D);
   - **Guincho:** toggle Online/Offline + rótulo, conforme blueprint 11.1 (hoje o toggle está solto no `page-header`).
3. Limitar a altura: `padding: 2rem 2.25rem;` e **nunca** usar `align-items-center` com colunas de alturas muito diferentes. Se mantiver 2 colunas, usar `align-items: start`.

```css
/* Aditivo: hero compacto */
.dash-hero, .tow-hero { padding: 2rem 2.25rem; }
.dash-hero .row, .tow-hero .row { align-items: flex-start; }
```

**Critério de aceite:** hero renderizado com no máximo ~240px de altura em desktop, sem área vazia maior que o próprio conteúdo.

---

## C. CONTRASTE: stat-cards do guincho ilegíveis

**Evidência.** No painel guincho, a linha de 4 stats ("Atendimentos hoje", "Ganho hoje", "Nota média", "Km hoje") está com **rótulos verde-acinzentados apagados sobre card claro** — praticamente invisíveis. É exatamente o modo de falha previsto na seção 14.3 do guia: superfície clara em tema escuro herdando cor pensada para fundo escuro.

**Causa-raiz.** `.tow-stat-label { color: var(--theme-muted); }` — no tema guincho, `--theme-muted` é `#aaddb5` (verde-claro para fundo escuro). Sobre o card branco `rgba(255,255,255,.84)`, o contraste cai para ~1.6:1. O bloco automático de contraste do `style.css` cobre `.tow-stat` via seletor de prefixo, mas a regra local `.tow-stat-label` definida no `<style>` da view **vence por ordem/especificidade**.

**Correção (aplicar no `<style>` da view ou promover ao `style.css`):**

```css
/* Aditivo: contraste de stats em superfícies claras nos temas escuros */
body.guincho .tow-stat-value,
body.admin  .tow-stat-value  { color: var(--surface-contrast-text, #142018); }

body.guincho .tow-stat-label,
body.admin  .tow-stat-label  { color: var(--surface-contrast-muted, #5a6b5f); }
```

Aplicar o mesmo padrão a **qualquer** texto novo dentro de `.tow-*`/`.fin-*`/`.dash-*` no tema escuro, incluindo o empty-state da fila (*"Aguardando novos pedidos..."*, que também está lavado no screenshot):

```css
body.guincho .tow-panel .empty-state { color: var(--surface-contrast-muted); }
```

**Critério de aceite:** todo texto sobre superfície clara no tema guincho atinge ≥ 4.5:1 (verificar com DevTools > Accessibility).

---

## D. DUPLICAÇÕES: um dado, um lugar

**Evidência e correções, item a item:**

**D1 — Cliente: dois CTAs "Pedir Socorro" concorrentes.**
Há um botão no `page-header` e cards "Pedir socorro" nas Ações rápidas — e nenhum `.btn-socorro` dentro do hero. O guia (1.1) exige **um** CTA dominante.
→ Manter apenas o `.btn-socorro` no hero (pílula grande, sombra verde). O do `page-header` sai. O card de atalho "Pedir socorro" também sai do painel de atalhos.

**D2 — Cliente: botão "Meu Perfil" no page-header.**
Já existe na sidebar e na navbar. → Remover do `page-header` (o page-header do dashboard fica sem ações, só título+subtítulo).

**D3 — Cliente: mini-métricas "0 Ativos / 2 Oficinas / 1 Veículos" dentro do painel de atalhos** duplicam a linha de stats logo abaixo (0 Pedidos ativos / 1 Concluídos / 1 Meus veículos / 2 Oficinas).
→ Remover as mini-métricas. A linha de `.dash-stat` clicáveis é a fonte única desses números.

**D4 — Cliente: painel "Ações rápidas" inteiro é redundante** depois das correções: Pedir socorro está no hero; Histórico, Veículos e Oficinas já são `.dash-stat` clicáveis + sidebar.
→ **Remover o painel de Ações rápidas por completo.** É isso que devolve o hero compacto do item B sem sobrar órfão.

**D5 — Guincho: status Online triplicado.** Aparece (1) toggle no page-header, (2) card "Status operacional — Disponível para receber corridas [Online]" nos atalhos, (3) chip "Disponibilidade em tempo real" no hero.
→ Fonte única: **toggle + rótulo dentro do hero** (blueprint 11.1). O card de status e o chip saem. ⚠️ Preservar `id="toggleDisponivel"` e o handler AJAX ao mover o elemento.

**D6 — Guincho: painel "Atalhos operacionais"** — mesmo caso do D4: Histórico e Financeiro já estão na sidebar e nos stats; "Operação" e "PIX" são links de perfil.
→ Remover o painel. Se quiser manter acesso rápido a Operação/PIX, viram 2 `.tow-stat`-link adicionais ou links na seção "Conta" da sidebar (já existe).

---

## E. GRID E FLUXO VERTICAL

**E1 — Guincho: card "Últimos Atendimentos" invadindo o rodapé.**
No screenshot o card flutua sobre o `footer`. Causa provável: o bloco foi inserido **depois** do fechamento da coluna/row (ou do `.main-content`), ficando fora do fluxo do grid.
→ Estrutura correta da região inferior do painel guincho:

```html
<div class="row g-4">
    <div class="col-lg-7">
        <div class="tow-card ...">Mapa em Tempo Real</div>
    </div>
    <div class="col-lg-5 d-flex flex-column gap-4">
        <div class="tow-card ...">Fila de Pedidos</div>       <!-- #pedidosDisponiveisContainer preservado -->
        <div class="tow-card ...">Últimos Atendimentos</div>  <!-- DENTRO da mesma coluna -->
    </div>
</div><!-- tudo antes do include do footer -->
```

**E2 — Cliente: colunas desbalanceadas** ("Últimos Pedidos" muito mais alto que "Sua Localização").
→ Mesmo padrão: `row g-4` com `col-lg-7` (mapa, `h-100`) e `col-lg-5` (últimos pedidos com `max-height` + `overflow-y:auto` interno, limitado a 5 itens + botão "Ver histórico").

```css
.dash-list-scroll { max-height: 560px; overflow-y: auto; }
```

**E3 — Stats: restaurar o padrão do guia (9.2).**
Nos screenshots os stat-cards estão estáticos e com alinhamento à esquerda inconsistente. Confirmar em ambos os painéis: card inteiro envolvido em `.dash-stat-link`/`.tow-stat-link` (área clicável total), hover `translateY(-2px)`, ícone 48×48 raio 16, valor 800 `line-height:1`, label abaixo.

---

## F. MICRO-AJUSTES (polimento)

1. **Ordem no hero:** H1 → subtítulo → chips → CTA. (Hoje: chips → H1.)
2. **Guincho, stats com fundo acinzentado:** além do texto (item C), elevar a opacidade do fundo para `rgba(255,255,255,.92)` para os cards não parecerem "desligados".
3. **Cliente, badge do pedido na lista:** já usa as pílulas `.badge-*` corretas (Cancelado/Aguardando pagamento/Concluído) — manter; apenas alinhar a badge verticalmente ao topo do card (`align-items:flex-start`).
4. **Chips do hero com ícone verde** (`.dash-chip i { color: var(--primary) }`) — no screenshot do guincho os ícones estão pretos.
5. **Page-header:** depois das remoções (D1/D2/D5), o `page-header` de ambos os dashboards contém apenas título + subtítulo — como no blueprint.

---

## ORDEM DE EXECUÇÃO RECOMENDADA

1. **A** (textos) — zero risco, efeito imediato.
2. **C** (contraste guincho) — zero risco, só CSS.
3. **D4 + D6** (remover painéis de atalhos) → destrava **B** (hero compacto).
4. **B** (recompor heros; mover `.btn-socorro` e toggle para dentro — cuidado com `id="toggleDisponivel"`).
5. **D1, D2, D3, D5** (deduplicações restantes).
6. **E1, E2, E3** (grid inferior e stats).
7. **F** (polimento).

## CHECKLIST DE ACEITE FINAL (validar com novo screenshot)

- [ ] Nenhum texto técnico visível na UI (A).
- [ ] Hero ≤ ~240px de altura, sem área vazia dominante; H1 antes dos chips (B).
- [ ] Cliente: exatamente **1** botão "Pedir Socorro" na tela, dentro do hero, estilo `.btn-socorro` (D1).
- [ ] Guincho: exatamente **1** indicador Online/Offline (toggle no hero) e o toggle continua funcionando via AJAX (D5).
- [ ] Todos os rótulos dos stats do guincho legíveis (contraste ≥ 4.5:1) (C).
- [ ] Nenhum card sobrepondo o footer; "Últimos Atendimentos" dentro da coluna direita (E1).
- [ ] Stats clicáveis com hover nos dois painéis (E3).
- [ ] IDs preservados: `toggleDisponivel`, `statusBannerCliente`, `pedidosDisponiveisContainer`, `pedidoAtivoClienteCard`, `pedidoAtivoGuinchoCard`, `map`.

---

*ADITIVO 01 · complementa GUIA_DESIGN_PAINEIS_GUINCHAFACIL.md · gerado a partir da auditoria visual dos dashboards renderizados.*
