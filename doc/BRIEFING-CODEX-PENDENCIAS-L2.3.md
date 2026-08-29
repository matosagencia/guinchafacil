# Briefing para o Codex — 3 pendências da nav reorganizada (Pacote L2.3)

Contexto: estamos remodelando o backoffice admin do GuinchaFácil (Central
Operacional, `/admin/central`). A sidebar nova (`src/Views/components/admin_nav_operacional.php`)
tem itens marcados `is-soon` ("em breve") que precisam virar telas reais.
Já implementamos (Claude, nesta sessão): Alertas Operacionais, Despacho,
Ocorrências, Especialistas. Faltam 3 itens **sem risco financeiro** — são os
que você vai fazer agora. Carteiras/Saques (financeiro, saque via Pix) ficam
de fora deste lote — não mexa neles.

## Regras gerais (iguais ao briefing anterior de vocês)

1. **Reaproveite o que já existe.** Não crie tabela nova se um campo/tabela
   já cobre o caso — os 3 itens abaixo foram pesquisados e o que já existe
   está listado em cada seção.
2. **Auth**: todas as rotas admin passam pelo guard central do roteador
   (`index.php`, terceiro elemento da tupla de rota = `'admin'`) —
   `AuthService::requireAuth('admin')` já é chamado pelo próprio Controller
   por convenção do projeto (olhe `AdminController::alertasOperacionais()`
   ou `AdminController::despacho()` como exemplo de estilo).
3. **CSRF**: toda ação POST usa `AuthService::gerarCsrfToken()` /
   `AuthService::validarCsrfToken()`, embutido como `csrf_token` no form e
   validado no Controller (403 se inválido). Copie o padrão de
   `AdminController::ocorrenciaCriar()`.
4. **Prepared statements sempre.** Nunca concatene entrada do usuário em SQL.
5. **NÃO toque** em `src/Views/admin/central_operacional.php`,
   `src/Views/admin/despacho.php`, `src/Views/admin/ocorrencias.php` nem em
   `src/Controllers/AdminController.php` fora dos métodos novos que vocês
   forem adicionar (evita conflito de merge com o que já existe).
6. **Nav**: em `src/Views/components/admin_nav_operacional.php`, troque cada
   `<span class="ops-nav-link is-soon">...` pelo `<a>` real correspondente
   (mesmo padrão dos itens que já viraram link — olhe "Alertas Operacionais"
   ou "Despacho" no mesmo arquivo). Em `src/Views/layouts/sidebar_admin.php`
   (sidebar clássica), adicione o link equivalente na seção correta, mesmo
   padrão dos itens que já existem lá.
7. Layout das telas: siga o padrão clássico (`layouts/header.php` +
   `layouts/sidebar_admin.php` + `<main class="main-content">` +
   `layouts/footer.php`) — é o que `alertas_operacionais.php`,
   `despacho.php` e `ocorrencias.php` usam. NÃO use o `.shell-ops` (3
   colunas) — esse é só da Central Operacional.

---

## Tarefa 1 — Avaliações (tela admin)

**O que já existe (não duplicar):**
- Tabela `avaliacoes` já existe (`install/migrate.php`): `id, pedido_id
  (UNIQUE), cliente_id, guincho_id, estrelas (1-5), comentario, criado_em`.
- Model `src/Models/Avaliacao.php` já tem `listarPorGuincho($guincho_id,
  $pagina)` (paginado, 20/página) e `mediaGuincho()` — **use estes métodos,
  não reescreva a query**.
- `guinchos.reputacao` e `guinchos.total_avaliacoes` já existem e já
  aparecem em `admin/guinchos.php` e `admin/guinhodetalhe.php` (só o
  número agregado, sem lista).
- **O que falta**: uma tela `/admin/avaliacoes` que liste avaliações
  (filtro por guincho e por nota), e um link "Ver avaliações" no
  `guinhodetalhe.php` apontando pra essa tela já filtrada por aquele
  guincho (`?guincho_id=X`).

**Entregável:**
- `AdminController::avaliacoes()`: lê `$_GET['guincho_id']` e
  `$_GET['nota']` (opcional), usa `Avaliacao::listarPorGuincho()` quando
  `guincho_id` vier, ou adicione um novo método `Avaliacao::listarTodas($filtros, $pagina)`
  no Model (paginado, join com `usuarios`/`guinchos` pra nome do cliente e
  do guincheiro) se precisar listar sem filtro de guincho — **siga o
  estilo dos métodos existentes no Model** (try/catch com `error_log`,
  prepared statement).
- View `src/Views/admin/avaliacoes.php`: tabela com nota (estrelas
  visuais, ex. ícones fa-star), comentário, pedido (link pro
  `/admin/pedido/{id}`), cliente, guincheiro, data. Cards de resumo (nota
  média geral, total de avaliações, distribuição 1-5 estrelas opcional).
- Rota `/admin/avaliacoes` em `index.php`.
- Link "Ver avaliações" em `guinhodetalhe.php` → `/admin/avaliacoes?guincho_id=X`.
- Trocar o item "Avaliações" de `is-soon` pra link real na nav (arquivo
  citado na regra 6) — ele fica na seção "Qualidade e Segurança".
- Adicionar equivalente na sidebar clássica.

---

## Tarefa 2 — Documentos (auditoria cross-guincho)

**O que já existe (não duplicar):**
- Campos já existem em `guinchos`: `cnh_numero`, `cnh_validade`,
  `doc_cnh_frente`, `doc_cnh_verso`, `foto_veiculo`.
- `guinchospendentes.php` já mostra os documentos, mas só por card de
  cadastro pendente (não é uma auditoria de todos os aprovados).
- `AdminAlertService.php` já tem uma checagem de CNH vencendo em 30 dias
  (função privada, procure `cnh_validade BETWEEN CURDATE()`), alimentando
  o painel de Alertas Operacionais — **não duplique essa lógica**, ela já
  correlaciona pro alerta geral.
- **O que falta**: uma tela cross-guincho que liste, para TODOS os
  guincheiros aprovados: se enviaram CNH frente/verso e foto do veículo, e
  o status da validade da CNH (válida / vencendo em ≤30 dias / vencida).

**Entregável:**
- `AdminController::documentos()`: busca todos os guinchos aprovados
  (reaproveite a query de `AdminController::guinchos()` como referência,
  incluindo o filtro `oferece_reboque` se fizer sentido) e calcula em PHP
  o status de validade da CNH a partir de `cnh_validade` (comparar com
  `date('Y-m-d')` + 30 dias) — **não recalcule isso em SQL duplicado**, só
  leia a coluna e derive em PHP, mesmo critério do `AdminAlertService`
  (vencida / vence em ≤30 dias / ok).
- View `src/Views/admin/documentos.php`: tabela com guincheiro, CNH
  (número + validade + badge de status), link pros 3 arquivos (frente,
  verso, foto — mesmo padrão de link `/arquivo/{id}?tipo=...` usado em
  `guinchospendentes.php`), com "ausente" quando o campo for vazio.
  Filtro por status (todos / vencidas / vencendo / ok).
- Rota `/admin/documentos`.
- Nav: seção "Pessoas e Frota", troca o item "Documentos".
- Sidebar clássica: link equivalente.

---

## Tarefa 3 — Proof-of-Road dedicado (tela cross-pedidos)

**O que já existe (não duplicar):**
- `ProofOfRoadService.php` e `RoutingSnapshotService.php` já fazem toda a
  ingestão/validação/hash-chain de GPS — **não toque nesses services**.
- Tabelas já existentes: `pedido_localizacoes` (pontos individuais, com
  `is_valid`, `rejection_code`, `hash_previous`/`hash_current`,
  `distance_validated_m` etc.) e `pedido_percurso_resumo` (resumo por
  pedido+fase: `total_points`, `valid_points`, `rejected_points`,
  `tracking_quality`, `max_gap_seconds`, `max_speed_kmh`).
- Já existe uma tela POR **por pedido**: `src/Views/admin/pedido_trilha.php`
  (rota `/admin/pedido/trilha/{id}`) — mapa da trilha, filtro por fase e
  validação. **Não reescreva essa tela.**
- **O que falta**: uma tela cross-pedidos que liste os pedidos com pior
  qualidade de rastreamento ou mais pontos rejeitados, pra auditoria
  proativa (hoje só dá pra ver isso abrindo pedido por pedido).

**Entregável:**
- `AdminController::proofOfRoad()`: consulta `pedido_percurso_resumo`
  (join com `pedidos`/`usuarios`/`guinchos`) ordenando por pior
  `tracking_quality` primeiro e/ou maior proporção `rejected_points /
  total_points`; filtro opcional por `tracking_quality` ou por pedido
  concluído recentemente (últimos N dias, `pedidos.criado_em`).
- View `src/Views/admin/proof_of_road.php`: tabela com pedido (link pro
  `/admin/pedido/trilha/{id}` já existente — **essa tela nova é só um
  índice/dashboard, o detalhe continua sendo `pedido_trilha.php`**),
  cliente, guincheiro, qualidade do rastreamento, % pontos rejeitados,
  maior gap de GPS (`max_gap_seconds`), data. Cards de resumo (total de
  pedidos com trilha, quantos com qualidade ruim).
- Rota `/admin/proof-of-road`.
- Nav: seção "Qualidade e Segurança", troca o item "Proof-of-Road".
- Sidebar clássica: link equivalente (pode usar o ícone `fa-route`).

---

## Entregável final (igual ao briefing anterior)

Log consolidado em `qa/logs/admin-pendencias-l2.3-<data>.log` contendo:
- Lista completa de arquivos criados/alterados.
- `php -l` de cada arquivo tocado (cole a saída completa).
- Prints (texto) das 3 URLs carregando com sucesso — se não tiver como
  testar contra o servidor real, diga isso explicitamente no log em vez de
  inventar resultado.
- Decisões de design tomadas (principalmente onde a especificação acima
  deixou espaço de julgamento).
- Pendências/limitações conhecidas.

Não crie testes Playwright — o Claude cuida disso na revisão, seguindo o
padrão já usado em `qa/suites/admin-ocorrencias.spec.ts` e
`admin-despacho.spec.ts`.
