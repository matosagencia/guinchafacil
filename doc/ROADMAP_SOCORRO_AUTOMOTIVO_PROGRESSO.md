# ROADMAP — Expansão para Socorro Automotivo — Progresso

**Fonte:** ROADMAP fornecido pelo usuário em 22/07/2026 (20 seções, 10 fundamentos, 11 etapas).
**Este documento:** acompanha o que foi de fato implementado, etapa por etapa, para não precisar reler o roadmap inteiro nem reavaliar o código do zero a cada sessão.

---

## ETAPA 0 — Congelamento e baseline

**Status: PARCIAL — feito o que dava para fazer sem executar PHP.**

O que foi verificado lendo o código (sem poder rodar):
- Mecanismo de migração é auto-descoberta: `install/migrate_runtime.php::applyPendingSqlMigrations()` varre `install/migration_*.sql` (ordenação natural), aplica pendentes e registra checksum em `schema_migrations`. Não precisa de registro manual — só criar o arquivo com o prefixo certo.
- `guinchos` e `pedidos` (schema base) foram lidos e mapeados antes de qualquer alteração — ver `install/migrate.php` linhas ~444 (guinchos) e ~476 (pedidos).
- Nenhuma tabela/coluna existente foi alterada de forma destrutiva. `pedidos` só ganhou 2 colunas novas, opcionais, via `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` defensivo (padrão idêntico ao já usado em `install/migration_add_plate_emplacamento.sql`).

**O que FALTA e só o usuário pode rodar (sandbox sem PHP-CLI):**
1. Abrir `/admin/health` (ou rodar `install/migrate.php` / `run_all_migrations.php`) para aplicar `migration_service_catalog_v1.sql`.
2. Confirmar que o fluxo de reboque atual continua verde (criar um pedido de reboque de teste ponta a ponta).
3. Rodar a suíte PHPUnit existente (`vendor/bin/phpunit`) uma vez, como baseline, antes de continuar para a Etapa 2.

---

## ETAPA 1 — Domínio: catálogo de serviços e capacidades

**Status: CONCLUÍDA (código escrito e revisado manualmente — falta só o usuário aplicar a migration e testar na prática).**

### O que foi criado

**Migration:** `install/migration_service_catalog_v1.sql`
- `service_categories` (6 categorias seed: TOWING, ROADSIDE_ASSISTANCE, ELECTRICAL_ASSISTANCE, TIRE_ASSISTANCE, LOCKSMITH, FUEL_ASSISTANCE)
- `service_types` (12 tipos seed: TOW_CAR, TOW_MOTORCYCLE, TOW_UTILITY, JUMP_START, BATTERY_TEST, BATTERY_REPLACEMENT, TIRE_CHANGE, TIRE_INFLATION, ELECTRICAL_DIAGNOSIS, MECHANICAL_ASSISTANCE, FUEL_DELIVERY, AUTOMOTIVE_LOCKSMITH)
- `provider_capabilities` (provider_id → guinchos.id, approval_status PENDING/APPROVED/SUSPENDED/REJECTED)
- `provider_equipment`
- `provider_vehicle_compatibility`
- `pedidos.service_type_id` + `pedidos.attendance_mode` (colunas novas, opcionais, com FK; pedidos existentes são retroativamente marcados como TOW_CAR/TOWING só como metadado — nenhum código existente lê essas colunas ainda, fluxo de reboque atual **não muda de comportamento**).

**Models** (`src/Models/Catalog/`): `ServiceCategory`, `ServiceType` (com os leitores estruturais `requiresDestination()`, `allowsConversionToTowing()`, `requiresDiagnostic()`, `requiresParts()`, `isTowing()` — §2.3 do roadmap: nenhum controller deve decidir por texto livre), `ProviderCapability` (declarar/aprovar/suspender/rejeitar, sempre nasce PENDING, idempotente por UNIQUE), `ProviderEquipment`, `ProviderVehicleCompatibility`.

**Controller novo:** `src/Controllers/AdminServiceCatalogController.php` — **não** foi adicionado ao `AdminController.php` (§12.3 do roadmap pede controllers especializados). Distinto de `AdminController::servicos()`, que continua administrando `catalogo_servicos` (atalhos visuais do painel do cliente, propósito diferente).
- `tipos()` / `tipoForm()` / `tipoSalvar()` — CRUD de tipos de serviço.
- `capacidades()` / `capacidadeDecidir()` — fila de aprovação (aprovar/suspender/rejeitar).

**GuinchoController:** métodos novos `capacidades()` / `capacidadesSalvar()` — tela "Quais serviços você oferece?" (§3.3 do roadmap). Sempre grava como PENDING; admin decide depois.

**Rotas novas** (`index.php`):
- `GET /admin/catalogo-servicos/tipos`, `GET /admin/catalogo-servicos/tipo/novo`, `POST /admin/catalogo-servicos/tipo/salvar`
- `GET /admin/catalogo-servicos/capacidades`, `POST /admin/catalogo-servicos/capacidade/decidir`
- `GET /guincho/capacidades`, `POST /guincho/capacidades/salvar`

**Views:** `admin/catalogo_servicos_tipos.php`, `admin/catalogo_servicos_tipo_form.php`, `admin/catalogo_servicos_capacidades.php`, `guincho/capacidades.php` — mais os links correspondentes em `sidebar_admin.php` e `sidebar_guincho.php`.

### Saída da Etapa 1 (conforme roadmap) — checagem

| Esperado | Status |
|---|---|
| admin cadastra serviços | ✅ `/admin/catalogo-servicos/tipos` |
| prestador recebe capacidades | ✅ `/guincho/capacidades` (declara) + `/admin/catalogo-servicos/capacidades` (aprova) |
| pedido referencia um tipo de serviço | ✅ colunas adicionadas, retrocompatíveis, ainda não lidas pelo fluxo (isso é Etapa 3) |

### Limitação desta sessão
Não há PHP-CLI disponível no sandbox usado para editar o código — toda a revisão foi manual (leitura linha a linha + checagem de balanceamento de chaves/parênteses via script), não houve `php -l` nem execução real. **Antes de seguir para a Etapa 2, rode a migration e teste as duas telas novas manualmente** (`/admin/catalogo-servicos/tipos`, `/guincho/capacidades`) no ambiente real.

---

## Confirmação do usuário (22/07)
Etapa 1 rodada em produção local via `php install\migrate.php` (CLI, sem precisar de `INSTALL_KEY`) — telas `/admin/catalogo-servicos/tipos` e `/guincho/capacidades` funcionando. O 403 em acessar o `.sql` direto pelo navegador era esperado (bloqueio do `.htaccess` contra acesso direto a `install/*.sql` — não é bug).

---

## ETAPA 2 — Triagem do cliente

**Status: CONCLUÍDA (código escrito e revisado manualmente — falta o usuário rodar a migration e testar).**

### O que foi criado

**Migration:** `install/migration_triage_v1.sql` — tabela `triage_sessions` (auditoria/histórico; a decisão em si não fica no banco).

**Motor de regras (puro, sem I/O):** `src/Services/Triage/TriageRuleEngine.php`
- `RULE_VERSION = 'v1'`, método `avaliar()` despacha por versão (`avaliarV1()`) — uma sessão antiga nunca muda de interpretação retroativamente se uma v2 for criada depois.
- 8 sintomas de Pergunta 1 (`SYMPTOMS`): NAO_LIGA, PNEU, PAROU_TRAJETO (mapeado à árvore de "pane elétrica" do roadmap), CHAVE, SEM_COMBUSTIVEL, COLISAO, PRECISA_TRANSPORTAR, NAO_SEI.
- Cada sintoma decide entre os 5 resultados do roadmap (`RECOMMENDED_SERVICE` / `ALTERNATIVE_SERVICES` / `SAFETY_RISK` / `TOWING_REQUIRED` / `MANUAL_REVIEW_REQUIRED`) usando só os campos estruturados das respostas — nunca comparação de texto livre.
- Casos de segurança tratados: colisão (SAFETY_RISK direto, nunca tenta resolver no local), cheiro de queimado/fumaça (SAFETY_RISK), roda danificada (TOWING_REQUIRED).

**Orquestração (com I/O):** `src/Services/Triage/TriageService.php`
- `avaliarEPersistir()` é idempotente por `session_token` (UNIQUE) — reenvio do mesmo token não duplica sessão, mesmo padrão de idempotência já usado no resto do projeto (INSERT + catch de duplicate key).
- Logs via `Logger::log`/`Logger::exception` no padrão do projeto (`TriageService / avaliarEPersistir / triagem`).
- `resolverServiceTypeRecomendado()` resolve o `service_type_id` real do catálogo (Etapa 1) a partir do código recomendado, revalidando que o tipo ainda está ativo.

**DTOs:** `src/DTO/Triage/TriageRequest.php`, `TriageResult.php` (mesmo padrão de `PedidoTransitionRequest.php` já usado no projeto).

**ClienteController:** `triagem()` (GET, pergunta 1 + perguntas 2 dinâmicas via JS simples mostra/esconde), `triagemResponder()` (POST, roda o motor e persiste), `triagemResultado()` (GET, mostra recomendação + alternativas com botão "Continuar").

**Rotas:** `GET /cliente/triagem`, `POST /cliente/triagem/responder`, `GET /cliente/triagem/resultado`. Link novo no menu do cliente ("Não sei o que houve").

**Fechamento do laço com o pedido (sem tocar no fluxo/estado atual):**
- `pedidonovo.php` ganhou um banner opcional + `<input type="hidden" name="service_type_id">` dentro do form já existente — só aparece quando a URL vem com `?service_type_id=X` (vindo da tela de resultado da triagem). Nenhuma mudança na lógica JS/mapa/distância já existente.
- `ClienteController::pedidoNovo()` e `pedidoCriar()` leem e revalidam esse `service_type_id` contra o catálogo ativo (Etapa 1). `pedidoCriar()` agora grava esse valor na coluna `pedidos.service_type_id` (adicionada na Etapa 1, opcional) — é o primeiro código real a popular essa coluna para pedidos novos. **Comportamento do fluxo de reboque continua idêntico**: quando não vem `service_type_id` (fluxo antigo, sem triagem), tudo funciona exatamente como antes.

### Limitação desta sessão
Mesma de sempre: sem PHP-CLI no sandbox usado para editar — revisão manual + checagem de balanceamento de chaves/parênteses, sem `php -l` real. **Rodar `php install\migrate.php` de novo antes de testar** (aplica `migration_triage_v1.sql`), depois testar `/cliente/triagem` ponta a ponta.

---

## Decisão de arquitetura (22/07, com o usuário)
Opção escolhida: **ampliar o ENUM existente** de `pedidos.status` (em vez de tabela de estado paralela ou status genérico + fase). Trade-off aceito: qualquer código que faça `switch`/`match` fechado sobre status precisa saber lidar com os valores novos — auditado abaixo.

---

## ETAPA 3 — Fluxos e máquinas de estado por tipo de atendimento

**Status: FUNDAÇÃO CONCLUÍDA — a camada declarativa de estados existe e está pronta; a orquestração real (controllers/services que efetivamente movem um pedido por diagnóstico/execução/conversão) é Etapas 5, 6 e 7, ainda não construída.**

### O que foi criado

**Migration:** `install/migration_order_flow_v1.sql` — amplia `pedidos.status` ENUM de forma aditiva (os 7 valores atuais continuam exatamente iguais, mesma ordem, mesmo default). 8 valores novos, só usados por pedidos com `attendance_mode <> 'TOWING'`: `diagnostico_iniciado`, `diagnostico_concluido`, `autorizacao_servico_pendente`, `em_execucao_servico`, `teste_final`, `conversao_reboque_pendente`, `conversao_aprovada_cliente`, `preparacao_veiculo`.

**`src/Services/OrderFlow/`** (novo, espelha a estrutura pedida no roadmap §5.5):
- `FlowDefinitionInterface` — contrato (`proximosEstados`, `podeTransitar`, `proximoStatusPadrao`).
- `TowingFlowDefinition` — **é literalmente o mapa que já existia em `PedidoStateMachine::NEXT` antes de hoje, copiado sem nenhuma alteração.** Fluxo de reboque garantidamente idêntico.
- `OnSiteFlowDefinition` — mapa novo cobrindo diagnóstico → execução → teste final → conclusão, com ramificações para conversão em reboque em três pontos (após diagnóstico, após autorização pendente, após teste final malsucedido) — cobre os cenários 2, 3 e 4 do roadmap (§15.4).
- `HybridFlowDefinition` — por ora, estende `OnSiteFlowDefinition` sem overrides (mesmos estados possíveis; a diferença "sem nova disputa" é lógica de serviço, não de máquina de estados — fica para a Etapa 7).
- `OrderFlowResolver::forPedido($pedido)` — escolhe a flow definition pelo `attendance_mode` do pedido, com fallback seguro para `TowingFlowDefinition` quando ausente/nulo/desconhecido.

**`PedidoStateMachine`** (arquivo existente, único ponto de chamada em todo o projeto: `PedidoTransitionService.php:150`) — refatorado para delegar em `OrderFlowResolver`, com um 3º parâmetro opcional `$attendanceMode`. Comportamento 100% preservado quando o parâmetro não é passado (default `'TOWING'`) ou quando `$pedido['attendance_mode']` é `'TOWING'` (todo pedido de reboque hoje). `PedidoTransitionService::approvePayment()` (e as demais 7 chamadas de `SELECT * FROM pedidos`, que já trazem `attendance_mode` desde a Etapa 1) agora passam esse valor adiante.

### O que NÃO foi feito ainda (de propósito)
- Nenhum controller/service novo dispara as transições `diagnostico_iniciado`, `teste_final`, `conversao_reboque_pendente` etc. — isso é o conteúdo real das Etapas 5 (diagnóstico/orçamento), 6 (Proof-of-Service) e 7 (conversão). A máquina de estados só valida transições que ainda ninguém pede.
- `DTO/PedidoTransitionRequest.php`/`PedidoTransitionResult.php` não precisaram mudar — já são genéricos o bastante (`targetStatus: string`) para aceitar os novos valores sem alteração de schema.

### Risco avaliado e mitigado
Antes de tocar em `PedidoStateMachine`, busquei **todo** uso dele no projeto: só existe uma chamada (`PedidoTransitionService.php:150`). Não há nenhum outro `switch`/`match` fechado sobre `pedidos.status` fora do que já era tratado por essa máquina de estados — o ENUM ampliado não quebra nada que já existia porque nenhum pedido real vai receber os valores novos até as Etapas 5-7 existirem.

### Limitação desta sessão
Mesma de sempre: sem PHP-CLI no ambiente de edição — revisão manual + checagem de chaves/parênteses, sem execução real. **Rodar `php install\migrate.php` de novo** para aplicar `migration_order_flow_v1.sql` antes de seguir.

---

---

## UX (22/07): triagem deixou de ser tela separada — agora vive dentro de /cliente/pedido/novo

**Motivo (do usuário):** inspirado no Uber — mapa em destaque, busca de endereço sobreposta no topo, e a "pergunta o que aconteceu" precisa estar na MESMA tela do pedido, não como um passo separado de navegação. Cenário-guia: pai às 3h da manhã na Av. Brasil, com filho no carro, com medo — precisa resolver em poucos toques, sem trocar de página.

**O que mudou em `src/Views/cliente/pedidonovo.php` (reescrita completa):**
- Mapa ocupa quase a tela inteira (`calc(100vh - cabeçalho - padding)`), com uma barra de busca de endereço flutuante no topo (estilo Uber) em vez de um campo dentro de formulário lateral. GPS silencioso continua tentando detectar a origem automaticamente ao abrir a tela (comportamento já existia, preservado).
- Por baixo do mapa, um **bottom sheet** com um wizard de 3 passos, sem nenhum reload de página:
  1. **"O que aconteceu?"** — 8 botões grandes tipo chip/ícone (não liga, pneu, parou no trajeto, chave, sem combustível, colisão, preciso transportar, não sei) — um toque já avança.
  2. **Detalhe rápido** (só para os 3 sintomas que têm pergunta 2 no motor de regras: não liga / pneu / parou no trajeto) — toggles sim/não grandes, não checkbox miúdo.
  3. **Confirmação** — mostra a recomendação (via o mesmo `TriageRuleEngine`/`TriageService` da Etapa 2, chamado por AJAX, sem duplicar a lógica em JS), pede veículo só se houver mais de um (auto-seleciona se só tiver um), e **só pede destino se o tipo de serviço recomendado exigir** (`ServiceType::requiresDestination()`, Etapa 1) — bateria/pneu/chaveiro no local não perguntam "para onde vai".
- Link discreto "Prefiro escolher manualmente" pula direto pro passo 3 com o comportamento antigo (todos os campos, destino sempre pedido) — sempre existe uma saída se a triagem automática falhar ou o usuário não quiser passar por ela.
- Todo o JS de mapa/geocodificação/GPS/cálculo de rota/estimativa de custo que já existia e funcionava foi **reaproveitado quase literalmente**, só reorganizado dentro do novo layout — nenhuma lógica de mapa foi reescrita do zero.

**Backend novo:** `ClienteController::triagemAvaliarJson()` — endpoint POST JSON (`/cliente/triagem/avaliar`) que chama o mesmo `TriageService::avaliarEPersistir()` da tela dedicada, retornando a recomendação, alternativas e `requires_destination` para o JS decidir a UI. **Nenhuma lógica de triagem duplicada** — uma fonte de verdade (`TriageRuleEngine`), dois pontos de entrada (tela dedicada `/cliente/triagem` continua existindo como fallback/deep-link; tela embutida é agora o caminho principal).

**Menu do cliente:** removido o item "Não sei o que houve" (apontava pra tela separada) — "Pedir Socorro" agora é o único ponto de entrada, e já contém a triagem.

**Risco assumido:** reescrita de uma tela de 736 linhas sem poder executar PHP/JS no ambiente de edição. Reaproveitei o máximo possível das funções já testadas (setOrigem/setDest/recalcular/geocode/GPS) exatamente como estavam, só movendo onde elas são chamadas — a superfície nova de risco real é o wizard (steps, chips, chamada AJAX) e o CSS do bottom sheet. **Testar ponta a ponta antes de considerar pronto**, especialmente: GPS automático, os 3 sintomas com pergunta 2, o caminho "não exige destino" (ex. bateria) preenchendo destino = origem automaticamente, e o botão "escolher manualmente".

---

## Confirmação (22/07): dropdown de oficina no destino já existe

Verificado por grep em `src/Views/cliente/pedidonovo.php` — dentro do bloco `#destinoBlock` já existe o carregamento de `OFICINAS` (oficinas cadastradas pelo cliente) e a renderização de um seletor/tabs para escolher uma delas como destino, em vez de digitar endereço manualmente, quando o cliente já tem pelo menos uma oficina cadastrada (`/cliente/oficinas`). Não foi necessário nenhum código novo — apenas confirmação. Esse bloco só aparece quando o tipo de serviço recomendado exige destino (`ServiceType::requiresDestination()`), consistente com o resto do redesign.

---

## ETAPA 9 (parcial, adiantada a pedido do usuário): tarifas por tipo de serviço

**Status: CONCLUÍDA.**

**Motivo:** o admin precisava configurar preço para os serviços novos (partida auxiliar, bateria, pneu, diagnóstico elétrico, mecânica, chaveiro, combustível) — até aqui só o reboque tinha tarifa configurável (`TarifaService`, que continua sendo a fonte de verdade para reboque e não foi alterado).

**Migration:** `install/migration_service_pricing_v1.sql` — tabela `service_pricing_rules` (uma regra por `service_type_id`, UNIQUE): `base_fee`, `pickup_km_price`, `tow_km_price` (nullable — só serviços que podem carregar o veículo usam), `labor_fee`, `minimum_price`, `night_multiplier`, `holiday_multiplier`, `active`.

**Model:** `src/Models/Catalog/ServicePricingRule.php` — `listarComTipos()` (LEFT JOIN com `service_types`, só ativos), `buscarPorServiceType()`, `salvar()` (upsert idempotente via `ON DUPLICATE KEY UPDATE` — reenvio de formulário não duplica), `estimarBase()` (helper de estimativa, documentado como *não* substituindo o cálculo oficial de reboque).

**Controller:** `AdminServiceCatalogController::tarifas()` / `tarifaSalvar()` — novo, log em `catalogo_servicos` a cada alteração (quem, quando, valores).

**Rotas:** `GET /admin/catalogo-servicos/tarifas`, `POST /admin/catalogo-servicos/tarifa/salvar`.

**View:** `src/Views/admin/catalogo_servicos_tarifas.php` — uma linha editável por tipo de serviço ativo, formulário próprio por linha (sem JS de tabela dinâmica, consistente com o resto do admin). Link de acesso adicionado na tela `/admin/catalogo-servicos/tipos`.

**Pendente do usuário:** rodar `& "C:\xampp\php\php.exe" install\migrate.php` de novo para aplicar `migration_service_pricing_v1.sql` (mesmo procedimento das migrations anteriores).

---

## ETAPA 11 (novo requisito, 22/07): modelo financeiro de duas fases — diagnóstico/execução vs. reboque

**Status: PROPOSTA DOCUMENTADA — implementação de schema/models ainda NÃO iniciada.**

### Por que parei antes de codificar

O usuário já detalhou, em mensagem própria, a política financeira completa (tabela de pagamento por situação + um schema `order_charge_items` proposto) e o princípio-guia, citado aqui porque rege todo o resto:

> "O motoqueiro não deve ser pago por 'resolver o carro'. Ele deve ser pago por: comparecer, diagnosticar corretamente e executar o protocolo contratado. Resolver é um possível resultado. Identificar corretamente que o veículo precisa ser rebocado também é um resultado válido e valioso."

Isso é uma decisão de dinheiro de verdade (quem recebe, quanto, sob qual condição) e o texto exato da tabela situação→pagamento e do schema `order_charge_items` que o usuário propôs ficou no histórico da conversa anterior a este resumo. Em vez de reconstruir de memória um schema financeiro e arriscar divergir do que foi especificado, a abordagem é: **documentar aqui o princípio + um desenho de schema consistente com tudo que já existe no projeto, e confirmar com o usuário antes de gravar migration ou tocar em qualquer caminho de payout real** (mesma cautela já aplicada ao não mexer em `PedidoStateMachine` sem mapear todos os call sites).

### Princípios já certos, não sujeitos a reinterpretação

1. Pagamento ao primeiro-respondente (motoqueiro/prestador de diagnóstico) é **desacoplado** do resultado "carro consertado". Ele é devido por: comparecimento + diagnóstico correto + execução do protocolo contratado + evidências enviadas + ausência de fraude.
2. Reboque, quando necessário, é uma **segunda fase financeira e operacionalmente separada** — pode ser outro prestador, com sua própria remuneração.
3. Isso exige granularidade que `pedidos.valor_total` (um valor único por pedido) não oferece — daí a necessidade de itens de cobrança por prestador/fase.
4. **Gatilho automático de pagamento não deve ser ligado ainda** — depende de evidência/checklist (Etapa 6, Proof-of-Service), que ainda não existe. Construir o schema agora é seguro; automatizar o payout antes da Etapa 6 não é.

### Status: CONFIRMADO E IMPLEMENTADO (22/07, mesma sessão)

O usuário confirmou a tabela situação→pagamento completa (12 situações) e o schema definitivo, com uma correção importante em relação à proposta inicial: **`situacao` não é ENUM, é `VARCHAR` validado pela aplicação** — "são eventos e decisões financeiras diferentes... um ENUM gigante viraria um pequeno cartório dentro do banco" (citação do usuário). Além disso, o usuário separou explicitamente duas dimensões que a proposta inicial misturava: `charge_status` (o que aconteceu com a cobrança) e `payable_status` (o que acontece com o repasse ao prestador) — são independentes (ex.: cobrança `APPROVED` com repasse `PENDING_EVIDENCE`).

**Migration:** `install/migration_order_charge_items_v1.sql` — cria `order_charge_items` (item a item: fase, tipo de cobrança, valores, `charge_status`, `payable_status`, versão de cálculo, evidência, idempotência) e `order_provider_settlements` (consolidado por pedido+prestador). Schema exatamente como especificado pelo usuário — `order_id`/`provider_id` com FK para `pedidos`/`guinchos`; `service_execution_id` deixado sem FK por ora (a tabela de execução/evidência é da Etapa 6, ainda não existe).

**Códigos:** `src/Models/Financial/ChargeCodes.php` — constantes para `phase_code` (8), `charge_type` (16), `charge_status` (8), `payable_status` (8) e as 12 `situação` do motoqueiro, exatamente como definidas pelo usuário.

**Models:** `src/Models/Financial/OrderChargeItem.php` e `OrderProviderSettlement.php` — CRUD idempotente (`idempotency_key` UNIQUE, `ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)` — retry não duplica), transições de `charge_status`/`payable_status` como métodos dedicados.

**Política:** `src/Services/Financial/ChargePolicyService.php` — classe pura (mesmo espírito do `TriageRuleEngine`, `POLICY_VERSION = 'v1'`), método `resolverItensPrimeiroRespondente(situationCode, context)` que mapeia cada uma das 12 situações para a lista estrutural de itens (fase + tipo de cobrança + payable_status inicial), fiel à tabela confirmada pelo usuário. **Não calcula valores em R$** (isso é papel de quem chama, usando `ServicePricingRule`/`TarifaService`) e **não grava nada no banco** — é só a decisão de "quais itens e com que status inicial".

### O que NÃO foi feito ainda (de propósito)

- **Nenhum controller/service de produção chama `ChargePolicyService` ou grava em `order_charge_items`/`order_provider_settlements` ainda.** A geração automática de itens (e principalmente a liberação de repasse) depende de evidência real — Etapa 6 (Proof-of-Service), que ainda não existe. Ligar isso agora seria decidir pagamento sem prova.
- `service_execution_id` está no schema mas sem FK — vai apontar para a tabela de execução/checklist quando a Etapa 6 for construída.
- A situação `OTHER_PROVIDER_EXECUTES_TOWING` é tratada como informativa/modificadora, não geradora de itens própria — lança `LogicException` se chamada diretamente, para forçar quem integrar a resolver a situação real do motoqueiro primeiro (ex. `TOWING_RECOMMENDED_ACCEPTED`) e tratar o reboque como pedido/fase separada, exatamente como o usuário descreveu ("o guincheiro recebe a etapa de reboque, sem divisão arbitrária").

**Pendente do usuário:** rodar `& "C:\xampp\php\php.exe" install\migrate.php` de novo para aplicar `migration_order_charge_items_v1.sql`.

---

## ETAPA 12 (novo requisito, 22/07): generalização de prestador — providers/units/members

**Status: CONCLUÍDA (schema + models + backfill; ainda não lido por nenhum controller).**

**Contexto:** o usuário trouxe uma análise completa (própria, com pesquisa de mercado incluída) mostrando que misturar oficina, mecânico autônomo, motossocorro e guincheiro numa única entidade `guincho` "vai virar problema técnico, comercial e jurídico", e propôs o modelo `Provider → Prestador (autônomo/oficina/empresa) → Unidade operacional (moto/carro/van/caminhão)`.

**Decisão de estratégia (via `AskUserQuestion`, antes de codificar):** o levantamento de blast-radius mostrou que `guinchos`/`guincho_id` aparecem em 50+ arquivos e que `AuthService::requireAuth('guincho')` é checado em 25 pontos como role literal — além disso, o catálogo de capacidades (Etapa 1) já assume `guinchos.id` como `provider_id`. Rename/refactor completo agora arriscaria o único fluxo em produção real (reboque). O usuário confirmou **camada aditiva**: nada do que existe é tocado; uma ponte nova é criada por cima.

**Migration:** `install/migration_providers_v1.sql` — cria `providers`, `provider_members`, `provider_units`. Cada `guincho` existente ganha automaticamente um `provider` (via `legacy_guincho_id`, 1:1) e uma `provider_unit` do tipo `TOW_TRUCK` (backfill idempotente — reexecução não duplica). Tipos usam `INT` (não `BIGINT UNSIGNED`) de propósito, para casar exatamente com `guinchos.id`/`usuarios.id` nas FKs (evita erro de constraint por mismatch de sinal).

**Models:** `src/Models/Provider/Provider.php` (tipos `INDIVIDUAL`/`WORKSHOP`/`ROADSIDE_COMPANY`/`TOWING_COMPANY`/`HYBRID_COMPANY`, `criar()` para prestadores novos sem guincho legado), `ProviderUnit.php` (tipos `MOTORCYCLE`/`SUPPORT_CAR`/`SERVICE_VAN`/`TOW_TRUCK`/`HEAVY_TOW_TRUCK`), `ProviderMember.php` (vínculo usuário↔provider, idempotente via `ON DUPLICATE KEY UPDATE`).

**Gap conhecido e aceito nesta etapa (documentado no cabeçalho da migration):** `provider_capabilities`/`provider_equipment`/`provider_vehicle_compatibility` (Etapa 1) continuam com FK em `guinchos.id`, não em `providers.id`. Um provider novo sem guincho (socorrista autônomo puro, por exemplo) ainda **não consegue** declarar capacidades pela tela atual — isso fica para quando o cadastro de prestadores novos (sem `guincho`) for de fato construído, fora do escopo desta etapa aditiva.

**O que NÃO foi feito ainda (de propósito):** nenhuma tela de cadastro para prestador novo (oficina, empresa, autônomo puro); nenhum controller lê `providers`/`provider_units`/`provider_members` ainda — só a migration escreve (backfill) e os models existem para uso futuro.

---

## ETAPA 13 (novo requisito, 22/07): preço governado por zona

**Status: CONCLUÍDA (schema + models; ainda não lido por nenhum controller).**

**Contexto:** mesma mensagem do usuário — preço totalmente livre por prestador gera bagunça e corrida por menor preço; tarifa única e absoluta para "São Paulo inteira" também não reflete a realidade (região, horário, trânsito, distância, risco). Modelo recomendado: preço-base tabelado pela plataforma + componentes variáveis calculados + faixas mín/máx + orçamento complementar só após diagnóstico + aprovação expressa do cliente.

**Migration:** `install/migration_pricing_zones_v1.sql` — `pricing_zones` (código/nome/polígono GeoJSON, ainda sem desenho de zona real), `service_price_rules` (regra por zona+tipo+categoria de veículo, com **histórico versionado** — nunca sobrescreve, sempre cria nova `version`, exatamente para que `order_price_snapshots` de pedidos antigos continue válido depois de reprecificação), `provider_price_preferences` (faixa que o prestador pode pedir dentro do permitido, com aprovação), `order_price_snapshots` (pedido congela a regra usada no momento — mudar tabela amanhã não altera pedido já aberto).

**Relação com o que já existe:** complementa, não substitui, `service_pricing_rules` (Etapa 9, MVP de regra global por tipo de serviço) nem `TarifaService` (reboque, produção). Enquanto nenhuma zona tiver regra própria, o comportamento atual (regra global) continua sendo o que vale — porque nenhum controller lê `service_price_rules` ainda.

**Models:** `src/Models/Pricing/PricingZone.php`, `ServicePriceRule.php` (`criarNovaVersao()` incrementa `version` automaticamente, nunca faz UPDATE destrutivo numa regra existente).

**O que NÃO foi feito ainda (de propósito):** nenhuma tela admin para desenhar zonas/regras; nenhum controller de pedido/checkout lê essas tabelas; a lógica de "prestador pede faixa, admin aprova" (`provider_price_preferences`) existe só como schema.

**Pendente do usuário:** rodar `& "C:\xampp\php\php.exe" install\migrate.php` de novo para aplicar `migration_providers_v1.sql` e `migration_pricing_zones_v1.sql`.

---

## ETAPA 4 (22/07): matching e ofertas por capacidade

**Status: CONCLUÍDA.**

**O que existia antes:** nenhum "serviço de matching" dedicado — a fila de ofertas do guincho é montada em `GuinchoController::montarOfertasDisponiveis()` (dashboard + polling AJAX) e duplicada em `SseController::pedidosDisponiveis()` (stream em tempo real), ambas filtrando só por raio+score sobre `Pedido::listarAguardandoGuincho()` (todos os pedidos `aguardando_guincho`, sem filtro de tipo de serviço). O aceite (`PedidoTransitionService::assignInternal()`) só validava `guincho.aprovado`/`disponivel` — nenhuma checagem de capacidade em lugar nenhum. `ProviderCapability::listarPrestadoresAprovados()` (Etapa 1) já existia com o comentário `// base do matching (Etapa 4)`, mas nunca era chamada.

**Bug encontrado e corrigido antes de implementar o filtro:** `ClienteController::pedidoCriar()` já gravava `service_type_id` (Etapa 2, triagem) mas **nunca gravava `attendance_mode`** — a coluna ficava sempre no `DEFAULT 'TOWING'` do schema, mesmo para pedidos de partida auxiliar/pneu/chaveiro etc. Sem essa correção, o filtro de capacidade nunca teria efeito (todo pedido pareceria reboque). Corrigido: agora `attendance_mode` é lido do `ServiceType` escolhido e gravado junto.

**Regra implementada (mesma em 4 pontos, para não deixar brecha):** pedidos com `attendance_mode = 'TOWING'` continuam visíveis a **qualquer** guincho aprovado — comportamento idêntico ao que já está em produção, zero mudança. Pedidos `ON_SITE`/`HYBRID` (os novos tipos de serviço) só aparecem/podem ser aceitos por um guincho com `provider_capabilities` **APPROVED** para aquele `service_type_id`.

Pontos onde a regra foi aplicada:
1. `GuinchoController::montarOfertasDisponiveis()` — filtro na fila (dashboard + polling AJAX `pedidosDisponiveis()`, que reaproveita o mesmo método).
2. `SseController::pedidosDisponiveis()` — mesmo filtro no stream em tempo real (implementação duplicada da anterior, corrigida separadamente).
3. `GuinchoController::aceitarForm()` — não exibe a tela de aceite se o guincho não tiver a capacidade (evita link direto).
4. `PedidoTransitionService::assignInternal()` — **defesa em profundidade**: revalida a capacidade no momento do aceite em si (dentro da mesma transação com `SELECT ... FOR UPDATE`), porque os filtros de listagem não protegem contra um POST direto pra `/guincho/aceitar/{id}` com um ID que o guincho nunca deveria ter visto.

**Novo helper:** `ProviderCapability::possuiCapacidadeAprovada(providerId, serviceTypeId): bool` — reutilizado nos 4 pontos acima, uma única fonte de verdade para "este prestador pode executar este serviço".

**O que NÃO foi feito ainda (de propósito):** nenhum ranking por capacidade além do score distância+reputação já existente (ex.: priorizar quem já executou mais daquele tipo de serviço) — isso é refinamento futuro, não bloqueia a Etapa 5. Também não há ainda um conceito de "oferta" formal (tabela própria) — o modelo continua sendo lista compartilhada com corrida pelo aceite, igual ao reboque hoje; introduzir ofertas nominais (1 pedido → N prestadores convidados, não just "todo mundo vê") é uma decisão de produto separada, não necessária para a Etapa 4 cumprir seu objetivo.

---

## ETAPA 5 (22/07): diagnóstico e orçamento complementar

**Status: CONCLUÍDA.**

Cobre o meio do fluxo `OnSiteFlowDefinition` (estados já existiam no ENUM desde a Etapa 3, nunca tinham sido disparados por código nenhum): `no_local → diagnostico_iniciado → diagnostico_concluido → (em_execucao_servico | autorizacao_servico_pendente | conversao_reboque_pendente) → em_execucao_servico → teste_final → (concluido | conversao_reboque_pendente)`.

**Migration:** `install/migration_diagnostico_orcamento_v1.sql` — `pedido_diagnosticos` (um por pedido, resultado estruturado: `RESOLVIDO_SEM_ORCAMENTO`/`REQUER_ORCAMENTO`/`REQUER_REBOQUE`) e `pedido_orcamentos` (itens em `itens_json`, valor total, status `PENDENTE`/`APROVADO`/`RECUSADO`).

**Models:** `src/Models/PedidoDiagnostico.php`, `PedidoOrcamento.php` — upsert idempotente via `ON DUPLICATE KEY UPDATE` (reenvio de formulário não duplica).

**Serviço de orquestração:** `src/Services/Diagnostico/DiagnosticoService.php` — não reimplementa concorrência/autorização, delega tudo para `PedidoTransitionService::transition()` (mesmo `SELECT ... FOR UPDATE` e `PedidoStateMachine::canTransition()` já usados pelo reboque). `diagnostico_iniciado → diagnostico_concluido` é sempre 2 passos (regra do `OnSiteFlowDefinition`); `concluirDiagnostico()` faz os dois e decide o destino real conforme o resultado escolhido.

**Mudança necessária em `PedidoTransitionService::authorizeTransition()`:** adicionado o ator `'cliente'`, mas **restrito a um único caso** — aprovar orçamento (`autorizacao_servico_pendente → em_execucao_servico`, e só se `pedido.cliente_id === actorId`). Nenhuma outra transição fica liberada para o cliente por essa via; cancelamento continua exclusivamente por `cancelByCliente()`.

**Bug real corrigido durante a implementação:** o botão único "Cheguei ao local / Iniciar Reboque / Finalizar Corrida" do `atendimento.php` (`GuinchoController::atualizarStatus()`, mapa hardcoded `no_local → em_reboque`) continuava aparecendo para pedidos `ON_SITE`, e agora que a máquina de estados valida de verdade (Etapa 3), esse clique passaria a falhar silenciosamente com "Transição inválida". Corrigido: o botão de reboque só renderiza quando `attendance_mode === 'TOWING'`; para os demais, um painel novo (diagnóstico → orçamento → execução → teste final) assume o lugar, com uma ação por estado.

**Decisão de propósito — recusa de orçamento:** se o cliente recusa, o orçamento fica `RECUSADO` e o pedido **permanece** em `autorizacao_servico_pendente` — nada é cancelado nem cobrado automaticamente. Resolução manual via admin/Demanda, mesmo princípio já adotado para falha de pagamento (não inventar uma regra de cancelamento automático sem o usuário ter especificado uma).

**Rotas novas:** `POST /guincho/diagnostico/iniciar/{id}`, `POST /guincho/diagnostico/concluir/{id}`, `POST /guincho/execucao/concluir/{id}`, `POST /guincho/teste-final/concluir/{id}`, `POST /cliente/orcamento/decidir/{id}`.

**UI:** painel novo em `guincho/atendimento.php` (uma ação por estado, reaproveitando o nonce de evidência já emitido para `teste_final → concluido`, mesmo padrão de foto+nonce do reboque) e um card de aprovação de orçamento em `cliente/pedidostatus.php`.

**O que NÃO foi feito ainda (de propósito):** `conversao_reboque_pendente` só mostra uma mensagem de espera — a conversão de fato (criar/despachar o segundo pedido de reboque, ligar o `HybridFlowDefinition`) é a Etapa 7. O fechamento financeiro de `teste_final → concluido` reaproveita a regra simples que já existia (comissão única sobre `custo_estimado`/`custo_final`) — a integração real com `order_charge_items`/`ChargePolicyService` (Etapa 11) para refletir o pagamento por comparecimento+diagnóstico independente do resultado ainda não está ligada aqui; é o próximo elo, não construído nesta etapa para não misturar dois riscos financeiros na mesma mudança.

---

## ETAPA 7 (22/07): conversão de socorro local para reboque

**Status: CONCLUÍDA.**

Fecha o gancho que a Etapa 5 deixou pronto: `conversao_reboque_pendente → conversao_aprovada_cliente → (preparacao_veiculo | aguardando_guincho) → ... → concluido`.

**Serviço novo:** `src/Services/Conversion/ConversionService.php` — `decidirConversao(pedidoId, clienteId, aprovado)`. Recusa: fica em `conversao_reboque_pendente`, sem cancelamento automático (mesma política de "sem decisão financeira automática" já usada em orçamento recusado). Aprovação: transita para `conversao_aprovada_cliente` e então decide entre dois caminhos:
- **Prestador híbrido** (já tem capacidade de REBOQUE aprovada, além do serviço local que estava executando) → `preparacao_veiculo`, continua com o mesmo prestador, sem nova disputa de matching.
- **Não híbrido** → `aguardando_guincho`, libera o prestador atual (`disponivel = 1`, `guincho_id = NULL`) e renova `expiracao_aceite` — reentra na fila de matching normal (Etapa 4 assume a partir daí, nenhum código novo necessário ali).

**Novo helper:** `ProviderCapability::possuiCapacidadeReboqueAprovada(providerId)` — mesma ideia de `possuiCapacidadeAprovada()`, mas verificando qualquer `service_type` com `attendance_mode = 'TOWING'`.

**Bug real evitado antes de ir para produção:** ao reentrar em `aguardando_guincho`, o pedido continuava com `attendance_mode = 'ON_SITE'` — o filtro de capacidade da Etapa 4 continuaria exigindo aprovação para o **serviço original** (ex.: partida auxiliar) em vez de capacidade de reboque, e nenhum guincho comum apareceria na fila. Corrigido: a mesma transição que libera o prestador anterior também vira `attendance_mode` para `'TOWING'` — a partir daí o pedido segue literalmente como um reboque comum (`TowingFlowDefinition`, idêntico ao fluxo já em produção: `aguardando_guincho → a_caminho → no_local → em_reboque → concluido`). A validação da própria transição (`conversao_aprovada_cliente → aguardando_guincho`) já rodou antes dessa mudança, usando o `attendance_mode` antigo — correto, pois essa transição só existe no `OnSiteFlowDefinition`.

**Mudança em `PedidoTransitionService::authorizeTransition()`:** o caso `'cliente'` (introduzido na Etapa 5) ganhou um segundo par liberado — `conversao_reboque_pendente → conversao_aprovada_cliente` — continua restrito ao dono do pedido, nenhuma outra combinação.

**Rotas novas:** `POST /guincho/preparacao/concluir/{id}` (prestador híbrido envia foto de coleta e inicia o reboque — reaproveita a mesma validação de geofence/evidência `'coleta'` já usada pelo reboque comum), `POST /cliente/conversao/decidir/{id}`.

**UI:** painel de decisão no `cliente/pedidostatus.php` (mostra a descrição do diagnóstico, se houver, e os botões aprovar/recusar) e um ajuste **defensivo e isolado** na barra de progresso de 6 passos — os estados novos (diagnóstico/orçamento/execução/conversão) são mapeados para o passo visual mais próximo (`no_local` ou `em_reboque`) só para fins de exibição, sem alterar os índices existentes usados pelo reboque (risco avaliado: qualquer erro aqui quebraria a barra de progresso do fluxo em produção — por isso o mapeamento é feito ANTES do `array_search`, sem tocar em `$statusOrder`/`$steps`).

**O que NÃO foi feito ainda (de propósito):** o fechamento financeiro do reboque pós-conversão continua usando a mesma regra simples (comissão única sobre `custo_estimado`/`custo_final`) do fluxo comum — a two-fase financeira de verdade (motoqueiro recebe pela fase dele, guincheiro recebe a fase de reboque, sem divisão arbitrária, conforme a tabela situação→pagamento do usuário) depende da integração com `order_charge_items`/`ChargePolicyService` (Etapa 11), que segue propositalmente desconectada até existir evidência estruturada (Etapa 6).

---

## ETAPA 6 (22/07): Proof-of-Service

**Status: CONCLUÍDA.**

Formaliza, num único registro consultável por pedido, o que antes estava espalhado em tabelas separadas (`pedido_diagnosticos`, `pedido_evidencias`): o serviço exigia diagnóstico? Exigia foto de antes/depois? Isso de fato aconteceu? Checklist fechou completo ou não?

**Migration:** `install/migration_proof_of_service_v1.sql` — cria `service_executions` (flags `requires_*`/`has_*` para diagnóstico e evidência antes/depois, `checklist_status` COMPLETO/INCOMPLETO). Também fecha um loop deixado aberto de propósito na Etapa 11: `order_charge_items.service_execution_id` e `order_provider_settlements.service_execution_id` existiam sem FK (a tabela alvo não existia ainda) — agora ganham a constraint, de forma defensiva (checa `INFORMATION_SCHEMA` antes de adicionar, como as outras migrations condicionais do projeto).

**Novos helpers estruturais:** `ServiceType::requiresBeforeEvidence()`/`requiresAfterEvidence()` — mesmo padrão de `requiresDiagnostic()`/`requiresParts()` (Etapa 1), fecha a lacuna que faltava para nenhum código ler essas colunas como array cru.

**Model:** `src/Models/ServiceExecution.php` — upsert idempotente via UNIQUE(pedido_id); reavaliar o mesmo pedido (ex.: evidência chegou depois) atualiza, não duplica.

**Serviço:** `src/Services/ProofOfService/ProofOfServiceService.php::avaliarEFechar(pedidoId, providerId)` — lê o que é exigido (`ServiceType`), checa o que de fato existe (`PedidoDiagnostico::buscarPorPedido()`, `PedidoEvidencia::buscarUltimaPorTipo()` para os tipos `'coleta'`/`'entrega'` já usados pelo reboque), grava o checklist. Não decide dinheiro — só registra o fato estruturado.

**Gap real fechado antes de fazer sentido avaliar o checklist:** não existia NENHUMA captura de evidência "antes" no fluxo local (só "depois", no teste final) — `requires_before_evidence` nasce `true` por padrão em todo `service_type`, então todo checklist fecharia sempre incompleto. Corrigido: `GuinchoController::diagnosticoIniciar()` agora exige foto de chegada (reaproveita o mesmo nonce `'coleta'` já emitido para `no_local`/`preparacao_veiculo`), documentando comparecimento — exatamente o primeiro item da tabela situação→pagamento do usuário ("chegou e...").

**Wiring:** `GuinchoController::testeFinalConcluir()` chama `ProofOfServiceService::avaliarEFechar()` quando o serviço termina resolvido no local. Se virou reboque (`conversao_reboque_pendente`), o Proof-of-Service desse atendimento fica em aberto de propósito — a prova de reboque é outra (geofence/POR), já existente.

**O que NÃO foi feito ainda (de propósito):** `checklist_status` não dispara NADA automaticamente — nenhum `order_charge_items`/pagamento é criado/liberado a partir daqui. Essa é a integração real com a Etapa 11 (`ChargePolicyService`), que continua propositalmente desligada até haver decisão explícita de produto sobre quando o gatilho financeiro liga. Também não há painel admin para revisar checklists incompletos (isso cabe na Etapa 9).

---

## ETAPA 14 (22/07): declaração veicular (MVP formulário)

**Status: CONCLUÍDA.**

Rumo definido pelo usuário após avaliar catálogo x API de placa: **cadastro 100% manual, sem API de placa e sem catálogo marca/modelo/versão**. O formulário é a fonte de verdade inicial. (O rascunho anterior de catálogo estruturado — `migration_vehicle_catalog_v1.sql`, `src/Models/Vehicle/*` — foi abandonado; os arquivos ficaram no disco como código morto aditivo, não referenciados em lugar nenhum.)

**Migration:** `install/migration_vehicle_declaration_v1.sql` — adiciona em `veiculos`: `verification_status` (DECLARED/DOCUMENT_SUBMITTED/VERIFIED), `vehicle_type`, `fuel_type`, `transmission_type`, `electric_type`, `operational_category`, `has_spare_tire`, `has_locking_bolt`, `document_uploaded`, `document_path`. Adiciona em `pedidos` as condições **situacionais** (mudam a cada ocorrência, por isso ficam no pedido e não no veículo): `veiculo_esta_batido`, `rodas_travadas`, `local_dificil_acesso`, `em_garagem_subsolo`. Tudo defensivo (checa `INFORMATION_SCHEMA`).

**Model:** `Veiculo::criar/atualizar` gravam os campos novos; documento opcional (CRLV-e) eleva `verification_status` para DOCUMENT_SUBMITTED sem nunca rebaixar um veículo já VERIFIED (`registrarDocumento()`).

**Views:** `veiculoform.php` ganhou combustível, câmbio, elétrico/híbrido, estepe, parafuso antifurto e upload opcional de documento (fora do webroot, `storage/private/veiculos_documentos`, já coberto pela regra do `.htaccess`). `pedidonovo.php` ganhou as 4 perguntas situacionais. `vehicle_type`/`operational_category` derivam do radio "tipo" já existente (não pergunta duas vezes).

**O que NÃO foi feito (de propósito):** nenhum gate de bloqueio — veículo DECLARED pede socorro normal. Sem viewer autenticado do documento (só eleva status; conferência do admin é trabalho da Etapa 9/16). Sem validação administrativa automática.

---

## ETAPA 15 (22/07): compatibilidade prestador × veículo

**Status: CONCLUÍDA (núcleo). Admin CRUD e Playwright ficam para Etapa 16/homologação.**

Consome a declaração da Etapa 14 + as condições situacionais do pedido e decide, em **três estados** (ELIGIBLE / REQUIRES_CONFIRMATION / INELIGIBLE), quem pode atender. Aplica em dois momentos: filtro da fila (conveniência) e **revalidação dentro da transação de aceite** (segurança).

**Adaptações à realidade do código (divergindo do rascunho):** tipos `INT` (não BIGINT UNSIGNED — regra de FK do projeto); categoria como VARCHAR (coerente com a Etapa 14, sem catálogo); `provider_id = guinchos.id` (mesma convenção de `provider_capabilities`, sem introduzir `provider_legacy_links`).

**Migration:** `install/migration_provider_vehicle_compatibility_v1.sql` — `provider_service_vehicle_capabilities` (capacidade por prestador/serviço/categoria, com flags supports_electric/hybrid/locked_wheels/damaged_vehicle/subsoil_access, requires_manual_confirmation), `service_vehicle_requirements` (requisitos por serviço/categoria) e `order_vehicle_requirements` (snapshot que congela o cenário do pedido). **Tabelas nascem vazias.**

**FALLBACK CONSERVADOR (crítico):** enquanto um prestador não tiver nenhuma capacidade veicular configurada para um serviço, o motor devolve ELIGIBLE. Ou seja, o fluxo de reboque em produção não muda em nada; a Etapa 15 é aditiva, não um gate que derruba o fluxo atual. As restrições só passam a valer quando o admin configurar capacidades.

**Serviço:** `src/Services/Dispatch/ProviderVehicleCompatibilityService.php` com `decide()` **puro** (sem I/O, todo o teste unitário bate aqui) e `evaluate(CompatibilityRequest)` que carrega do banco e delega. DTOs `CompatibilityRequest`/`CompatibilityResult`. Snapshot montado por `OrderVehicleRequirementService::registrar()` no `pedidoCriar`. Códigos DSP-CMP-* nos logs (Logger::log, system=DISPATCH, phase=vehicle_compatibility_check).

**Wiring:** filtro em `GuinchoController::montarOfertasDisponiveis()`, `SseController::pedidosDisponiveis()` e `aceitarForm()` (esconde INELIGIBLE, deixa passar REQUIRES_CONFIRMATION com aviso); revalidação transacional em `PedidoTransitionService::assignInternal()` (só ELIGIBLE aceita direto; REQUIRES_CONFIRMATION e INELIGIBLE são barrados). Categoria desconhecida (pedido sem snapshot, ex.: anterior à Etapa 15) nunca é escondida — vira REQUIRES_CONFIRMATION.

**Correção estrutural encontrada:** o autoloader (`index.php`) era **plano** (só `src/{Controllers,Models,Services}/{Classe}.php`), sem varrer subpastas — classes em `Models/Catalog`, `Models/Dispatch`, `Services/Dispatch` etc. não resolviam sozinhas (risco latente: `SseController` já usava `ProviderCapability` sem `require`). Adicionado fallback recursivo cacheado, preservando o fast-path plano.

**Teste:** `tests/Unit/Dispatch/ProviderVehicleCompatibilityServiceTest.php` — 11 cenários sobre `decide()` (leve+carro=ELIGIBLE, categoria não atendida=INELIGIBLE, capacidade suspensa, elétrico/rodas/batido sem suporte, subsolo=confirmação, dados incompletos=confirmação, fallback sem config=ELIGIBLE).

**O que NÃO foi feito ainda (de propósito):** admin CRUD de capacidades/requisitos (`/admin/servicos/{id}/veiculos`, `/admin/prestadores/{id}/capacidades`) — fica na Etapa 16. Sem peso/dimensão do veículo no snapshot (Etapa 14 não captura peso), então DSP-CMP-008/009/010 existem mas não disparam no MVP. Sem fingerprint de idempotência armazenado na oferta (a avaliação é recalculada no aceite, que é o que importa). Sem testes de integração/Playwright ainda (Etapa 10/homologação). Fluxo "revisar dados antes de aceitar" para REQUIRES_CONFIRMATION: hoje o backend barra o aceite e mostra os avisos; a tela de confirmação dedicada é refinamento posterior.

---

## ETAPA 16 (22/07): /admin/servicos unificado + reboque protegido + admin de compatibilidade

**Status: CONCLUÍDA (núcleo). Tela do cliente ainda lê `catalogo_servicos` (decisão do usuário: não mexer no fluxo de maior tráfego agora).**

Fecha três coisas: torna `service_types` a fonte única de catálogo no admin, protege o reboque como serviço de sistema e entrega o admin de compatibilidade veicular que ficou pendente da Etapa 15.

**Unificação (decisão do usuário — "redirecionar"):** `/admin/servicos` deixou de renderizar o catálogo cosmético legado (`catalogo_servicos` — atalhos de ícone/cor/ordem do painel do cliente) e passa a **redirecionar** para `/admin/catalogo-servicos/tipos` (o catálogo estruturado real). As rotas `servico/*` legadas continuam existindo porque a tela de "novo pedido" do cliente ainda lê `catalogo_servicos`, mas não há mais entrada de admin separada. (Fusão total — migrar ícone/cor para `service_types` e apontar a tela do cliente para lá, aposentando `catalogo_servicos` — ficou explicitamente para depois, para não mexer na tela de maior tráfego agora.)

**Reboque protegido:** `install/migration_system_towing_service_v1.sql` adiciona `is_system`/`is_removable`/`can_disable` em `service_types` e marca todos os serviços `attendance_mode='TOWING'` (TOW_CAR/TOW_MOTORCYCLE/TOW_UTILITY) como sistema (is_system=1, is_removable=0, can_disable=0, active=1). A proteção **não é só UI**: `src/Services/Catalog/SystemServiceProtectionService.php` lança `DomainException('SRV-SYS-001')` em qualquer tentativa de remover/desativar, e `AdminServiceCatalogController::tipoSalvar()` chama `assertActiveChangeAllowed()` antes de gravar (e força active=1 em serviço protegido). A view `catalogo_servicos_tipos.php` mostra o badge "Sistema" (cadeado).

**Admin de compatibilidade veicular (Etapa 15 pendente):** nova tela `/admin/catalogo-servicos/compatibilidade?service_type_id=` — por serviço, edita requisitos gerais (`service_vehicle_requirements`: plataforma/guincho/dolly/testador/partida/macaco, capacidade mínima, certificação elétrica) e capacidades veiculares dos prestadores (`provider_service_vehicle_capabilities`: categoria, status, peso máx, suporta elétrico/híbrido/rodas travadas/batido/subsolo, exige confirmação manual). Controller: `compatibilidade()`, `requisitoSalvar()`, `capacidadeVeicularSalvar()` — tudo em `AdminServiceCatalogController` (não em `AdminController`, conforme instrução explícita do usuário). Model ganhou `ServiceVehicleRequirement::salvar()` (upsert idempotente, categoria NULL = regra geral).

**Rotas novas:** GET `/admin/catalogo-servicos/compatibilidade`; POST `/admin/catalogo-servicos/requisito/salvar`, `/admin/catalogo-servicos/capacidade-veicular/salvar`.

**O que NÃO foi feito (de propósito):** fusão total (retirar `catalogo_servicos`, migrar campos visuais para `service_types`, apontar tela do cliente) — adiada por decisão do usuário. Página única com abas (Geral/Veículos/Tarifas/Evidências/Prestadores/Zonas) do rascunho original: entregue como páginas separadas linkadas entre si (tipos → tarifas → compatibilidade → capacidades), não como um único formulário em abas. Sem CRUD de zonas/evidências nesta tela (zonas já têm schema na Etapa 13; evidências são governadas por `requires_before/after_evidence` no tipo).

---

## ETAPA 8 (22/07): produtos e estoque (começando por bateria)

**Status: CONCLUÍDA (fundação + gestão). Integração ao orçamento/baixa automática fica como próximo passo incremental.**

Dá corpo real ao que antes era só o flag `requires_parts` e a fase `PARTS_SUPPLY`/`PARTS_FEE` (sem catálogo de produto por trás). MVP focado em bateria, camada aditiva, sem gatilho financeiro automático (mesma postura das Etapas 11/12/13).

**Migration:** `install/migration_produtos_estoque_v1.sql` — `produtos` (catálogo global: sku, nome, categoria, especificação, preço de referência; seed de 5 baterias comuns), `provider_produtos_estoque` (estoque por prestador = guinchos.id, com override de preço) e `estoque_movimentos` (livro-razão idempotente: ENTRADA/SAIDA/AJUSTE/ESTORNO com `hash_idempotencia` UNIQUE). Tipos INT.

**Models:** `Produto`, `ProviderProdutoEstoque` (upsert por UNIQUE(provider, produto), `precoEfetivo()` = override ou referência).

**Serviço:** `src/Services/Estoque/EstoqueService.php` — toda mudança de saldo passa por aqui e grava movimento. `baixarPorPedido()` é idempotente por hash (retry/refresh não debita duas vezes) e transacional (`SELECT ... FOR UPDATE` na linha de estoque, rejeita saldo negativo). `entrada()`, `estornarPorPedido()`, `disponivel()`. Dar baixa NÃO cria cobrança — dinheiro continua sendo decisão do fluxo financeiro.

**Admin:** `AdminProdutoController` (controller próprio, não no `AdminController`) + views `produtos.php`/`produtoform.php` — CRUD do catálogo. Rotas `/admin/produtos`, `/admin/produto/novo`, `/admin/produto/salvar`. Link no sidebar admin substituiu o "Catálogo de Serviços" legado (que agora redireciona junto com a unificação da Etapa 16).

**Guincho:** `GuinchoController::estoque()`/`estoqueSalvar()` + view `guincho/estoque.php` — cada prestador define quantidade e preço de venda dos seus produtos. Link "Meu Estoque" no sidebar do guincho. Rotas `/guincho/estoque`, `/guincho/estoque/salvar`.

**O que NÃO foi feito ainda (de propósito):** o picker de bateria dentro do orçamento complementar (Etapa 5, `pedido_orcamentos.itens_json` é livre) e a baixa automática de estoque na conclusão do serviço não foram ligados — `EstoqueService::baixarPorPedido()` está pronto para ser chamado, mas o vínculo orçamento→produto e o gatilho na conclusão são o próximo passo incremental (e dependem da mesma decisão de produto que mantém o financeiro desligado). Categorias além de bateria (pneu/fluido) já cabem no schema, mas não têm fluxo próprio ainda.

---

## ETAPA 9 (22/07): telas e controllers admin especializados

**Status: CONCLUÍDA (o essencial). A maior parte foi entregue incrementalmente nas etapas anteriores; esta fecha o item que faltava.**

Boa parte do que a Etapa 9 previa já saiu ao longo do caminho, em controllers próprios (nunca engordando `AdminController`): tipos de serviço, tarifas, aprovação de capacidades e compatibilidade veicular (`AdminServiceCatalogController`), produtos/estoque (`AdminProdutoController`). O que faltava era a **fila de revisão de checklists incompletos** (fecha o loop da Etapa 6, Proof-of-Service).

**Novo:** `AdminProofOfServiceController::checklistsIncompletos()` + view `proof_of_service_incompletos.php` — lista as `service_executions` com `checklist_status = INCOMPLETO` (faltou diagnóstico e/ou foto de antes/depois exigida), com pedido, cliente, prestador, o que exatamente falta e link para o detalhe do pedido. `ServiceExecution::listarIncompletos()`/`contarIncompletos()`. Rota `/admin/checklists-incompletos` + item no sidebar admin. Read-only: serve para o admin dar seguimento manual antes de qualquer liberação financeira (consistente com a postura de não ligar gatilho automático).

**Bug corrigido no caminho (colisão de variável):** o `header.php` define `$tipo` = perfil do usuário (`'admin'`), sobrescrevendo a variável `$tipo` que os controllers passavam como o tipo de serviço. Isso quebrava `/admin/catalogo-servicos/tipo/novo?id=N` (card vazio: `TypeError` ao indexar a string `'admin'`) e deixava a tela de compatibilidade sempre com `$stId=0`. Corrigido capturando `$servicoTipo = $tipo ?? null` ANTES do `include header.php` nas duas views.

**O que NÃO foi feito (de propósito):** cadastro de "prestador genérico" fora do modelo `guincho` — depende de decisão de produto (quando abrir prestador que não é guincho); a ponte `providers/provider_units/provider_members` (Etapa 12) já está pronta para quando isso for decidido. Ações de moderação/reprocessamento em massa dos checklists não foram incluídas (a fila é de leitura + follow-up manual).

---

## ETAPA 10 (22/07): homologação — testes das etapas novas

**Status: CONCLUÍDA (testes escritos). Falta rodar `vendor/bin/phpunit` e a suíte Playwright no ambiente real para confirmar verde.**

Cobre com testes as etapas novas (8/14/15/16), reaproveitando a infra já existente (PHPUnit + SQLite in-memory no `tests/bootstrap.php`; Playwright em `qa/suites`).

**Unitários (puros, sem banco):**
- `tests/Unit/Dispatch/ProviderVehicleCompatibilityServiceTest.php` (Etapa 15) — 11 cenários do motor `decide()`.
- `tests/Unit/Catalog/SystemServiceProtectionServiceTest.php` (Etapa 16) — proteção do reboque (SRV-SYS-001): protegido não remove nem desativa; comum pode; manter ativo sempre permitido.

**Integração (SQLite in-memory):**
- `tests/Integration/EstoqueServiceTest.php` (Etapa 8) — saldo inicial, entrada soma, baixa decrementa, **baixa idempotente por pedido** (segunda chamada não debita; um único movimento registrado), saldo insuficiente rejeita sem ficar negativo, estorno devolve saldo e é idempotente.
- `tests/Integration/ProviderVehicleCompatibilityEvaluateTest.php` (Etapa 15) — wiring de `evaluate()` com o banco: **fallback conservador** (sem config = ELIGIBLE, preserva reboque), capacidade aprovada = ELIGIBLE, suspensa = INELIGIBLE (DSP-CMP-004), categoria não atendida = INELIGIBLE (DSP-CMP-005).
- `tests/bootstrap.php` — estendido com as 6 tabelas novas (produtos, provider_produtos_estoque, estoque_movimentos, provider_service_vehicle_capabilities, order_vehicle_requirements, service_vehicle_requirements).

**Playwright (E2E, `qa/suites/provider-vehicle-compatibility.spec.ts`):** 6 casos admin — /admin/servicos redireciona para o catálogo estruturado; reboque exibe selo "Sistema"; tela de compatibilidade carrega o seletor; **editar tipo de serviço renderiza o formulário (regressão explícita do bug do card vazio)**; catálogo de produtos carrega; fila de checklists incompletos carrega. Todos pulam se não houver credencial de admin de teste configurada (mesmo padrão dos specs existentes).

**O que NÃO foi feito ainda (de propósito):** testes E2E que fabricam pedido real de socorro no local com bateria/compatibilidade ponta a ponta (depende do picker de estoque no orçamento, que ficou como próximo passo da Etapa 8). Regressão completa da suíte antiga não foi reexecutada aqui (sem PHP no ambiente de edição) — cabe ao ambiente real rodar `vendor/bin/phpunit` e `npx playwright test`.

---

## Próximo passo sugerido
Todas as etapas do roadmap de expansão (4 a 10 e 14 a 16) estão concluídas — matching, diagnóstico, conversão, prova de serviço, declaração veicular, compatibilidade, catálogo unificado, produtos/estoque, fila admin de checklists incompletos e os testes de homologação. Antes do piloto, rodar no ambiente real: `& "C:\xampp\php\php.exe" install\migrate.php`, `vendor/bin/phpunit` e `npx playwright test`. Ganchos deixados prontos para o próximo passo incremental: baixa de estoque no orçamento (`EstoqueService::baixarPorPedido()`), CRUD de compatibilidade já operável em `/admin/catalogo-servicos/compatibilidade`. Já pode considerar `provider_units`/`providers` como camada opcional de leitura (join pela ponte `legacy_guincho_id`), sem obrigar nada novo. Etapas 11, 12 e 13 estão com schema + models prontos, mas propositalmente não conectados a nenhum fluxo real — o gatilho de tudo isso (geração automática de cobrança, leitura de preço por zona, cadastro de prestador novo) é trabalho de etapas futuras que dependem de evidência (Etapa 6) e de decisão de produto (quando abrir cadastro de prestador fora do modelo `guincho`). Recomendo também validar a reescrita de UX (mapa+triagem) na prática, já que é a tela de maior tráfego do sistema.
