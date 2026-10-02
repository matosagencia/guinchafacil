# Faixa B — Cadastro/Auth + Painéis

**Dono:** DeepSeek · **Quem testa:** ChatGPT (testes PHP de contrato) · **Aprova e faz merge:** o dono
**Branch de trabalho:** `lane/B` · **Antes de commitar:** `tools/lane_guard.ps1 -Lane B`, depois `tools/smoke.ps1`
Última atualização: 2026-10-01

> Leitura permitida: `doc/ARQUITETURA.md`, `doc/CONTRATOS.md`, `doc/DECISOES.md`, este arquivo e os arquivos da faixa B em `doc/lanes.json`. Não explorar o resto do repositório.
> Antes de citar qualquer arquivo, método ou coluna, confirme com o trecho real. Sem evidência, escreva **"não verificado"**.

> **Nota de path (D3):** esta ficha usa `doc/` (singular), que é a pasta real do repositório. `docs/lanes.json` e `tools/lane_guard.ps1` ainda usam `docs/` — decisão pendente. Não renomear nada por conta própria.

## 1. Escopo

**Rotas:** `/login`, `/auth/magic/{token}`, `/checkout/cliente`, `/cliente/*`, `/oficina/*`, `/guincho/*`, `/admin/pedidos`, `/admin/pedido/{id}`

**Arquivos que a faixa B escreve** (fonte oficial: `docs/lanes.json`):

- `src/Controllers/AuthController.php`, `src/Controllers/OficinaController.php`
- `src/Services/Auth/MagicLinkService.php`
- `src/Views/auth/*`, `src/Views/cliente/*`, `src/Views/oficina/*`, `src/Views/guincho/*`
- `src/Views/public/checkout-cliente.php`
- `src/Views/admin/pedidos.php`, `src/Views/admin/pedidodetalhe.php`, `src/Views/admin/partials/_pedido_oficina_detalhe.php`

**Fora do escopo (emitir `ROTA_PEDIDA` ou `CONTRATO_PEDIDO` e parar):** `index.php`, `config.php`, `CheckoutController.php` (faixa A), qualquer arquivo de A ou C, e os arquivos sem faixa definida.

## 2. Estado atual (verificado em 2026-10-01)

> **Verificação 2026-10-01:** itens 1 (assets no `oficina/dashboard.php`), 2 (partial `_pedido_oficina_detalhe.php` já incluído em `pedidodetalhe.php:348`, consumindo os 7 campos do Contrato Pedido v1) e 3 (badge `origem_funil` em `admin/pedidos.php` via closure `$getOrigemFunilBadge`) **já estão implementados**. `php -l` limpo no partial. Item 1 **não alterar** (Opção A: só muda se o ChatGPT apontar falha no cenário `oficina-alertas`).

| Item | Estado |
| --- | --- |
| `AuthController.php` | Existe. Cobre Google, login, magic link e `preCotacao`. |
| `MagicLinkService.php` | Existe. |
| `Views/auth/login.php` | Reescrito. |
| `Views/public/checkout-cliente.php` | Existe. |
| `Views/admin/partials/_pedido_oficina_detalhe.php` | **Criado.** Consome só Contrato Pedido v1 (7 campos). Nenhuma consulta direta a tabela. |
| `admin/pedidodetalhe.php` | **Require do partial dentro do `<main>`** (linha 348), antes do bloco "Prova de Serviço: Fotos" (linha 350). Require antigo do topo removido. |
| `admin/pedidos.php` | Badge `origem_funil` implementado via closure `$getOrigemFunilBadge` (idempotente, não muta `$worklist`). |
| `sidebar_oficina.php` | **Verificado:** 5 hrefs, todos `/oficina/*` (dashboard, pedidos, historico, financeiro, perfil). Zero links para `/especialista/*` ou outro perfil. **Nada a corrigir.** |
| `oficina/dashboard.php` | **Atualizado:** inclui `components/communications.css` (linha 13), `core/offline-queue.js`, `atendimento-status.js`, `communications.js` (linhas 180-182) — mesmo conjunto do `guincho/dashboard.php` (sem o `leaflet.js`, que é específico do guincho). |
| Contrato Pedido v1 | `A.md` (`CONTRATO_RESPOSTA`) responde os 7 campos. Faixa A publica versão final. |

## 3. Missão (nesta ordem)

1. ~~`src/Views/oficina/dashboard.php`: incluir `communications.css`, `communications.js`, `atendimento-status.js` e `offline-queue.js` exatamente como o `guincho/dashboard.php` já faz.~~ **CONCLUÍDO 2026-10-01.** Validado com `php -l`.
2. ~~Criar `src/Views/admin/partials/_pedido_oficina_detalhe.php` e incluí-lo em `admin/pedidodetalhe.php`.~~ **CONCLUÍDO 2026-10-01.** Require dentro do `<main>`. Validado com `php -l`.
3. ~~`admin/pedidos.php`: coluna/badge `origem_funil`.~~ **CONCLUÍDO.**
4. ~~`sidebar_oficina.php`: todos os href devem apontar para `/oficina/*`.~~ **VERIFICADO — nenhuma alteração necessária.**
5. **Auth (`MagicLinkService` e login):** sem mudança de comportamento. Só corrigir o que o teste da faixa reportar. **Em espera do relatório do ChatGPT.**

Dependência: o item 2 só fecha quando a faixa A publicar o Contrato Pedido v1 (resposta preliminar em `A.md` já cobre).

## 4. Contratos que esta faixa consome

| Contrato | Origem | Campos usados |
| --- | --- | --- |
| Pedido v1 | Faixa A | `oficina_id`, `oficina_nome`, `triagem`, `custo_assistencia`, `custo_reboque`, `recomendacao`, `origem_funil` |

Esta faixa **não publica** contratos próprios até o momento.

## 5. Critérios de aceite (testa: ChatGPT)

- [ ] `/oficina/dashboard` carrega os 4 assets novos (`communications.css`, `offline-queue.js`, `atendimento-status.js`, `communications.js`) com HTTP 200 no DevTools → Network.
- [ ] Console limpo: sem "communications is not defined" nem "AtendimentoStatus is not defined".
- [ ] `/oficina/dashboard` recebe alerta em tempo real como o `/guincho/dashboard`.
- [ ] `/admin/pedido/{id}` renderiza o card "Oficina & Decisão" **dentro** de `<main class="main-content shell-main shell-content">` (validar no view-source).
- [ ] O card mostra `oficina_nome`, `triagem`, `custo_assistencia`, `custo_reboque`, `recomendacao` — com `—` nos campos nulos antes da migration da faixa A, e valores reais depois.
- [ ] `/admin/pedidos` exibe badge `origem_funil` (`oficina` | `especialista` | `—`).
- [ ] Login e magic link continuam com o mesmo comportamento.
- [ ] Cenários relacionados: `admin-enxerga-oficina` e `oficina-alertas`.

A faixa B **não escreve** os testes da própria faixa (R5).

## 6. Regras que valem aqui

- Orçamento de investigação: máx. 5 arquivos e 5 comandos antes de propor correção. Falhou 2x no mesmo ponto → registrar em `doc/BLOCKERS.md` (sintoma, o que tentou, hipótese) e parar.
- Diff mínimo, sem refatorar ou apagar fora da tarefa. UTF-8 sem BOM, `php -l` limpo.
- Toda checagem de rota usa GET (R7).
- Nunca exibir segredos.
- Formato de entrega: (a) lista de arquivos CRIAR/ALTERAR, (b) código completo de cada um (comandos PowerShell para o XAMPP do Windows), (c) comando exato para testar, (d) saída esperada, (e) como reverter.

## 7. Pendências e bloqueios

| Item | Situação |
| --- | --- |
| Contrato Pedido v1 final | Aguardando faixa A |
| **B-001 - 404 nas rotas B em localhost** | **RESOLVIDO** - causa: URL com prefixo `/guinchafacil`; docroot ja e a raiz do projeto. Ver `doc/BLOCKERS.md`. |
| **B-002 — tipo de `triagem`** | **Ativo** — `CONTRATO_PEDIDO` emitido para A |
| **B-004 - migration dos 7 campos do Contrato Pedido v1** | **Ativo** - `CONTRATO_PEDIDO` emitido para A |
| Fronteira `CheckoutController::cliente` (A) × `/checkout/cliente` (B) | Decisão D9 pendente |
| Arquivos sem faixa | Decisão D10 pendente |
| Pasta `docs/` × `doc/` | Decisão D3 pendente |

Ao terminar uma entrega: atualizar este arquivo (estado, contratos, pendências) e parar.

## 8. Histórico

| Data | Entrega | Resultado |
| --- | --- | --- |
| 2026-09-30 | Criação da ficha da faixa | — |
| 2026-10-01 | Scripts no `oficina/dashboard.php` | OK — 4 assets incluídos (linhas 13, 180-182) |
| 2026-10-01 | Require do partial no `pedidodetalhe.php` | OK — dentro do `<main>` (linha 348) |
| 2026-10-01 | Badge `origem_funil` em `admin/pedidos.php` | OK — já estava implementado |
| 2026-10-01 | Verificação de hrefs em `sidebar_oficina.php` | OK — nenhuma violação |
| 2026-10-01 | B-001 fechado (URL com prefixo /guinchafacil; docroot ja e a raiz); doc/BLOCKERS.md populado com B-001/B-002/B-004 | OK - sem alteracao de codigo PHP |
| 2026-10-01 | Verificação do estado da faixa B; `php -l` nos arquivos | Itens 1–3 já prontos; B-001 (404) e B-002 (`triagem`) abertos; sem diff de código PHP |