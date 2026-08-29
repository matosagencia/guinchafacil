# Briefing para Codex — Lote de pendências reais da auditoria de 2026-08-06 (GuinchaFácil)

**Contexto do projeto:** GuinchaFácil, plataforma de intermediação de socorro/reboque (PHP puro, MySQL, sem framework). Convenções obrigatórias (já usadas em todo o projeto):

- Migrations em `install/*.sql`, sempre **idempotentes** (guard via `INFORMATION_SCHEMA` antes de `ALTER TABLE`/`CREATE TABLE`), executadas em ordem alfabética por `install/migrate.php` — nomeie qualquer migration nova pra ordenar depois da mais recente.
- Sem evidência, não está pronto — todo valor exibido/cobrado precisa vir de dado real, nunca estimado. Se faltar dado, "sem dado", nunca 0 ou 100% por padrão.
- Toda integração financeira/de estoque precisa ser **idempotente** (retry, refresh, concorrência ou reenvio não podem duplicar efeito) — os dois services envolvidos aqui (`OrderChargeItem`, `EstoqueService`) já foram desenhados assim; preserve esse contrato.
- Ao final de cada fase: `php -l` limpo em todo arquivo `.php` tocado, e rodar a suíte de testes relevante (PHPUnit e/ou Playwright) antes de reportar como concluído.
- **Este briefing nasceu de uma auditoria read-only externa (não minha) cruzando rotas/controllers/services/models/views/migrations/QA/documentação.** Os achados de "o que já está conectado" nela foram conferidos e estão corretos; peguei o que ela classificou como pendência real e organizei em fases por dependência/risco.

## Ordem de execução (dependência e risco crescente)

```
Fase 1 (baixo risco, corrige registro)  → auditoria real da suíte de testes Pix
Fase 2 (risco moderado, contido)        → baixa automática de estoque no orçamento
Fase 3 (risco financeiro, modo sombra)  → order_charge_items em modo sombra (auditoria paralela, NÃO substitui pagamento real)
Fase 4 (depende da Fase 2)              → E2E completo de socorro com bateria/compatibilidade/estoque
```

Fases 1 e 3 podem rodar em paralelo se houver capacidade — nenhuma depende da outra. Fase 4 **depende estritamente** da Fase 2 estar concluída (precisa do picker de estoque real pra ter o que testar ponta a ponta). Fase 2 é independente das demais.

---

## Fase 1 — Auditar (não recriar do zero) a suíte de testes de Pix

### Por que esta fase existe

A constituição do projeto (`doc/CONSTITUICAO_ATUALIZACAO_SECAO6_2026-07-18.md:27`) marca "Testes automatizados do Pix" como **PENDENTE**. Isso está **desatualizado**: já existe uma suíte PHPUnit substancial cobrindo Pix/webhook:

- `tests/Unit/PixServiceTest.php` (24 métodos — mapeamento de chave Pix, transferência via API, idempotency key, reprocessamento com sucesso/falha, status "processando")
- `tests/Unit/WebhookControllerTest.php`
- `tests/Unit/WebhookSignatureTest.php`
- `tests/Unit/MercadoPagoProviderPixTest.php`
- `tests/Integration/PaymentWebhookIdempotencyTest.php`
- `tests/Integration/PixGuardTest.php`
- `tests/Integration/PaymentJobServiceTest.php`, `PaymentJobDeadLetterTest.php`
- `tests/Integration/PayoutLedgerConsistencyTest.php`
- `tests/Integration/SplitRepasseConclusaoTest.php`

Isso já é o padrão do projeto que se repetiu 2x nesta sessão: documentação desatualizada dizendo "pendente" sobre algo que já foi construído (mesmo caso do estorno automático, que já existia em `EstornoService`/`cron_cancelar_pedidos_expirados.php` apesar da constituição dizer o contrário). **Não confie no rótulo "PENDENTE" sem antes rodar o que já existe.**

### Tarefas

1. Rodar a suíte inteira (`vendor/bin/phpunit` ou equivalente configurado no projeto) e registrar o resultado real: quantos passam, quantos falham, quais ficam skip por falta de credencial/sandbox.
2. Mapear cobertura real vs. os pontos críticos do fluxo Pix: `WebhookController::mercadoPago()` (linha 60) e `WebhookController::pagSeguro()` (linha 142), `EstornoService::estornar()` (estorno automático, ver `ExpiracaoPedidosService` — adicionado em 05/08/2026, ainda sem teste dedicado — **este é um gap real**, escreva um `tests/Unit/ExpiracaoPedidosServiceTest.php` ou `tests/Integration/` cobrindo: pedido expira → cancela → tenta estorno → nunca fica preso em "estornando" mesmo com falha no gateway), `MercadoPagoProvider`/`PagSeguroProvider` (ambos os gateways, não só MP).
3. Só depois de mapear os gaps reais, escrever os testes que realmente faltam — não duplicar o que já existe.
4. Atualizar `doc/CONSTITUICAO_ATUALIZACAO_SECAO6_2026-07-18.md` (ou o documento de status vigente que a substituiu) trocando "PENDENTE" pelo estado real, com a lista de arquivos de teste como evidência — sem isso, esse mesmo item vai continuar sendo reportado como pendência em toda auditoria futura.

### Critério de entrega

- Relatório de execução real da suíte (não estimado).
- Lista exata dos gaps novos cobertos, com nome de arquivo.
- Doc de status corrigido, citando os arquivos de teste como evidência (regra "sem evidência, não está pronto" se aplica também a marcar algo como pronto).

---

## Fase 2 — Baixa automática de estoque ao concluir orçamento

### O que já existe

- `EstoqueService::baixarPorPedido(int $providerId, int $produtoId, int $pedidoId, int $qtd = 1, ?string $descricao = null): bool` (`src/Services/Estoque/EstoqueService.php:42-49`) — já é idempotente (hash `SAIDA:{pedidoId}:{produtoId}:{providerId}`), já transacional (`SELECT ... FOR UPDATE`), já rejeita saldo negativo. **Não precisa mudar esse método** — só falta chamá-lo.
- `PedidoOrcamento` (`src/Models/PedidoOrcamento.php`) — `pedido_orcamentos.itens_json` guarda os itens decididos pelo cliente.
- `DiagnosticoService::decidirOrcamento(int $pedidoId, int $clienteId, bool $aprovado): PedidoTransitionResult` (`src/Services/Diagnostico/DiagnosticoService.php:108-129`) — processa a decisão do cliente. Hoje, quando `$aprovado === true`, só faz `PedidoOrcamento::decidir()` + transição pra `em_execucao_servico`. **Não chama `EstoqueService::baixarPorPedido()`.**
- Chamador: `ClienteController::orcamentoDecidir(int $id)` (`src/Controllers/ClienteController.php:972`).

### Tarefa

1. Em `DiagnosticoService::decidirOrcamento()`, quando `$aprovado === true` e a transição pra `em_execucao_servico` tiver sucesso, iterar `itens_json` do orçamento e, **para cada item que referencia um produto de estoque real** (ver como `itens_json` distingue peça física de mão-de-obra/serviço — inspecionar o schema real gravado por quem cria o orçamento antes de assumir a estrutura), chamar `EstoqueService::baixarPorPedido($providerId, $produtoId, $pedidoId, $qtd, $descricao)`.
2. **Não baixar estoque de itens que não são produto físico** (mão de obra, taxa de deslocamento, etc.) — se `itens_json` não tiver hoje um jeito de diferenciar isso, esse é o primeiro ajuste necessário (adicionar um campo tipo `tipo: 'produto'|'servico'` ou `produto_id: null` pra serviço, com migration idempotente se a coluna authored for nova).
3. Se `EstoqueService::baixarPorPedido()` retornar `false` (saldo insuficiente), **decidir e documentar o comportamento**: bloquear a aprovação do orçamento com mensagem clara pro prestador ("estoque insuficiente para X"), ou aprovar mesmo assim e sinalizar pro admin (decisão de produto — se não estiver óbvio no código/doc existente, registrar a decisão tomada e por quê no log de execução, não decidir silenciosamente).
4. Espelhar a mesma baixa (ou confirmar que já está coberta) no fechamento via `GuinchoController::testeFinalConcluir()` (linha 1357) — casos onde o atendimento é resolvido no local SEM passar por orçamento formal, mas ainda assim consome peça de estoque.
5. Cobertura de teste: `tests/Integration/` (já existe `EstoqueServiceTest` — estender ou criar um novo cobrindo o fluxo completo orçamento → aprovação → baixa) + um novo cenário em `qa/suites/atendimento-variacoes.spec.ts` ou um spec dedicado, fabricando um orçamento real com item de estoque e confirmando a baixa via snapshot direto no banco (mesmo padrão de `tools/qa_get_pedido_snapshot.php` — pode precisar de um `tools/qa_get_estoque_snapshot.php` novo, seguindo o mesmo padrão dos outros `qa_get_*_snapshot.php`).

### Fora de escopo nesta fase

- Reversão de estoque em cancelamento pós-aprovação — `EstoqueService::estornarPorPedido()` já existe; só confirmar que ele é chamado no fluxo de cancelamento de pedido já em `em_execucao_servico` (`CancelamentoService`/`PedidoTransitionService`) — se não for, é um gap adicional a registrar (não necessariamente a resolver nesta fase, documentar no log).

---

## Fase 3 — `order_charge_items` em modo sombra (NÃO substituir o pagamento real ainda)

### Contexto de risco — leia antes de programar

`GuinchoController::testeFinalConcluir()` (linha 1357-1434) hoje decide o valor pago ao prestador via `splitRepasseParaConclusao()` + `PaymentJobService::enqueuePixPayout()` — isso é dinheiro real saindo via Pix. `ChargePolicyService`/`OrderChargeItem` foram desenhados de propósito para NÃO estarem no caminho de produção ainda, porque a regra de cobrança por fases depende de evidência (Proof-of-Service) e ainda não foi validada com dado real.

**Por isso esta fase é "modo sombra": gravar o que `ChargePolicyService` calcularia, em paralelo, sem trocar o que efetivamente é pago.** Trocar o cálculo real de pagamento é uma decisão de produto separada, que exige comparar os dois valores (antigo vs. novo) em pedidos reais antes de decidir migrar — **não é para o Codex decidir sozinho fazer essa troca nesta fase.**

### Tarefas

1. Em `GuinchoController::testeFinalConcluir()` (ou no ponto de fechamento equivalente pra reboque, se for outro método — mapear todos os pontos que hoje fecham financeiramente um pedido), **depois** que `ProofOfServiceService::avaliarEFechar()` já rodou (linha ~1396) e o resultado (evidência validada ou não) está disponível, chamar `ChargePolicyService::resolverItensPrimeiroRespondente($situationCode, $context)` com o `situation_code` real derivado do desfecho do pedido, e gravar o resultado via `OrderChargeItem::criar()` — **sem alterar em nada** o valor que `splitRepasseParaConclusao()`/`PaymentJobService::enqueuePixPayout()` calculam e pagam.
2. Mapear `$situationCode` real a partir do estado do pedido (resolvido no local vs. recomendado reboque aceito/recusado, cancelamento durante atendimento, no-show, evidência faltando, diagnóstico inconsistente, etc.) — usar os 10 `ChargeCodes::SITUATION_*` já modelados; se algum desfecho real do sistema não mapear claramente pra nenhuma situação existente, documentar no log em vez de forçar um mapeamento errado.
3. Criar uma tela/relatório admin simples (ou reaproveitar `/admin/financeiro` se fizer sentido) mostrando, por pedido, o valor que **foi pago de verdade** (Pix/split atual) lado a lado com o que `order_charge_items` teria gerado — essa comparação é o artefato que vai permitir decidir depois se a migração de verdade é segura.
4. Testes: `tests/Integration/` cobrindo que `OrderChargeItem::criar()` é chamado com os dados certos em cada situação, e que **nenhum valor pago muda** (teste de regressão explícito: valor do Pix antes e depois desta fase precisa ser idêntico byte a byte pro mesmo cenário).

### Critério de entrega

- `order_charge_items` sendo populada em produção, em paralelo, sem qualquer mudança no valor pago hoje.
- Relatório comparativo (mesmo que simples) disponível pro admin.
- Teste de regressão provando que o valor pago não mudou.
- **Explicitamente não fazer nesta fase:** trocar `PaymentJobService::enqueuePixPayout()` pra usar `order_charge_items` como fonte de verdade — isso fica pra uma decisão de produto separada, depois de revisar o comparativo com dado real.

---

## Fase 4 — E2E completo de socorro com bateria/compatibilidade/estoque

**Depende da Fase 2 estar concluída** (sem baixa de estoque real conectada, não há o que testar ponta a ponta).

### O que já existe (não recriar)

- `ProviderVehicleCompatibilityServiceTest`, `SystemServiceProtectionServiceTest` (unitários), `EstoqueServiceTest`, `ProviderVehicleCompatibilityEvaluateTest` (integração) — cobertura de unidade já boa.
- `qa/suites/provider-vehicle-compatibility.spec.ts` — 6 casos, mas só telas admin (catálogo/compatibilidade/checklists), não fabrica pedido real.
- `qa/suites/atendimento-eletrica-rj.spec.ts` — cobre bateria descarregada, mas resolução simples sem orçamento/estoque.
- `qa/suites/atendimento-variacoes.spec.ts` (linha ~266) — bateria + alternador com falha, **gera orçamento**, mas não valida baixa de estoque (que não existia até a Fase 2).

### Tarefa

1. Criar seed novo `tools/prepare_atendimento_bateria_estoque_qa_seed.php` (mesmo padrão de `tools/prepare_atendimento_socorro_qa_seed.php`): guincho especialista com produto de bateria em estoque (quantidade conhecida), veículo compatível, pedido de socorro elétrico real.
2. Criar `qa/suites/atendimento-bateria-estoque-e2e.spec.ts` fabricando o fluxo completo: pedido → diagnóstico → orçamento com item de bateria → cliente aprova → **estoque baixa** (confirmar via `qa_get_estoque_snapshot.php` da Fase 2) → conclusão do atendimento → confirma valores finais.
3. Cobrir também o caminho de recusa/incompatibilidade (veículo incompatível com a peça — `ProviderVehicleCompatibilityService` deve bloquear ou avisar, conforme já definido pro resto do sistema).

### Critério de entrega

- Spec novo passando de ponta a ponta contra o ambiente real (não mockado).
- Seed reexecutável/idempotente, seguindo o padrão dos demais `tools/prepare_*_qa_seed.php`.

---

## Fora de escopo deste lote (não fazer)

- **Cadastro de prestador genérico** (`providers`/`provider_units`/`provider_members`) — confirmado como pendência de produto futura, não prioridade agora; o fluxo principal continua em `guinchos` de propósito.
- **Unificação do catálogo duplo** (`ServicoCatalogo` legado vs. `service_types`) — legado funcional, ainda necessário pra tela de novo pedido do cliente; migração completa é decisão de produto separada.
- **`Pedido::atribuirGuincho()`** (deprecated, só QA/seeds) — manter como está, não remover enquanto os seeds antigos existirem.
- **`versoesLegado()`** (`AdminVehicleCatalogController.php:197`) — rota de compatibilidade real, não mexer.
- **Tabelas de carteira manual** (`guincheiro_carteira`, `guincheiro_movimentos`, `pagamento_liquidacoes`, `saques_guincheiro`, `saque_eventos`) — obsoletas por decisão de produto (repasse Pix automático via `PaymentJobService` substituiu saque manual); não reconectar.

## Critérios gerais de entrega (todas as fases)

1. `php -l` limpo em todo arquivo `.php` tocado.
2. Nenhuma migration destrutiva sem idempotência.
3. Nenhuma mudança no valor efetivamente pago a prestadores fora do que a Fase 2/3 explicitamente autoriza (Fase 3 é modo sombra — **zero mudança de valor pago**).
4. Preencher `doc/LOG-EXECUCAO-LOTE-AUDITORIA-2026-08-06.md` (já criado, ver estrutura) conforme cada fase avança — é o que será revisado antes de aceitar o lote como concluído.
5. Ao final de cada fase, resumo indicando: arquivos alterados/criados, testes rodados (com resultado real, não "deveria passar"), e qualquer decisão de produto que precisou ser tomada no meio do caminho (registrar, não decidir arbitrariamente algo que devia voltar pro usuário).
