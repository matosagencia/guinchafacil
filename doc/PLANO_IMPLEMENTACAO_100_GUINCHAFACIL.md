# GuinchaFácil — Plano de Implementação em 3 Níveis para Conclusão de 100%

**Versão:** 1.0  
**Data-base:** 14/07/2026  
**Pacote analisado:** `guinchafacil(2).zip`  
**Roadmap visual incorporado:** `ESQUEMA_PAGINAS_GUINCHAFACIL.md`  
**Documentos de referência:** `PLANO_FINALIZACAO_GUINCHAFACIL_POR_PLAYWRIGHT.md`, `GUIA_DESIGN_PAINEIS_GUINCHAFACIL.md`, `ADITIVO_01_AJUSTES_PAINEIS.md`, `ESPECIFICACAO_TECNICA_TELAS_E_COMUNICADOS_GUINCHAFACIL.md`, `RELATORIO_CONSOLIDACAO_CONSTITUCIONAL.md` e auditoria técnica de 14/07/2026.

---

# 1. Objetivo

Concluir o GuinchaFácil como um produto integrado, auditável e escalável, composto por:

1. backoffice PHP;
2. portal responsivo do cliente;
3. portal responsivo do guincho;
4. API estável e versionada;
5. aplicativos Android de cliente e guincho;
6. sistema de pagamento, repasse e estorno;
7. Proof-of-Road e evidências antifraude;
8. Central de Comunicados;
9. painel administrativo operacional;
10. suíte automatizada de testes e observabilidade;
11. pacote jurídico e de governança previsto na constituição do projeto.

O objetivo não é apenas possuir todos os arquivos. **100% significa que cada requisito está implementado, integrado, testado, documentado, observável e aprovado pelos critérios de aceite.**

---

# 2. Ponto de partida e definição de 100%

## 2.1 Estado atual considerado

A auditoria anterior mediu aproximadamente:

| Indicador | Estado atual |
|---|---:|
| Cobertura estrutural web | 79% |
| Integração funcional web | 68% |
| Prontidão de produção | 60% |
| Aderência visual | 39% |
| Central de Comunicados pronta | 28% |
| Android | 0% |
| Pacote jurídico/governança | 0% |

Esses números são a linha de base deste plano.

## 2.2 O que será chamado de 100%

O projeto só poderá ser classificado como concluído quando todos os gates abaixo estiverem verdes:

| Gate | Critério obrigatório |
|---|---|
| G1 — Segurança | nenhum segredo distribuído; uploads privados; CSRF, XSS, sessão e autorização revisados |
| G2 — Banco | instalação limpa e upgrade idempotente aprovados em banco vazio e banco com dados |
| G3 — Pedido | fluxo cliente → pagamento/freeflow → aceite → atendimento → conclusão aprovado |
| G4 — POR | percurso íntegro, idempotente, transacional, com buffer offline e validação antifraude |
| G5 — Evidências | coleta e entrega privadas, nonce de uso único, geofence e transição atômica |
| G6 — Cancelamento | preview versionado, snapshot, confirmação e estorno atômicos |
| G7 — Financeiro | pagamento, split, carteira, saques, jobs e reprocessamento auditados |
| G8 — UX | telas dos três perfis aderentes ao design system, responsivas e acessíveis |
| G9 — Comunicados | publicação, agenda, frequência, imagem responsiva e métricas completas |
| G10 — Admin | Command Center, incidentes, QA, logs, financeiro e governança operacionais |
| G11 — API/Android | API v1 estável e apps Android cliente/guincho aprovados |
| G12 — QA | suites unitárias, integração, E2E e visuais integralmente verdes na mesma execução |
| G13 — Operação | backup, restore, crons, filas, healthcheck, alertas e runbook validados |
| G14 — Jurídico | apólices, aceite contratual, privacidade e decisões estratégicas implementados |

## 2.3 Progressão esperada pelos três níveis

| Nível | Propósito | Prontidão esperada ao final |
|---|---|---:|
| **Nível 1** | Estabilizar fundação, segurança e constituição operacional | 76% |
| **Nível 2** | Completar recursos, painéis e experiência dos três perfis | 92% |
| **Nível 3** | API/Android, governança, certificação e release | 100% |

Os percentuais são gates de maturidade, não soma de quantidade de arquivos.

---

# 3. Decisões de arquitetura antes de iniciar

## 3.1 O roadmap visual será adaptado, não copiado literalmente

O arquivo `ESQUEMA_PAGINAS_GUINCHAFACIL.md` descreve uma aplicação de táxi com cor primária âmbar. No GuinchaFácil:

- verde `#2fb34a` continua sendo a cor primária da marca;
- âmbar `#f59e0b` fica restrito a alerta, avaliação e atenção;
- os status atuais do domínio serão preservados;
- as rotas MVC existentes serão mantidas sempre que possível;
- geocoding e roteamento não serão chamados diretamente de serviços públicos pelo navegador;
- valores, cancelamentos, ETA e status virão do backend como fonte de verdade;
- o layout de táxi será usado como referência de hierarquia, densidade, cards, bottom sheets e navegação mobile.

## 3.2 Status oficiais do pedido

Não substituir os status do GuinchaFácil pelos nomes genéricos do projeto de táxi.

```text
aguardando_pagamento
aguardando_guincho
a_caminho
no_local
em_reboque
concluido
cancelado
```

## 3.3 Rotas oficiais

O novo layout deverá consumir as rotas atuais ou rotas novas explicitamente versionadas. Não criar aliases aleatórios do tipo `?route=corrida/...` dentro das views.

## 3.4 Migração incremental

Cada view será migrada para o novo design system sem reescrita total simultânea:

1. criar componente novo;
2. integrar ao contrato atual;
3. testar a view;
4. aprovar screenshot;
5. remover CSS/JS antigo;
6. avançar para a próxima view.

## 3.5 Regra dos controllers

Controllers atuais muito grandes não devem receber mais responsabilidades:

- `AdminController.php`: 1.827 linhas;
- `AuthController.php`: 1.709 linhas;
- `GuinchoController.php`: 882 linhas;
- `ClienteController.php`: 757 linhas.

Novas funções serão implementadas em controllers especializados, mantendo compatibilidade das rotas durante a transição.

---

# 4. NÍVEL 1 — Estabilização constitucional e segurança

## 4.1 Objetivo do nível

Remover todos os bloqueadores que podem causar corrupção de dados, fraude, vazamento, erro 500 ou resultado E2E inconsistente.

**Gate do Nível 1:** o fluxo web completo deve terminar do pedido à conclusão, com chat, POR, coleta, entrega, cancelamento, pagamento/freeflow e logs, sem erro 500.

---

## 4.2 Pacote L1.1 — Segredos, ambiente e distribuição

### Problemas atuais

- `.env.local` está dentro do pacote distribuído;
- existem arquivos temporários e uploads reais no projeto;
- `qa/node_modules` foi empacotado;
- URLs externas ainda aparecem em CSP e em algumas views;
- arquivos de ambiente não estão separados por deployment.

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `.gitignore` | bloquear `.env*`, exceto `.env.example`; `public/uploads/*`; `qa/node_modules`; traces; screenshots de QA; arquivos `.tmp` |
| `.env.example` | documentar todas as chaves sem valores reais; separar DB, pagamentos, SMTP, storage, QA e Android API |
| `config.php` | validar configuração obrigatória por ambiente; impedir produção com placeholders; expor `APP_ENV`, `APP_URL`, `STORAGE_PATH` e `PUBLIC_ASSET_URL` |
| `index.php` | mover CSP para serviço/configuração por ambiente; adicionar `Referrer-Policy`, `Permissions-Policy` e `X-Content-Type-Options` |
| `public/uploads/.htaccess` | bloquear execução PHP e listagem de diretório enquanto uploads legados forem migrados |
| `files/README_CONSOLIDACAO.txt` | atualizar regras de empacotamento e dados que não podem entrar no release |

### Arquivos a criar

```text
config/
  app.php
  security.php
  storage.php
  services.php

tools/
  release/package_release.php
  security/check_secrets.php
  security/check_public_uploads.php

doc/
  RUNBOOK_SEGREDOS_E_AMBIENTES.md
```

### Comportamento obrigatório

- produção não inicia se qualquer token estiver vazio, for placeholder ou usar credencial de sandbox indevida;
- `APP_DEBUG=false` obrigatório em produção;
- uploads não podem ser executáveis;
- pacote de release contém apenas dependências de produção;
- qualquer falha de configuração registra classe, função, arquivo, fase e código.

### Logs

| Código | Origem | Fase |
|---|---|---|
| `CFG-ENV-001` | `ConfigValidator::validate` | leitura do ambiente |
| `CFG-SEC-002` | `ConfigValidator::assertProductionSafe` | bloqueio de produção |
| `REL-PKG-001` | `ReleasePackager::build` | empacotamento |

### Testes

```text
tests/Unit/ConfigValidationTest.php
tests/Integration/ReleasePackageSafetyTest.php
```

### Critério de aceite

- nenhum segredo no ZIP final;
- scanner retorna zero ocorrência de chaves reais;
- release bloqueia `.env.local`, uploads, traces e `node_modules`.

---

## 4.3 Pacote L1.2 — Banco, migrations e instalador idempotente

### Problemas atuais

- runners de migration antigos e novos coexistem;
- existe histórico de `catch` duplicado;
- há índice declarado mais de uma vez;
- migrations dependem excessivamente do registro de versão;
- não existe teste automatizado de instalação limpa + upgrade completo.

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `install/run_all_migrations.php` | tornar runner oficial único; lock; transaction quando suportada; checksum; log detalhado |
| `install/migration_runtime.php` | centralizar helpers `tableExists`, `columnExists`, `indexExists`, `foreignKeyExists` |
| `install/run.php` | delegar ao runner oficial; remover lógica duplicada |
| `install/migrate.php` | transformar em wrapper de compatibilidade ou arquivar |
| `database/run_migration.php` | transformar em wrapper CLI do runner oficial |
| migrations existentes | corrigir duplicidade, adicionar guards e rollback lógico documentado |
| `install/guinchafacil.sql` | gerar snapshot coerente após todas as migrations aprovadas |

### Arquivos a criar

```text
src/Services/Database/
  MigrationRunnerService.php
  SchemaInspectorService.php
  MigrationLockService.php

install/
  migration_release_hardening_v1.sql
  verify_schema.php

tests/Integration/
  CleanInstallTest.php
  UpgradeAllMigrationsTest.php
  MigrationIdempotencyTest.php
```

### Alterações de schema deste nível

```text
schema_migrations
  + checksum_sha256
  + execution_ms
  + executed_by
  + status
  + error_message
```

### Critério de aceite

1. banco vazio → todas as migrations executam;
2. segunda execução → zero alteração destrutiva;
3. banco de cópia de produção → upgrade completo;
4. falha em migration → log e estado consistente;
5. `verify_schema.php` aprova tabelas, colunas, índices e FKs.

---

## 4.4 Pacote L1.3 — Máquina de estados e concorrência

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `src/Services/Pedido/PedidoStateMachine.php` | retornar `noop` explícito quando origem=destino; definir pré-condições por etapa |
| `src/Services/Pedido/PedidoTransitionService.php` | centralizar transação, lock, ator, auditoria e side effects |
| `src/DTO/PedidoTransitionRequest.php` | incluir `request_id`, `idempotency_key`, `evidence_id`, `routing_snapshot_id` |
| `src/DTO/PedidoTransitionResult.php` | incluir `changed`, `noop`, `previous_status`, `current_status`, `error_code` |
| `src/Models/Pedido.php` | restringir métodos diretos de status/atribuição; marcar APIs legadas como privadas/deprecadas |
| `src/Controllers/ClienteController.php` | delegar cancelamento e criação ao serviço especializado |
| `src/Controllers/GuinchoController.php` | delegar aceite, recusa e avanço ao serviço especializado |
| `src/Controllers/AdminController.php` | delegar atribuição e mudança de status |

### Arquivos a criar

```text
src/Services/Pedido/
  PedidoAcceptanceService.php
  PedidoIdempotencyService.php
  PedidoPreconditionService.php

src/Models/
  PedidoIdempotency.php

install/
  migration_pedido_idempotency_v1.sql

tests/Integration/
  PedidoAcceptanceRaceTest.php
  PedidoTransitionNoopTest.php
  PedidoTransitionIdempotencyTest.php
```

### Schema proposto

```text
pedido_idempotency
  id
  idempotency_key UNIQUE
  pedido_id
  actor_type
  actor_id
  operation
  response_json
  created_at
  expires_at
```

### Critério de aceite

- dois guinchos disputando o mesmo pedido: apenas um recebe sucesso;
- repetição da mesma requisição retorna o mesmo resultado;
- nenhuma transição ocorre fora de `PedidoTransitionService`;
- mudança de etapa, auditoria e side effects são atômicos.

---

## 4.5 Pacote L1.4 — Proof-of-Road transacional, íntegro e offline

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `src/Services/POR/ProofOfRoadService.php` | transação única para ponto + resumo + hash; idempotência; retorno normalizado |
| `src/Services/POR/LocationValidationService.php` | centralizar thresholds por configuração |
| `src/Services/POR/DistanceAccumulatorService.php` | precisão determinística e proteção contra duplicidade |
| `src/Services/POR/MapMatchingService.php` | separar validação geométrica de map matching real/fallback |
| `src/Services/POR/RoutingSnapshotService.php` | recalcular rota restante e ETA por etapa; cache e timeout |
| `src/Services/POR/StreetResolutionService.php` | aplicar cache e limite de frequência |
| `src/Models/PedidoLocalizacao.php` | paginação incremental e consulta por `since_point_id` |
| `src/Models/PedidoPercursoResumo.php` | armazenar versão, qualidade e verificação de hash |
| `src/Controllers/GuinchoController.php` | receber lote offline e retornar itens aceitos/rejeitados individualmente |
| `src/Controllers/SseController.php` | publicar status de qualidade do percurso sem excesso de payload |

### Arquivos a criar

```text
src/Services/POR/
  ProofIntegrityService.php
  OfflineLocationBatchService.php
  PorThresholdService.php
  RoutingProviderInterface.php
  OsrmRoutingProvider.php

src/DTO/POR/
  LocationPointInput.php
  LocationPointResult.php
  LocationBatchResult.php

install/
  migration_por_integrity_v2.sql

tests/Unit/
  PorThresholdServiceTest.php
  ProofIntegrityServiceTest.php

tests/Integration/
  ProofOfRoadTransactionTest.php
  ProofOfRoadOfflineBatchTest.php
  ProofOfRoadReplayTest.php
  ProofOfRoadTeleportTest.php
```

### Novos campos sugeridos

```text
pedido_localizacoes
  + ingest_request_id
  + device_point_id
  + hash_previous
  + hash_current
  + validation_version
  + rejection_code

pedido_percurso_resumos
  + integrity_status
  + integrity_checked_at
  + valid_points_count
  + rejected_points_count
  + last_valid_point_at
  + quality_score
  + quality_version
```

### JS a criar neste nível

```text
public/assets/js/tow/proof-of-road-tracker.js
public/assets/js/core/offline-queue.js
```

### Requisitos do tracker

- `watchPosition` com `enableHighAccuracy`;
- buffer em IndexedDB quando offline;
- UUID por ponto no dispositivo;
- envio em lote;
- backoff exponencial;
- remoção somente dos pontos confirmados pelo servidor;
- pausa quando atendimento não estiver ativo;
- indicador de GPS, precisão, último envio e pendências.

### Critério de aceite

- nenhum ponto se perde em interrupção de rede simulada;
- replay é idempotente;
- teleporte e velocidade incompatível são rejeitados;
- hash integral pode ser revalidado;
- API suporta `since_point_id`;
- ETA acompanha guincho→origem e depois guincho→destino.

---

## 4.6 Pacote L1.5 — Evidência privada, nonce único e transição atômica

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `src/Services/Evidence/EvidenceService.php` | separar validação, storage, persistência e transição; consumir nonce; idempotência por hash |
| `src/Models/PedidoEvidencia.php` | status da evidência, storage key, auditoria, hash e versão |
| `src/Controllers/ArquivoController.php` | servir apenas usuário autorizado; `Content-Disposition`; cache privado; proteção contra path traversal |
| `src/Services/Pedido/PedidoTransitionService.php` | exigir `evidence_id` aprovado, não nome de arquivo |
| `src/Views/guincho/atendimento.php` | exibir validações e falhas por etapa |
| `public/assets/js/atendimento-status.js` | upload com idempotency key, progresso e erro estruturado |

### Arquivos a criar

```text
src/Services/Evidence/
  EvidenceStorageService.php
  EvidenceImageProcessor.php
  EvidenceNonceService.php
  EvidenceAuthorizationService.php
  EvidenceTransactionService.php

src/Models/
  EvidenceNonce.php

storage/private/evidencias/
  .gitkeep

install/
  migration_evidence_private_v2.sql

tests/Unit/
  EvidenceImageProcessorTest.php
  EvidenceNonceSingleUseTest.php

tests/Integration/
  EvidencePrivateDeliveryTest.php
  EvidenceTransitionAtomicityTest.php
  EvidenceIdempotencyTest.php
```

### Regras de imagem

- JPEG, PNG e WebP;
- máximo 5 MB de entrada;
- dimensão máxima configurável, por exemplo 4096×4096;
- decodificar e regravar com GD/Imagick;
- remover EXIF desnecessário;
- gerar miniatura administrativa;
- arquivo salvo fora de `public/`;
- acesso apenas por `/arquivo/{id}` com autorização.

### Fluxo atômico

```text
validar nonce
→ validar pedido/ator/fase
→ validar GPS/geofence
→ validar e reprocessar imagem
→ abrir transação
→ registrar evidência pendente
→ validar transição
→ marcar evidência aceita
→ executar transição
→ commit
→ mover arquivo temporário para storage definitivo
```

Em falha após o arquivo temporário, executar limpeza compensatória.

### Critério de aceite

- nonce reutilizado retorna conflito controlado;
- upload repetido retorna a mesma evidência;
- arquivo não é acessível por URL pública direta;
- falha de transição não deixa registro/arquivo órfão;
- entrega final não gera HTTP 500.

---

## 4.7 Pacote L1.6 — Cancelamento com snapshot e versão

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `src/Services/Cancelamento/CancelamentoCalculationService.php` | resultado determinístico com versão, fatores e qualidade do tracking |
| `src/Services/CancelamentoService.php` | preview persistido, confirmação por snapshot/hash, transação com estorno e penalidade |
| `src/Models/Pedido.php` | remover cálculos legados duplicados |
| `src/Controllers/ClienteController.php` | exigir `snapshot_id` na confirmação |
| `src/Controllers/GuinchoController.php` | desistência e penalidade atômicas |
| `src/Controllers/AdminController.php` | cancelamento administrativo auditado |
| `src/Views/cliente/pedidostatus.php` | modal com valor, regra, versão e validade do preview |
| `src/Views/guincho/atendimento.php` | preview da penalidade antes da desistência |

### Arquivos a criar

```text
src/Models/
  CancellationSnapshot.php

src/DTO/Cancelamento/
  CancellationPreview.php
  CancellationConfirmation.php

install/
  migration_cancel_snapshot_v2.sql

tests/Unit/
  CancellationFormulaVersionTest.php

tests/Integration/
  CancellationSnapshotTest.php
  CancellationRaceTest.php
  CancellationRefundAtomicityTest.php
```

### Campos mínimos

```text
cancelamento_snapshots
  id
  pedido_id
  actor_type
  actor_id
  formula_version
  factors_json
  por_quality
  fee_amount
  refund_amount
  penalty_amount
  snapshot_hash
  expires_at
  confirmed_at
  created_at
```

### Critério de aceite

- confirmação usa o mesmo snapshot visualizado;
- snapshot expirado exige novo preview;
- tracking insuficiente não gera cobrança automática silenciosa;
- cancelamento, estorno, penalidade e auditoria são atômicos.

---

## 4.8 Pacote L1.7 — Pagamento, repasse e jobs

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `src/Controllers/PagamentoController.php` | separar orquestração de providers; idempotency key; resposta consistente |
| `src/Controllers/WebhookController.php` | normalizar assinatura, evento duplicado e transação |
| `src/Models/Pagamento.php` | reduzir responsabilidade; separar queries financeiras |
| `src/Services/PaymentJobService.php` | lock, retry policy, dead-letter e observabilidade |
| `src/Services/PixService.php` | contrato de provider; timeout/retry seguros |
| `src/Services/EstornoService.php` | idempotência e auditoria |
| `src/Workers/PixPayoutWorker.php` | heartbeat, lock e shutdown seguro |
| `tools/cron_reprocessar_pix.php` | chamar worker oficial e retornar exit code adequado |
| `src/Views/admin/financeiro.php` | fila, falhas e reprocessamento explícitos |

### Arquivos a criar

```text
src/Services/Payment/
  PaymentProviderInterface.php
  MercadoPagoProvider.php
  PagSeguroProvider.php
  PaymentOrchestrator.php
  PaymentIdempotencyService.php
  PayoutLedgerService.php

src/Models/Finance/
  PaymentEvent.php
  PayoutLedgerEntry.php

install/
  migration_payment_ledger_v2.sql

tests/Integration/
  PaymentWebhookIdempotencyTest.php
  PaymentJobDeadLetterTest.php
  PayoutLedgerConsistencyTest.php
```

### Critério de aceite

- webhook duplicado não duplica pagamento;
- freeflow não exibe etapa de cobrança;
- produção exige pagamento aprovado antes do dispatch;
- split e carteira fecham contabilmente;
- jobs falhos podem ser reprocessados pelo admin com trilha completa.

---

## 4.9 Pacote L1.8 — Correções objetivas de HTML, rotas e JavaScript

### Correções obrigatórias

1. remover BOM de views;
2. converter todos os arquivos para UTF-8 sem BOM;
3. corrigir mojibake;
4. remover literal `` `r`n ``;
5. eliminar IDs duplicados;
6. remover texto técnico da interface;
7. corrigir o fluxo do campo rápido;
8. remover `innerHTML` com conteúdo vindo da API;
9. eliminar catches vazios;
10. carregar Leaflet local, não por CDN.

### Arquivos a alterar

```text
src/Views/cliente/dashboard.php
src/Views/cliente/pedidonovo.php
src/Views/cliente/pedidostatus.php
src/Views/guincho/dashboard.php
src/Views/guincho/pedidoaceitar.php
src/Views/guincho/atendimento.php
src/Views/admin/dashboard.php
src/Views/layouts/header.php
src/Views/layouts/footer.php
public/assets/js/quick-rescue.js
public/assets/js/cliente-pedido.js
public/assets/js/atendimento-status.js
public/assets/js/app.js
index.php
```

### Campo rápido — contrato final deste nível

```text
POST /cliente/pedido/rascunho
```

Payload:

```json
{
  "endereco_origem": "...",
  "lat_origem": -22.0,
  "lng_origem": -43.0,
  "source": "gps|autocomplete"
}
```

Resposta AJAX:

```json
{
  "ok": true,
  "draft_id": "...",
  "redirect": "/cliente/pedido/novo"
}
```

### Arquivos a criar

```text
public/assets/js/core/api-client.js
public/assets/js/core/logger.js
public/assets/js/core/dom.js
public/assets/js/client/quick-rescue-controller.js

tests/Integration/QuickRescueDraftTest.php
qa/suites/quick-rescue.spec.ts
```

### Critério de aceite

- digitar endereço executa geocode via backend;
- GPS executa reverse geocode via backend;
- formulário nunca troca action por string replace;
- rascunho abre pedido novo com origem preenchida;
- nenhum ID duplicado nas páginas auditadas.

---

## 4.10 Pacote L1.9 — Sessão, SSE, polling e chat

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `public/assets/js/session-manager.js` | tornar única fonte de tratamento de 401/419; retorno à rota atual |
| `public/assets/js/app.js` | usar `ApiClient`; remover wrappers concorrentes |
| `src/Controllers/SseController.php` | heartbeat, event id, timeout, retry e autorização por recurso |
| `src/Services/ChatService.php` | validação, paginação e idempotência |
| `src/Services/NotificacaoService.php` | desacoplar notificações de transporte específico |
| `src/Controllers/ClienteController.php` | respostas JSON padronizadas |
| `src/Controllers/GuinchoController.php` | respostas JSON padronizadas |
| `public/assets/js/atendimento-status.js` | reconexão com backoff; abort ao sair; estado visível |

### Arquivos a criar

```text
public/assets/js/core/event-stream.js
public/assets/js/components/chat-controller.js
src/DTO/ApiResponse.php
qa/suites/chat-bilateral.spec.ts
qa/suites/sse-reconnect.spec.ts
```

### Critério de aceite

- sessão expirada interrompe polling/SSE e abre ação única de login;
- reconexão não cria conexões duplicadas;
- mensagens não são duplicadas;
- chat continua após reconexão;
- erro de API nunca injeta HTML não escapado.

---

## 4.11 Pacote L1.10 — QA portável e baseline único

### Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `src/Services/QA/PlaywrightRunnerService.php` | remover dependência obrigatória de PowerShell/cmd; comandos por plataforma |
| `tools/qa_worker.php` | lock, heartbeat, cancelamento e logs |
| `tools/qa_debug_command.php` | mostrar comando final sanitizado |
| `qa/playwright.config.ts` | projetos desktop/mobile; traces apenas em falha; baseURL configurável |
| `qa/package.json` | scripts `test:core`, `test:visual`, `test:release` |
| `qa/reporters/guinchafacil-reporter.ts` | consolidar códigos de falha e artefatos |
| `src/Views/admin/simulacao.php` | separar simulação de QA |
| `src/Controllers/AdminController.php` | retirar rotas de QA após criação de controller próprio |

### Arquivos a criar

```text
src/Controllers/AdminQaController.php
src/Services/QA/QaRunService.php
src/Services/QA/QaArtifactService.php
src/Views/admin/qa/index.php
src/Views/admin/qa/run.php
src/Views/admin/qa/result.php
public/assets/js/admin/qa-center.js
public/assets/css/pages/admin-qa.css

tools/qa/run.sh
tools/qa/run.ps1
tools/qa/run.php
```

### Suites mínimas do gate Nível 1

```text
qa/suites/smoke.spec.ts
qa/suites/sessao-seguranca.spec.ts
qa/suites/pedido-novo.spec.ts
qa/suites/pagamento-sandbox.spec.ts
qa/suites/concorrencia-aceite.spec.ts
qa/suites/chat-bilateral.spec.ts
qa/suites/por-antifraude.spec.ts
qa/suites/upload-seguranca.spec.ts
qa/suites/cancelamento.spec.ts
qa/suites/atendimento-completo.spec.ts
```

### Gate de saída do Nível 1

- 10/10 suítes core verdes na mesma execução;
- PHP lint integral;
- unitários e integração verdes;
- migration limpa e upgrade verdes;
- nenhum P0 aberto;
- fluxo final não produz HTTP 500.

---

# 5. NÍVEL 2 — Conclusão funcional e reconstrução dos três perfis

## 5.1 Objetivo do nível

Entregar as funcionalidades previstas no roadmap visual, adaptadas ao domínio de guincho, com três identidades:

- **cliente:** branco, leve, acolhedor;
- **guincho:** verde-escuro, operacional;
- **admin:** preto, denso, analítico.

**Gate do Nível 2:** todos os fluxos web e todas as páginas definidas para o release web devem estar implementados, responsivos e com testes visuais aprovados.

---

## 5.2 Pacote L2.1 — Design system modular

### Arquivos a criar

```text
public/assets/css/
  tokens.css
  base.css
  shell.css
  utilities.css

public/assets/css/themes/
  client.css
  tow.css
  admin.css

public/assets/css/components/
  buttons.css
  cards.css
  forms.css
  stats.css
  status.css
  timeline.css
  map.css
  bottom-sheet.css
  bottom-nav.css
  toast.css
  modal.css
  tables.css
  communications.css

public/assets/css/pages/
  landing.css
  auth.css
  client-dashboard.css
  client-request.css
  client-tracking.css
  client-history.css
  client-profile.css
  tow-dashboard.css
  tow-offer.css
  tow-attendance.css
  tow-finance.css
  admin-dashboard.css
  admin-tables.css
  admin-communications.css
  admin-governance.css
```

### Arquivos a alterar

```text
public/assets/css/style.css
public/assets/css/components/dashboard.css
public/assets/css/components/communications.css
src/Views/layouts/header.php
src/Views/layouts/footer.php
src/Views/layouts/sidebar_cliente.php
src/Views/layouts/sidebar_guincho.php
src/Views/layouts/sidebar_admin.php
```

### Regra de transição do CSS

`style.css` continuará temporariamente como compatibilidade, mas deixará de receber componentes novos. Cada view migrada carregará apenas:

```html
<link rel="stylesheet" href="/public/assets/css/tokens.css">
<link rel="stylesheet" href="/public/assets/css/base.css">
<link rel="stylesheet" href="/public/assets/css/shell.css">
<link rel="stylesheet" href="/public/assets/css/themes/client.css">
<link rel="stylesheet" href="/public/assets/css/pages/client-dashboard.css">
```

Ao final do Nível 2:

- zero CSS embutido em views migradas;
- zero `style="..."` nas telas principais;
- `style.css` reduzido ao legado ainda não migrado.

---

## 5.3 Tokens gráficos definitivos

### Global

```css
:root {
  --brand: #2fb34a;
  --brand-dark: #1f8a36;
  --brand-strong: #146b29;
  --success: #16a34a;
  --warning: #f59e0b;
  --danger: #dc2626;
  --info: #2563eb;

  --font-sans: "Segoe UI", Inter, system-ui, -apple-system, sans-serif;
  --radius-xs: 8px;
  --radius-sm: 12px;
  --radius-md: 16px;
  --radius-lg: 24px;
  --radius-xl: 30px;
  --radius-pill: 999px;

  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;
  --space-10: 40px;

  --topbar-height: 64px;
  --mobile-topbar-height: 60px;
  --sidebar-width: 248px;
  --bottom-nav-height: 72px;
  --content-max: 1440px;
  --control-height: 52px;
  --action-height: 56px;

  --shadow-card: 0 12px 34px rgba(15, 23, 42, .10);
  --shadow-float: 0 28px 80px rgba(15, 23, 42, .14);
  --transition: 180ms ease;
}
```

### Cliente — tema branco

```css
body.cliente {
  --theme-bg: #f4f8f5;
  --theme-surface: #ffffff;
  --theme-surface-2: #edf5ef;
  --theme-border: #d6e7da;
  --theme-text: #142018;
  --theme-muted: #587061;
  --theme-accent: #2fb34a;
  --theme-accent-hover: #1f8a36;
  --theme-nav: #ffffff;
  --theme-sidebar: #edf5ef;
}
```

### Guincho — tema verde operacional

```css
body.guincho {
  --theme-bg: #061209;
  --theme-surface: #0d2413;
  --theme-surface-2: #14351c;
  --theme-border: #285a33;
  --theme-text: #f6fff7;
  --theme-muted: #b4d9bb;
  --theme-accent: #42d564;
  --theme-accent-hover: #2fb34a;
  --theme-nav: #050f07;
  --theme-sidebar: #07170b;
  --surface-light-text: #142018;
  --surface-light-muted: #5a6b5f;
}
```

### Admin — tema preto

```css
body.admin {
  --theme-bg: #07090c;
  --theme-surface: #11151a;
  --theme-surface-2: #171d24;
  --theme-border: #28313b;
  --theme-text: #f7fafc;
  --theme-muted: #aeb8c3;
  --theme-accent: #2fb34a;
  --theme-accent-hover: #42d564;
  --theme-nav: #050607;
  --theme-sidebar: #090b0e;
}
```

### Métricas gráficas obrigatórias

| Elemento | Desktop | Mobile |
|---|---:|---:|
| Topbar | 64px | 60px |
| Sidebar | 248px | bottom nav 72px |
| Padding main | 32px | 16px |
| Gap de grid | 24px | 16px |
| Controle | 52px | 52px |
| CTA operacional | 56px | 56px |
| Card | raio 24px | raio 20px |
| Hero | máximo 240px | altura automática |
| Bottom sheet | máx. 440px | 42–55vh |
| Touch target | mínimo 44×44px | mínimo 44×44px |

---

## 5.4 Pacote L2.2 — Shell, navegação e componentes compartilhados

### Arquivos a criar

```text
src/Views/components/
  page_header.php
  profile_badge.php
  status_badge.php
  status_timeline.php
  route_summary.php
  map_shell.php
  bottom_sheet.php
  bottom_navigation.php
  empty_state.php
  active_order_card.php
  user_avatar.php
  notification_badge.php

public/assets/js/components/
  bottom-navigation.js
  bottom-sheet.js
  toast.js
  modal-controller.js
  status-timeline.js
  map-controller.js
```

### Arquivos a alterar

```text
src/Views/layouts/header.php
src/Views/layouts/footer.php
src/Views/layouts/sidebar_cliente.php
src/Views/layouts/sidebar_guincho.php
src/Views/layouts/sidebar_admin.php
public/assets/js/bootstrap-config.js
public/assets/js/ui-hooks.js
```

### Comportamento mobile

Cliente:

```text
Painel | Socorro | Pedidos | Conta
```

Guincho:

```text
Painel | Ofertas | Atendimento | Conta
```

Admin mantém sidebar desktop e drawer no tablet.

### Critério de aceite

- navegação disponível com polegar em 360px;
- item ativo textual e visual;
- conteúdo nunca fica escondido pela bottom nav;
- sidebar desktop e bottom nav não aparecem simultaneamente no mobile.

---

## 5.5 Pacote L2.3 — Páginas públicas e autenticação

O roadmap identifica landing, login, cadastros e recuperação de senha.

### Estado atual

- login e autenticação existem;
- cadastros existem;
- recuperação existe;
- landing comercial dedicada não existe;
- CSS de auth é repetido/embutido.

### Arquivos a criar

```text
src/Controllers/HomeController.php
src/Views/public/home.php
src/Views/public/partials/hero.php
src/Views/public/partials/features.php
src/Views/public/partials/how_it_works.php
src/Views/public/partials/final_cta.php
public/assets/css/pages/landing.css
public/assets/js/public/landing.js
public/assets/img/landing/
```

### Arquivos a alterar

```text
index.php
src/Views/auth/login.php
src/Views/auth/registrocliente.php
src/Views/auth/registroguincho.php
src/Views/auth/esqueceu_senha.php
src/Views/auth/redefinir_senha.php
public/assets/js/auth-registro-cliente.js
public/assets/js/auth-registro-guincho.js
public/assets/js/auth-redefinir-senha.js
```

### Nova decisão de rota

- `/` passa a ser landing;
- `/login` continua login;
- usuário autenticado acessando `/` recebe CTA para o painel, não redirecionamento agressivo.

### Critério de aceite

- auth sem CSS inline;
- validações inline e acessíveis;
- cadastro de guincho mostra estado “em análise”;
- recuperação por SMTP configurado, sem depender silenciosamente de `mail()`.

---

# 6. Cliente — funcionalidades e arquivos do Nível 2

## 6.1 Estrutura final do dashboard

### Sem pedido ativo

```text
[Topbar]
[Saudação curta]
[Campo rápido: Onde está o veículo?] [GPS] [Pedir socorro]
[Tipos de socorro: Reboque | Bateria | Pneu | Pane seca | Agendar]
[Comunicado ativo]
[Indicadores]
[Localização atual] [Últimos pedidos]
[Bottom nav]
```

### Com pedido ativo

```text
[Topbar]
[Card operacional ativo com ETA, status, guincho, placa, avaliação]
[Mapa compacto]
[Ligar] [Chat] [Compartilhar] [Acompanhar]
[Comunicado ocultado]
[Histórico curto]
[Bottom nav]
```

## 6.2 Arquivos a alterar

```text
src/Controllers/ClienteController.php
src/Services/ClienteService.php
src/Services/PedidoService.php
src/Services/TarifaService.php
src/Services/GeocodingService.php
src/Views/cliente/dashboard.php
src/Views/cliente/pedidonovo.php
src/Views/cliente/pedidostatus.php
src/Views/cliente/historico.php
src/Views/cliente/financeiro.php
src/Views/cliente/perfil.php
src/Views/cliente/avaliacao.php
src/Views/cliente/veiculos.php
src/Views/cliente/oficinas.php
public/assets/js/cliente-pedido.js
public/assets/js/atendimento-status.js
```

## 6.3 Controllers a criar e extração gradual

```text
src/Controllers/ClientePedidoController.php
src/Controllers/ClientePerfilController.php
src/Controllers/ClienteFinanceiroController.php
src/Controllers/ClienteNotificationController.php
```

### Rotas a redirecionar internamente

| Rota | Controller novo |
|---|---|
| `/cliente/pedido/rascunho` | `ClientePedidoController::draft` |
| `/cliente/pedido/novo` | `ClientePedidoController::createForm` |
| `/cliente/pedido/criar` | `ClientePedidoController::create` |
| `/cliente/pedido/{id}` | `ClientePedidoController::status` |
| `/cliente/pedido/status-json/{id}` | `ClientePedidoController::statusJson` |
| `/cliente/cancelar/{id}` | `ClientePedidoController::cancel` |
| `/cliente/financeiro` | `ClienteFinanceiroController::index` |
| `/cliente/perfil` | `ClientePerfilController::form` |

## 6.4 Componentes a criar

```text
src/Views/cliente/components/
  quick_rescue.php
  rescue_type_cards.php
  active_order.php
  driver_card.php
  request_quote.php
  payment_method_drawer.php
  cancellation_preview.php
  recent_orders.php
  favorite_places.php
```

## 6.5 JavaScript a criar

```text
public/assets/js/client/
  quick-rescue-controller.js
  request-wizard.js
  quote-controller.js
  payment-drawer.js
  tracking-controller.js
  cancellation-controller.js
  rating-controller.js
  favorite-places.js
```

## 6.6 Pedido novo — wizard final

A tela `pedidonovo.php` será convertida em três etapas, sem duplicar regra no frontend.

### Etapa 1 — rota

- origem pré-preenchida pelo rascunho;
- destino por autocomplete;
- oficina favorita opcional;
- mapa e markers;
- botão “Continuar”.

### Etapa 2 — serviço e cotação

- veículo;
- tipo de socorro;
- observação;
- distância;
- ETA estimado;
- valor vindo de `TarifaService`;
- resumo da rota.

### Etapa 3 — confirmação

- forma de pagamento;
- política de cancelamento resumida;
- aceite de valor;
- CTA “Confirmar pedido”.

### Métricas

| Elemento | Medida |
|---|---:|
| mapa desktop | mínimo 520px |
| bottom sheet mobile | 45–58vh |
| input | 52px |
| CTA | 56px |
| passo ativo | 28–32px |
| padding do card | 20–24px |

## 6.7 Tracking final

`pedidostatus.php` deverá renderizar todos os estados sem trocar de página:

- buscando guincho;
- guincho a caminho;
- no local;
- em reboque;
- concluído;
- cancelado.

### Composição

```text
mapa fullscreen/expandido
bottom sheet
  motorista/guincho
  ETA e distância
  timeline
  origem/destino
  ligar/chat/compartilhar
  cancelamento secundário
```

### Regra de rota

- `a_caminho`: guincho → origem;
- `no_local`: posição atual/origem;
- `em_reboque`: guincho → destino;
- `concluido`: trilha histórica.

## 6.8 Páginas novas do cliente

```text
src/Views/cliente/notificacoes.php
src/Views/cliente/configuracoes.php
src/Views/cliente/ajuda.php
src/Views/cliente/recibo.php
```

### Serviços/modelos novos

```text
src/Models/Notification.php
src/Models/UserPreference.php
src/Models/FavoritePlace.php
src/Services/NotificationCenterService.php
src/Services/UserPreferenceService.php
src/Services/FavoritePlaceService.php
```

### Migration

```text
install/migration_client_experience_v1.sql
```

Tabelas:

```text
notifications
notification_deliveries
user_preferences
favorite_places
```

### Gate do cliente

- dashboard e pedido utilizáveis em 360px;
- rascunho rápido aprovado;
- cotação não é calculada no JS;
- tracking muda rota por etapa;
- chat, cancelamento e sessão testados;
- screenshots aprovados em 390, 768 e 1366px.

---

# 7. Guincho — funcionalidades e arquivos do Nível 2

## 7.1 Dashboard final

```text
[Hero compacto: nome + toggle online]
[Ganhos hoje] [Atendimentos] [Nota] [Km]
[Comunicado operacional]
[Oferta nova com timer]
[Mapa de cobertura]
[Fila de ofertas]
[Últimos atendimentos]
[Bottom nav]
```

## 7.2 Arquivos a alterar

```text
src/Controllers/GuinchoController.php
src/Services/GuinchoService.php
src/Services/RankingService.php
src/Services/POR/ProofOfRoadService.php
src/Views/guincho/dashboard.php
src/Views/guincho/pedidoaceitar.php
src/Views/guincho/atendimento.php
src/Views/guincho/financeiro.php
src/Views/guincho/historico.php
src/Views/guincho/perfil.php
src/Views/guincho/perfil_conta.php
src/Views/guincho/perfil_operacao.php
src/Views/guincho/perfil_bancario.php
public/assets/js/atendimento-status.js
```

## 7.3 Controllers a criar

```text
src/Controllers/GuinchoDispatchController.php
src/Controllers/GuinchoAtendimentoController.php
src/Controllers/GuinchoFinanceiroController.php
src/Controllers/GuinchoNotificationController.php
```

### Rotas a redirecionar

| Rota | Controller novo |
|---|---|
| `/guincho/pedidos-disponiveis` | `GuinchoDispatchController::available` |
| `/sse/pedidos` | `GuinchoDispatchController::stream` |
| `/guincho/aceitar/{id}` | `GuinchoDispatchController::accept` |
| `/guincho/recusar/{id}` | `GuinchoDispatchController::reject` |
| `/guincho/atendimento/{id}` | `GuinchoAtendimentoController::show` |
| `/guincho/status/{id}` | `GuinchoAtendimentoController::transition` |
| `/guincho/localizacao` | `GuinchoAtendimentoController::location` |
| `/guincho/financeiro` | `GuinchoFinanceiroController::index` |

## 7.4 Componentes a criar

```text
src/Views/guincho/components/
  availability_toggle.php
  offer_card.php
  offer_timer.php
  route_preview.php
  client_card.php
  por_status.php
  evidence_capture.php
  attendance_action_bar.php
  earnings_summary.php
```

## 7.5 JavaScript a criar

```text
public/assets/js/tow/
  dashboard-controller.js
  offer-stream.js
  offer-timer.js
  attendance-controller.js
  proof-of-road-tracker.js
  evidence-capture.js
  finance-controller.js
```

## 7.6 Card de oferta — conteúdo mínimo

```text
Pedido #12375                       00:28
4,8 km até o cliente · ETA 11 min
Origem
Destino
Peugeot 207 · falha mecânica
18,2 km previstos
Pagamento confirmado
R$ 189,60
[Recusar] [Aceitar]
```

### Regras

- aceitar à direita, verde cheio;
- recusar à esquerda, outline;
- timer abaixo de 10s em danger;
- barra visual de tempo;
- aceitar envia idempotency key;
- HTTP 409 significa pedido assumido por outro guincho;
- card nunca usa `innerHTML` com strings da API.

## 7.7 Atendimento — painel POR visível

```text
GPS ativo
Precisão: 8 m
Último ponto: há 4 s
Pontos pendentes: 0
Qualidade: 96%
Rota íntegra: sim
```

### Barra de ação por estado

| Estado | CTA |
|---|---|
| `a_caminho` | Cheguei ao local |
| `no_local` | Iniciar reboque |
| `em_reboque` | Finalizar atendimento |
| `concluido` | Ver resumo |

O CTA deve ficar fixo no rodapé mobile e nunca ser habilitado se as pré-condições falharem.

## 7.8 Financeiro do guincho

### Dados

- saldo disponível;
- saldo a liberar;
- ganhos hoje;
- ganhos semana/mês;
- comissão;
- saques;
- extrato;
- falhas/pendências.

### Arquivos a criar

```text
src/Views/guincho/saques.php
src/Views/guincho/recibo.php
src/Services/GuinchoWalletService.php
src/Models/GuinchoWallet.php
src/Models/GuinchoWalletMovement.php
```

A tabela `guincheiro_carteira` existente deve ser migrada/validada, não duplicada.

## 7.9 Gate do guincho

- oferta aparece sem refresh manual;
- toggle online é único e persiste;
- geolocalização continua após oscilação de rede;
- aceite concorrente tratado;
- etapas bloqueadas por POR/evidência;
- saldo e extrato reconciliados;
- screenshots aprovados em 390, 768 e 1366px.

---

# 8. Admin — funcionalidades e arquivos do Nível 2

## 8.1 Command Center final

O admin deixa de ser apenas um dashboard estatístico e passa a ser um centro operacional.

```text
[KPIs operacionais]
[Mapa vivo: pedidos + guinchos]
[Pedidos por fase]
[Alertas críticos]
[POR suspeito]
[Evidências pendentes/recusadas]
[Pagamentos/jobs falhos]
[QA e health]
[Comunicados ativos]
[Documentos/apólices a vencer]
```

## 8.2 Arquivos a alterar

```text
src/Controllers/AdminController.php
src/Views/admin/dashboard.php
src/Views/admin/pedidos.php
src/Views/admin/pedidodetalhe.php
src/Views/admin/pedido_trilha.php
src/Views/admin/usuarios.php
src/Views/admin/guinchos.php
src/Views/admin/financeiro.php
src/Views/admin/logs_v2.php
src/Views/admin/health.php
src/Views/layouts/sidebar_admin.php
```

## 8.3 Controllers a criar

```text
src/Controllers/AdminOpsController.php
src/Controllers/AdminPedidoController.php
src/Controllers/AdminUserController.php
src/Controllers/AdminFinanceController.php
src/Controllers/AdminLogController.php
src/Controllers/AdminQaController.php
src/Controllers/AdminGovernanceController.php
```

## 8.4 Services a criar

```text
src/Services/Admin/
  AdminDashboardService.php
  AdminOpsMapService.php
  IncidentService.php
  AdminFilterService.php
  ExportService.php
```

## 8.5 Views e JS a criar

```text
src/Views/admin/ops/index.php
src/Views/admin/ops/incidents.php
src/Views/admin/ops/map.php
src/Views/admin/ops/por_alerts.php
src/Views/admin/ops/payment_alerts.php

public/assets/js/admin/
  ops-stream.js
  dashboard-charts.js
  ops-map.js
  table-filters.js
  incident-controller.js
```

## 8.6 Chart.js

Se for utilizado, deve ser versionado localmente:

```text
public/assets/vendor/chartjs/chart.umd.min.js
```

Não carregar de CDN.

## 8.7 Gate do admin

- dashboard JSON desacoplado da view;
- mapa atualiza sem reload total;
- alertas levam ao registro correspondente;
- filtros persistem na URL;
- exportação usa filtros atuais;
- QA não está mais dentro do controller monolítico;
- nenhum card se sobrepõe ao footer.

---

# 9. Central de Comunicados — conclusão no Nível 2

## 9.1 Estado existente

Já existem:

```text
src/Controllers/AdminComunicadoController.php
src/Controllers/ComunicadoController.php
src/Models/Comunicado.php
src/Services/ComunicadoService.php
src/Services/MediaUploadService.php
src/Views/admin/comunicados/*
src/Views/components/communication_carousel.php
public/assets/js/communications.js
public/assets/css/components/communications.css
install/migration_comunicados_v1.sql
```

## 9.2 Arquivos a alterar

| Arquivo | Alteração |
|---|---|
| `AdminComunicadoController.php` | log estruturado; respostas HTTP corretas; duplicar; ordenar; métricas |
| `ComunicadoController.php` | endpoint de métricas impression/click/dismiss; frequência por usuário/sessão |
| `Comunicado.php` | remover catches silenciosos; paginação com total; operações métricas atômicas |
| `ComunicadoService.php` | placements, audiência, frequência, validade e view model completo |
| `MediaUploadService.php` | usar processador de imagem seguro; storage apropriado |
| `communication_carousel.php` | `<picture>`, imagem desktop/mobile, controls, `aria-live`, dismiss |
| `communications.js` | duração individual, pausa, visibilidade, reduced motion, IntersectionObserver |
| `communications.css` | wide/card, temas por perfil, responsividade e reservas de layout |
| views admin | todos os campos, preview real, focal point, calendário e métricas |

## 9.3 Arquivos a criar

```text
src/Services/Communication/
  CommunicationMetricService.php
  CommunicationFrequencyService.php
  CommunicationImageService.php

src/Models/
  CommunicationEvent.php

public/assets/js/admin/communication-editor.js
public/assets/js/components/communication-carousel.js
public/assets/css/pages/admin-communications.css

install/migration_comunicados_v2.sql

qa/suites/communications.spec.ts
```

## 9.4 Placements oficiais

```text
cliente_dashboard_top
guinho_dashboard_after_stats
admin_dashboard_notice
public_landing_featured
```

Corrigir a grafia interna para `guincho_dashboard_after_stats`; criar migration de compatibilidade caso dados já existam.

## 9.5 Dimensões

| Formato | Desktop | Mobile | Proporção |
|---|---:|---:|---:|
| Banner cliente wide | 1440×420 | 1080×608 | 24:7 / 16:9 |
| Banner guincho | 1440×360 | 1080×608 | 4:1 / 16:9 |
| Card promocional | 640×360 | 720×720 | 16:9 / 1:1 |

## 9.6 Regras de exibição

- máximo 5 itens ativos por placement;
- duração de 5 a 20 segundos;
- padrão 8 segundos;
- transição 350ms;
- pausar em hover, foco e aba oculta;
- reduced motion desativa autoplay;
- frequência: sempre, sessão, dia ou limite personalizado;
- dismiss armazena TTL;
- não mostrar durante pagamento, aceite, atendimento, evidência ou cancelamento;
- imagem nunca contém texto obrigatório; título e CTA permanecem HTML.

## 9.7 Métricas

```text
impression
view_50_percent
click
dismiss
complete_cycle
```

Métricas devem ser deduplicadas por comunicado, usuário/sessão, placement e janela de tempo.

## 9.8 Gate

- agenda respeitada;
- público correto;
- imagem desktop/mobile correta;
- focal point aplicado;
- frequência e dismiss funcionam;
- métricas aparecem no admin;
- carrossel acessível e sem layout shift.

---

# 10. Catálogo de serviços e funcionalidades inspiradas no roadmap

O roadmap visual apresenta categorias rápidas de serviço. No GuinchaFácil, isso deve virar catálogo administrável.

## 10.1 Tipos iniciais

```text
reboque
bateria
pneu
pane_seca
chaveiro
agendamento
outro
```

## 10.2 Arquivos a criar

```text
src/Models/ServiceType.php
src/Services/ServiceCatalogService.php
src/Controllers/AdminServiceTypeController.php
src/Views/admin/service-types/index.php
src/Views/admin/service-types/form.php
public/assets/js/admin/service-type-editor.js
install/migration_service_catalog_v1.sql
```

## 10.3 Campos

```text
id
slug
titulo
descricao
icone
cor_semantica
ativo
ordem
requer_destino
requer_reboque
requer_foto
regras_json
created_at
updated_at
```

## 10.4 Integrações

- dashboard cliente;
- pedido novo;
- cálculo de tarifa;
- ranking de guinchos;
- oferta do guincho;
- relatórios do admin.

---

# 11. Notificações e preferências

## 11.1 Arquivos a criar

```text
src/Controllers/NotificationController.php
src/Services/NotificationCenterService.php
src/Models/Notification.php
src/Models/NotificationDelivery.php
src/Models/UserPreference.php
src/Views/components/notification_center.php
public/assets/js/components/notification-center.js
install/migration_notifications_v1.sql
```

## 11.2 Eventos mínimos

- pedido criado;
- guincho atribuído;
- guincho chegando;
- mudança de etapa;
- mensagem de chat;
- pagamento confirmado/falhou;
- reembolso;
- saque;
- documento/apólice próximo do vencimento;
- comunicado importante;
- incidente de conta.

## 11.3 Canais

- in-app;
- e-mail;
- push Android no Nível 3.

---

# 12. Gate de saída do Nível 2

## Funcional

- todas as páginas web do roadmap adaptado existem;
- cliente, guincho e admin possuem navegação coerente;
- Central de Comunicados completa;
- notificações e preferências operacionais;
- Command Center operacional;
- catálogo de serviços administrável.

## Visual

- zero texto técnico na UI;
- zero IDs duplicados;
- zero inline style nas telas principais;
- contraste AA;
- 360px sem overflow horizontal;
- cliente branco, guincho verde, admin preto;
- um CTA dominante por contexto.

## Testes

Adicionar ao baseline:

```text
qa/suites/client-dashboard-visual.spec.ts
qa/suites/client-request-wizard.spec.ts
qa/suites/client-tracking-visual.spec.ts
qa/suites/tow-dashboard-visual.spec.ts
qa/suites/tow-offer.spec.ts
qa/suites/tow-attendance-visual.spec.ts
qa/suites/admin-command-center.spec.ts
qa/suites/communications.spec.ts
qa/suites/notifications.spec.ts
qa/suites/accessibility.spec.ts
```

Gate: **20/20 suítes core+produto verdes na mesma execução.**

---

# 13. NÍVEL 3 — API, Android, governança, certificação e release

## 13.1 Objetivo do nível

Transformar o sistema web consolidado em produto final completo, com API versionada, aplicativos Android, governança jurídica, operação monitorada e release reproduzível.

---

# 14. API v1 para Android e integrações

## 14.1 Problema atual

As rotas atuais misturam renderização de views e endpoints JSON. O Android precisa de contratos estáveis, autenticação por token, versionamento e erros normalizados.

## 14.2 Estrutura a criar

```text
src/Controllers/Api/V1/
  AuthApiController.php
  ClientOrderApiController.php
  TowDispatchApiController.php
  TowAttendanceApiController.php
  TrackingApiController.php
  ChatApiController.php
  PaymentApiController.php
  NotificationApiController.php
  ProfileApiController.php
  CommunicationApiController.php

src/Middleware/
  ApiAuthMiddleware.php
  RoleMiddleware.php
  JsonBodyMiddleware.php
  IdempotencyMiddleware.php
  ApiRateLimitMiddleware.php

src/Services/Api/
  ApiTokenService.php
  ApiResponseFactory.php
  ApiPaginationService.php
  DeviceRegistrationService.php

src/Models/
  ApiToken.php
  UserDevice.php

src/DTO/Api/V1/
  ErrorResponse.php
  PaginatedResponse.php
  OrderResponse.php
  TrackingResponse.php

routes/
  api_v1.php

install/
  migration_api_v1.sql
```

## 14.3 Endpoints mínimos

```text
POST   /api/v1/auth/login
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout
GET    /api/v1/me

POST   /api/v1/client/orders/draft
POST   /api/v1/client/orders/quote
POST   /api/v1/client/orders
GET    /api/v1/client/orders/{id}
POST   /api/v1/client/orders/{id}/cancel-preview
POST   /api/v1/client/orders/{id}/cancel

GET    /api/v1/tow/offers
POST   /api/v1/tow/offers/{id}/accept
POST   /api/v1/tow/offers/{id}/reject
POST   /api/v1/tow/orders/{id}/location-batch
POST   /api/v1/tow/orders/{id}/transition
POST   /api/v1/tow/orders/{id}/evidence

GET    /api/v1/orders/{id}/tracking
GET    /api/v1/orders/{id}/chat
POST   /api/v1/orders/{id}/chat

GET    /api/v1/notifications
POST   /api/v1/devices
GET    /api/v1/communications
```

## 14.4 Contrato de erro

```json
{
  "ok": false,
  "error": {
    "code": "POR-GEO-003",
    "message": "Localização fora da área permitida.",
    "field": null,
    "retryable": false,
    "request_id": "req_..."
  }
}
```

## 14.5 Testes

```text
tests/Api/V1/AuthApiTest.php
tests/Api/V1/ClientOrderApiTest.php
tests/Api/V1/TowDispatchApiTest.php
tests/Api/V1/TrackingApiTest.php
tests/Api/V1/EvidenceApiTest.php
tests/Api/V1/ApiAuthorizationTest.php
```

---

# 15. Aplicativos Android

## 15.1 Arquitetura recomendada

Usar projeto multi-módulo Kotlin, com dois apps e módulos compartilhados.

```text
android/
  settings.gradle.kts
  build.gradle.kts
  gradle.properties
  gradle/libs.versions.toml

  app-cliente/
    build.gradle.kts
    src/main/AndroidManifest.xml
    src/main/java/br/com/guinchafacil/cliente/

  app-guincho/
    build.gradle.kts
    src/main/AndroidManifest.xml
    src/main/java/br/com/guinchafacil/guincho/

  core-network/
  core-domain/
  core-database/
  core-location/
  core-ui/
  core-logging/

  feature-auth/
  feature-client-dashboard/
  feature-client-request/
  feature-client-tracking/
  feature-tow-dashboard/
  feature-tow-offers/
  feature-tow-attendance/
  feature-chat/
  feature-payment/
  feature-notifications/
  feature-profile/
```

## 15.2 Stack

- Kotlin;
- Jetpack Compose;
- Material 3 customizado;
- Retrofit/OkHttp;
- Kotlin Serialization;
- Room;
- WorkManager;
- Hilt;
- Google Maps ou MapLibre, conforme licença/decisão;
- FCM;
- CameraX;
- DataStore;
- encrypted storage para tokens.

## 15.3 App cliente — telas

```text
Login/cadastro
Dashboard com campo rápido
Pedido em etapas
Cotação/mapa
Pagamento
Procurando guincho
Tracking
Chat
Cancelamento
Histórico/recibo
Avaliação
Notificações
Perfil/veículos/oficinas
```

## 15.4 App guincho — telas

```text
Login/status de aprovação
Dashboard online/offline
Ofertas com timer
Detalhe/aceite
Atendimento
POR/GPS
Captura de evidência
Chat
Financeiro/saque
Histórico
Perfil operacional/bancário
Notificações
```

## 15.5 Serviços Android críticos

```text
android/core-location/.../TowLocationService.kt
android/core-location/.../OfflineLocationQueue.kt
android/core-network/.../AuthInterceptor.kt
android/core-network/.../IdempotencyInterceptor.kt
android/core-logging/.../StructuredLogger.kt
android/feature-tow-attendance/.../EvidenceCaptureUseCase.kt
android/feature-client-tracking/.../TrackingRepository.kt
```

## 15.6 Regras de background

- foreground service somente durante atendimento ativo;
- notificação persistente clara;
- WorkManager para reenvio de pontos;
- fila Room;
- política de bateria e precisão documentada;
- interrupção automática após conclusão/cancelamento;
- permissões solicitadas no momento de uso.

## 15.7 Testes Android

```text
unit tests de ViewModel/use cases
MockWebServer para API
testes Room/WorkManager
Compose UI tests
instrumentação de GPS simulado
teste de rotação/process death
teste offline→online
Firebase Test Lab ou matriz equivalente
```

## 15.8 Gate Android

- cliente conclui pedido em sandbox/freeflow;
- guincho recebe e aceita;
- chat funciona;
- localização continua com app em background;
- evidências chegam válidas;
- sessão e refresh token funcionam;
- crash-free e ANR monitorados.

---

# 16. Pacote jurídico e governança constitucional

## 16.1 Sistemas ausentes a implementar

### SEG-01 — Apólices de seguro

```text
src/Controllers/AdminInsuranceController.php
src/Models/InsurancePolicy.php
src/Services/InsurancePolicyService.php
src/Views/admin/insurance/index.php
src/Views/admin/insurance/form.php
src/Views/admin/insurance/detail.php
public/assets/js/admin/insurance-controller.js
install/migration_seg_01_insurance.sql
```

Campos:

```text
id
guincho_id
seguradora
numero_apolice
cobertura_json
inicio_vigencia
fim_vigencia
arquivo_storage_key
status
validado_por
validado_em
created_at
updated_at
```

Regras:

- grace period de 30 dias conforme decisão registrada;
- upload obrigatório;
- alertas de vencimento;
- guincho sem apólice válida pode ser bloqueado conforme configuração;
- e-mail operacional `apolices@...` configurável.

### CT-01 — Aceite contratual

```text
src/Controllers/ContractController.php
src/Controllers/AdminContractController.php
src/Models/ContractVersion.php
src/Models/ContractAcceptance.php
src/Services/ContractService.php
src/Views/admin/contracts/*
src/Views/legal/contract_acceptance.php
install/migration_ct_01_contracts.sql
```

Regras:

- contratos versionados;
- versão inicial prevista: 1.4;
- hash do conteúdo;
- IP, user agent e timestamp;
- novo aceite quando versão obrigatória mudar;
- bloqueio controlado até aceite.

### PU-01 — Política e privacidade

Alterar:

```text
politica-privacidade.php
termos-servico.php
src/Views/layouts/footer.php
```

Criar:

```text
src/Controllers/PrivacyController.php
src/Models/PrivacyRequest.php
src/Services/PrivacyRequestService.php
src/Views/privacy/request.php
src/Views/admin/privacy/index.php
install/migration_pu_01_privacy.sql
```

Recursos:

- exportação de dados;
- correção;
- exclusão/anomização conforme retenção legal;
- registro de consentimento;
- política sem placeholders.

### GOV-01-B — Decisões estratégicas

```text
src/Controllers/AdminGovernanceController.php
src/Models/StrategicDecision.php
src/Services/StrategicDecisionService.php
src/Views/admin/governance/index.php
src/Views/admin/governance/form.php
src/Views/admin/governance/detail.php
install/migration_gov_01_strategic_decisions.sql
```

Regras:

- log append-only;
- nenhuma edição/destruição comum;
- correção por registro supersessor;
- trigger de proteção;
- ator, justificativa, impacto e anexos;
- auditoria administrativa.

---

# 17. Observabilidade, operação e release

## 17.1 Logs padronizados

Formato obrigatório em PHP e JavaScript:

```json
{
  "timestamp": "2026-07-14T12:00:00Z",
  "level": "error",
  "system": "evidence",
  "class": "EvidenceTransactionService",
  "function": "submitAndTransition",
  "file": "EvidenceTransactionService.php",
  "phase": "transaction_commit",
  "code": "EVD-TRN-002",
  "request_id": "req_...",
  "user_id": 10,
  "pedido_id": 63,
  "message": "Falha ao concluir evidência e transição.",
  "context": {}
}
```

## 17.2 Matriz inicial de códigos

| Sistema | Prefixo |
|---|---|
| Configuração | `CFG` |
| Migration | `MIG` |
| Pedido | `ORD` |
| Dispatch | `DSP` |
| POR | `POR` |
| Evidência | `EVD` |
| Cancelamento | `CAN` |
| Pagamento | `PAY` |
| Repasse | `PYO` |
| Chat | `CHT` |
| Comunicados | `COM` |
| Notificações | `NTF` |
| Admin Ops | `OPS` |
| API | `API` |
| Android cliente | `AND-C` |
| Android guincho | `AND-T` |
| Jurídico | `LEG` |
| QA | `QAT` |

## 17.3 Arquivos a criar

```text
src/Services/Observability/
  StructuredLogService.php
  MetricService.php
  TraceContextService.php
  AlertService.php

src/Controllers/AdminIncidentController.php
src/Models/Incident.php
src/Views/admin/incidents/*

install/migration_observability_v3.sql
```

## 17.4 Crons/Workers

Padronizar e documentar:

```text
tools/cron_cancelar_pedidos_expirados.php
tools/cron_limpar_logs.php
tools/cron_limpar_tokens.php
tools/cron_reprocessar_pix.php
tools/cron_retencao_operacional.php
tools/qa_worker.php
```

Criar:

```text
tools/cron_comunicados.php
tools/cron_notificacoes.php
tools/cron_apolices.php
tools/cron_incident_alerts.php
```

Cada comando deve:

- possuir lock;
- retornar exit code;
- registrar início/fim/erro;
- ser idempotente;
- ter health status no admin.

## 17.5 Backup e restore

Criar:

```text
tools/backup/backup_database.php
tools/backup/backup_private_storage.php
tools/backup/verify_backup.php
tools/backup/restore_dry_run.php
doc/RUNBOOK_BACKUP_RESTORE.md
```

Gate:

- backup restaurado em ambiente isolado;
- evidências e anexos disponíveis;
- checksum aprovado;
- tempo de restauração registrado.

---

# 18. Performance, acessibilidade e segurança final

## 18.1 Metas

| Métrica | Alvo |
|---|---:|
| LCP | ≤ 2,5s em 4G intermediário |
| CLS | ≤ 0,1 |
| INP | ≤ 200ms |
| JS inicial dashboard | ≤ 180 KB gzip sem mapa |
| contraste | WCAG AA 4,5:1 |
| touch target | ≥ 44×44px |
| erros JS | zero no fluxo principal |
| vazamento de memória SSE/mapa | zero após navegação repetida |

## 18.2 Ferramentas/testes

```text
qa/suites/accessibility.spec.ts
qa/suites/performance-budget.spec.ts
qa/suites/visual-regression.spec.ts
qa/suites/security-headers.spec.ts
tests/Integration/AuthorizationMatrixTest.php
tests/Integration/PrivateFileAccessTest.php
```

## 18.3 Breakpoints de screenshot

```text
360×800
390×844
768×1024
1024×768
1366×768
1440×900
1920×1080
```

---

# 19. Matriz consolidada de arquivos

Legenda:

- **C**: criar;
- **A**: alterar;
- **R**: retirar/arquivar após migração.

## 19.1 Raiz/configuração

| Ação | Arquivo |
|---|---|
| A | `.gitignore` |
| A | `.env.example` |
| R | `.env.local` do pacote |
| A | `config.php` |
| A | `index.php` |
| C | `config/app.php` |
| C | `config/security.php` |
| C | `config/storage.php` |
| C | `config/services.php` |

## 19.2 Banco

| Ação | Arquivo |
|---|---|
| A | `install/run_all_migrations.php` |
| A | `install/migration_runtime.php` |
| A | migrations existentes |
| C | `install/migration_release_hardening_v1.sql` |
| C | `install/migration_pedido_idempotency_v1.sql` |
| C | `install/migration_por_integrity_v2.sql` |
| C | `install/migration_evidence_private_v2.sql` |
| C | `install/migration_cancel_snapshot_v2.sql` |
| C | `install/migration_payment_ledger_v2.sql` |
| C | `install/migration_client_experience_v1.sql` |
| C | `install/migration_comunicados_v2.sql` |
| C | `install/migration_service_catalog_v1.sql` |
| C | `install/migration_notifications_v1.sql` |
| C | `install/migration_api_v1.sql` |
| C | `install/migration_seg_01_insurance.sql` |
| C | `install/migration_ct_01_contracts.sql` |
| C | `install/migration_pu_01_privacy.sql` |
| C | `install/migration_gov_01_strategic_decisions.sql` |
| C | `install/migration_observability_v3.sql` |

## 19.3 Controllers PHP

### Alterar

```text
AdminController.php
AdminComunicadoController.php
ArquivoController.php
AuthController.php
ClienteController.php
ComunicadoController.php
GeocodeController.php
GuinchoController.php
PagamentoController.php
SseController.php
WebhookController.php
```

### Criar

```text
HomeController.php
ClientePedidoController.php
ClientePerfilController.php
ClienteFinanceiroController.php
ClienteNotificationController.php
GuinchoDispatchController.php
GuinchoAtendimentoController.php
GuinchoFinanceiroController.php
GuinchoNotificationController.php
AdminOpsController.php
AdminPedidoController.php
AdminUserController.php
AdminFinanceController.php
AdminLogController.php
AdminQaController.php
AdminGovernanceController.php
AdminInsuranceController.php
AdminContractController.php
AdminServiceTypeController.php
NotificationController.php
ContractController.php
PrivacyController.php
AdminIncidentController.php
Api/V1/*
```

## 19.4 Services/modelos

Criar os services especializados descritos nos pacotes L1–L3. Prioridade de extração:

1. Evidence;
2. POR;
3. Pedido;
4. Payment;
5. Admin Ops;
6. Communications;
7. Notifications;
8. API;
9. Legal/Governance.

## 19.5 Views

### Alterar prioritariamente

```text
src/Views/cliente/dashboard.php
src/Views/cliente/pedidonovo.php
src/Views/cliente/pedidostatus.php
src/Views/guincho/dashboard.php
src/Views/guincho/pedidoaceitar.php
src/Views/guincho/atendimento.php
src/Views/admin/dashboard.php
src/Views/admin/comunicados/*
src/Views/layouts/*
```

### Criar

```text
src/Views/public/*
src/Views/components/*
src/Views/cliente/components/*
src/Views/cliente/notificacoes.php
src/Views/cliente/configuracoes.php
src/Views/cliente/ajuda.php
src/Views/cliente/recibo.php
src/Views/guincho/components/*
src/Views/guincho/saques.php
src/Views/guincho/recibo.php
src/Views/admin/ops/*
src/Views/admin/qa/*
src/Views/admin/incidents/*
src/Views/admin/insurance/*
src/Views/admin/contracts/*
src/Views/admin/governance/*
src/Views/admin/privacy/*
src/Views/admin/service-types/*
src/Views/legal/*
src/Views/privacy/*
```

## 19.6 CSS

Criar estrutura modular da seção 5.2 e migrar por página. Ao final:

- `style.css` não deve conter CSS específico das telas novas;
- `dashboard.css` antigo deve ser removido ou reduzido a compatibilidade;
- `communications.css` deve ser substituído pela versão de componente completa.

## 19.7 JavaScript

### Alterar/retirar

```text
A/R public/assets/js/quick-rescue.js
A public/assets/js/cliente-pedido.js
A public/assets/js/atendimento-status.js
A public/assets/js/app.js
A public/assets/js/session-manager.js
A/R public/assets/js/communications.js
```

### Criar

```text
public/assets/js/core/*
public/assets/js/components/*
public/assets/js/client/*
public/assets/js/tow/*
public/assets/js/admin/*
public/assets/js/public/*
```

## 19.8 Android

Criar integralmente a árvore `android/` descrita na seção 15.

---

# 20. Sequência de execução obrigatória

## Nível 1 — ordem

1. segredos e release seguro;
2. runner de migrations;
3. correções UTF-8/IDs/rotas do quick rescue;
4. máquina de estados/idempotência;
5. POR transacional e buffer;
6. evidência privada/atômica;
7. cancelamento snapshot;
8. pagamento/repasse;
9. sessão/SSE/chat;
10. QA portável e baseline.

Não iniciar reconstrução visual extensa antes do item 6, para não esconder falhas operacionais atrás de uma interface nova.

## Nível 2 — ordem

1. tokens, temas e shell;
2. componentes globais;
3. dashboard cliente e quick rescue;
4. pedido novo e tracking;
5. dashboard guincho e oferta;
6. atendimento/POR visual;
7. Command Center admin;
8. Comunicados;
9. notificações/preferências;
10. páginas secundárias e visual regression.

## Nível 3 — ordem

1. API v1;
2. Android core e autenticação;
3. Android cliente;
4. Android guincho;
5. push/FCM;
6. jurídico/governança;
7. observabilidade e incidentes;
8. performance/segurança;
9. backup/restore;
10. release candidate.

---

# 21. Critérios de “feito” para qualquer tarefa

Uma tarefa só muda para concluída quando:

1. código implementado;
2. migration criada, se aplicável;
3. rollback/compatibilidade documentado;
4. logs implementados;
5. teste unitário ou integração criado;
6. E2E criado quando existe fluxo de usuário;
7. resultado verde;
8. documentação atualizada;
9. screenshot aprovado para UI;
10. nenhuma regressão detectada no baseline.

Não considerar “feito” porque a classe ou a tela existe.

---

# 22. Checklist de release 100%

## Código e banco

- [ ] zero P0/P1 aberto;
- [ ] PHP lint integral;
- [ ] TypeScript/JS sem erro;
- [ ] migrations limpas e idempotentes;
- [ ] schema verificado;
- [ ] segredo zero no pacote.

## Fluxo

- [ ] pedido freeflow;
- [ ] pedido produção/sandbox;
- [ ] aceite concorrente;
- [ ] chat;
- [ ] POR online e offline;
- [ ] coleta;
- [ ] entrega;
- [ ] cancelamento cliente;
- [ ] desistência guincho/reassign;
- [ ] estorno;
- [ ] repasse/saque.

## UX

- [ ] cliente branco;
- [ ] guincho verde;
- [ ] admin preto;
- [ ] mobile 360px;
- [ ] tablet;
- [ ] desktop;
- [ ] contraste AA;
- [ ] foco visível;
- [ ] nenhum texto técnico;
- [ ] nenhum ID duplicado;
- [ ] nenhum inline style nas telas principais.

## Sistemas novos

- [ ] Comunicados completos;
- [ ] notificações;
- [ ] catálogo de serviços;
- [ ] Command Center;
- [ ] apólices;
- [ ] contratos;
- [ ] privacidade;
- [ ] decisões estratégicas;
- [ ] API v1;
- [ ] Android cliente;
- [ ] Android guincho.

## Operação

- [ ] crons monitorados;
- [ ] workers monitorados;
- [ ] alertas ativos;
- [ ] backup aprovado;
- [ ] restore testado;
- [ ] runbook publicado;
- [ ] release reproduzível;
- [ ] rollback ensaiado.

## QA final

- [ ] 20+ suites web verdes na mesma execução;
- [ ] API tests verdes;
- [ ] Android unit/UI/instrumentation verdes;
- [ ] visual regression aprovada;
- [ ] accessibility aprovada;
- [ ] performance budget aprovado;
- [ ] security headers aprovados.

---

# 23. Resultado esperado por nível

## Ao concluir o Nível 1

O sistema deixa de ser “funcional com riscos” e passa a ter um fluxo constitucional confiável, reproduzível e protegido contra os principais defeitos de concorrência, fraude, uploads, cancelamento e pagamento.

## Ao concluir o Nível 2

O sistema web atinge o nível visual e funcional das referências de táxi, porém com identidade própria do GuinchaFácil e com as regras mais avançadas do domínio de reboque.

## Ao concluir o Nível 3

O produto passa a ser completo: web, admin, API, Android, jurídico, observabilidade, operação e release. Nesse ponto, “100%” deixa de ser uma estimativa e passa a ser um conjunto verificável de gates verdes.

---

# 24. Primeira ação recomendada

Abrir um branch de consolidação e executar apenas o **Nível 1 — Pacotes L1.1 a L1.3** inicialmente:

```text
feature/finalizacao-n1-foundation
```

Primeiro commit:

```text
chore(release): remover segredos e padronizar configuração
```

Segundo commit:

```text
refactor(migrations): unificar runner e adicionar verificação de schema
```

Terceiro commit:

```text
fix(order): idempotência e transição atômica
```

Após esses três commits, executar instalação limpa, upgrade e suíte core. Só então avançar para POR e evidências.

---

**Fim do plano.**
