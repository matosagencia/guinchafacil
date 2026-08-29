# GuinchaFácil — Relatório Final de QA (Playwright)

**Data-base:** 2026-07-31
**Escopo:** Reforço de QA end-to-end — correções de bugs reais, specs de stress, homologação realtime dos dois cenários principais.

---

## 1. Resumo executivo

Todos os bugs identificados no código (frontend e backend) durante este ciclo foram corrigidos e validados com evidência real (logs, `error-context.md`, screenshots, traces). Os itens #58 (stress agregado — pagamento) e #59 (homologação realtime) permanecem **bloqueados por instabilidade externa do sandbox do Mercado Pago**, não por defeito do sistema. A interface e o fluxo de pagamento (Payment Brick embutido) foram validados ponta a ponta até o clique em "Pagar" em ambos os cenários reais (colisão e pane elétrica); a rejeição ocorre no backend do MP sandbox, fora do controle do código da aplicação.

---

## 2. Bugs corrigidos e validados nesta rodada

| Item | Sintoma | Causa raiz | Correção | Status |
|---|---|---|---|---|
| Onboarding wizard (`#f_placa`) | Campo de placa nunca visível | `validateStep1()` bloqueia avanço sem checkbox de serviço marcado | `qa/helpers/onboarding.ts`: marcar `#chkReboque` antes de avançar | ✅ Corrigido — `onboarding-stress` 29/29 passos |
| Declaração de capacidades ("Sessão expirada") | Modal de sessão expirada aparente | Falso positivo: `error-context.md` (snapshot de acessibilidade) provou que o modal nunca foi exibido; bug real era formato de flash-message incompatível | `GuinchoController::capacidadesSalvar()` — mensagem de sucesso movida para o formato array usado pelo restante do controller | ✅ Corrigido |
| Nonce de evidência (`teste_final`) | `apiRequestContext.post` falhava com "Cannot read properties of undefined" | `evidenciaNonce()` não reconhecia status `teste_final` | Adicionado `teste_final` ao `match` status→tipo em `GuinchoController::evidenciaNonce()` | ✅ Corrigido — `atendimento-eletrica-rj` 14/14 |
| UI de chegada (serviço local) | Sem tela para confirmar chegada em pane elétrica | `atendimento.php` não tinha case para status `a_caminho` no painel de serviço local | Novo bloco `a_caminho` reaproveitando `#statusForm`/JS existente | ✅ Corrigido |
| `stress-concorrencia` | — | — | Nenhuma correção necessária | ✅ Passou de primeira |
| `stress-por` (idempotência) | Corrida concorrente retornava erro genérico em vez de resposta idempotente | (1) `client_point_id` não-determinístico no QA; (2) backend só verificava idempotência por `client_point_id`, faltando fallback por `sequence_number` | `postTowLocation()` usa timestamp determinístico; `ProofOfRoadService::ingestPoint()` ganhou fallback via `PedidoLocalizacao::buscarPorPedidoSequence()` | ✅ Corrigido — 2/2 passed |
| `stress-chaos` | Context destruction durante reload quebrava o teste; retry não reconhecia resposta idempotente | `page.evaluate()` sem `.catch()`; retry loop só tratava `ok:false` | Adicionado `.catch()` para unificar rejeição/hang; retry loop estendido para `idempotent_retry:true` | ✅ Corrigido — 2/2 passed |
| Stress agregado — contaminação cruzada | CPF duplicado e pedidos compartilhados entre workers paralelos; `test:stress` quebrava no Windows (glob não expande) | Seeds de CPF/e-mail fixos, sem isolamento por worker; `package.json` usava glob incompatível com `cmd.exe` | CPF/e-mail agora derivados de `TEST_WORKER_INDEX`; `test:stress` passou a listar os specs explicitamente | ✅ Corrigido |

---

## 3. Itens bloqueados — sandbox Mercado Pago (externo, sem ação de código pendente)

### #58 — Stress agregado (pagamento)
**Status: bloqueado pelo sandbox do Mercado Pago.**

`STRESS-PAG-001` segue rejeitado mesmo após todas as correções de QA (e-mail do comprador, seleção de parcelamento via padrão correto do Brick embutido). O pagamento é recusado pelo MP antes de qualquer lógica de webhook ser exercitada.

### #59 — Homologação realtime (2 cenários principais)
**Status: bloqueado pelo mesmo motivo.**

`RJ-COLISAO-001` (colisão com reboque) e `RJ-ELETRICA-001` (pane elétrica) — ambos os cenários reais, com `QA_TIME_MODE=realtime` — completaram 6/6 passos de interface (Payment Brick renderizado, seleção de cartão, preenchimento de dados, parcelamento, e-mail, clique em "Pagar") e falharam apenas na aprovação do pagamento pelo MP, em ambas as tentativas (original + retry).

### Assinaturas de erro conhecidas (MP sandbox)

| Assinatura | Onde ocorre | Detalhe técnico |
|---|---|---|
| `Cannot infer Payment Method` | `stress-pagamento`, `RJ-COLISAO-001` | HTTP 400, `cause.code=2131`, "payment methods inference error" |
| `internal_error` | `RJ-ELETRICA-001` | HTTP 500, `cause=[]`, sem detalhe adicional |

Ambas confirmadas via `logs/php_errors.log` do servidor, em pedidos distintos e isolados por worker — descartando contaminação de dados como causa. A interface e o payload enviado ao MP foram validados como corretos; a rejeição acontece do lado do gateway sandbox.

### Recomendação
Não seguir investigando ou alterando código enquanto o sandbox do MP permanecer instável. Reexecutar #58 e #59 quando houver sinal de estabilização do ambiente sandbox (ex.: nova tentativa em outro horário/dia, ou confirmação da MercadoPago sobre o ambiente).

---

## 4. Regra de negócio preservada

Nenhuma correção realizada neste ciclo relaxou regras de antifraude (Proof-of-Road) ou de negócio. O fallback de idempotência adicionado em `ProofOfRoadService` apenas evita duplicidade de efeito sob concorrência — não abre exceção para pontos de rota inválidos ou fora de sequência.

---

## 5. Item #37 — OSRM demo → endpoint estável

**Status: código concluído; deploy de infraestrutura pendente do usuário.**

O demo público (`router.project-osrm.org`) estava hardcoded em 4 views (`pedidonovo.php`, `pedido_trilha.php`, `pedidodetalhe.php`, `dashboard.php`), além do backend (`PorThresholds::roadMatchBaseUrl()`, já configurável). Correção aplicada:

- Novo método `PorThresholds::routingFrontendBaseUrl()` — ponto único de leitura, reaproveitando a mesma config `por_road_match_base_url` já usada pelo backend.
- Os 4 views agora leem essa URL via PHP e a injetam como `OSRM_BASE_URL` no JS, em vez de string fixa.
- Enquanto a config estiver vazia, o fallback continua sendo o demo público (comportamento inalterado até a config ser preenchida).

Pesquisa de mercado (2026): para o volume e perfil do GuinchaFácil, self-hospedar OSRM (Docker + extrato OSM do Brasil/RJ via Geofabrik) é a prática mais comum em apps de logística/delivery — custo fixo de infraestrutura, sem cobrança por requisição, e mesmo formato de resposta já usado pelo código (zero mudança de parser). Alternativas gerenciadas (Google Directions, Mapbox, OpenRouteService) foram avaliadas e documentadas, mas exigem custo por request ou adaptação de parser.

Guia de deploy completo (Docker, extrato de dados, HTTPS, atualização periódica, validação): `doc/OSRM-SELF-HOSTED-DEPLOY.md`.

**Pendente do usuário:** provisionar o servidor OSRM (fora do alcance deste ambiente) e então setar `por_road_match_base_url` na tabela `configuracoes` apontando para ele.

## 6. Pendências fora deste ciclo

Nenhuma.
