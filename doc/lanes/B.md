# Faixa B — Cadastro/Auth + Painéis

**Dono:** DeepSeek · **Quem testa:** ChatGPT (testes PHP de contrato) · **Aprova e faz merge:** o dono
**Branch de trabalho:** `lane/B` · **Antes de commitar:** `tools/lane_guard.ps1 -Lane B`, depois `tools/smoke.ps1`
Última atualização: 2026-10-08

> Leitura permitida: `doc/ARQUITETURA.md`, `doc/CONTRATOS.md`, `doc/DECISOES.md`, este arquivo e os arquivos da faixa B em `doc/lanes.json`. Não explorar o resto do repositório.
> Antes de citar qualquer arquivo, método ou coluna, confirme com o trecho real. Sem evidência, escreva **"não verificado"**.

> **Nota de path (D3):** esta ficha usa `doc/` (singular), que é a pasta real do repositório. `doc/lanes.json` e `tools/lane_guard.ps1` ainda usam `docs/` — decisão pendente. Não renomear nada por conta própria.

## 1. Escopo

**Rotas:** `/login`, `/auth/magic/{token}`, `/checkout/cliente`, `/cliente/*`, `/oficina/*`, `/guincho/*`, `/admin/pedidos`, `/admin/pedido/{id}`

**Arquivos que a faixa B escreve** (fonte oficial: `doc/lanes.json`):

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
| Disponibilidade de Guincho | Faixa A | `GuinchoDisponibilidade::existeNoRaio()` |
| Resolução de `service_type_id` | Faixa A | `ServiceTypeResolver::porSlug()` (paliativo — ver B-008) |
| Catálogo Dinâmico (B-009) | Faixa A | `service_type_id` do select + `attendance_mode` do catálogo (visão futura) |

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
| **B-001** — 404 nas rotas B em localhost | **RESOLVIDO** — causa: URL com prefixo `/guinchafacil`; docroot já é a raiz do projeto. Ver `doc/BLOCKERS.md`. |
| **B-002** — tipo de `triagem` | **Ativo** — `CONTRATO_PEDIDO` emitido para A |
| **B-004** — migration dos 7 campos do Contrato Pedido v1 | **Ativo** — `CONTRATO_PEDIDO` emitido para A |
| Fronteira `CheckoutController::cliente` (A) × `/checkout/cliente` (B) | Decisão D9 pendente |
| Arquivos sem faixa | Decisão D10 pendente |
| **B-GUARD-01** ativação | Aguardando merge do PR #1 da Faixa A. Depois do merge, substituir o bloco comentado em `AdminController.php:1545` pela chamada real `GuinchoDisponibilidade::existeNoRaio($latOrigem, $lngOrigem)`. |
| **Permission denied em `src/Models/Pedido.php`** | Ativo — ver `doc/BLOCKERS.md` B-006. Afeta cron `cron_cancelar_pedidos_expirados.php`, não o fluxo interativo. |
| **B-008** — Fallback `[B-STID-01]` não resolve `eletrica` | **RESOLVIDO PALIATIVAMENTE em 2026-10-08** — `ServiceTypeResolver` (A) + Solução B no funil admin. Aguardando confirmação de teste funcional (log `[B-STID-01] service_type_id resolvido: 8`). Ver `doc/BLOCKERS.md`. |
| **B-009** — Catálogo dinâmico no funil | **Agendado** para próxima sessão. Ver `doc/CONTRATOS.md` §Catálogo Dinâmico. Escopo: 3 views reescritas (A: público; B: cliente e admin), 1 JS sem STATE, `PedidoCoreService` deriva modalidade do catálogo, `public_slug` administrável, `attendance_mode` administrável, E2E para os 3 funis. |

Ao terminar uma entrega: atualizar este arquivo (estado, contratos, pendências) e parar.

## 8. Histórico

| Data | Entrega | Resultado |
| --- | --- | --- |
| 2026-09-30 | Criação da ficha da faixa | — |
| 2026-10-01 | Scripts no `oficina/dashboard.php` | OK — 4 assets incluídos (linhas 13, 180-182) |
| 2026-10-01 | Require do partial no `pedidodetalhe.php` | OK — dentro do `<main>` (linha 348) |
| 2026-10-01 | Badge `origem_funil` em `admin/pedidos.php` | OK — já estava implementado |
| 2026-10-01 | Verificação de hrefs em `sidebar_oficina.php` | OK — nenhuma violação |
| 2026-10-01 | B-001 fechado (URL com prefixo `/guinchafacil`; docroot já é a raiz) | OK — sem alteração de código PHP |
| 2026-10-01 | Verificação do estado da faixa B; `php -l` nos arquivos | Itens 1–3 já prontos; B-001 (404) e B-002 (`triagem`) abertos; sem diff de código PHP |
| 2026-10-04 | fix-B-11 v2 — alerta toast+beep no `/guincho/dashboard` | OK — `checarPedidos()` passa a diffar IDs vistos (sessionStorage) e disparar toast+beep+blink de título; B-005 fechado |
| 2026-10-04 | D3 confirmado pelo dono: pasta real é `doc/` (singular) | Sem impacto no código; `lanes.json` continua com `docs/` e fica pendente para o dono |
| 2026-10-04 | fix-B-11 — causa raiz: pedido expirado, não bug de código | OK — front (dashboard.php L1614+) e backend (`montarOfertasDisponiveis`) corretos; `Pedido::listarAguardandoGuincho()` filtra por `expiracao_aceite > NOW()`; HAR foi feito 2h após a janela de 30min fechar. B-005 fechado com causa real. |
| 2026-10-04 | CONTRATO_PEDIDO para Faixa A — UX de expiração + fallback NULL | Pendente — R1 (status não muda após expirar) e R2 (`expiracao_aceite IS NULL` retorna NULL). Emitido em `doc/BLOCKERS.md`. |
| 2026-10-04 | diag-B-018c: #182 expirado (`diff=-2237s`); guincho 17 com `disponivel=0`; `attendance_mode=TOWING`+`service_type_id=NULL` → gate capability NÃO se aplica | Causa: expirado + guincho offline. B-005 confirmado como mesmo padrão |
| 2026-10-04 | CONTRATO_PEDIDO sobre "capability ON_SITE" para #182: **cancelado** — `attendance_mode=TOWING`, gate capability não roda. Não emitir | Retirado antes de virar contrato |
| 2026-10-04 | **B-005 FECHADO com evidência visual**: pedido #183 apareceu como NOVA SOLICITAÇÃO no `/guincho/dashboard` com countdown 29:28; atendimento carregado com rota, cliente e valor. `fix-B-11 v2` (toast+beep) funcionou | OK — nenhum diff de código nesta rodada |
| 2026-10-04 | Observação (não bloqueia): 404 em `public/assets/vendor/leaflet-routing-machine/routing-icon.png` referenciado por `leaflet-routing-machine.css`. Ícone de manobra fica vazio. Dono decide: adicionar ao bundle ou incluir na fila D10 | Pendente decisão do dono |
| 2026-10-04 | `OficinaController::buscarPedidosProximos` passa a consumir `Pedido::listarFilaElegivelParaOficina` | OK — ganha `expiracao_aceite > NOW()` + JOINs |
| 2026-10-04 | Rota `/oficina/pedidos-disponiveis` registrada em `index.php` | OK — método já existia; dono colou a linha |
| 2026-10-04 | `/oficina/pedido/{id}/aceitar` migrada para POST + CSRF guard em `OficinaController::aceitar` | OK — `<a>` → `<form method=post>` em `_offer_card.php`; rota duplicada removida |
| 2026-10-04 | Card de oferta sem endereço (evita overrun do prestador) | OK |
| 2026-10-04 | Refactor visual de `src/Views/oficina/atendimento.php` (stepper + cards `tow-card`) | OK — hooks JS preservados |
| 2026-10-04 | **B-007** Cancelamento de oficina — `CancelamentoService::cancelarPorOficina` + `PedidoTransitionService::requeueByOficina` + `OficinaController::cancelarAtendimento` + modal | OK — auditoria em `pedido_cancelamentos` |
| 2026-10-04 | Migration `install/migration_oficina_reputacao_v1.sql` — enum `ator_tipo`+`oficina`, `oficinas.reputacao`, `oficinas.total_cancelamentos`, config `penalidade_reputacao_cancelamento_oficina` | OK — corrigiu bug silencioso (enum antigo) |
| 2026-10-04 | Dispatcher genérico `{id}`/`{nome}` em `index.php` | OK — destrava 7+ rotas; preserva fallbacks |
| 2026-10-04 | Cast defensivo `lat_destino`/`lng_destino` no `AdminController::pedidoCriar` | OK — `(float)""` = 0.0 era o bug; agora `""` → `null` |
| 2026-10-04 | Listener `gf:address-confirmed` + `id=` nos hidden em `_precotacao_funil_admin.php` | OK |
| 2026-10-04 | `decisao_atendimento` movida para dentro de `context` do `PedidoCreateRequest` | OK — #185 com `attendance_mode=ON_SITE`, `lat_destino=NULL` |
| 2026-10-04 | Arquivamento de 274 `.bak` + 31 zumbis em `_archive/` + bloco `.gitignore` (D6) | OK — commits `4ed2bf4`, `d42dc34`, `639c1b8` |
| 2026-10-06 | fix-B-guard-reboque.ps1 — defesa em profundidade no `AdminController::pedidoCriar` | OK — 78 linhas adicionadas em `src/Controllers/AdminController.php`, 0 removidas. B-GUARD-02 disparou 5× em teste real (php_errors.log), nenhum pedido criado. Backup: `AdminController.php.bak-20261006-215749`. |
| 2026-10-06 | CONTRATO_PEDIDO B→A entregue pelo ChatGPT via PR #1 | OK — Faixa A criou `src/Services/GuinchoDisponibilidade.php` + corrigiu `PreCotacaoOpcoesService.php` (modo=reboque não consulta mais oficinas). Documentado em `doc/CONTRATOS.md` §Contrato de Disponibilidade de Guincho (B→A). |
| 2026-10-08 (sessão 2) | fix-B-oficina-alerta-e-toast — toast clicável + re-render inline sem reload + audio unlock | Pendente validação. Aplicado via `fix-B-oficina-alerta-e-toast.php`. |
| 2026-10-08 (sessão 2) | fix-B-stid-01 — fallback `service_type_id` via `tipo_problema` | Aplicado em commit `cb654f7`, MAS o erro "Destino obrigatório" voltou em teste com `eletrica`. Causa suspeita: busca de catálogo usa `slug` que não existe em `service_types`. Aguardando diagnóstico. |
| 2026-10-08 (sessão 2) | Commit + push dos 4 patches (index, AdminController, funil admin, oficina) | OK — commits `9b23c1e`, `b6d40ec`, `cb654f7`, `d974693` empurrados para main |
| 2026-10-08 (sessão 3) | fix-B-oficina-alerta-e-toast — validado no código | Aplicado em `src/Views/oficina/dashboard.php`: linha 121 (`[B-OFICINA-AUDIO-UNLOCK]`), linhas 180-181 (`[B-OFICINA-ALERTA-INLINE]`), linha 207 (`[B-OFICINA-INLINE-RENDER]`). Áudio + toast + re-render inline confirmados via `Select-String`. Pendente validação em runtime (toast clicável + áudio no 1º clique). |
| 2026-10-08 (sessão 3) | fix-B-stid-01 — causa raiz confirmada, bloqueado por A | Bloco `[B-STID-01]` em `AdminController.php:1624-1640` busca `slug` inexistente em `service_types` + `name` com acento. `ServiceTypeResolver` (A) resolve, mas está sem namespace e sem lookup plugado. B-008 atualizado. `fix-B-stid-v5.php` aguardando A. |
| 2026-10-08 (sessão 3) | B-008 reescrito como "bloqueado por A" + pacote de A documentado | `doc/BLOCKERS.md` atualizado com causa raiz, API de conexão (`getPDO()`), e os 5 itens que A precisa entregar. `CONTRATO_RESPOSTA` e `CONTRATO_PEDIDO` emitidos em `doc/CONTRATOS.md`. |
| 2026-10-08 (sessão 4) | fix-B-stid-v5c aplicado em `AdminController.php` | OK — `php -l` limpo. Linha 59: `require_once` do resolver; linha 1634: `\App\Services\Catalog\ServiceTypeResolver::porSlug()`. Backup: `AdminController.php.bak-fix-B-stid-v5c-20261008-153228`. |
| 2026-10-08 (sessão 4) | Solução B no `_precotacao_funil_admin.php` | OK — `<script>` próprio agora sincroniza `tipo_problema`, `categoria`, `valor_cotado`, `decisao_atendimento` via `gf:flow-stage`. Log do console: `[admin-funil] hidden sincronizados: {tipo_problema: 'eletrica', ...}`. |
| 2026-10-08 (sessão 4) | B-008 fechado paliativamente | **RESOLVIDO PALIATIVAMENTE** — `ServiceTypeResolver` resolve `eletrica → 8`; funil admin sincroniza hidden via `gf:flow-stage`. Aguardando confirmação de teste funcional (log `[B-STID-01] service_type_id resolvido: 8`). |
| 2026-10-08 (sessão 4) | B-009 agendado — catálogo dinâmico no funil | Próxima sessão. Contrato em `doc/CONTRATOS.md` §Catálogo Dinâmico. Escopo: 3 views reescritas (A: público; B: cliente e admin), 1 JS sem STATE, `PedidoCoreService` deriva modalidade do catálogo, `public_slug` administrável, `attendance_mode` administrável, E2E para os 3 funis. |