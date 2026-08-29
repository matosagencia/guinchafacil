# Atualização da Constituição Mestra — Seção 6 (Matriz-mãe de status)

**Para:** colar nas instruções do projeto GuinchaFácil (Claude.ai), substituindo a Seção 6.2 atual.
**Motivo:** auditoria de código real feita em 18/07/2026 encontrou a matriz bastante desatualizada — vários itens marcados PENDENTE ou NOVO REQUISITO já estavam implementados há tempos, e alguns itens marcados PRONTO/PARCIAL tinham bugs reais nunca documentados. Cada linha abaixo tem evidência de arquivo/código, não suposição.

Mantenha a Seção 6.1 (legenda) como está. Substitua só a tabela 6.2.

---

## 6.2 Mapa consolidado de status (atualizado 18/07/2026)

| Bloco | Estado | Observação executiva |
|---|---|---|
| Fluxo Pix real ao concluir serviço | PRONTO | Confirmado em sessão anterior |
| Reprocessamento manual do Pix pelo admin | PRONTO | Confirmado em sessão anterior |
| Botão de reprocessamento no detalhe do pedido | PRONTO | Confirmado em sessão anterior |
| Notificação de conclusão com valor real do repasse | PRONTO | Confirmado em sessão anterior |
| Locks em crons | PRONTO | — |
| Transação atômica no webhook | PRONTO | — |
| Parsing PagSeguro com SimpleXML | PRONTO | — |
| Retentativas Pix com colunas auxiliares | PRONTO/PARCIAL | Não reverificado em 18/07 |
| Reset de senha usando serviço central | PRONTO/PARCIAL | Não reverificado em 18/07 |
| Headers e SameSite | PRONTO/PARCIAL | Não reverificado em 18/07 |
| Filtro de paginação por busca | PRONTO | — |
| Avaliação com janela e reputação | PRONTO/PARCIAL | Não reverificado em 18/07 |
| Limpeza de logs (cron) | **PRONTO** | Script já existia; agendamento no Windows Task Scheduler criado em 18/07 (`schtasks`, confirmado ÊXITO) |
| Testes automatizados do Pix | **PRONTO (06/08/2026)** | Doc estava desatualizado — a suíte já existia e passa 100%: `tests/Unit/PixServiceTest.php` (24 métodos), `WebhookControllerTest.php`, `WebhookSignatureTest.php`, `MercadoPagoProviderPixTest.php`, `tests/Integration/PaymentWebhookIdempotencyTest.php`, `PixGuardTest.php`, `PaymentJobServiceTest.php`, `PaymentJobDeadLetterTest.php`, `PayoutLedgerConsistencyTest.php`, `SplitRepasseConclusaoTest.php`. Execução real em 06/08/2026 (filtro Pix/pagamentos): **24 testes, 46 asserções, todos passaram.** Gap cobrido na mesma data: `tests/Integration/ExpiracaoPedidosServiceTest.php`, cobrindo o cancelamento+estorno automático por timeout de 30 min (`ExpiracaoPedidosService`, extraído de `tools/cron_cancelar_pedidos_expirados.php` em 05/08/2026) que ainda não tinha teste dedicado. Regra geral: não confiar em rótulo "PENDENTE" sem rodar o que já existe — este item e "Estorno automático" (linha acima) foram os dois casos encontrados nesta sessão de documentação dizendo pendente sobre algo já implementado. |
| Estorno automático | **PRONTO** | `EstornoService.php` implementado e integrado a `CancelamentoService`/`PedidoTransitionService`, com suporte a estorno parcial (Mercado Pago `amount`, PagSeguro `refundValue`) |
| Admin Health real navegável | **PRONTO** | `HealthService.php` + rota `/admin/health` |
| Simulador oficial ponta a ponta | **PRONTO** | `SimulationService.php` + rota `/admin/simulador`, models `SimulationRun/Step/Artifact` |
| Cobertura completa do simulador | PENDENTE | Não reverificado em 18/07 |
| Suíte de testes executável no pacote | **PARCIAL → melhor** | 24+ specs em `qa/suites/`; suíte funcionário/gerente criada em 18/07 (13 testes, verde); gate completo rodado em 18/07: 53 passed, 0 failed, 14 skipped (specs opt-in que exigem env vars extras) |
| PIX-GUARD sem pagamento aprovado | **PRONTO** | Confirmado ativo em logs (`PIX-GUARD-03`) |
| Rate-limit com UNIQUE e chave canônica | **PRONTO (20/07)** | `UNIQUE KEY uk_ip_rota` já existia (`migration_v3.sql`). Chave canônica era o gap real: `RateLimiter`/`AuthService`/`DemandaService`/`Logger` confiavam direto em `X-Forwarded-For` (forjável pelo cliente, permitia furar rate-limit de login/pagamento trocando o header a cada tentativa). Corrigido com `RequestIpResolver`: usa `REMOTE_ADDR` (não forjável) por padrão; só considera `X-Forwarded-For` se a conexão vier de IP listado em `TRUSTED_PROXIES` (vazio por padrão neste deploy sem reverse proxy). |
| Link de fotos de prova de serviço no admin | **PRONTO (20/07)** | Gap novo encontrado: `admin/pedidodetalhe.php` linkava direto pra `/public/uploads/{foto}`, caminho morto desde que `EvidenceService` passou a salvar evidências em `storage/private/evidencias` (fora do webroot). Corrigido pra usar a rota autenticada `/evidencia/{id}` via `PedidoEvidencia::buscarUltimaPorTipo()`. |
| Política única de senha 8+ | **PRONTO** | Backend sempre exigiu 8+ em todos os pontos; 4 telas com `minlength="6"` divergente corrigidas em 18/07 (`guincho/perfil.php`, `admin/usuarioform.php`, `admin/usuarioedit.php`, `admin/guinchoform.php`) |
| Download autenticado de documentos | **PRONTO** | Gap real corrigido em 18/07: CNH/foto do veículo do guincheiro ficavam em `public/uploads/` (dentro do webroot, ignorando toda autenticação); movidos para `UPLOAD_PATH_DOCS` fora do webroot, mesmo padrão de `storage/private/evidencias/`. Duas telas admin que linkavam direto pra `/uploads/...` corrigidas para `/arquivo/{id}?tipo=...` |
| Escape de HTML em emails | **PRONTO** | Todo dado dinâmico passa por `NotificacaoService::e()` (`htmlspecialchars`) antes do template — confirmado em 18/07 |
| Geocoding unificado com cache como caminho real | **PRONTO** | Gap real corrigido em 18/07: só `reverseGeocode()` tinha cache persistente (tabela `geocoding_cache`); `geocode()` (forward) e `buscarCep()` só tinham cache em memória (não sobrevivia entre requisições). Schema já previa os tipos `forward`/`cep`, sem código usando — implementado |
| Seed base sem `comissao_percentual` | **NÃO-ISSUE** | Chave foi renomeada para `comissao_plataforma` há tempos; é o nome usado em todo o código real (5 lugares, todos com fallback seguro `?? 0.15`). `comissao_percentual` só sobrevive em scripts de migração históricos sem efeito prático |
| Link correto no email de cancelamento | PENDENTE | Não reverificado em 18/07 |
| Carteira financeira do guincheiro (estados em_compensação/liberado + saque manual) | **DECISÃO: NÃO IMPLEMENTAR (20/07)** | Reavaliado em sessão de 20/07: o modelo de carteira com saque manual da Constituição foi decidido explicitamente como **superado pelo repasse Pix automático já existente** (`PaymentJobService::enqueuePixPayout()`, disparado ao concluir o pedido). Manter os dois modelos em paralelo duplicaria o repasse e criaria risco financeiro real. As tabelas `pagamento_liquidacoes`/`guincheiro_movimentos`/`guincheiro_carteira`/`saques_guincheiro`/`saque_eventos` continuam existindo no banco (não removidas) mas **não serão conectadas à lógica de negócio** — decisão de escopo, não gap técnico. Ver itens abaixo para o que foi implementado no lugar. |
| Transparência do processamento do gateway no painel financeiro | **PRONTO (20/07)** | Implementado como substituto direto da carteira: `Pagamento::statusGatewayResumo()` decodifica o `webhook_payload` já salvo em `Pagamento::aprovar()` e extrai status/status_detail/forma de pagamento/bandeira do MercadoPago (`payment_type_id`/`payment_method_id`) e do PagSeguro (`paymentMethod.type`/`.brand`). Exibido em `/admin/financeiro` numa coluna dedicada, com ícone de bandeira (Visa/Master/Amex/Pix/Boleto via Font Awesome) e a forma de pagamento do CLIENTE sempre visível — inclusive quando não há payload parseável (fallback pro método/gateway bruto) — e um aviso explícito de que a coluna "Repasse Pix" (guincho) é independente do resultado da cobrança do cliente, pra não confundir "falha no repasse" com "pagamento recusado". |
| Recibo/comprovante de repasse Pix pro guincheiro | **PRONTO (20/07)** | Nova rota `/guincho/pedido/recibo/{id}` (`GuinchoController::recibo()`), acessível só pelo guincho dono do pedido e só depois que `pago_guincho = 1`. Gera uma página imprimível (estilo fatura) com valor bruto, comissão retida, valor líquido, ID da transação Pix e data do repasse — com aviso explícito de que não substitui nota fiscal. Link "Recibo" adicionado na coluna Repasse de `/guincho/financeiro` quando o repasse já caiu. |
| Solicitação/autorização de saque | **SUPRIMIDO (20/07)** | Não será implementado — ver decisão da carteira acima. O guincheiro recebe automaticamente via Pix assim que o pedido conclui; não há fluxo de "pedir pra sacar". |
| Liberação manual de saldo retido pelo admin | **SUPRIMIDO (20/07)** | Não será implementado — mesma decisão. Não existe saldo retido a liberar; o repasse já é automático. |
| Tela admin para editar `.env` | **PRONTO** | Rotas `/admin/env`, `/admin/env/salvar`, `/admin/env/auditoria` + `ConfigSecurityService`. Bug real corrigido em 20/07: `ConfigSecurityService::loadEnvFile()` tinha um guard (`getenv($key) !== false → skip`) que, sob Apache/mod_php (workers persistentes), fazia alterações salvas via `/admin/env` nunca se propagarem pra workers já ativos até reiniciar o Apache — afetava qualquer chave, não só o gateway. Corrigido pra sempre sobrescrever via `putenv()` a cada request. |
| Seletor único de gateway de cobrança | **PRONTO** | `PAYMENT_GATEWAY_ACTIVE` + `PaymentProviderFactory`/`MercadoPagoProvider`/`PagSeguroProvider`. Validado ponta a ponta em 20/07 pela Suite E (ver abaixo). |
| Emails temáticos por perfil | PARCIAL | Não reverificado em 18/07 |
| Protocolo Playwright oficial | **PARCIAL → avançado** | Suítes cobrindo cadastro, pedido, chat, financeiro, admin e funcionário/gerente/demandas. Suite E (governança do `.env` + seletor de gateway) escrita e validada em 20/07: `qa/suites/governanca-env-gateway.spec.ts`, E2E-GOV-E1 e E2E-GOV-E2, ambos passando (13/13 passos) após o fix do bug de staleness do `.env` acima. |
| Rastreamento em tempo real com nome de ruas (`a_caminho`) | **PRONTO** | Contradiz backlog anterior: `RoutingSnapshotService` + `StreetResolutionService` (map-matching, geofence, reverse geocode) implementados; rota guincho→origem desenhada corretamente; teste dedicado `E2E-ORD-002` existe (opt-in, precisa de env vars de credenciais de teste) |
| Cancelamento com taxa proporcional à distância | **PRONTO** | Contradiz backlog anterior: `CancellationCalculationService` já usa fórmula `distance_ratio`/`time_ratio`, não é regra binária. Bloqueio de <2km é regra de negócio válida (atendimento quase concluído), não limitação técnica |
| Tarifa centralizada em `GeoService::calcularCusto()` | **PRONTO** | `TarifaService::calcularDetalhado()` é o único caminho real; nenhuma view reimplementa a fórmula |
| `SseController.php` integrado | **PRONTO** | 3 rotas registradas, usadas em 6 views — não é código morto |
| `.env.local` duplicado dentro do webroot | **PRONTO** | Corrigido em 18/07: cópia interna (`C:\xampp\htdocs\guinchafacil-secrets\`) apagada; sistema usa só a cópia externa, fora do webroot |
| Corrupção recorrente de tabelas MariaDB (`mysql.*`) | **MITIGADO** | Causa raiz identificada em 18/07: instâncias duplicadas de `mysqld.exe` rodando simultaneamente (reinício não-limpo) + possível interferência de antivírus (`Operating system error number 32`). Exclusão de antivírus aplicada; nenhum serviço do Windows duplicando o processo (confirmado via `Get-Service`) |

---

## Nota de processo

12 itens da antiga seção "pendências herdadas da auditoria de 14/07" foram reverificados linha por linha contra o código real em 18/07/2026: **9 já estavam resolvidos** (a documentação estava desatualizada, não o código) e **3 tinham bug real**, corrigidos na mesma sessão (download de documentos — o mais sério, política de senha, cache de geocoding). Recomenda-se que futuras atualizações da Constituição sejam feitas a partir de evidência de código (grep/read direto), não por herança de auditorias anteriores sem reverificação.

**Correção sobre a Fase 2 (financeiro ampliado) — SUPERADA pela decisão de 20/07:** esta nota, escrita antes da sessão de 20/07, tratava a Fase 2 (carteira do guincheiro com saque manual) como "próximo bloco de trabalho real". Isso foi reavaliado e revertido: a carteira com saque manual foi decidida como **fora de escopo**, substituída pelo repasse Pix automático já existente (`PaymentJobService::enqueuePixPayout()`). Ver linha "Carteira financeira do guincheiro" e as duas linhas de saque/liberação manual na tabela acima (todas **SUPRIMIDO/NÃO IMPLEMENTAR, 20/07**) — são a decisão vigente. Esta nota fica registrada só como histórico do raciocínio anterior; não usar como pendência.
