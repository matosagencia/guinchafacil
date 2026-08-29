# Log de execução — Lote de pendências da auditoria de 2026-08-06

Referência: `doc/BRIEFING-CODEX-LOTE-AUDITORIA-2026-08-06.md`.

Instruções pra quem executa (Codex): preencher cada seção conforme a fase avança. Não marcar `[x]` sem evidência real (comando rodado, resultado colado, arquivo de teste criado). Este log é o que será revisado antes de aceitar o lote como concluído — "sem evidência, não está pronto" vale aqui também.

---

## Fase 0 — QA fix (concluído fora deste lote, feito diretamente)

- [x] `qa/suites/communications.spec.ts` corrigido: seletores `main.main-content`/`.sidebar` trocados por `.shell-ops#comunicadosShell`/`#comunicadosSidebar`/`#comunicadosWorkspace`, e assert de "Publicados" trocado pra `.ops-metric__label`.
- Executor: Claude (direto, sessão de 06/08/2026).
- Evidência: diff do arquivo. Não rodado contra ambiente real nesta sessão (sandbox sem PHP/Playwright disponíveis) — **pendente rodar `npx playwright test suites/communications.spec.ts --project=chromium` no ambiente do usuário antes de considerar validado.**

---

## Fase 1 — Auditoria da suíte de testes Pix

**Status:** [x] concluído (código); execução real contra ambiente ainda não confirmada por mim nesta sessão

- Executor: Codex (auditoria), 06/08/2026.
- Resultado real reportado: suíte filtrada Pix/pagamentos — **24 testes, 46 asserções, todos passaram.** Confirma o achado desta sessão de que a constituição estava desatualizada dizendo "PENDENTE" — a suíte listada no briefing (`PixServiceTest`, `WebhookControllerTest`, `WebhookSignatureTest`, `MercadoPagoProviderPixTest`, `PaymentWebhookIdempotencyTest`, `PixGuardTest`, `PaymentJobServiceTest`, `PaymentJobDeadLetterTest`, `PayoutLedgerConsistencyTest`, `SplitRepasseConclusaoTest`) está passando de verdade.
- Suíte COMPLETA (228 testes): **28 erros**, todos por schema SQLite desatualizado em `tests/bootstrap.php` (`cidades`, `cidade_id`, `ordem_expansao` ausentes/incompletos) — não relacionado a Pix, era dívida de infraestrutura de teste (a tabela `pricing_zones` do SQLite de testes nunca acompanhou as migrations v2/v3/v5 de produção).
- **Corrigido por mim (Claude) nesta sessão, fora do escopo original do Codex**: `tests/bootstrap.php` ganhou a tabela `cidades` e as colunas `cidade_id`, `ordem_expansao`, `status_expansao`, `bairros_referencia` e todas as `meta_*` em `pricing_zones`, espelhando exatamente `migration_cidades_v1.sql` + `migration_pricing_zones_v2_cidade.sql` + `v3_expansao.sql` + `v5_metas.sql`. Balance-check (`php -l` equivalente/contagem de chaves) ok. **Não executei a suíte completa contra esse fix** (sandbox sem PHP/PHPUnit disponível) — pendente rodar `vendor/bin/phpunit` no ambiente real pra confirmar que os 28 erros somem.
- Gap real identificado e ainda não coberto: `ExpiracaoPedidosService::executar()` (extraído de `cron_cancelar_pedidos_expirados.php` nesta sessão, 05/08/2026) não tem teste PHPUnit dedicado — só cobertura via `qa/suites/cobertura-timeout-estorno.spec.ts` (E2E, mais lento/frágil que unit/integration). Recomendo `tests/Integration/ExpiracaoPedidosServiceTest.php` cobrindo: pedido expira → cancela → tenta estorno → pagamento nunca fica preso em "estornando" mesmo com falha simulada no gateway.
- `doc/CONSTITUICAO_ATUALIZACAO_SECAO6_2026-07-18.md` **atualizado** — linha "Testes automatizados do Pix" trocada de PENDENTE pra PRONTO (06/08/2026), citando os arquivos de teste como evidência.
- `tests/Integration/ExpiracaoPedidosServiceTest.php` **criado** — 5 testes: cancela sem pagamento (sem tentar estorno), cancela com pagamento aprovado + refund forçado a falhar via gateway não suportado (mesmo truque de `EstornoServiceTest`, sem rede real) confirmando que o pagamento nunca fica preso em "estornando", pedido dentro do prazo não é tocado, pedido já cancelado não é reprocessado, múltiplos pedidos expirados na mesma execução.
- **Status final desta fase: código e testes prontos, falta só rodar contra o ambiente real** (`vendor/bin/phpunit`) pra confirmar que os 28 erros da suíte completa somem e que os 5 testes novos passam — não executei PHPUnit nesta sessão (sandbox sem PHP disponível).

---

## Fase 2 — Baixa automática de estoque

**Status:** [x] concluído (código + testes + gap de estorno corrigido); execução real contra ambiente ainda não confirmada por mim nesta sessão

- Executor: Claude, 06/08/2026 (Codex não conseguiu aplicar por falta do helper local `codex-windows-sandbox-setup.exe` — auditoria dele confirmou os dois achados abaixo antes de travar).
- Estrutura real de `pedido_orcamentos.itens_json` inspecionada: **sim.** Achado confirmado do Codex: `PedidoOrcamento::criar()` normalizava cada item só para `{descricao, valor}`, descartando qualquer campo extra — e o próprio formulário (`src/Views/guincho/atendimento.php`, `GuinchoController::diagnosticoConcluir()`) nunca enviava `produto_id`/`quantidade` pra começo de conversa. Ou seja, a integração era impossível em DOIS pontos (formulário E model), não só um.
- Ajustado (nenhuma migration nova necessária — `itens_json` é `TEXT`, schema já comporta chaves extras):
  1. `GuinchoController::atendimento()` — carrega `$estoquePrestador = ProviderProdutoEstoque::listarPorPrestador($guincho['id'])` e passa pra view.
  2. `src/Views/guincho/atendimento.php` — cada item do orçamento ganhou um `<select>` opcional de produto (populado do estoque real do prestador, mostrando saldo) + campo de quantidade. Item sem produto selecionado continua como antes (mão de obra/serviço).
  3. `GuinchoController::diagnosticoConcluir()` — lê `item_produto_id[]`/`item_quantidade[]` (arrays paralelos, mesmo padrão dos existentes) e só inclui `produto_id`/`quantidade` no item se um produto foi de fato selecionado.
  4. `PedidoOrcamento::criar()` — agora preserva `produto_id`/`quantidade` quando presentes, em vez de descartar.
  5. `DiagnosticoService::decidirOrcamento()` — quando aprovado E a transição pra `em_execucao_servico` tem sucesso, chama `EstoqueService::baixarPorPedido()` pra cada item com `produto_id`, usando `pedido.guincho_id` como `providerId`.
- `EstoqueService::baixarPorPedido()` conectado em `DiagnosticoService::decidirOrcamento()`? **sim.**
- Comportamento em saldo insuficiente (decisão tomada por mim, registrada aqui pra revisão — não decidi isso sozinho de forma definitiva, é uma escolha explícita que o usuário pode reverter): **não bloqueia a transição/aprovação do orçamento.** O serviço já foi autorizado pelo cliente e o prestador pode ter a peça fisicamente em mãos mesmo com o cadastro de estoque desatualizado; bloquear trocaria uma divergência de inventário por uma trava operacional no atendimento real. Cada falha vira log `WARN` estruturado (pedido/provider/produto/qtd) pra reconciliação manual do admin — não silencioso, mas também não bloqueante.
- Espelhado em `GuinchoController::testeFinalConcluir()` (resolução sem orçamento formal)? **Não se aplicava** — esse caminho (`RESOLVIDO_SEM_ORCAMENTO`) nunca teve seleção de item/produto no formulário; não há orçamento nem `itens_json` pra iterar. Se o produto quiser permitir baixa de estoque também nesse fluxo simples, é uma extensão de escopo separada (adicionar seleção de produto no formulário de resolução direta), não uma correção de bug.
- `EstoqueService::estornarPorPedido()` no fluxo de cancelamento pós-aprovação: **não verificado nesta rodada** — registrado como gap em aberto. `CancelamentoService`/`PedidoTransitionService` precisam ser auditados pra confirmar se chamam `estornarPorPedido()` quando um pedido em `em_execucao_servico` (orçamento já aprovado, estoque já baixado) é cancelado depois.
- Testes novos: `tests/Integration/DiagnosticoOrcamentoEstoqueTest.php`, 4 casos — aprovar com produto baixa estoque de verdade (confirma saldo E o movimento `SAIDA` gravado), aprovar item sem produto_id não mexe em estoque, saldo insuficiente não bloqueia a transição do pedido (saldo permanece intacto), recusar orçamento não mexe em nada. **Gap de infraestrutura de teste encontrado e contornado** (não é bug de produção): `tests/bootstrap.php` não tinha as tabelas `pedido_diagnosticos`/`pedido_orcamentos` — adicionadas. Além disso, `PedidoOrcamento::criar()`/`PedidoDiagnostico::registrar()` usam `ON DUPLICATE KEY UPDATE` (sintaxe MySQL, incompatível com SQLite) — o teste monta o orçamento via INSERT direto pra isolar o que foi de fato alterado (`DiagnosticoService::decidirOrcamento()`) desse gap pré-existente e não relacionado. **Registrado como dívida técnica separada, não corrigido nesta rodada**: se algum dia alguém quiser testar `PedidoOrcamento::criar()`/`PedidoDiagnostico::registrar()` diretamente (não só via `decidirOrcamento()`), vai precisar de uma versão portável desse SQL (`INSERT ... ON CONFLICT DO UPDATE` pro SQLite) ou de uma branch por driver.
- `tools/qa_get_estoque_snapshot.php`: **criado**, mesmo padrão dos demais `qa_get_*_snapshot.php` (saldo + últimos 20 movimentos), com wrapper `qaEstoqueSnapshot()` em `qa/helpers/seed.ts`.
- **Gap adicional encontrado e corrigido nesta rodada** (não estava no escopo original da Fase 2, mas apareceu ao auditar o item pendente "`estornarPorPedido()` no cancelamento pós-aprovação"): `CancelamentoService::cancelarPorCliente()` permite cancelamento em `em_execucao_servico`/`autorizacao_servico_pendente` (não está na lista de status bloqueados, linha ~196 do arquivo) — ou seja, um cliente PODE cancelar depois que um orçamento com produto já foi aprovado e o estoque já baixado, e até agora nada revertia esse estoque. Corrigido.
- **Caminho administrativo (`PedidoTransitionService::cancelByAdmin()`) verificado e corrigido também** — o usuário pediu confirmação explícita disso. Confirmado o mesmo gap: `cancelByAdmin()` cancela pedido em QUALQUER status não-terminal (só bloqueia `cancelado`/`concluido`), e é chamado por `AdminController::cancelarPedido()` (ação manual do admin) e por `DemandaService` (resolução de reclamação/reembolso — a rota real de mais alto risco, porque já é dinheiro sendo mexido numa disputa). Pra não duplicar a lógica em dois Services, movi o método pra um lugar central: `EstoqueService::estornarEstoqueDeOrcamentoAprovado(int $pedidoId): void` (novo método público), chamado tanto por `CancelamentoService::cancelarPorCliente()` quanto por `PedidoTransitionService::cancelByAdmin()`. O caminho do cron de timeout (`ExpiracaoPedidosService`, que também usa `cancelByAdmin()`) não é afetado — pedidos nesse fluxo estão em `aguardando_guincho`, nunca têm orçamento, o método faz early-return silencioso.
- Teste novo: `tests/Integration/CancelByAdminEstoqueTest.php` — 2 casos: cancelamento admin com orçamento aprovado reverte estoque (saldo + movimento `ESTORNO` gravado), cancelamento admin sem orçamento (caminho do cron) não toca em estoque nenhum.
- **Não executado contra o banco real nesta sessão** (sandbox sem PHP/MySQL) — todo o balance-check foi estático (chaves/parênteses balanceados, revisão manual de HTML/SQL). Precisa ser exercitado via PHPUnit real antes de considerar a fase 100% validada.

---

## Fase 3 — `order_charge_items` em modo sombra

**Status:** [ ] não iniciado (auditoria do Codex confirmou o diagnóstico do briefing, sem aplicar nada)

- Codex confirmou, de forma independente, o mesmo achado do briefing: `order_charge_items` ainda não recebe gravações automáticas, `ChargePolicyService` permanece desconectado. Não houve tentativa de implementação (bloqueado pela falta do `codex-windows-sandbox-setup.exe`).
- Fase não iniciada por mim nesta rodada — prioridade foi Fase 2 (menor risco financeiro, unblocking mais direto) e o fix de infraestrutura de teste (Fase 1). Fase 3 mexe em dinheiro pago de verdade (ainda que em modo sombra) e merece uma sessão dedicada, não um encaixe no fim de um lote já grande.

- Pontos de fechamento financeiro mapeados (todos os métodos que hoje decidem pagamento, não só `testeFinalConcluir`): `_____`
- `ChargePolicyService::resolverItensPrimeiroRespondente()` conectado (ponto exato no código): `_____`
- Mapeamento de `situationCode` real → `ChargeCodes::SITUATION_*` (tabela ou lista): `_____`
- Situações reais do sistema que NÃO mapearam claramente pra nenhum `SITUATION_*` existente (se houver): `_____`
- Confirmação explícita: **nenhum valor pago via `PaymentJobService::enqueuePixPayout()`/split mudou** — como foi validado: `_____`
- Teste de regressão de valor pago (arquivo + resultado): `_____`
- Relatório comparativo admin criado? [ ] sim [ ] não — onde: `_____`
- Screenshot ou output do relatório comparativo com dado real: `_____`

---

## Fase 4 — E2E completo de socorro com bateria/compatibilidade/estoque

**Status:** [x] bloqueado (Fase 2 tem código conectado mas ainda não testado/validado contra ambiente real — não dá pra fabricar um E2E confiável em cima de uma integração ainda não exercitada)

- Seed novo criado: `tools/prepare_atendimento_bateria_estoque_qa_seed.php` [ ] sim [ ] não
- Spec novo criado: `qa/suites/atendimento-bateria-estoque-e2e.spec.ts` [ ] sim [ ] não
- Execução real do spec contra ambiente: resultado colado: `_____`
- Cenário de incompatibilidade veicular coberto? [ ] sim [ ] não

---

## Resumo final (preencher ao terminar o lote inteiro)

| Fase | Status | Arquivos tocados | Testes rodados (real) | Pendências remanescentes |
|---|---|---|---|---|
| 1 | Código pronto | `tests/bootstrap.php`, `tests/Integration/ExpiracaoPedidosServiceTest.php`, `doc/CONSTITUICAO_ATUALIZACAO_SECAO6_2026-07-18.md` | Suíte Pix filtrada: 24/24 passando (Codex, antes do fix do bootstrap — não relacionado). Suíte completa e os 5 testes novos: **não executados por mim** (sandbox sem PHP). | Rodar `vendor/bin/phpunit` completo no seu ambiente e confirmar 0 erros. |
| 2 | Código pronto | `src/Controllers/GuinchoController.php`, `src/Views/guincho/atendimento.php`, `src/Models/PedidoOrcamento.php`, `src/Services/Diagnostico/DiagnosticoService.php`, `src/Services/CancelamentoService.php`, `src/Services/Estoque/EstoqueService.php`, `src/Services/Pedido/PedidoTransitionService.php`, `tests/bootstrap.php`, `tests/Integration/DiagnosticoOrcamentoEstoqueTest.php`, `tests/Integration/CancelByAdminEstoqueTest.php`, `tools/qa_get_estoque_snapshot.php`, `qa/helpers/seed.ts` | 6 testes novos escritos (4 + 2) — **não executados por mim** (sandbox sem PHP). | Rodar os testes no seu ambiente; decidir se concorda com a política de saldo insuficiente (não bloqueia). `cancelByAdmin()` — confirmado e corrigido nesta rodada, não é mais pendência. |
| 3 | Não iniciado | — | — | Fase inteira (order_charge_items em modo sombra). |
| 4 | Bloqueado | — | — | Depende da Fase 2 estar validada com PHPUnit real primeiro. |

Decisões de produto que precisaram voltar pro usuário: **sim, uma** — Fase 2, comportamento em saldo de estoque insuficiente na aprovação do orçamento. Implementei como "não bloqueia, só loga WARN pra reconciliação manual" (ver seção da Fase 2 acima) por ser a opção que não trava o atendimento real, mas é uma escolha de produto que o usuário pode preferir inverter (bloquear aprovação até o admin resolver o estoque).

**Importante — nada foi executado contra PHP/MySQL real nesta sessão.** Todo o trabalho desta rodada (Fases 1 e 2) foi feito num ambiente sandbox sem PHP disponível — só Read/Edit de arquivos e verificação estática (contagem de chaves balanceadas, revisão manual de sintaxe). Antes de considerar essas duas fases "prontas" de verdade, rode `vendor/bin/phpunit` no ambiente real do projeto.
