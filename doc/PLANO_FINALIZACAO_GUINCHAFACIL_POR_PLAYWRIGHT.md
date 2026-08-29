# GuinchaFácil — Plano criterioso de finalização, observabilidade e testes Playwright

**Data da auditoria:** 12/07/2026  
**Base analisada:** `GuinchaFacil-consolidado-2026-07-12.zip`  
**Aplicação:** GuinchaFácil Web/PHP  
**Objetivo:** concluir o fluxo operacional, o Proof-of-Road (POR), o cálculo constitucional de cancelamento, a localização em português e um sistema Playwright operado pelo dashboard administrativo.

---

## 1. Diagnóstico executivo

O projeto já possui uma base operacional relevante:

- autenticação por perfis;
- criação e aceite de pedidos;
- estados `aguardando_pagamento`, `aguardando_guincho`, `a_caminho`, `no_local`, `em_reboque`, `concluido` e `cancelado`;
- chat cliente ↔ guincheiro;
- upload das fotos de coleta e entrega;
- polling de status;
- pagamentos, estornos e repasse PIX;
- health check;
- logs no arquivo e banco;
- simulador administrativo interno em PHP;
- arquivos iniciais de testes Playwright.

Entretanto, o projeto ainda não pode ser considerado pronto para produção porque faltam garantias operacionais e antifraude. Os principais bloqueadores são:

1. **O POR não persiste o percurso.** O endpoint atual só sobrescreve `guinchos.lat_atual` e `guinchos.lng_atual`.
2. **As transições de status não possuem geofence.** O guincheiro pode informar chegada ou conclusão sem estar no local.
3. **As fotografias não estão vinculadas ao GPS, horário, fase e nonce do servidor.**
4. **O cancelamento não utiliza distância e tempo comprovados pelo percurso.**
5. **O cliente não recebe uma trilha auditável das ruas efetivamente percorridas.**
6. **As instruções de rota usam dependência `@latest` e podem aparecer em inglês.**
7. **O simulador atual chama serviços e banco diretamente; ele não testa a interface real.**
8. **A suíte Playwright presente no ZIP não está empacotada para execução reproduzível.** Não há `package.json` nem `playwright.config`, e um teste contém caminhos locais e credenciais de banco fixas.
9. **O ZIP contém arquivo `.env` com dados sensíveis.** As credenciais expostas devem ser rotacionadas antes de qualquer publicação.
10. **Há implementações mortas ou divergentes**, como `SseController`, métodos antigos em `RankingService` e classes incompletas em `public/assets/js/app.js`.

---

## 2. Ordem correta de edificação

A implementação deve ser feita **por sistema funcional**, nesta ordem:

| Ordem | Sistema | Motivo |
|---:|---|---|
| 0 | Segurança e segredos | Impede exposição de produção e execução perigosa dos testes. |
| 1 | Observabilidade e padrão de debug | Toda fase posterior precisa deixar diagnóstico preciso. |
| 2 | Migrações e modelo de dados | POR, evidências e testes precisam de persistência estável. |
| 3 | Máquina de estados do pedido | Centraliza e protege todas as transições. |
| 4 | POR persistente e antifraude | Gera a fonte confiável de distância, tempo e localização. |
| 5 | Evidências de coleta e entrega | Vincula fotografia, local e transição. |
| 6 | Rota, ruas e ETA em português | Entrega a informação ao cliente. |
| 7 | Cancelamento constitucional | Passa a utilizar o percurso comprovado. |
| 8 | Pagamentos e repasse assíncrono | Evita duplicidade, travamento e repasse indevido. |
| 9 | Playwright no dashboard | Valida o conjunto completo em navegador real. |
| 10 | Operação, cron, legal e limpeza | Fecha prontidão de produção. |

A razão para colocar observabilidade antes do POR é simples: rastreamento sem diagnóstico vira caça ao fantasma com mapa na mão.

---

# 3. Padrão obrigatório de debug

## 3.1 Identidade mínima de cada evento

Todo log, erro, auditoria e fase de teste deve conter:

```text
application = GUINCHAFACIL
system      = POR | PEDIDO | ROUTING | EVIDENCIA | CANCELAMENTO | CHAT | PAGAMENTO | DISPATCH | AUTH | ADMIN | E2E | OPS
class       = NomeDaClasse
function    = nomeDaFuncao
file        = caminho/arquivo.php ou caminho/arquivo.ts
phase       = etapa interna da operação
code        = código estável do evento/erro
request_id  = correlação da requisição HTTP
run_id      = correlação da simulação/teste
pedido_id   = pedido relacionado
usuario_id  = usuário autenticado
cliente_id  = cliente relacionado
Guincho_id  = guincho relacionado
duration_ms = duração da operação
message     = descrição humana
context     = JSON com valores técnicos permitidos
```

### Formato de identificação rápida

```text
GUINCHAFACIL | POR | ProofOfRoadService | ingestPoint |
src/Services/ProofOfRoadService.php | validate_accuracy | POR-VAL-003
```

O admin deve conseguir copiar essa linha e localizar imediatamente:

- sistema;
- classe;
- função;
- arquivo;
- fase;
- código.

## 3.2 Exemplo de log JSON

```json
{
  "ts": "2026-07-12T15:42:18-03:00",
  "level": "WARN",
  "application": "GUINCHAFACIL",
  "system": "POR",
  "class": "LocationValidationService",
  "function": "validatePoint",
  "file": "src/Services/POR/LocationValidationService.php",
  "phase": "validate_jump",
  "code": "POR-VAL-006",
  "request_id": "req_01J...",
  "run_id": "e2e_01J...",
  "pedido_id": 63,
  "guincho_id": 8,
  "duration_ms": 4,
  "message": "Ponto rejeitado por deslocamento incompatível com o intervalo.",
  "context": {
    "distance_m": 8450.2,
    "elapsed_s": 2.1,
    "calculated_speed_kmh": 14486.1,
    "accepted": false
  }
}
```

## 3.3 Alterações no logger

### Arquivos existentes a alterar

- `src/Services/Logger.php`
  - manter `Logger::log()` para compatibilidade;
  - criar `Logger::event()` com campos estruturados;
  - criar `Logger::startSpan()` e `Logger::endSpan()` ou um helper simples de duração;
  - gravar JSONL por dia: `logs/app-YYYY-MM-DD.jsonl`;
  - mascarar automaticamente senha, token, chave PIX, CPF, authorization e cookies.

- `src/Services/AuditTrailService.php`
  - adicionar `request_id`, `run_id`, `pedido_id`, `actor_type`, `actor_id` e `event_code`;
  - separar **erro técnico** de **evento de negócio**.

- `index.php`
  - gerar `request_id` no início de toda requisição;
  - devolver `X-Request-ID` no header;
  - incluir `request_id` nos handlers de exceção, shutdown e controller.

### Migration necessária

Alterar `app_logs` para receber, no mínimo:

```text
application, system, file, phase, code,
request_id, run_id, pedido_id, usuario_id, guincho_id,
duration_ms, ctx_json
```

Índices mínimos:

```text
(criado_em)
(system, criado_em)
(code, criado_em)
(request_id)
(run_id)
(pedido_id, criado_em)
```

### Códigos iniciais

| Código | Local principal | Significado |
|---|---|---|
| `RTR-001` | `index.php` | Rota não encontrada. |
| `RTR-002` | `index.php` | Controller ou action inexistente. |
| `AUTH-401` | `AuthService::requireAuth` | Sessão ausente ou expirada. |
| `AUTH-403` | Controllers | CSRF ou perfil inválido. |
| `POR-ING-001` | `ProofOfRoadService::ingestPoint` | Ponto recebido. |
| `POR-VAL-003` | `LocationValidationService::validatePoint` | Precisão insuficiente. |
| `POR-VAL-006` | `LocationValidationService::validatePoint` | Salto ou velocidade impossível. |
| `POR-GEO-001` | `GeofenceService::validateTransition` | Fora da geofence. |
| `EVD-UPL-001` | `EvidenceService::store` | Upload iniciado. |
| `EVD-UPL-006` | `EvidenceService::store` | MIME real inválido. |
| `CAN-CAL-001` | `CancellationCalculationService::calculate` | Cálculo iniciado. |
| `PAY-PIX-004` | `PixPayoutService::process` | Repasse duplicado bloqueado. |
| `E2E-RUN-001` | `PlaywrightRunnerService::enqueue` | Execução enfileirada. |
| `E2E-RUN-009` | runner Node | Processo encerrado por timeout. |

---

# 4. Segurança e segredos — prioridade P0

## Situação encontrada

- O pacote contém `.env` com credenciais reais ou aparentemente reais.
- `tests/e2e/constituicao-fluxo.spec.js` contém credenciais de banco e caminhos absolutos locais.
- `install/guinchafacil.sql` cria um administrador padrão com senha conhecida.
- Há arquivos de imagens de teste em `public/uploads` dentro do pacote.

## Edificação necessária

### Arquivos a alterar

- `.env`
  - não distribuir;
  - criar `.env.example` sem valores sensíveis.

- `.gitignore`
  - adicionar `.env`, logs, uploads, relatórios Playwright, traces e storage temporário.

- `tests/e2e/constituicao-fluxo.spec.js`
  - remover senha, usuário, banco, caminho XAMPP e executáveis fixos;
  - substituir tudo por variáveis de ambiente e fixtures.

- `install/guinchafacil.sql`
  - remover criação de admin com senha padrão;
  - exigir criação segura via `install/cli_install.php` ou primeiro acesso.

- `config.php`
  - falhar com mensagem segura quando variável obrigatória não estiver definida;
  - nunca registrar o valor da variável sensível.

## Ações operacionais imediatas

1. Rotacionar credenciais do banco presentes no pacote.
2. Rotacionar tokens de pagamentos, SMTP e simulador, caso tenham sido utilizados.
3. Remover o ZIP antigo de locais públicos e históricos compartilhados.
4. Verificar logs de acesso ao arquivo `.env`.
5. Confirmar que `.htaccess` ou configuração do servidor bloqueia arquivos iniciados por ponto.

## Debug

```text
SYSTEM=SECURITY
CLASS=ConfigSecurityService
FUNCTION=validateEnvironment
FILE=src/Services/Security/ConfigSecurityService.php
PHASE=scan_exposed_secrets
CODE=SEC-ENV-001
```

## Critério de aceite

- Nenhuma senha ou token aparece em repositório, ZIP ou relatório.
- O sistema inicia em homologação apenas com `.env` externo.
- O teste E2E roda em Linux e Windows sem caminho absoluto.
- O admin padrão não existe automaticamente.

---

# 5. Governança de banco e migrations — prioridade P0/P1

## Situação encontrada

`SimulationRun` e `SimulationStep` criam tabelas durante a execução através de `CREATE TABLE IF NOT EXISTS`. Isso mascara falhas de migration e pode exigir permissões DDL no usuário da aplicação.

## Edificação necessária

### Criar

- `install/migration_observability_v2.sql`
- `install/migration_por_v1.sql`
- `install/migration_evidencias_v1.sql`
- `install/migration_simulation_runner_v2.sql`
- `install/migration_payment_jobs_v1.sql`
- `install/migration_schema_versions.sql`

### Alterar

- `src/Models/SimulationRun.php`
- `src/Models/SimulationStep.php`

Remover `ensureTable()` da execução normal. O model deve falhar de forma explícita com:

```text
DBM-SIM-001 — tabela simulation_runs ausente; execute migration_simulation_runner_v2.sql
```

### Criar um registro de versões

```text
schema_migrations
- version
- filename
- checksum_sha256
- applied_at
- applied_by
- execution_ms
- success
- error_message
```

## Critério de aceite

- Usuário web do banco não precisa de `CREATE TABLE` ou `ALTER TABLE` em produção.
- `HealthService` compara migrations esperadas e aplicadas.
- Toda migration possui checksum e é idempotente quando aplicável.

---

# 6. Máquina de estados do pedido — prioridade P1

## Situação encontrada

As mudanças de status estão distribuídas entre:

- `GuinchoController::atualizarStatus()`;
- `Pedido::atualizarStatus()`;
- `Pedido::atribuirGuincho()`;
- `AdminController::pedidoAlterarStatus()`;
- `AdminController::pedidoAtribuir()`;
- `SimulationService::fase7AvancarStatus()`.

Isso permite que alguns fluxos contornem validações. O simulador, por exemplo, chama `Pedido::atualizarStatus()` diretamente e não testa foto, geofence ou controlador HTTP.

## Edificação necessária

### Criar

- `src/Services/Pedido/PedidoStateMachine.php`
- `src/Services/Pedido/PedidoTransitionService.php`
- `src/DTO/PedidoTransitionRequest.php`
- `src/DTO/PedidoTransitionResult.php`

### Transições permitidas

```text
aguardando_pagamento -> aguardando_guincho
aguardando_guincho   -> a_caminho
a_caminho            -> no_local
no_local             -> em_reboque
em_reboque           -> concluido
estados permitidos   -> cancelado, conforme ator e regra
```

Cada transição deve validar:

- ator e propriedade do pedido;
- status atual travado com `SELECT ... FOR UPDATE`;
- pré-condições do POR;
- evidência obrigatória;
- pagamento quando aplicável;
- idempotência;
- atualização de disponibilidade do guincho;
- evento de auditoria.

### Arquivos a refatorar

- `src/Controllers/GuinchoController.php`
  - `aceitar()`;
  - `atualizarStatus()`.

- `src/Controllers/AdminController.php`
  - `pedidoAlterarStatus()`;
  - `pedidoAtribuir()`;
  - `pedidoCancelar()`.

- `src/Models/Pedido.php`
  - `atribuirGuincho()`;
  - `atualizarStatus()` devem se tornar operações internas/restritas.

- `src/Services/SimulationService.php`
  - parar de alterar status diretamente.

## Defeitos atuais que devem ser corrigidos

### Cancelamento administrativo

`AdminController::pedidoCancelar()` chama:

```text
PedidoService::cancel($pedidoId, $clienteId)
```

sem informar `isAdmin=true`. Assim, o fluxo administrativo pode ser tratado como cancelamento do cliente, aplicando regra e autoria incorretas.

**Novo destino:**

```text
AdminCancellationService::cancelByAdmin()
```

### Atribuição administrativa

`AdminController::pedidoAtribuir()` chama `Pedido::atribuirGuincho()` e depois `Pedido::atualizarStatus()` sem lock transacional e sem checar disponibilidade do guincho.

## Debug

```text
SYSTEM=PEDIDO
CLASS=PedidoTransitionService
FUNCTION=transition
FILE=src/Services/Pedido/PedidoTransitionService.php
PHASE=validate_preconditions
CODE=ORD-TRN-004
```

## Critério de aceite

- Nenhum controller atualiza `pedidos.status` diretamente.
- Nenhuma transição inválida é possível por refresh, chamada manual ou concorrência.
- Duas tentativas simultâneas de aceite resultam em exatamente um vencedor.

---

# 7. Proof-of-Road persistente e antifraude — prioridade P1

## Situação encontrada

### Backend atual

```text
GuinchoController::atualizarLocalizacao()
src/Controllers/GuinchoController.php
```

Recebe somente `lat`, `lng` e CSRF, chama:

```text
Guincho::atualizarLocalizacao()
```

O histórico anterior é perdido.

### Frontend atual

```text
src/Views/guincho/atendimento.php
```

usa `navigator.geolocation.watchPosition()` e envia a posição com intervalo controlado, mas não envia:

- pedido;
- sequência;
- precisão;
- velocidade;
- direção;
- timestamp do dispositivo;
- identificador idempotente do ponto.

## 7.1 Modelo de dados

### Tabela `pedido_localizacoes`

Campos mínimos:

```text
id
pedido_id
guincho_id
usuario_id
fase
sequence_number
client_point_id
latitude
longitude
accuracy_m
speed_mps
heading_deg
device_timestamp
server_timestamp
received_at
previous_point_id
distance_raw_m
distance_validated_m
distance_accumulated_m
elapsed_ms
calculated_speed_kmh
street_name
street_source
match_confidence
is_valid
rejection_code
hash_previous
hash_current
request_id
run_id
```

Índices:

```text
UNIQUE (pedido_id, client_point_id)
UNIQUE (pedido_id, sequence_number)
(pedido_id, server_timestamp)
(guincho_id, server_timestamp)
(pedido_id, fase, is_valid)
```

### Tabela `pedido_percurso_resumos`

```text
pedido_id
fase
total_points
valid_points
rejected_points
started_at
last_point_at
duration_seconds
distance_raw_m
distance_validated_m
max_gap_seconds
max_speed_kmh
tracking_quality
last_street
last_latitude
last_longitude
updated_at
```

## 7.2 Serviços a criar

- `src/Services/POR/ProofOfRoadService.php`
  - `ingestPoint()`;
  - `getCurrentSnapshot()`;
  - `getTrail()`;
  - `getSummary()`.

- `src/Services/POR/LocationValidationService.php`
  - validar faixa de coordenadas;
  - precisão;
  - timestamp;
  - sequência;
  - duplicidade;
  - velocidade;
  - salto;
  - lacuna;
  - fase ativa.

- `src/Services/POR/DistanceAccumulatorService.php`
  - somar apenas pontos válidos;
  - ignorar ruído mínimo;
  - limitar distância por salto;
  - manter valor bruto e validado.

- `src/Services/POR/GeofenceService.php`
  - `isNearOrigin()`;
  - `isNearDestination()`;
  - `validateTransition()`.

- `src/Services/POR/MapMatchingService.php`
  - interface para provedor de map matching;
  - timeout e circuit breaker;
  - cache;
  - fallback sem bloquear ingestão.

- `src/Services/POR/StreetResolutionService.php`
  - resolver rua apenas em pontos relevantes;
  - não fazer reverse geocode em cada atualização;
  - cache geográfico por célula.

- `src/Models/PedidoLocalizacao.php`
- `src/Models/PedidoPercursoResumo.php`

## 7.3 Endpoint

### Substituir ou evoluir

```text
POST /guincho/localizacao
GuinchoController::atualizarLocalizacao()
```

Payload:

```json
{
  "csrf_token": "...",
  "pedido_id": 63,
  "client_point_id": "uuid",
  "sequence": 45,
  "latitude": -22.901,
  "longitude": -43.191,
  "accuracy_m": 12.4,
  "speed_mps": 8.2,
  "heading_deg": 175,
  "device_timestamp": 1783874518123
}
```

Resposta:

```json
{
  "ok": true,
  "accepted": true,
  "point_id": 9981,
  "sequence": 45,
  "distance_accumulated_m": 7134.8,
  "tracking_quality": "good",
  "current_street": "Linha Vermelha",
  "request_id": "req_..."
}
```

## 7.4 Regras antifraude mínimas

Configurações administrativas:

```text
por_max_accuracy_m
por_max_speed_kmh
por_max_gap_seconds
por_min_point_distance_m
por_arrival_radius_m
por_destination_radius_m
por_min_valid_points_arrival
por_min_valid_points_delivery
por_photo_gps_max_age_seconds
por_allow_manual_review
```

Pontos suspeitos não devem ser apagados. Devem ser gravados como rejeitados com motivo.

## 7.5 Bloqueios de status

### `a_caminho -> no_local`

Exigir:

- ponto válido recente;
- distância da origem dentro da geofence;
- quantidade mínima de pontos válidos;
- rastreamento sem lacuna crítica.

### `no_local -> em_reboque`

Exigir:

- geofence de origem;
- evidência de coleta válida;
- nonce de evidência ainda válido.

### `em_reboque -> concluido`

Exigir:

- geofence de destino;
- distância e tempo plausíveis;
- evidência de entrega válida;
- rastreio recente.

## Debug por função

| Fase | Classe/Função | Arquivo | Código principal |
|---|---|---|---|
| Recepção | `ProofOfRoadService::ingestPoint` | `src/Services/POR/ProofOfRoadService.php` | `POR-ING-001` |
| Auth/pedido | `ProofOfRoadService::authorize` | mesmo arquivo | `POR-AUT-002` |
| Precisão | `LocationValidationService::validateAccuracy` | `src/Services/POR/LocationValidationService.php` | `POR-VAL-003` |
| Sequência | `LocationValidationService::validateSequence` | mesmo arquivo | `POR-VAL-004` |
| Tempo | `LocationValidationService::validateTimestamp` | mesmo arquivo | `POR-VAL-005` |
| Salto | `LocationValidationService::validateJump` | mesmo arquivo | `POR-VAL-006` |
| Distância | `DistanceAccumulatorService::accumulate` | `src/Services/POR/DistanceAccumulatorService.php` | `POR-DST-001` |
| Geofence | `GeofenceService::validateTransition` | `src/Services/POR/GeofenceService.php` | `POR-GEO-001` |
| Matching | `MapMatchingService::matchSegment` | `src/Services/POR/MapMatchingService.php` | `POR-MAT-001` |
| Rua | `StreetResolutionService::resolve` | `src/Services/POR/StreetResolutionService.php` | `POR-STR-001` |

## Critério de aceite

- É possível reconstruir a sequência completa do atendimento.
- Saltos e pontos ruins ficam registrados e não entram na cobrança.
- Chegada e conclusão são bloqueadas fora da geofence.
- O admin vê trilha, pontos rejeitados, qualidade e distância validada.

---

# 8. Evidências de coleta e entrega — prioridade P1

## Situação encontrada

`GuinchoController::atualizarStatus()` chama `processarUpload()` e grava somente o nome do arquivo em `pedidos.foto_plataforma` ou `pedidos.foto_destino`.

Não existe metadado técnico suficiente para provar:

- quem enviou;
- onde estava;
- qual ponto GPS foi associado;
- quando o servidor autorizou a captura;
- hash do arquivo;
- MIME real;
- se o mesmo arquivo foi reutilizado.

## Edificação necessária

### Criar tabela `pedido_evidencias`

```text
id
pedido_id
guincho_id
tipo = coleta | entrega
status = pending | accepted | rejected
nonce
nonce_expires_at
location_point_id
latitude
longitude
accuracy_m
server_captured_at
client_captured_at
original_filename
stored_filename
storage_path
mime_type
size_bytes
width
height
sha256
validation_code
created_at
request_id
run_id
```

### Criar serviços

- `src/Services/Evidence/EvidenceNonceService.php`
  - emitir nonce curto e de uso único.

- `src/Services/Evidence/EvidenceUploadService.php`
  - validar `finfo`/MIME real;
  - tamanho e dimensões;
  - reprocessar imagem para remover payload oculto e EXIF sensível;
  - gerar nome aleatório forte;
  - calcular SHA-256;
  - armazenar fora do diretório público.

- `src/Services/Evidence/EvidenceValidationService.php`
  - vincular ao ponto GPS recente;
  - verificar geofence;
  - impedir reutilização de hash no mesmo ou em outros pedidos;
  - validar fase.

- `src/Controllers/ArquivoController.php`
  - servir evidência somente após autorização;
  - não expor caminho físico.

### Alterar

- `GuinchoController::atualizarStatus()`
  - não processar upload diretamente;
  - receber `evidence_id` já validado;
  - delegar transição à máquina de estados.

## Debug

```text
GUINCHAFACIL | EVIDENCIA | EvidenceUploadService | store |
src/Services/Evidence/EvidenceUploadService.php | validate_mime | EVD-UPL-006
```

## Critério de aceite

- Arquivo renomeado para `.jpg` com conteúdo inválido é rejeitado.
- Foto de coleta fora da origem é rejeitada ou enviada para revisão manual.
- Foto de entrega sem ponto GPS recente não conclui o pedido.
- O admin vê hash, localização, horário e resultado de validação.

---

# 9. Rota, ruas e ETA em português — prioridade P1/P2

## Situação encontrada

Os arquivos:

- `src/Views/cliente/pedidostatus.php`;
- `src/Views/guincho/atendimento.php`;
- `src/Views/admin/pedidodetalhe.php`;

carregam:

```text
leaflet-routing-machine@latest
```

Isso torna a versão imprevisível. O painel pode exibir instruções em inglês.

## Edificação necessária

### Dependências

- fixar versão do Leaflet e Leaflet Routing Machine;
- preferencialmente armazenar os assets dentro de `public/vendor/`;
- eliminar `@latest`;
- criar localização `pt-BR` controlada pelo projeto.

### Criar

- `public/assets/js/routing/route-manager.js`
- `public/assets/js/routing/formatter-pt-br.js`
- `public/assets/js/routing/trail-layer.js`
- `src/Services/Routing/RouteService.php`
- `src/Services/Routing/RouteSnapshotService.php`

### Tela do cliente

Durante `a_caminho`:

```text
posição atual -> origem
```

Durante `no_local`:

```text
parado na origem / preparação
```

Durante `em_reboque`:

```text
posição atual -> destino
```

Sempre exibir separadamente:

1. trilha efetivamente percorrida;
2. rota prevista restante;
3. rua atual;
4. distância restante;
5. ETA;
6. horário da última atualização;
7. qualidade do rastreamento.

### API de status

Alterar:

```text
ClienteController::pedidoStatusJson()
src/Controllers/ClienteController.php
```

para retornar:

```text
current_location
current_street
tracking_quality
last_location_at
route_remaining_distance_m
route_remaining_duration_s
trail_polyline ou pontos paginados
```

Não devolver milhares de pontos em cada polling. Usar `since_point_id`.

## Debug

| Problema | Classe/Função | Arquivo | Código |
|---|---|---|---|
| Falha de rota | `RouteService::calculate` | `src/Services/Routing/RouteService.php` | `RTE-CAL-001` |
| Timeout provedor | `RouteService::requestProvider` | mesmo arquivo | `RTE-PRV-004` |
| Tradução ausente | `RouteFormatterPtBr::formatInstruction` | `public/assets/js/routing/formatter-pt-br.js` | `RTE-I18N-001` |
| Rua atual | `StreetResolutionService::resolve` | POR | `POR-STR-001` |
| Trilha incremental | `ClienteController::pedidoTrailJson` | controller | `RTE-TRL-001` |

## Critério de aceite

- Nenhum texto `Head`, `Continue`, `Turn`, `Take`, `Keep` ou `Merge` aparece na interface.
- O cliente vê rua atual e horário da atualização.
- A linha percorrida não é confundida com a rota prevista.
- A interface funciona em desktop e mobile.

---

# 10. Cancelamento constitucional por percurso e tempo — prioridade P1/P2

## Situação encontrada

`CancelamentoService::calcularTaxaCliente()` usa atualmente:

```text
max(taxa fixa, percentual do custo)
```

com bloqueio por proximidade calculada entre a posição atual do guincho e a origem.

Isso não mede:

- quilômetros efetivamente percorridos;
- duração da mobilização;
- qualidade do rastreamento;
- pontos rejeitados;
- percurso antes de retornar ou desviar.

A migration `migration_add_distancia_percorrida.sql` existe, mas o dado não é alimentado pelo POR.

## Edificação necessária

### Criar

- `src/Services/Cancelamento/CancellationCalculationService.php`
- `src/Services/Cancelamento/CancellationSnapshotService.php`
- `src/DTO/CancellationQuote.php`
- `src/Models/PedidoCancelamento.php`

### Snapshot obrigatório

No preview e novamente dentro da transação de cancelamento:

```text
pedido_id
status
actor_type
accepted_at
cancel_requested_at
mobilization_seconds
distance_raw_m
distance_validated_m
tracking_quality
valid_points
rejected_points
last_point_at
last_latitude
last_longitude
last_street
planned_distance_to_origin_m
fee_fixed
fee_distance
fee_time
fee_total
refund_total
calculation_version
calculation_json
```

### Regra

A fórmula financeira final deve ser copiada literalmente da Constituição vigente. A implementação deve receber parâmetros de configuração e possuir versão, por exemplo:

```text
cancel_formula_version = POR-TIME-DISTANCE-V1
```

O backend nunca deve confiar no valor enviado pelo modal. O preview é informativo; o cancelamento recalcula sob lock.

### Alterar

- `CancelamentoService::previewCliente()`;
- `CancelamentoService::calcularTaxaCliente()`;
- `CancelamentoService::cancelarPorCliente()`;
- `EstornoService::estornar()`;
- `ClienteController::pedidoStatusJson()`;
- `src/Views/cliente/pedidostatus.php`.

### Corrigir cancelamento administrativo

Criar:

```text
AdminCancellationService::cancelByAdmin()
```

com justificativa, senha confirmada, autoria `admin`, auditoria, regra de estorno explícita e sem fingir ser cliente.

## Debug

```text
GUINCHAFACIL | CANCELAMENTO | CancellationCalculationService | calculate |
src/Services/Cancelamento/CancellationCalculationService.php | calculate_distance_fee | CAN-CAL-003
```

## Critério de aceite

- O valor exibido no modal é reproduzível pelo snapshot gravado.
- O valor não muda silenciosamente entre preview e confirmação; se mudar, a API informa o novo valor e exige nova confirmação.
- Pontos rejeitados pelo POR não geram cobrança.
- Estorno parcial é idempotente.

---

# 11. Dispatch, ranking e aceite — prioridade P2

## Situação encontrada

`GuinchoController::pedidosDisponiveis()` utiliza apenas `RankingService::calcularScore()`, o que está funcional como cálculo simples.

Porém `RankingService::buscarGuinchosDisponiveis()` referencia campos antigos/incompatíveis, como:

```text
g.latitude
g.longitude
g.veiculo_placa
g.veiculo_modelo
raio_busca
```

Enquanto o schema atual usa nomes como:

```text
lat_atual
lng_atual
placa_guincho
raio_atual_km
```

Esse método deve ser removido ou corrigido para não virar uma armadilha futura.

## Edificação necessária

- centralizar matching em `DispatchService`;
- usar uma única consulta/modelo de guincho disponível;
- validar GPS recente, não apenas coordenada existente;
- bloquear guincho já ocupado;
- registrar score detalhado;
- manter aceite com lock transacional;
- corrigir atribuição manual do admin.

### Criar

- `src/Services/Dispatch/DispatchService.php`
- `src/Services/Dispatch/DispatchScoreService.php`
- `src/DTO/DispatchCandidate.php`

## Debug

```text
SYSTEM=DISPATCH
CLASS=DispatchService
FUNCTION=findCandidates
FILE=src/Services/Dispatch/DispatchService.php
PHASE=filter_stale_location
CODE=DSP-FLT-004
```

## Critério de aceite

- Guincho sem posição recente não recebe pedido por proximidade fictícia.
- Dois guinchos não aceitam o mesmo pedido.
- Score, distância, filtros e rejeições aparecem no debug do pedido.

---

# 12. Chat e tempo real — prioridade P2

## Situação encontrada

O polling atual funciona. Existe `SseController.php`, mas ele não está registrado em `index.php` nem utilizado por `EventSource` no frontend.

Também há bug potencial em:

```text
public/assets/js/app.js
AjaxService::post()
AjaxService::get()
```

`window.apiFetch()` já retorna o JSON decodificado, mas essas funções tentam chamar `.json()` novamente.

## Decisão recomendada

Para finalizar o POR sem aumentar o risco:

1. manter polling inicialmente;
2. instrumentar polling e falhas;
3. remover ou desativar explicitamente o código morto do SSE;
4. integrar SSE apenas depois do POR e do Playwright estarem verdes.

## Alterações

- corrigir `AjaxService`;
- remover `ChatManager` e `StatusPoller` esqueletos se não forem usados;
- padronizar um único cliente HTTP;
- incluir `last_message_id` e `since_point_id`;
- registrar latência, falhas consecutivas e sessão expirada.

## Debug

```text
SYSTEM=CHAT
CLASS=ChatService
FUNCTION=sendMessage
FILE=src/Services/ChatService.php
PHASE=insert_message
CODE=CHT-SND-001
```

## Critério de aceite

- Mensagem não duplica após retry.
- Polling para quando a sessão expira.
- Cliente e guincheiro recebem a mensagem dentro do SLA configurado.

---

# 13. Pagamentos, estornos e repasse PIX — prioridade P1/P2

## Situação encontrada

`GuinchoController::atualizarStatus()` executa lógica de conclusão, criação de pagamento, guard PIX, transferência, confirmação e notificações dentro da mesma requisição HTTP da mudança de status.

Riscos:

- timeout no navegador;
- conclusão repetida;
- resposta perdida após transferência aprovada;
- notificação derrubando o fluxo;
- acoplamento entre status operacional e gateway financeiro.

## Edificação necessária

### Criar fila transacional

- `payment_jobs`;
- `payment_job_attempts`;
- `PaymentJobService`;
- `PixPayoutWorker`;
- idempotency key por `pedido_id + operação`.

### Novo fluxo

```text
concluir pedido
-> commit da transição e evidência
-> criar job de repasse
-> responder sucesso operacional
-> worker processa PIX
-> admin acompanha status
```

### Alterar

- `GuinchoController::atualizarStatus()`;
- `Pagamento::prepararRepassePix()`;
- `PixService::transferir()`;
- `tools/cron_reprocessar_pix.php`.

## Debug

```text
SYSTEM=PAGAMENTO
CLASS=PixPayoutWorker
FUNCTION=processJob
FILE=src/Workers/PixPayoutWorker.php
PHASE=gateway_request
CODE=PAY-PIX-006
```

## Critério de aceite

- Refresh ou retry não gera segundo PIX.
- Falha do gateway não desfaz a conclusão do atendimento.
- Admin visualiza fila, tentativas, resposta mascarada e próxima tentativa.
- Playwright executa apenas sandbox/dry-run.

---

# 14. Logs e diagnóstico no admin — prioridade P1

## Situação encontrada

A tela `src/Views/admin/logs.php` exibe:

- tabela simples de `app_logs`;
- webhooks;
- últimas 200 linhas do arquivo.

Faltam filtros e correlação.

## Edificação necessária

### Alterar

- `AdminController::logs()`;
- `src/Views/admin/logs.php`.

### Filtros

```text
período
nível
system
class
function
file
phase
code
request_id
run_id
pedido_id
usuario_id
guincho_id
texto
```

### Visualização

- cards por evento;
- expandir `context` formatado;
- copiar identificador técnico;
- abrir pedido relacionado;
- abrir execução Playwright relacionada;
- gráfico de erros por sistema;
- gráfico de códigos mais frequentes;
- latência P50/P95 por operação;
- exportar JSONL/CSV mascarado.

## Critério de aceite

Ao receber um erro, o admin encontra em menos de um minuto:

```text
sistema -> classe -> função -> arquivo -> fase -> código -> pedido -> execução
```

---

# 15. Sistema Playwright dentro do dashboard administrativo

## 15.1 O que já existe

### Simulador PHP interno

- rota: `/admin/simulador`;
- controller: `AdminController::simulador()` e `AdminController::simularExecutar()`;
- engine: `SimulationService::run()`;
- persistência: `SimulationRun` e `SimulationStep`;
- view: `src/Views/admin/simulacao.php`;
- resultado: `src/Views/admin/simulacao_resultado.php`.

Esse simulador é útil para:

- testar services;
- testar banco;
- testar guards financeiros;
- testar chat por model/service;
- testar avaliação.

Ele **não testa**:

- navegador;
- DOM;
- clique real;
- duas sessões independentes;
- permissões de GPS;
- `watchPosition`;
- mapa e rotas;
- polling visual;
- upload real do formulário;
- modal de cancelamento;
- sessão expirada na interface;
- CSP e assets.

### Playwright presente

Há arquivos em `tests/e2e/`, mas o pacote não contém configuração Node reproduzível. O teste constitucional atual também acessa o banco diretamente com credenciais e caminhos locais. Ele deve ser substituído, não apenas remendado.

## 15.2 Arquitetura correta

O Playwright será controlado pelo dashboard, mas **não deve executar dentro da requisição PHP**.

### Fluxo

```text
Admin clica Executar
-> PHP valida permissão e ambiente
-> grava job como queued
-> responde imediatamente com run_id
-> worker separado reivindica o job
-> worker executa Playwright
-> reporter grava fases e artefatos
-> dashboard consulta status
-> admin abre trace, vídeo, screenshot e logs
```

### Motivo

Executar `npx playwright test` diretamente em `AdminController`:

- mantém worker PHP ocupado;
- pode estourar timeout;
- amplia risco de command injection;
- dificulta cancelamento;
- mistura permissões do servidor web com Chromium;
- não suporta fila nem heartbeat.

## 15.3 Diretórios novos

```text
qa/
├── package.json
├── package-lock.json
├── playwright.config.ts
├── tsconfig.json
├── fixtures/
│   ├── auth.fixture.ts
│   ├── gps.fixture.ts
│   ├── evidence.fixture.ts
│   ├── api.fixture.ts
│   └── test-data.fixture.ts
├── reporters/
│   └── guinchafacil-reporter.ts
├── suites/
│   ├── smoke.spec.ts
│   ├── atendimento-completo.spec.ts
│   ├── por-antifraude.spec.ts
│   ├── cancelamento.spec.ts
│   ├── pagamento-sandbox.spec.ts
│   ├── sessao-seguranca.spec.ts
│   ├── concorrencia-aceite.spec.ts
│   └── upload-seguranca.spec.ts
├── helpers/
│   ├── route-generator.ts
│   ├── log-context.ts
│   └── assertions.ts
└── fixtures-data/
    ├── coleta.jpg
    ├── entrega.jpg
    └── arquivo-invalido.jpg
```

### PHP/admin

```text
src/Controllers/AdminQaController.php
src/Services/QA/PlaywrightRunnerService.php
src/Services/QA/QaRunService.php
src/Services/QA/QaArtifactService.php
src/Models/QaRun.php
src/Models/QaStep.php
src/Models/QaArtifact.php
src/Views/admin/qa/index.php
src/Views/admin/qa/run.php
src/Views/admin/qa/artifact.php
tools/qa_worker.php
```

O `AdminController` já está grande. Recomenda-se controller separado.

## 15.4 Rotas administrativas

```text
GET  /admin/qa
POST /admin/qa/run
GET  /admin/qa/run/{run_id}
GET  /admin/qa/run/{run_id}/status
POST /admin/qa/run/{run_id}/cancel
GET  /admin/qa/run/{run_id}/artifact/{artifact_id}
POST /admin/qa/run/{run_id}/cleanup
```

Rotas internas do runner, caso o worker seja remoto:

```text
POST /internal/qa/jobs/claim
POST /internal/qa/jobs/{run_id}/heartbeat
POST /internal/qa/jobs/{run_id}/step
POST /internal/qa/jobs/{run_id}/artifact
POST /internal/qa/jobs/{run_id}/finish
```

Essas rotas devem usar assinatura HMAC, token rotacionável, timestamp e allowlist de IP quando possível.

## 15.5 Banco

### Evoluir `simulation_runs`

Adicionar:

```text
engine = php_internal | playwright
suite
requested_by
requested_at
target_environment
target_url
browser
viewport
locale
timezone
status = queued | running | passed | failed | cancelled | timeout
worker_id
worker_pid
heartbeat_at
started_at
finished_at
exit_code
app_version
git_commit
configuration_json
summary_json
```

### Evoluir `simulation_steps`

Adicionar:

```text
system
class
function
file
phase
code
status
duration_ms
expected_json
actual_json
error_message
stack_trace
started_at
finished_at
```

### Criar `simulation_artifacts`

```text
id
run_id
step_id
kind = screenshot | video | trace | html_report | stdout | stderr | json | network
filename
private_path
mime_type
size_bytes
sha256
created_at
```

### Criar `simulation_job_events`

Fila e heartbeat:

```text
run_id
event
worker_id
payload_json
created_at
```

## 15.6 Tela do dashboard

Na seção **Governança**, manter:

```text
Simulador Oficial
```

com duas abas:

### Aba 1 — Simulação Interna PHP

- botão executar;
- PIX dry-run;
- fases internas;
- sem navegador.

### Aba 2 — Testes Reais Playwright

Campos:

- suíte;
- ambiente alvo;
- navegador;
- desktop/mobile;
- rota de teste;
- velocidade simulada;
- intervalo de pontos;
- mensagens cliente e guincho;
- imagem de coleta;
- imagem de entrega;
- modo pagamento obrigatoriamente sandbox/dry-run;
- gravar vídeo;
- gravar trace;
- limpar dados ao final;
- parar na primeira falha.

### Execução ao vivo

```text
[OK] E2E-AUTH-001 Cliente autenticado
[OK] E2E-ORD-001 Pedido criado #123
[OK] E2E-DSP-001 Guincho visualizou o pedido
[OK] E2E-DSP-002 Guincho aceitou o pedido
[OK] E2E-CHT-001 Mensagem cliente -> guincho
[OK] E2E-CHT-002 Mensagem guincho -> cliente
[14/32] E2E-POR-001 Deslocamento para coleta
[OK] E2E-GEO-001 Entrada na geofence da origem
[OK] E2E-EVD-001 Comprovante de coleta aceito
[21/47] E2E-POR-002 Deslocamento para destino
[OK] E2E-GEO-002 Entrada na geofence do destino
[OK] E2E-EVD-002 Comprovante de entrega aceito
[OK] E2E-ORD-007 Pedido concluído
[OK] E2E-PAY-001 Repasse dry-run enfileirado
```

Cada fase abre:

- sistema;
- classe;
- função;
- arquivo;
- fase;
- código;
- esperado;
- encontrado;
- screenshot;
- request/response;
- trace.

## 15.7 Worker

### `tools/qa_worker.php`

Responsabilidades:

1. verificar `APP_ENV` e `SIMULATION_ENABLED`;
2. reivindicar um job com lock;
3. montar configuração permitida;
4. iniciar processo Node com argumentos de allowlist;
5. registrar PID;
6. capturar stdout e stderr;
7. atualizar heartbeat;
8. encerrar em timeout;
9. importar resultado e artefatos;
10. limpar processo e dados temporários.

### Segurança do worker

- usuário Linux sem acesso de escrita ao código;
- sem shell arbitrário vindo do formulário;
- suíte escolhida por enum/allowlist;
- URL alvo em allowlist;
- concorrência limitada;
- timeout rígido;
- diretório por `run_id`;
- artefatos fora de `public/`;
- nenhum token em stdout;
- PIX real bloqueado por código e configuração.

## 15.8 Reporter Playwright

### `qa/reporters/guinchafacil-reporter.ts`

Deve capturar:

- `onBegin`;
- `onTestBegin`;
- `onStepBegin`;
- `onStepEnd`;
- `onTestEnd`;
- `onEnd`.

Cada `test.step()` deve usar código estável:

```typescript
await test.step('E2E-POR-003 | deslocamento até a coleta', async () => {
    // ...
});
```

O reporter grava JSONL local e envia/importa para `simulation_steps`.

## 15.9 Uso de duas sessões

O atendimento completo deve utilizar dois `BrowserContext` independentes:

```text
contextCliente
contextGuincho
```

Opcionalmente um terceiro:

```text
contextAdmin
```

Isso impede compartilhamento de cookie e reproduz dois navegadores reais.

## 15.10 GPS programático

Criar helper:

```text
qa/helpers/route-generator.ts
```

Responsabilidades:

- interpolar pontos entre coordenadas;
- controlar velocidade;
- inserir curvas/pontos intermediários;
- chamar `contextGuincho.setGeolocation()`;
- aguardar confirmação do endpoint POR;
- registrar sequência e rua recebida.

No ambiente de teste, o intervalo do frontend deve ser configurável, por exemplo:

```text
POR_UPLOAD_INTERVAL_MS=250
```

Nunca alterar o comportamento de produção por query string pública.

## 15.11 Upload de evidências

Usar `locator.setInputFiles()` nos inputs reais da tela do guincheiro.

Validar:

- preview;
- envio multipart;
- evidência no banco;
- hash;
- localização associada;
- imagem exibida ao cliente;
- bloqueio de arquivo inválido.

## 15.12 Artefatos obrigatórios

Em falha:

- screenshot cliente;
- screenshot guincho;
- vídeo;
- `trace.zip`;
- HTML report;
- stdout;
- stderr;
- console do navegador;
- erros JS;
- requisições com falha;
- logs do backend filtrados por `run_id`;
- snapshot do pedido, POR, evidências e pagamento.

A documentação oficial do Playwright oferece BrowserContext isolado, geolocalização programática, upload por `setInputFiles`, HTML reporter e Trace Viewer. Esses recursos devem ser usados como base do runner.

## 15.13 Suítes obrigatórias

### `smoke`

- login cliente;
- login guincho;
- login admin;
- dashboards;
- health check.

### `atendimento-completo`

- criar pedido;
- aceitar;
- chat bilateral;
- deslocar até origem;
- registrar ruas;
- foto de coleta;
- deslocar até destino;
- foto de entrega;
- concluir;
- avaliação.

### `por-antifraude`

- salto de dezenas de quilômetros em segundos;
- timestamp antigo;
- sequência repetida;
- ponto duplicado;
- precisão ruim;
- GPS indisponível;
- tentativa de chegada fora da geofence;
- foto sem GPS recente;
- conclusão fora do destino;
- lacuna longa de rastreamento.

### `cancelamento`

- antes do pagamento;
- aguardando guincho;
- após aceite e sem deslocamento;
- após percurso parcial;
- próximo da origem;
- após chegada;
- preview versus cálculo final;
- estorno parcial dry-run;
- cancelamento do guincho;
- cancelamento administrativo.

### `concorrencia-aceite`

- dois guinchos tentam aceitar o mesmo pedido;
- um vence;
- o outro recebe conflito controlado;
- pedido não fica em estado inconsistente.

### `pagamento-sandbox`

- pagamento aprovado;
- webhook duplicado;
- repasse enfileirado uma vez;
- retry;
- falha e reprocessamento;
- nenhum gateway real.

### `sessao-seguranca`

- expiração durante polling;
- expiração durante chat;
- expiração durante upload;
- retorno ao login preservado;
- CSRF inválido.

### `upload-seguranca`

- MIME falso;
- tamanho excessivo;
- arquivo repetido;
- imagem corrompida;
- upload fora da fase.

## 15.14 Critério de aceite do sistema Playwright

- Admin enfileira sem travar a página.
- O dashboard mostra heartbeat e fases em tempo real.
- É possível cancelar uma execução.
- Falha indica classe, função, arquivo, fase e código.
- Trace e screenshots são acessíveis apenas ao admin.
- O runner não executa contra produção.
- O runner não contém credenciais no código.
- Todos os dados de teste são marcados e limpos.

---

# 16. Operação, cron, health e documentos legais

## Pendências encontradas

- scripts `tools/cron_*.php` existem, mas o agendamento não é comprovado pelo projeto;
- `termos-servico.php` contém placeholder de meios de pagamento;
- `politica-privacidade.php` contém placeholder de endereço;
- `SseController.php` está sem integração;
- assets externos usam CSP com `unsafe-inline` e `unsafe-eval`;
- algumas dependências são carregadas por CDN;
- uploads de teste estão dentro do pacote público.

## Edificação necessária

### Cron

Registrar heartbeat de cada job em tabela:

```text
cron_jobs
cron_executions
```

Health deve alertar quando um cron estiver atrasado.

### Legal

Preencher e versionar:

- meios de pagamento;
- endereço e controlador LGPD;
- política de retenção de localização;
- finalidade do POR;
- acesso à trilha pelo usuário;
- prazo de retenção das evidências;
- procedimento de contestação de taxa.

### Retenção

Criar política explícita para:

- pontos GPS;
- fotos;
- chats;
- logs;
- traces e vídeos de teste.

### Dependências frontend

- fixar versões;
- armazenar localmente quando possível;
- reduzir CSP gradualmente;
- remover `unsafe-eval` quando a dependência permitir.

---

# 17. Matriz de localização rápida de defeitos

| Sintoma | Sistema | Classe/Função | Arquivo |
|---|---|---|---|
| GPS não é enviado | POR frontend | `iniciarGps` / `enviarGps` | `src/Views/guincho/atendimento.php` ou novo `public/assets/js/por-tracker.js` |
| API rejeita GPS | POR API | `GuinchoController::atualizarLocalizacao` / `ProofOfRoadService::ingestPoint` | `src/Controllers/GuinchoController.php`, `src/Services/POR/ProofOfRoadService.php` |
| Ponto rejeitado | POR validação | `LocationValidationService::validatePoint` | `src/Services/POR/LocationValidationService.php` |
| Distância errada | POR distância | `DistanceAccumulatorService::accumulate` | `src/Services/POR/DistanceAccumulatorService.php` |
| Chegada bloqueada | Geofence | `GeofenceService::validateTransition` | `src/Services/POR/GeofenceService.php` |
| Rua não aparece | Routing/POR | `StreetResolutionService::resolve` | `src/Services/POR/StreetResolutionService.php` |
| Instrução em inglês | Routing frontend | `RouteFormatterPtBr::formatInstruction` | `public/assets/js/routing/formatter-pt-br.js` |
| Cliente não atualiza | Pedido status | `ClienteController::pedidoStatusJson` | `src/Controllers/ClienteController.php` |
| Foto rejeitada | Evidência | `EvidenceUploadService::store` | `src/Services/Evidence/EvidenceUploadService.php` |
| Foto não aparece | Evidência/status | `ClienteController::pedidoStatusJson` | `src/Controllers/ClienteController.php` |
| Status mudou indevidamente | Pedido | `PedidoTransitionService::transition` | `src/Services/Pedido/PedidoTransitionService.php` |
| Taxa divergente | Cancelamento | `CancellationCalculationService::calculate` | `src/Services/Cancelamento/CancellationCalculationService.php` |
| Estorno falhou | Pagamento | `EstornoService::estornar` | `src/Services/EstornoService.php` |
| PIX duplicado/bloqueado | Pagamento | `PixPayoutWorker::processJob` | `src/Workers/PixPayoutWorker.php` |
| Chat não envia | Chat | `ClienteController::chatEnviar` ou `GuinchoController::chatEnviar` | controllers correspondentes |
| Chat não lista | Chat | `Chat::listarPorPedido` | `src/Models/Chat.php` |
| Pedido não aparece ao guincho | Dispatch | `GuinchoController::pedidosDisponiveis` / `DispatchService::findCandidates` | controller/service |
| Dois guinchos aceitaram | Dispatch/Pedido | `PedidoTransitionService::accept` | `src/Services/Pedido/PedidoTransitionService.php` |
| Playwright não inicia | E2E runner | `PlaywrightRunnerService::start` | `src/Services/QA/PlaywrightRunnerService.php` |
| Teste travado | E2E worker | `qa_worker.php` fase heartbeat/timeout | `tools/qa_worker.php` |
| Passo falhou | E2E reporter | `GuinchaFacilReporter::onStepEnd` | `qa/reporters/guinchafacil-reporter.ts` |
| Sessão expirou | Auth | `AuthService::requireAuth` / `SessionManager` | `src/Services/AuthService.php`, `public/assets/js/session-manager.js` |

---

# 18. Entregas sugeridas por pacote

## Pacote 1 — Segurança + observabilidade

- rotação e remoção de segredos;
- `.env.example` e `.gitignore`;
- Logger v2;
- migration de logs;
- filtros do admin;
- request ID;
- códigos de erro.

## Pacote 2 — Banco + máquina de estados

- migrations versionadas;
- `PedidoTransitionService`;
- correção do cancelamento administrativo;
- correção de atribuição administrativa;
- testes de concorrência.

## Pacote 3 — POR v1

- tabelas;
- ingestão;
- validações;
- distância;
- geofence;
- resumo;
- tela administrativa da trilha.

## Pacote 4 — Evidências + rota pt-BR

- nonce e upload seguro;
- vínculo com GPS;
- formatter pt-BR;
- rua, ETA e trilha incremental para cliente.

## Pacote 5 — Cancelamento + financeiro

- snapshot;
- fórmula constitucional versionada;
- estorno idempotente;
- fila de repasse;
- reprocessamento.

## Pacote 6 — QA Playwright no admin

- Node/Playwright configurado;
- fila e worker;
- reporter;
- dashboard;
- suites completas;
- screenshots, vídeos, trace e HTML report.

## Pacote 7 — Operação final

- crons;
- retenção;
- legal;
- CSP;
- dependências locais;
- limpeza de código morto;
- checklist de produção.

---

# 19. Definição final de pronto

O GuinchaFácil estará pronto para homologação final quando:

1. nenhuma credencial estiver dentro do pacote;
2. toda mudança de status passar por uma única máquina de estados;
3. o POR registrar e validar o percurso completo;
4. chegada, coleta e entrega dependerem de geofence e evidência;
5. o cliente visualizar rua, ETA e trilha real em português;
6. o cancelamento usar snapshot de distância e tempo comprovados;
7. pagamentos e PIX forem idempotentes e assíncronos;
8. o admin localizar qualquer falha por sistema, classe, função, arquivo, fase e código;
9. o dashboard executar as suítes Playwright sem travar o PHP;
10. toda falha Playwright gerar trace, screenshots, logs e contexto do pedido;
11. crons e health check comprovarem execução;
12. documentos legais explicarem rastreamento, retenção e contestação;
13. as suítes obrigatórias estiverem verdes em homologação.

