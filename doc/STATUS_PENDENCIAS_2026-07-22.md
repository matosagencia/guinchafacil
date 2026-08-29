# GuinchaFácil — Status de Pendências (auditoria real de código, 2026-07-22)

> Este documento é a fonte de verdade **desta rodada de trabalho**. Ele substitui a necessidade de reavaliar a "Constituição Mestra" (doc de planejamento) contra o código a cada correção — aqui só entra o que foi **verificado lendo o código** ou **implementado agora**.
> Atualizar este arquivo a cada task concluída, antes de marcar a task como completed.

## Legenda
- ✅ AUDITADO-OK: já implementado e testado no código, nenhuma ação necessária.
- 🔧 EM ANDAMENTO: gap real confirmado, sendo implementado nesta rodada.
- ⏳ PENDENTE: gap real confirmado, ainda não iniciado.

---

## Itens auditados como já prontos (não mexer, salvo regressão)

| Item | Status | Onde |
|---|---|---|
| PIX-GUARD (pedido só avança com pagamento aprovado, idempotente) | ✅ AUDITADO-OK | `WebhookController`, `PagamentoAprovacaoService`, `PedidoTransitionService::approvePayment()` (SELECT FOR UPDATE + transação). Testes: `PaymentWebhookIdempotencyTest`, `PedidoTransitionServiceTest`, `WebhookControllerTest`, `WebhookSignatureTest`. |
| Estorno automático ligado ao cancelamento | ✅ AUDITADO-OK | `EstornoService::estornar()` chamado por `CancelamentoService.php:218` e `DemandaService.php:357`. Claim atômico via SELECT FOR UPDATE + estado transitório `estornando`. |
| Governança do `.env` pelo admin | ✅ AUDITADO-OK | `AdminController::envGovernanca/envSalvar/envAuditoria`, escrita atômica (tmp+rename), mascaramento, auditoria em `env_auditoria`. E2E: `qa/suites/governanca-env-gateway.spec.ts` (E2E-GOV-E1). |
| Escape de HTML em emails | ✅ AUDITADO-OK | `NotificacaoService::e()` (htmlspecialchars) usado em todos os templates. |
| Seed `comissao_plataforma` (era `comissao_percentual`) | ✅ AUDITADO-OK | `install/migrate.php` fase 5 (seed) + fase 6 (self-heal de formato antigo). `install/guinchafacil.sql` está órfão/vazio — não é a fonte real, ignorar. |
| Link no email de cancelamento | ✅ AUDITADO-OK | `NotificacaoService::pedidoCancelado()` → `/cliente/pedido/novo`, rota existe em `index.php:227`. |
| Simulador oficial ponta a ponta | ✅ AUDITADO-OK | `SimulationService::run()` fases 1-11, acionável em `/admin/simulador`. Gap menor: reusa cliente existente em vez de cadastrar novo — coberto por `qa/suites/cadastro-cliente-bulk.spec.ts` separadamente. Não é bloqueador. |
| Admin Health navegável | ✅ AUDITADO-OK (estrutura) | `/admin/health`, 19 domínios, `productionChecklist()`. **Domínios carteira/saques ficam vermelhos até a wallet ser implementada (ver abaixo) — não é bug do Health.** |
| Padrão de logs | ✅ AUDITADO-OK (é o padrão a seguir) | `Logger::log($level, $classe, $funcao, $sistema, $mensagem, $contexto)` grava JSONL (`logs/app-YYYY-MM-DD.jsonl`) + tabela `app_logs`, com redaction automático. `Logger::exception()` para catch blocks. **Usar este padrão em todo código novo.** |
| Idempotência geral | ✅ AUDITADO-OK (padrão ad-hoc consistente) | 3 implementações independentes seguindo o mesmo desenho: coluna `idempotency_key`/chave de negócio + `UNIQUE` no schema + `INSERT`/`SELECT FOR UPDATE` em transação + catch de duplicate key. Seguir este padrão em código novo. |

---

## ⚠️ DECISÃO DE ESCOPO (22/07/2026): carteira financeira do guincheiro NÃO será implementada

Confirmado pelo usuário nesta sessão — e já registrado anteriormente em `doc/CONSTITUICAO_ATUALIZACAO_SECAO6_2026-07-18.md` (linhas 42/45/46, decisão de 20/07): o modelo de carteira com estados em_compensação/liberado + saque manual está **fora de escopo**, superado pelo repasse Pix automático já existente (`PaymentJobService::enqueuePixPayout()`, disparado ao concluir o pedido).

**No lugar da carteira**, a resolução de falha de pagamento/repasse é feita por **admin ou pelos gerentes**, via sistema de Demanda já implementado:
- `DemandaService::criar()` (tipo `'pagamento'`) — funcionário ou admin abre a demanda referenciando o `payment_job_id` a reprocessar.
- `DemandaService::decidir()` — só gerente (ou admin) decide; um gerente nunca decide a própria demanda; dupla aprovação de **dois gerentes distintos** é exigida quando o valor envolvido ultrapassa `demanda_valor_dupla_aprovacao` (padrão R$500) — mesmo mecanismo usado para `alteracao_dados`.
- `DemandaService::executar()` (tipo `'pagamento'`) → `PaymentJobService::forceRetry($jobId, $gerenteExecutor)` — reenfileira o job de repasse Pix, com auditoria (`AuditTrailService`), idempotência (`hash_idempotencia` + `UNIQUE`) e logs padronizados.
- Admin também tem via direta e mais rápida: `AdminController::pixReprocessar()` → `PixService::reprocessar()`, sem passar pela demanda (ação de admin não precisa de segunda aprovação).

Isso já cobre exatamente o requisito "pagamento manual em caso de falha, realizado pelo admin ou pelos 2 gerentes" — não havia necessidade de código novo aqui, só corrigir o Admin Health, que ficava permanentemente vermelho verificando tabelas de uma carteira que nunca vai existir (ver item 1 abaixo).

---

## Gaps reais corrigidos nesta rodada

### 1. ✅ RESOLVIDO — Admin Health cobrando tabelas de carteira inexistente
**Arquivo:** `src/Services/HealthService.php` (`checkCarteira()`, `checkSaques()`).
**Antes:** fazia `SELECT COUNT(*) FROM guincheiro_carteira/guincheiro_movimentos/pagamento_liquidacoes/saques_guincheiro/saque_eventos` e retornava `fail()` (nível "erro") quando as tabelas não existiam — o que é sempre, já que a carteira foi suprimida. Domínio ficava permanentemente vermelho no `/admin/health` por uma decisão de escopo, não um bug real.
**Depois:** os dois métodos retornam `ok()` com status `'suprimido'`, explicando a decisão e apontando para o fluxo de Demanda que resolve o caso de uso real. Comentário `§ESCOPO-CARTEIRA-01` documenta a decisão inline com link pro doc de constituição.

### 2. ✅ RESOLVIDO — RateLimiter: race condition (era task #6)
**Arquivo:** `src/Services/RateLimiter.php`.
**Antes:** `checkLimit()`/`recordAttempt()` faziam SELECT seguido de INSERT/UPDATE sem atomicidade. Duas requisições concorrentes sem registro prévio podiam ambas tentar INSERT; a segunda gerava `PDOException` de duplicate key só logada via `error_log()` e descartada — perda silenciosa de contagem, furando rate-limit em rajada.
**Depois:** `recordAttempt()` usa `INSERT ... ON DUPLICATE KEY UPDATE tentativas = tentativas + 1` (atômico, via `UNIQUE KEY uk_ip_rota`), seguido de um `UPDATE` condicional guardado por `WHERE tentativas >= ? AND (bloqueado_ate IS NULL OR bloqueado_ate < NOW())` para setar o bloqueio — idempotente, sem round-trip de leitura. `checkLimit()` teve o reset de janela guardado por `primeira_tentativa` (idempotente sob concorrência). `error_log()` trocado por `Logger::exception`/`Logger::log` com padrão `RateLimiter / checkLimit|recordAttempt / rate_limit`.
**Comentário no código:** `§RATE-ATOMIC-01`.

### 3. ✅ RESOLVIDO — EstornoService: logs inconsistentes (era task #13)
**Arquivo:** `src/Services/EstornoService.php`.
**Antes:** 4 chamadas `error_log('[EstornoService::estornar][...] ...')` cruas, fora do padrão JSONL + `app_logs` usado no resto do projeto.
**Depois:** todas trocadas por `Logger::exception`/`Logger::log(LEVEL_INFO|WARN, 'EstornoService', 'estornar', 'pagamento', ...)`, mesmo contexto informativo preservado (pedido_id, pagamento_id, fase, erro).

### 4. ✅ RESOLVIDO — Centralização da leitura do gateway ativo (era task #14/#5)
**Arquivos:** `src/Services/Payment/PaymentProviderFactory.php`, `src/Services/Payment/GatewayRotationService.php`, `src/Controllers/PagamentoController.php`.
**Antes:** 3 leituras independentes de `PAYMENT_GATEWAY_ACTIVE` (`PaymentProviderFactory::ativo()`, `GatewayRotationService::gatewayConfigurado()`, `PagamentoController::gatewayAtivo()`), funcionalmente idênticas mas sem fonte única.
**Depois:** `PaymentProviderFactory::gatewayAtivoRaw(): string` é agora o único ponto que lê a constante; os outros dois métodos delegam para ele. Comentário `§GATEWAY-CENTRAL-01`. Leituras diretas remanescentes (`configuracoes.php` view, `HealthService::checkGateway()`) são só exibição/diagnóstico, não decisão de roteamento — não precisam centralizar.

---

## Itens já confirmados prontos sem qualquer ação (reafirmado 22/07)
Pagamento manual em falha via admin/gerentes, PIX-GUARD, estorno automático, governança do `.env`, escape de HTML, seed de comissão, link de cancelamento, simulador oficial — ver tabela no topo deste documento.

---

## Item novo (pedido do usuário 22/07): chave seletora liga/desliga o log do sistema

**Pedido:** um seletor na tela de configuração para ligar/desligar o LOG do sistema. Manter ligado na fase de DEV; em produção, ligar só em janelas de manutenção periódica.

**Implementado:**
- `SYSTEM_LOG_ENABLED` (true/false) — nova constante em `config.php`, lida via `env()`, fallback `'true'` (dev).
- `Logger::enabled()` + guarda no início de `Logger::event()` (`src/Services/Logger.php`) — kill switch total: quando desligado, nenhum evento é gravado (nem `logs/app-*.jsonl`, nem tabela `app_logs`), qualquer nível, sem exceção de severidade — é a decisão de negócio pedida, não um filtro.
- Editável em `/admin/env` (governança do `.env`, já auditada e com escrita atômica): novo grupo "Log do Sistema" em `AdminController::envSalvar()`/`updateEnvValues()` e na view `src/Views/admin/env_governanca.php` (renderizado como dropdown true/false, com nota explicativa da recomendação DEV-ligado/produção-desligado-exceto-manutenção).
- `SYSTEM_LOG_ENABLED=true` já setado no `.env.local` externo ativo (`guinchafacil-secrets/.env.local`) — log permanece ligado agora, na fase de dev.
- `.env.example` (template) documentado com `SYSTEM_LOG_ENABLED=false`, refletindo o padrão recomendado para produção.
- Comentário `§LOG-TOGGLE-01` no código nos dois pontos-chave (`config.php`, `Logger.php`).

**Nota:** o toggle passa a valer no próximo request sem precisar reiniciar o Apache — `ConfigSecurityService::loadEnvFile()` já foi corrigido (20/07) para sempre sobrescrever via `putenv()` a cada request, então não há o problema de staleness sob mod_php que afetava outras chaves antes dessa correção.

---

## Log de implementação

- **22/07/2026** — Auditoria completa de código real (subagent) contra a lista de pendências da constituição antiga; maioria já resolvida. Gaps reais identificados: Admin Health carteira/saques, RateLimiter race condition, EstornoService logs, centralização do gateway.
- **22/07/2026** — Usuário decidiu: carteira financeira NÃO será implementada; solução é pagamento manual por admin/gerentes — confirmado já existente via `DemandaService` (tipo `pagamento`) + `PaymentJobService::forceRetry()`.
- **22/07/2026** — Corrigidos: `HealthService::checkCarteira/checkSaques`, `RateLimiter::checkLimit/recordAttempt`, `EstornoService::estornar` (logs), `PaymentProviderFactory::gatewayAtivoRaw` + delegação em `GatewayRotationService`/`PagamentoController`. Sem interpretador PHP disponível no sandbox para `php -l`; revisão manual linha a linha de cada trecho editado feita antes de fechar cada item.
