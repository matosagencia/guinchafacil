# Faixa B — Cadastro/Auth + Painéis

**Dono:** DeepSeek · **Quem testa:** ChatGPT (testes PHP de contrato) · **Aprova e faz merge:** o dono
**Branch de trabalho:** `lane/B` · **Antes de commitar:** `tools/lane_guard.ps1 -Lane B`, depois `tools/smoke.ps1`
Última atualização: 2026-10-15

> Leitura permitida: `doc/ARQUITETURA.md`, `doc/CONTRATOS.md`, `doc/DECISOES.md`, este arquivo e os arquivos da faixa B em `doc/lanes.json`. Não explorar o resto do repositório.
> Antes de citar qualquer arquivo, método ou coluna, confirme com o trecho real. Sem evidência, escreva **"não verificado"**.

> **Nota de path (D3):** esta ficha usa `doc/` (singular), que é a pasta real do repositório. `doc/lanes.json` e `tools/lane_guard.ps1` ainda usam `docs/` — decisão pendente. Não renomear nada por conta própria.

## 0. Sessão 2026-10-15 — o que foi feito e o que fica

**Nenhum diff de código nesta sessão.** A faixa B foi validada indiretamente por ferramenta e o foco do dono migrou para T1/T2 (faixa A).

### 0.1 — Verificações que tocaram a faixa B (via `tools/verify_gaps.php`)

| # | Item do `verify_gaps` | Relevância para B | Resultado |
|---|---|---|---|
| 1 | `ServiceTypeResolver` — 12 slugs longos + `combustivel` | Contrato A→B que sustenta **B-008** (paliativo) e a futura **B-009** | ✅ 12/12 + 1/1 |
| 2 | `tests/bootstrap.php` sem `vendor/`, com autoloader nativo | Faixa C, não B | ✅ |
| 3 | CSP `style-src` com `fonts.googleapis.com` | Compartilhado (header/layouts) | ✅ |
| 4 | **Menu admin — blocos de ocultação** | **Faixa B** (`admin_nav_operacional.php`) | ✅ 5 blocos; Marketing/Logs/Health fora de `href` ativo |
| 5 | Lint de `Pagamento.php` | Faixa A | ✅ |

**Conclusão:** o item 4 confirma que a limpeza do menu admin feita em sessões anteriores continua íntegra (Marketing, Logs e Health não aparecem mais como `href` ativo). Os demais itens são contratos que B consome, não arquivos que B edita.

### 0.2 — Parametrização do dono (fora de B, mas afeta views de B)

O dono gravou em `configuracoes` (dev: `guinchafacil_dev`) os parâmetros oficiais de §5.4, §7.4, §9, §10 e §21 e registrou a §22 em `doc/CONTRATOS.md`.

Efeito colateral na faixa B: os valores que as **views do admin e dos painéis** exibem (elevação, anéis, `bloqueio_carencia_horas`, pedágio) passam a ter fonte canônica única. **Não requer alteração de código em B** — as views continuam lendo `Configuracao::get()`. Se alguma view tinha valor *hardcoded* de terreno/anéis/carência, é débito a corrigir sob demanda (registrar antes de mexer).

### 0.3 — Foco do dono migrou para T1/T2

`CONTRATOS.md` §20 fixa a ordem **T1 → T2 → T3 → T4**:

- **T1 (motor de preço)** e **T2 (`aplicarDestinoPagamento`)** são da **faixa A**.
- **T3 (extrato decomposto da fatura)** é a única tarefa da ordem que **cai em B** (§20.5, instrução emitida em 14/10).
- **T4 (limpeza)** é majoritariamente C, com partes de A (`Database.php`, `AdminController.php` mojibake).

**B não puxa T1/T2.** B aguarda T3 ser destravado por T1 (números dos vetores §20.3) e T2 (formato do split em centavos).

## 1. Escopo

**Rotas:** `/login`, `/auth/magic/{token}`, `/checkout/cliente`, `/cliente/*`, `/oficina/*`, `/guincho/*`, `/admin/pedidos`, `/admin/pedido/{id}`

**Arquivos que a faixa B escreve** (fonte oficial: `doc/lanes.json`):

- `src/Controllers/AuthController.php`, `src/Controllers/OficinaController.php`
- `src/Services/Auth/MagicLinkService.php`
- `src/Views/auth/*`, `src/Views/cliente/*`, `src/Views/oficina/*`, `src/Views/guincho/*`
- `src/Views/public/checkout-cliente.php`
- `src/Views/admin/pedidos.php`, `src/Views/admin/pedidodetalhe.php`, `src/Views/admin/partials/_pedido_oficina_detalhe.php`

**Fora do escopo (emitir `ROTA_PEDIDA` ou `CONTRATO_PEDIDO` e parar):** `index.php`, `config.php`, `CheckoutController.php` (faixa A), qualquer arquivo de A ou C, e os arquivos sem faixa definida.

**Adicionados por decisão D10 (2026-10-08):**
- `src/Models/Configuracao.php` → B
- `src/Services/Address/*` → B
- `src/Services/PreCotacao/PreCotacaoOpcoesService.php` → B
- `src/Services/CoberturaService.php` → B
- `src/Views/components/admin_nav_operacional.php` → B (patch de menu aplicado em 03/10)
- `src/Views/layouts/header.php` → compartilhado (só o dono edita)
- `src/Controllers/AdminController.php`, `src/Views/admin/pedidonovo_etapa1.php`, `src/Views/admin/pedidonovo_funil.php`, `src/Views/admin/partials/_precotacao_funil_admin.php`, `public/assets/css/pages/admin-pedido-flow.css` → B (atribuição informal, Caminho 3 do funil admin)

## 2. Estado atual

> **Verificação 2026-10-15:** `verify_gaps.php` 12/12 OK. Item 4 (menu admin) confirma o estado íntegro dos blocos de ocultação (Marketing/Logs/Health fora de `href` ativo). Item 1 (ServiceTypeResolver) confirma o contrato A→B intacto.

> **Verificação 2026-10-11 (Onda 2.1):** `registro_simples.php` e `perfil_completar.php` com import de `tokens.css` (1 ocorrência cada, sem duplicata).

> **Verificação 2026-10-10 (Onda 1):** os 5 itens da MISSÃO colada estão integralmente implementados. Nenhum diff de código necessário. Os 4 assets de `oficina/dashboard.php` existem no repositório (`offline-queue.js` verificado em Onda 2).

| Item | Estado |
| --- | --- |
| `AuthController.php` | Existe. Cobre Google, login, magic link e `preCotacao`. |
| `MagicLinkService.php` | Existe. Dependências (`RateLimiter`, `Configuracao`, `AuthService::sanitizeReturnPath`, SMTP/Brevo, WAPI) **não verificadas** — ver P-B-01. |
| `Views/auth/login.php` | Reescrito. |
| `Views/public/checkout-cliente.php` | Existe. |
| `Views/admin/partials/_pedido_oficina_detalhe.php` | Criado. Consome só Contrato Pedido v1 (7 campos). Nenhuma consulta direta a tabela. |
| `admin/pedidodetalhe.php` | Require do partial dentro do `<main>` (linha 348), antes de "Prova de Serviço: Fotos" (linha 350). |
| `admin/pedidos.php` | Badge `origem_funil` via closure `$getOrigemFunilBadge`. |
| `sidebar_oficina.php` | Verificado: 5 hrefs, todos `/oficina/*`. Zero links para outro perfil. |
| `oficina/dashboard.php` | Inclui os 4 assets (linhas 13, 180-182) — mesmo conjunto do `guincho/dashboard.php`. |
| Menu admin (`admin_nav_operacional.php`) | 5 blocos de ocultação confirmados por `verify_gaps` (2026-10-15). |
| Contrato Pedido v1 | Fechado em `CONTRATOS.md` §3 (B-002 e B-004 fechados no banco conforme v2.0). |
| Views de registro | `registro_simples.php` + `perfil_completar.php` ativos; `registro_cliente.php` e `registro_guincho.php` são código morto (P-B-04). |

## 3. Missão (nesta ordem)

1. ~~`oficina/dashboard.php`: incluir os 4 assets.~~ **CONCLUÍDO 2026-10-01.** Validado.
2. ~~Criar `_pedido_oficina_detalhe.php` e incluí-lo.~~ **CONCLUÍDO 2026-10-01.**
3. ~~`admin/pedidos.php`: badge `origem_funil`.~~ **CONCLUÍDO.**
4. ~~`sidebar_oficina.php`: hrefs `/oficina/*`.~~ **VERIFICADO — nada a alterar.**
5. **Auth (`MagicLinkService` e login):** sem mudança de comportamento. **Em espera do relatório do ChatGPT** (P-B-01).

**Próximas missões, condicionadas ao dono:**
6. **T3 — extrato decomposto da fatura** (§20.5). Bloqueada até T1 fechar (números) e T2 fechar (formato do split). Quando destravar, escrever no `_pedido_oficina_detalhe.php` + canhoto do parceiro + gravar em `order_price_snapshots`.
7. **B-009 — catálogo dinâmico nos 3 funis.** Agendada. Escopo: 3 views reescritas (A: público; B: cliente e admin), 1 JS sem STATE, `PedidoCoreService` deriva modalidade do catálogo, `public_slug` administrável, `attendance_mode` administrável, E2E para os 3 funis.

## 4. Contratos que esta faixa consome

| Contrato | Origem | Campos usados | Estado 2026-10-15 |
| --- | --- | --- | --- |
| Pedido v1 | Faixa A | `oficina_id`, `oficina_nome`, `triagem`, `custo_assistencia`, `custo_reboque`, `recomendacao`, `origem_funil` | Fechado (`CONTRATOS.md` §3) |
| Disponibilidade de Guincho | Faixa A | `GuinchoDisponibilidade::existeNoRaio()`, `::candidatosPorAnel()`, `::escolherAnel()` | Ativo; assinatura de `candidatosPorAnel` ainda divergente (§7.4) |
| Resolução de `service_type_id` | Faixa A | `ServiceTypeResolver::porSlug()` (paliativo — B-008) | Confirmado por `verify_gaps` item 1 (12/12 + `combustivel`) |
| Catálogo Dinâmico (B-009) | Faixa A | `service_type_id` do select + `attendance_mode` do catálogo | Agendado |
| Bloqueio Financeiro (B-010) | Faixa A | `BloqueioFinanceiroService::guinchoEstaBloqueado()` | Contrato aceito; gate §9.6 ainda **não ativo** (faturas_parceiro com 0 linhas) |
| Extrato decomposto (T3) | Faixa A | Saída de `PrecificacaoService::calcular()['detalhamento']` | **Bloqueada por T1/T2** |

Esta faixa **não publica** contratos próprios até o momento.

## 5. Critérios de aceite (testa: ChatGPT)

- [x] `/oficina/dashboard` carrega os 4 assets com HTTP 200 no DevTools.
- [x] Console limpo: sem "communications is not defined" nem "AtendimentoStatus is not defined".
- [x] `/oficina/dashboard` recebe alerta em tempo real como o `/guincho/dashboard`.
- [x] `/admin/pedido/{id}` renderiza o card "Oficina & Decisão" dentro de `<main class="main-content shell-main shell-content">`.
- [x] O card mostra os campos do Contrato Pedido v1 com `—` nos nulos.
- [x] `/admin/pedidos` exibe badge `origem_funil`.
- [ ] Login e magic link continuam com o mesmo comportamento — **teste de C pendente (P-B-01)**.
- [ ] Cenários relacionados `admin-enxerga-oficina` e `oficina-alertas` — **teste de C pendente**.

A faixa B **não escreve** os testes da própria faixa (R5).

## 6. Regras que valem aqui

- Orçamento de investigação: máx. 5 arquivos e 5 comandos antes de propor correção. Falhou 2x no mesmo ponto → registrar em `doc/BLOCKERS.md` e parar.
- Diff mínimo, sem refatorar ou apagar fora da tarefa. UTF-8 sem BOM, `php -l` limpo.
- Toda checagem de rota usa GET (R7).
- Nunca exibir segredos.
- Formato de entrega: (a) lista de arquivos CRIAR/ALTERAR, (b) código completo (PowerShell para XAMPP Windows), (c) comando exato de teste, (d) saída esperada, (e) rollback.

## 7. Pendências e bloqueios

| Item | Situação |
| --- | --- |
| **P-B-01** — `MagicLinkService` depende de `RateLimiter`, `Configuracao`, `AuthService::sanitizeReturnPath`, SMTP/Brevo, WAPI. Nenhum verificado. | **Aberto — teste da Faixa C** |
| **P-B-04** — Views órfãs `registro_cliente.php` e `registro_guincho.php`. | **Aberto — decidir (arquivar/deletar/reativar)** |
| **P-B-05** — Oficina sem formulário completo em uma página (assimetria com cliente/guincho). | **Feature nova — não é Onda 1** |
| **P-B-07** — `registro_simples.php` e `perfil_completar.php` usam `var(--gf-*)` com import próprio de `tokens.css`, independentes do `header.php`. | **Informativo** |
| **P-B-08** — `cidade.php` tem CSS inline das badges. Se A migrar para `public-landing.css`, mover. | **Informativo** |
| **B-009** — Catálogo dinâmico no funil. | **Agendado — bloqueado por decisão do dono sobre quando iniciar** |
| **B-010** — Bloqueio financeiro operacional. | **Ativo** — contrato aceito por A; gate §9.6 depende de (a) faturas reais geradas (0 linhas hoje), (b) `POST /webhook/mercadopago/pix` registrado, (c) `marcarPaga()` desbloqueando, (d) aviso ao parceiro. **Não avançar sem (a)-(d)** |
| **T3 — extrato decomposto** | **Bloqueado por T1/T2 (faixa A)** |
| **Pré-requisito T3:** validar o formato do split em centavos contra §20.4 | Aguardando A |
| **Pré-requisito T3:** confirmar `order_price_snapshots` recebe o `detalhamento` completo | `PrecificacaoService::gravarSnapshot()` já grava `calculation_context_json` com reconciliação centesimal — verificar se a saída cobre todos os campos que o extrato T3 precisa (base, elevação, terreno, deadhead, pedágio, comissão, reserva gateway, líquido) |
| **Contrato Pedido v1 final** | Fechado (`CONTRATOS.md` §3) |
| **B-001** — 404 nas rotas B em localhost | **RESOLVIDO** — URL correta é `http://localhost:8080/` (docroot = raiz) |
| **B-002** — tipo de `triagem` | **RESOLVIDO** — fechado no banco conforme v2.0 |
| **B-004** — migration dos 7 campos | **RESOLVIDO** — fechado no banco |
| **B-005** — alerta no dashboard do guincho | **RESOLVIDO 2026-10-04** — pedido expirado, não bug |
| **B-008** — Fallback `[B-STID-01]` para `eletrica` | **RESOLVIDO 2026-10-08** — `ServiceTypeResolver` + `ServiceType::isTowing()` + Solução B + skip-veiculo |
| **B-007** — Cancelamento de oficina | **RESOLVIDO 2026-10-04** |
| **B-GUARD-01** ativação | Aguardando merge do PR #1 de A — depois substituir bloco comentado em `AdminController.php:1545` pela chamada real |
| **B-GUARD-02** defesa em profundidade | Aplicado — 78 linhas em `AdminController.php` (commit `2026-10-06`) |
| **Permission denied em `src/Models/Pedido.php`** | Ativo — afeta cron `cron_cancelar_pedidos_expirados.php`, não o fluxo interativo |
| **Mojibake em `AdminController.php`** | **Registrado** — não corrigir agora (risco × benefício; ver `BLOCKERS.md` débito técnico) |
| **`.bak-*` na raiz fora do git** | Arquivados em `_archive/` (commits `4ed2bf4`, `d42dc34`, `639c1b8`) — não remover do git sem autorização do dono |
| **Faturas dos parceiros** | **ENTREGUE 2026-10-08** — 4 rotas, 2 views, 4 métodos no `AdminController`, item na sidebar |
| **Menu admin — blocos de ocultação** | **Confirmado 2026-10-15** por `verify_gaps` item 4 (5 blocos; Marketing/Logs/Health fora de `href` ativo) |

## 8. Histórico

| Data | Entrega | Resultado |
| --- | --- | --- |
| 2026-09-30 | Criação da ficha da faixa | — |
| 2026-10-01 | Scripts no `oficina/dashboard.php` | OK — 4 assets (linhas 13, 180-182) |
| 2026-10-01 | Require do partial no `pedidodetalhe.php` | OK — dentro do `<main>` (linha 348) |
| 2026-10-01 | Badge `origem_funil` em `admin/pedidos.php` | OK |
| 2026-10-01 | Verificação de hrefs em `sidebar_oficina.php` | OK — nenhuma violação |
| 2026-10-01 | B-001 fechado | OK |
| 2026-10-04 | fix-B-11 v2 — toast+beep no `/guincho/dashboard` | OK — B-005 fechado |
| 2026-10-04 | D3 confirmado pelo dono: pasta real é `doc/` | `lanes.json` continua com `docs/` — pendência do dono |
| 2026-10-04 | `OficinaController::buscarPedidosProximos` migra para `Pedido::listarFilaElegivelParaOficina` | OK |
| 2026-10-04 | `/oficina/pedido/{id}/aceitar` migrada para POST + CSRF | OK |
| 2026-10-04 | **B-007** cancelamento de oficina | OK |
| 2026-10-04 | Migration `install/migration_oficina_reputacao_v1.sql` | OK |
| 2026-10-04 | Dispatcher genérico `{id}`/`{nome}` em `index.php` | OK — destrava 7+ rotas |
| 2026-10-04 | Arquivamento de 274 `.bak` + 31 zumbis em `_archive/` | OK |
| 2026-10-06 | fix-B-guard-reboque.ps1 — B-GUARD-02 | OK — 78 linhas em `AdminController.php` |
| 2026-10-06 | CONTRATO_PEDIDO B→A via PR #1 (ChatGPT) | OK |
| 2026-10-08 (s.2) | fix-B-oficina-alerta-e-toast | Aplicado — pendente validação |
| 2026-10-08 (s.2) | fix-B-stid-01 — fallback `service_type_id` | Aplicado em `cb654f7`; regressou em teste com `eletrica` |
| 2026-10-08 (s.3) | B-008 reescrito como "bloqueado por A" | Pacote de A documentado em `BLOCKERS.md` |
| 2026-10-08 (s.4) | fix-B-stid-v5c aplicado em `AdminController.php` | OK — `ServiceTypeResolver::porSlug()` |
| 2026-10-08 (s.4) | Solução B no `_precotacao_funil_admin.php` | OK — hidden sincronizados via `gf:flow-stage` |
| 2026-10-08 (s.5) | fix-B-stid-v6c + Solução B + skip-veiculo | OK — B-008 fechado com evidência |
| 2026-10-08 (s.5) | Tela `/admin/faturas` + `/admin/fatura/{id}` | OK — 4 métodos, 2 views, 4 rotas |
| 2026-10-08 (s.5) | Item "Faturas" na sidebar admin | OK |
| 2026-10-08 (s.5) | Card "Comissão" no `_pedido_oficina_detalhe.php` | OK |
| 2026-10-08 (s.5) | `OficinaController::financeiro()` refatorado | OK |
| 2026-10-08 (s.5) | CONTRATO_PEDIDO B→A (bloqueio financeiro) aceito | OK — B-010 aberto |
| 2026-10-08 (s.5) | `FaturaService` + `FaturaWebhookController` descomprimidos + UTF-8 | OK |
| 2026-10-10 | Onda 1 — verificação dos 5 itens da MISSÃO | Integralmente implementados |
| 2026-10-11 | Onda 2 — tokens, cores, rodapé de bandeiras, badges amigáveis | Encerrada |
| 2026-10-11 | Onda 2.1 a 2.5 (imports, cores, bandeiras, badges, acentos) | OK |
| 2026-10-11 | **P-B-06** — mojibake em `registro_simples.php` e `perfil_completar.php` | **RESOLVIDO** por `tools/fix-B-encoding-voces.ps1` |
| 2026-10-11 | Onda 3 — revestimento visual do `pre-cotacao.php` clássico | Em andamento |
| **2026-10-15** | **Sessão sem diff de código.** `verify_gaps.php` 12/12 OK (itens 1 e 4 tocam B). Parametrização do dono gravada em `configuracoes` + §22 em `CONTRATOS.md`. Foco migrou para T1/T2 (faixa A). | **B aguarda T3 / B-009** |

Ao terminar uma entrega: atualizar este arquivo (estado, contratos, pendências) e parar.


---

## B-011 · B2/B3 preparados, bloqueados por green-light (2026-10-16)

**Contrato recebido:** `CONTRATO_RESPOSTA — A → B · B-011 (§23)`
**Patch preparado:** `patches/B-011-B2-B3.patch.txt` (NÃO aplicado)

### Por que não aplicado ainda

O próprio contrato pede: *"Bloqueio: não ativar B-011-B2/B3 até a Faixa A
liberar a implementação corrigida e homologada do motor."*

### Assinaturas verificadas

| Serviço | Assinatura | Status |
|---|---|---|
| `RoutingService::distanciaKm()` | `(float, float, float, float): ?float` | OK |
| `RoutingService::ultimaFonte()` | `(): ?string` | OK |
| `ZonePricingService::resolverZonaPorCoordenada()` | `(float, float): ?array` | OK |
| `ServiceType::buscarPorCodigo()` | `(string): ?array` | Divergência §2 abaixo |

### CONTRATO_PEDIDO emitidos

1. **Camada 5 de `buscarRegra()`** — permite fallback cross-zone, viola §23 §3.
2. **`ServiceType::buscarPorCodigo()`** não filtra `active = 1`.

### Pré-requisito JS aplicado

`pre-cotacao-flow.js` envia `tipo_problema=reboque` quando modo = `levar_carro`.
Sem isso, o mapeamento §1 do contrato recebia string vazia.

### O que o patch contém

- Substituição 1: resolução dinâmica via `ServiceType::buscarPorCodigo()` com fail-closed
- Substituição 2: distância obrigatória via `RoutingService` para TOWING
- Substituição 3: `PrecificacaoService::calcular()` como fonte única
- Substituição 4: persistência de `regra_id`, `regra_version`, `detalhamento`,
  `fonte_km`, `zona_id`, `origem_tarifa=precificacao_oficial`

### Quando aplicar

Quando a Faixa A avisar explicitamente:
- Motor homologado
- Camada 5 de `buscarRegra()` corrigida
- `buscarPorCodigo()` filtrando `active`

Aplicar o patch, rodar T1/T2 para confirmar verde, atualizar este arquivo.