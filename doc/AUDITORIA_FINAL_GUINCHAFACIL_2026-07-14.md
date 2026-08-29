# GuinchaFácil — Auditoria final de implementação, pendências e revisão profunda de produto/UI

**Data da auditoria:** 14/07/2026  
**Pacote auditado:** `guinchafacil(2).zip`  
**Escopo lido:** `doc/`, banco/migrations, controllers, models, services, views, CSS, JavaScript, suíte Playwright, artefatos de QA e imagens de referência.

---

# 0. Veredito executivo

O projeto está **avançado em quantidade de componentes**, mas ainda não está no mesmo percentual quando medido por **aceite real de produção**.

| Métrica | Percentual auditado | Significado |
|---|---:|---|
| Cobertura estrutural do escopo web documentado | **79%** | Classes, tabelas, rotas, telas e serviços previstos existem em grande parte. |
| Integração funcional do escopo web | **68%** | Os subsistemas conversam entre si, mas existem fluxos incompletos e contratos divergentes. |
| Prontidão de aceite/produção | **60%** | Ainda há bloqueadores de E2E, segurança, migrations, evidências, layout e portabilidade de QA. |
| Aderência visual atual aos guias e screenshots | **39%** | Há tokens e componentes, porém composição, hierarquia, mobile e acabamento ainda estão abaixo da referência. |
| Central de Comunicados | **55% estrutural / 28% pronta** | Banco/controller/model existem; renderização, formulário, métricas e comportamento do carrossel estão incompletos. |
| Aplicativo Android | **0% neste ZIP** | Não existem Gradle, Manifest, Activities, Fragments nem fontes Kotlin/Java. |
| Pacote constitucional jurídico `SEG-01/CT-01/PU-01/GOV-01` | **0% neste ZIP** | Não foram localizadas tabelas/classes de apólices, aceites contratuais ou decisões estratégicas. |

## Conclusão objetiva

O GuinchaFácil **não está a 90% de uma versão final**, embora pareça próximo quando se contam apenas arquivos. A leitura mais honesta é:

- **79% construído estruturalmente**;
- **60% pronto para ser aceito em produção**;
- o restante não é “encher tela”: é a parte mais delicada — integração, antifraude, atomicidade, QA verde, segurança, acabamento visual e experiência operacional.

O núcleo do produto está de pé. Agora o risco é terminar “por cima”, acumulando recursos em telas que ainda possuem contratos quebrados. Primeiro devemos consolidar a fundação; depois elevar o layout ao nível dos screenshots.

---

# 1. Como os percentuais foram calculados

Para evitar percentual decorativo, cada requisito foi avaliado em quatro eixos:

| Eixo | Peso | Pergunta de auditoria |
|---|---:|---|
| Estrutura | 30% | Arquivos, classes, tabelas, rotas e componentes existem? |
| Integração | 35% | O recurso está ligado ao fluxo real, usando os contratos corretos? |
| Aceite/testes | 20% | Há teste verificável e resultado verde atual? |
| Hardening | 15% | Há segurança, logs, transação, tratamento de erro e portabilidade? |

Para documentos exclusivamente visuais, a régua foi adaptada:

- componentes existentes: 25%;
- composição/hierarquia: 30%;
- responsividade/acessibilidade: 20%;
- fidelidade visual e comportamento JavaScript: 25%.

Esses percentuais representam o estado do pacote analisado, não uma estimativa baseada no histórico da conversa.

---

# 2. Primeira pergunta — o que está em `doc/` e quanto foi implementado

## 2.1 Matriz documento por documento

| Documento | Cobertura estrutural | Pronto para aceite | Diagnóstico |
|---|---:|---:|---|
| `INSTRUCOES.txt` | **90% web** | **78% web** | Modo `production/sandbox/freeflow`, `payment_required`, admin, serviço e fluxo web existem. A parte Android não existe. |
| `ALTERACOES_SESSAO_SEGURA.md` | **100%** | **92%** | `session-manager.js`, endpoint de sessão, retorno seguro e teste standalone existem e o teste passou. |
| `CHANGELOG-fluxo-pedido-cancelamento.md` | **90%** | **72%** | Localização, chat, cancelamento bilateral e estorno existem, mas a arquitetura atual ainda tem lacunas de atomicidade e snapshot. |
| `CORRECOES_CANCELAMENTO_POLLING.md` | **95%** | **88%** | Correções principais estão integradas; ainda há dependência de fluxos maiores e artefato E2E falhando. |
| `RELATORIO_CONSOLIDACAO_CONSTITUCIONAL.md` | **85%** | **63%** | Documento histórico: várias lacunas que ele apontava foram edificadas, porém o POR/evidências ainda não atingiram o aceite constitucional completo. |
| `guinchafacil-backlog-tecnico.md` | **82%** | **62%** | SSE, tarifa, cancelamento e rastreamento evoluíram; permanecem feriados/categoria persistida, código morto, crons externos e refinamentos de rota. |
| `PLANO_FINALIZACAO_GUINCHAFACIL_POR_PLAYWRIGHT.md` | **80%** | **59%** | É a constituição técnica mais importante. Quase todos os subsistemas foram iniciados, mas vários critérios de aceite continuam parciais. |
| `GUIA_DESIGN_PAINEIS_GUINCHAFACIL.md` | **62%** | **43%** | Paletas e componentes existem; composição, mobile, consistência de classes e acabamento ainda divergem. |
| `ADITIVO_01_AJUSTES_PAINEIS.md` | **52%** | **35%** | Algumas correções foram aplicadas, mas ainda há texto técnico, status duplicado, caracteres quebrados, IDs repetidos e hierarquia inconsistente. |
| `ESPECIFICACAO_TECNICA_TELAS_E_COMUNICADOS_GUINCHAFACIL.md` | **54%** | **32%** | Estrutura inicial da Central de Comunicados e campo rápido existe; comportamento visual e contratos principais ainda estão incompletos. |

## 2.2 `INSTRUCOES.txt` — modo de pagamento e fluxo livre

### Implementado

- Migration com `system_mode` e `payment_required`.
- Opções no admin: produção, sandbox e freeflow.
- `PedidoService::modoOperacao()`.
- `PedidoService::pagamentoObrigatorio()`.
- `PedidoService::podeIniciarAtendimento()`.
- Status inicial centralizado em `statusInicialPedido()`.
- Pedido freeflow liberado para `aguardando_guincho`.
- Pagamento virtual/freeflow registrado.
- Suíte Playwright de pagamento sandbox presente.
- Artefato histórico verde para `E2E-PAY-001` e `E2E-PAY-002`.

### Não implementado

- Aplicativo Android do cliente e do guincho.
- Ocultação da etapa de pagamento em Activities/Fragments, porque não há app Android no pacote.
- Aceite final reproduzível em todos os ambientes; os resultados históricos são mistos.

### Percentual correto

- **Escopo web do documento: 90% estrutural / 78% aceito.**
- **Escopo literal, incluindo Android: aproximadamente 66%.**

---

## 2.3 Sessão segura

### Implementado

- Cookies `httponly`, modo estrito e `secure` sob HTTPS.
- Regeneração periódica de sessão.
- Endpoint `/auth/session-status`.
- Tratamento centralizado por `session-manager.js`.
- Retorno interno validado para impedir redirect externo.
- Integração com polling e SSE.
- Evento `guincha:session-expired`.
- Teste `SessionExpirationTest.php` aprovado em quatro verificações.

### Pendente

- Teste E2E completo da expiração durante atendimento, chat, upload e pagamento em ambiente semelhante à produção.
- Eliminar chamadas AJAX legadas que não usem o mesmo wrapper.

**Aceite atual: 92%.**

---

## 2.4 Máquina de estados e concorrência

### Implementado

- `PedidoStateMachine`.
- `PedidoTransitionService`.
- DTOs de solicitação e resultado.
- Lock transacional no aceite do guincho.
- Atribuição administrativa dentro do serviço.
- Transições auditadas.
- Bloqueio de uso direto de `Pedido::atualizarStatus()` e `Pedido::cancelar()`.
- Suíte de concorrência com artefato histórico verde para dois cenários.

### Pendente

- `PedidoStateMachine::canTransition()` aceita transição para o mesmo estado; deveria retornar no-op explícito, não sucesso indistinguível.
- `Pedido::atribuirGuincho()` ainda pode ser usado diretamente por ferramentas/fluxos legados.
- Pré-condições de evidência ainda dependem parcialmente de nome de arquivo/contexto, não exclusivamente de um `evidence_id` persistido e aceito.
- Ator `system` consegue contornar pré-condições em caminhos que precisam ser limitados.
- Falta um teste atual verde do fluxo integral até `concluido`.

**Cobertura: 85%. Aceite: 70%.**

---

## 2.5 Proof-of-Road

### Implementado

- `ProofOfRoadService`.
- `LocationValidationService`.
- `DistanceAccumulatorService`.
- `GeofenceService`.
- `MapMatchingService`.
- `StreetResolutionService`.
- `RoutingSnapshotService`.
- Tabelas de pontos e resumo de percurso.
- UUID, sequência, precisão, velocidade, heading e timestamp.
- Validação geográfica, temporal, sequência, gap, duplicidade e velocidade.
- Pontos rejeitados preservados para auditoria.
- Resumo de distância e qualidade.
- Hash encadeado.
- `watchPosition()` durante atendimento.
- Suíte POR com execuções históricas verdes e vermelhas.

### Pendente

1. Inserção do ponto e atualização do resumo não estão claramente protegidas por uma única transação.
2. Corrida de duplicidade precisa converter violação da chave única em resposta idempotente, não erro genérico.
3. O `MapMatchingService` não executa map matching real; trabalha mais como validação/geofence/fallback.
4. Reverse geocoding pode ser chamado com frequência excessiva, potencialmente a cada envio de GPS.
5. A API de trilha precisa paginação incremental com `since_point_id`.
6. Faltam thresholds constitucionais centralizados:
   - número mínimo de pontos válidos;
   - idade máxima do último ponto;
   - gap crítico;
   - qualidade mínima;
   - distância mínima compatível com a etapa.
7. O hash SHA-256 encadeado detecta alteração casual, mas não é assinatura autenticada; falta rotina de verificação de integridade.
8. ETA restante ainda é estimativa simplificada, não rota real restante recalculada.
9. O cliente não recebe um painel de qualidade tão claro quanto o backend registra.

**Cobertura: 78%. Aceite: 58%.**

---

## 2.6 Evidências de coleta e entrega

### Implementado

- Tabela/model `PedidoEvidencia`.
- `EvidenceService`.
- Nonce HMAC com expiração.
- Validação da idade do GPS e geofence.
- `finfo` para MIME.
- JPEG/PNG.
- Limite de 5 MB.
- Nome aleatório.
- SHA-256 do arquivo.
- Metadados de fase/localização.
- Testes históricos de upload com execuções verdes.

### Pendente crítico

- Nonce ainda pode ser reutilizado enquanto válido; precisa ser de uso único/consumido.
- Falta detecção idempotente por hash/fase/pedido.
- Falta validar dimensões e reprocessar a imagem para remover payload/metadados indesejados.
- Arquivo, registro da evidência e transição de estado não formam uma única unidade atômica.
- Arquivos podem ficar órfãos quando a transição falha.
- Evidências estão em diretório público; a constituição indicava armazenamento privado e entrega autenticada.
- `ArquivoController` não apresenta contrato completo para servir evidências privadas.
- A migration não possui todos os campos de auditoria originalmente previstos.
- O artefato atual de QA informa falha HTTP 500 justamente na evidência final de entrega.

**Cobertura: 70%. Aceite: 48%.**

---

## 2.7 Cancelamento constitucional

### Implementado

- Preview de cancelamento.
- Cálculo baseado em distância/tempo do POR.
- Geofence de bloqueio.
- Taxa e motivo retornados ao cliente.
- Cancelamento do cliente, guincho e admin.
- Estorno parcial.
- Penalidade de reputação do guincho.
- Dados de rastreamento incluídos no status JSON.

### Pendente

- Falta snapshot imutável da simulação utilizada na decisão.
- Falta versão da fórmula e JSON dos fatores aplicados.
- Preview e confirmação acontecem em transações separadas; existe risco TOCTOU.
- O valor confirmado pelo cliente não é confrontado de forma robusta com uma versão/snapshot.
- A normalização usa distância do pedido origem→destino em um cálculo de mobilização guincho→origem; os conceitos não são equivalentes.
- Qualidade insuficiente do tracking deveria bloquear cálculo automático ou exigir revisão.
- Cancelamento do guincho e penalidade precisam ser atômicos.
- Métodos legados/dead code ainda permanecem.

**Cobertura: 78%. Aceite: 55%.**

---

## 2.8 Pagamentos, estornos e repasse

### Implementado

- Mercado Pago e PagSeguro.
- Checkout por ambiente.
- Webhooks.
- Pagamento freeflow.
- Split interno.
- `PaymentJobService`.
- Worker PIX.
- Idempotency key por pedido.
- Retry/backoff e tentativas.
- Reprocessamento administrativo.
- Métricas financeiras e tela de status.
- Conclusão operacional separada do job assíncrono de repasse.

### Pendente

- Reexecutar a matriz de testes em banco limpo e ambiente equivalente à produção.
- Revisar concorrência entre workers com `SKIP LOCKED` ou lock idempotente explicitamente documentado.
- Consolidar reconciliação entre gateway, pagamento local e job PIX.
- Cobrir falha depois de concluir o atendimento, sem perder a obrigação financeira.

**Cobertura: 90%. Aceite: 75%.**

---

## 2.9 Observabilidade e logs administrativos

### Implementado

- Logger JSONL e banco.
- `system`, `class`, `function`, `file`, `phase`, `code`.
- `request_id`, `run_id`, pedido, usuário, guincho, duração.
- Redação de chaves sensíveis.
- Audit trail.
- Tela de logs v2.
- Filtros e correlação.
- Exportação.
- Health e cron monitor.

### Pendente

- Eliminar `error_log()` e catches silenciosos remanescentes.
- `Comunicado` atualmente engole exceções sem log.
- Padronizar o mesmo contrato nos JavaScripts críticos.
- Criar alerta para sequência repetida de códigos críticos, não só consulta manual.

**Cobertura: 90%. Aceite: 82%.**

---

## 2.10 Banco e migrations

### Implementado

- Instalador completo.
- `install/migrate.php` como runner canônico.
- Registro de versão e checksum.
- Detecção de drift.
- Schema health.
- Migrations para POR, evidências, pagamentos, observabilidade, QA e comunicados.

### Pendente crítico

- Teste obrigatório em banco vazio ainda não foi comprovado nesta auditoria.
- `run_all_migrations.php` contém dois `catch (Throwable $e)` consecutivos; o segundo é inalcançável.
- `migration_simulation_runner_v2.sql` declara `idx_step_id` na criação e tenta adicioná-lo outra vez.
- Existem migrations com comandos que dependem do versionamento para não rodar duas vezes, mas não são idempotentes isoladamente.
- Falta teste automático `install -> migrate -> migrate novamente -> health`.

**Cobertura: 85%. Aceite: 65%.**

---

## 2.11 Playwright no admin

### Implementado

- `package.json`, config e TypeScript.
- 12 suítes.
- Reporter customizado.
- Models/migrations para runs, steps e artifacts.
- Worker.
- Serviços e telas administrativas.
- Resultados históricos para pagamento, concorrência, POR e upload.
- TypeScript compilou sem erro nesta auditoria.

### Pendente crítico

- O resultado atual é **failed**.
- `E2E-ORD-001` falha com HTTP 500 na evidência de entrega.
- `PlaywrightRunnerService::buildCommand()` usa construções PowerShell/`cmd.exe` e `npx.cmd`; não é portável para Linux.
- Há caminhos absolutos do Windows nos artefatos e fixtures.
- Há senhas padrão de teste em helpers; precisam existir apenas em ambiente QA isolado.
- Falta baseline integral verde antes da liberação.
- O controle permanece concentrado em `AdminController`, contrariando a separação desejada.

**Cobertura: 75%. Aceite: 45%.**

---

## 2.12 Design e painéis

### O que existe

- Paletas por perfil.
- Tokens globais.
- Sidebar, navbar, cards, stats, hero, timeline, chat e mapas.
- CSS premium inicial.
- Dashboard específico para cliente e guincho.
- Tema admin escuro.
- Breakpoints Bootstrap.

### Desvios medidos

- **429 ocorrências de `style="..."` em 45 views.**
- `style.css` possui 778 linhas; ainda concentra muitas responsabilidades.
- `AdminController.php`: 1.827 linhas.
- `AuthController.php`: 1.709 linhas.
- `cliente/dashboard.php` possui dois `id="map"`.
- `pedidonovo.php` possui dois `id="inputDest"`.
- `pedidostatus.php` possui IDs duplicados em blocos condicionais.
- `cliente/dashboard.php` e `guincho/dashboard.php` têm BOM UTF-8.
- Foram encontradas dezenas de ocorrências de mojibake (`VocÃª`, `ConcluÃ­dos`, etc.).
- Views ainda usam Leaflet/Chart.js via CDN apesar de assets locais.
- Cliente mistura classes BEM corretas (`app-stat__value`) com classes inexistentes (`app-stat-value`).
- Componentes usam `app-card` no CSS e `dash-card` no HTML.
- Há dois componentes de pedido ativo no cliente.
- Há dois componentes de atendimento ativo no guincho.
- Status online aparece repetido no guincho.
- Texto técnico permanece visível no painel do guincho.
- Renderização dinâmica da fila usa `innerHTML` com conteúdo vindo da API sem escape.

**Aderência visual/funcional atual: aproximadamente 39%.**

---

# 3. Segunda pergunta — o que falta para concluir

## 3.1 Bloqueadores P0 — não liberar antes de corrigir

| Ordem | Pendência | Sistema/classe/arquivo | Critério de conclusão |
|---:|---|---|---|
| 1 | Remover segredo distribuído | `.env.local` dentro do ZIP | Pacote sem segredos; credenciais rotacionadas se já compartilhadas. |
| 2 | Corrigir HTTP 500 da evidência final | `EvidenceService`, `PedidoTransitionService`, `GuinchoController::atualizarStatus` | `E2E-ORD-001` verde até `concluido`. |
| 3 | Tornar evidência atômica e privada | `EvidenceService`, migration, `ArquivoController` | Upload + registro + transição consistentes; arquivo autenticado. |
| 4 | Corrigir campo rápido do cliente | `cliente/dashboard.php`, `quick-rescue.js`, rota POST | Um clique em GPS ou endereço cria rascunho e abre pedido preenchido. |
| 5 | Eliminar IDs duplicados | dashboards, novo pedido, status, detalhe admin | Teste DOM com zero IDs duplicados. |
| 6 | Limpar BOM e mojibake | dashboards e arquivos listados | UTF-8 consistente, nenhum `Ã`, `Â` ou literal `` `r`n ``. |
| 7 | Corrigir XSS da fila do guincho | `guincho/dashboard.php` | Sem `innerHTML` com dados remotos; usar DOM/textContent. |
| 8 | Validar migrations em banco vazio | `install/migrate.php`, SQLs | Instalar, migrar, repetir e executar health sem erro. |
| 9 | Remover índice duplicado e catch morto | migrations/runner | Runner e SQL sem comandos conflitantes. |
| 10 | Baseline Playwright verde | `qa/` | Todas as suítes obrigatórias verdes em execução única. |

## 3.2 P1 — necessário para concluir o produto constitucional

1. **POR robusto:** transação, paginação incremental, thresholds de qualidade, integridade verificável e buffer offline.
2. **Cancelamento imutável:** snapshot, versão da fórmula, confirmação com hash/versionamento e operação atômica.
3. **Rota real:** ETA e distância pelo trajeto restante, não apenas Haversine.
4. **Central de Comunicados completa:** imagem, formato, duração, frequência, dismiss, agenda, métricas e preview real.
5. **Refatoração de controllers:** Admin/Auth/Guincho/Cliente por domínio.
6. **Design system único:** retirar CSS inline e classes antigas/duplicadas.
7. **Admin command center:** mapa operacional, incidentes, filas e saúde.
8. **Oferta ao guincho:** distância, ETA, valor, problema, fotos, timer e concorrência.
9. **Painel POR visível:** qualidade, sequência, último GPS e requisitos da próxima transição.
10. **QA portável:** Linux/Windows sem comandos fixos de PowerShell.

## 3.3 P2 — diferenciação de produto baseada nos screenshots

- Navegação inferior mobile.
- Carteira e seleção de pagamento em drawer.
- Serviços rápidos no dashboard do cliente.
- Locais favoritos e oficina preferida no compositor.
- Compartilhamento de acompanhamento.
- Central de notificações.
- Fila visual de ofertas para guincho.
- Gestão de frota/proprietário.
- Compliance documental e vencimentos.
- Campanhas segmentadas por perfil.

## 3.4 Decisão de escopo obrigatória

Há uma contradição documental:

- `INSTRUCOES.txt` ainda fala em app Android;
- `guinchafacil-backlog-tecnico.md` declara que Android foi removido do escopo;
- o objetivo original do produto menciona app Android;
- este ZIP não contém projeto Android.

Antes do fechamento, a Constituição precisa declarar uma das opções:

### Opção A — release web responsivo/PWA

- Android sai oficialmente da versão 1.
- O layout mobile recebe prioridade máxima.
- Pode-se empacotar futuramente com Trusted Web Activity/Capacitor, mas isso não deve ser prometido sem projeto próprio.

### Opção B — produto com app Android nativo

- Criar repositório/app Kotlin.
- Definir API versionada, autenticação por token, background location, notificações e upload offline.
- O percentual global cai, porque esse subsistema está em 0%.

---

# 4. Auditoria técnica do pacote

## 4.1 Validações executadas

| Verificação | Resultado |
|---|---|
| PHP lint | **323 arquivos PHP, zero erro de sintaxe** |
| TypeScript Playwright | **`npx tsc --noEmit` aprovado** |
| Teste de sessão | **4 verificações aprovadas** |
| PHPUnit | **não executável neste ambiente**: faltam `dom`, `mbstring`, `xml`, `xmlwriter` |
| E2E atual do pacote | **falhou** na evidência final com HTTP 500 |
| Rotas mapeadas | **105** |
| Views PHP | **60** |
| Migrations SQL versionadas | **20** |
| Suítes Playwright | **12** |

## 4.2 Evidências de qualidade arquitetural

### Pontos fortes

- Front controller com request ID, CSP nonce e tratamento de fatal.
- Máquina de estados e transições com lock.
- POR e evidência deixaram de ser somente ideia.
- Pagamento assíncrono e jobs são boa decisão.
- Logger possui contexto suficiente para manutenção.
- QA já produz artefatos e códigos de cenário.
- Temas por perfil já possuem base consistente.

### Dívidas estruturais

- Controllers grandes demais.
- Views misturam consulta, HTML, CSS e JavaScript.
- Dependência de CDN apesar de assets locais.
- CSS distribuído por convenções conflitantes.
- Catches silenciosos.
- Código legado morto em `RankingService` e `app.js`.
- Cópias antigas do projeto dentro de `files/`, ampliando risco de confusão e varreduras falsas.

---

# 5. Screenshots de táxi × GuinchaFácil atual

As referências apresentam um produto visualmente superior por cinco motivos:

1. **Ação primária fica na primeira dobra.**
2. **A tela é composta por tarefas, não por módulos administrativos.**
3. **Dados operacionais aparecem em cards compactos.**
4. **Mapa, ETA e pessoa responsável formam uma única narrativa.**
5. **Navegação mobile é persistente e alcançável pelo polegar.**

O GuinchaFácil possui grande parte dos dados necessários, mas ainda os apresenta de forma fragmentada.

---

# 6. Cliente — comparação e especificação final

## 6.1 Referência

Os screenshots do cliente oferecem:

- campo “para onde deseja ir?” no topo;
- sugestões de serviços;
- atividades recentes;
- card “motorista chegando”;
- mapa e ETA;
- ligação/chat/compartilhamento;
- seleção de pagamento;
- carteira;
- favoritos;
- navegação inferior.

## 6.2 O que o GuinchaFácil já possui

- cadastro de veículos;
- oficinas favoritas;
- pedido novo com origem/destino;
- estimativa de preço;
- pagamento;
- acompanhamento;
- chat;
- timeline;
- dados e telefone do guincho;
- cancelamento com preview;
- fotos de coleta/entrega;
- financeiro/histórico.

## 6.3 Lacunas atuais do dashboard

- O campo rápido está no topo, mas o contrato está quebrado:
  - o formulário faz POST em `/cliente/pedido/novo`;
  - a rota POST correta é `/cliente/pedido/rascunho`;
  - texto digitado não é geocodificado;
  - a primeira submissão apenas muda um booleano;
  - o botão muda de verde para vermelho sem razão semântica.
- Existem dois mapas com `id="map"`.
- Existem dois componentes de pedido ativo.
- O banner de comunicados não renderiza imagem.
- Classes CSS e HTML não correspondem.
- Falta navegação inferior mobile.
- Falta uma composição de serviços rápidos.

## 6.4 Dashboard final do cliente

### Sem pedido ativo

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ Onde está o veículo?                                                     │
│ [ endereço / usar localização atual                       ] [GPS] [SOCORRO]│
└──────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────── HERO ─────────────────────────────────────┐
│ Olá, Bruno.                                                             │
│ Socorro veicular rápido, transparente e acompanhado em tempo real.      │
│ [1 veículo] [2 oficinas] [último pedido: 12 jul]                         │
└──────────────────────────────────────────────────────────────────────────┘

[Reboque agora] [Bateria] [Pneu] [Combustível] [Agendar]

┌──────────────────── comunicado segmentado ───────────────────────────────┐
│ imagem responsiva + título + CTA                                        │
└──────────────────────────────────────────────────────────────────────────┘

┌──────── mapa/localização ─────────────┐ ┌──────── atividades recentes ──┐
│                                      │ │ Pedido #63 — concluído        │
│                                      │ │ Pedido #59 — cancelado        │
└──────────────────────────────────────┘ └─────────────────────────────────┘
```

### Com pedido ativo

O compositor de socorro e os serviços são substituídos por um card operacional:

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ Guincho a caminho                                      Chega em 08 min  │
│ Matos Agência · ★ 4,8 · Ford Cargo ABC1D23                              │
│ Origem: Rua do Propósito, Gamboa                                        │
│ [Acompanhar] [Chat] [Ligar] [Compartilhar]                               │
└──────────────────────────────────────────────────────────────────────────┘
```

## 6.5 Métricas do layout cliente

| Elemento | Desktop | Mobile |
|---|---:|---:|
| Topbar | 64 px | 60 px |
| Compositor rápido | 80–96 px | 168–196 px |
| Input | 52 px | 52 px |
| Botão GPS | 52 × 52 px | 52 × 52 px |
| CTA | 168 × 52 px | 100% × 56 px |
| Hero | 180–220 px | auto, máximo visual ~260 px |
| Card de serviço | mínimo 148 px | 132 px |
| Mapa do dashboard | 380 px | 300 px |
| Card ativo | mínimo 148 px | auto |
| Bottom nav | — | 72 px + safe-area |

## 6.6 HTML recomendado — compositor rápido

```html
<section class="gf-rescue-composer" aria-labelledby="rescue-title">
  <div class="gf-rescue-composer__copy">
    <span class="gf-eyebrow">Atendimento imediato</span>
    <h1 id="rescue-title">Onde está o veículo?</h1>
  </div>

  <form class="gf-rescue-composer__form"
        action="/cliente/pedido/rascunho"
        method="post"
        data-controller="quick-rescue">
    <input type="hidden" name="csrf_token" value="...">
    <input type="hidden" name="lat_origem" data-field="lat">
    <input type="hidden" name="lng_origem" data-field="lng">

    <label class="gf-location-field">
      <span class="sr-only">Endereço atual do veículo</span>
      <span class="gf-location-field__icon" aria-hidden="true">●</span>
      <input name="endereco_origem"
             maxlength="220"
             autocomplete="street-address"
             placeholder="Digite o endereço ou ponto de referência"
             data-field="address">
      <span class="gf-location-field__status" data-role="status"></span>
    </label>

    <button class="gf-icon-button" type="button" data-action="locate"
            aria-label="Usar minha localização atual">⌖</button>
    <button class="gf-button gf-button--rescue" type="submit" data-role="submit">
      Pedir socorro
    </button>
  </form>
</section>
```

## 6.7 Serviços rápidos sugeridos

Os “Suggestions” do táxi devem ser traduzidos para necessidades reais do GuinchaFácil:

| Serviço | Dados adicionais | Destino do fluxo |
|---|---|---|
| Reboque imediato | origem, destino, veículo | pedido completo |
| Bateria/partida | origem, veículo | pedido pré-configurado |
| Pneu | origem, veículo, estepe | pedido pré-configurado |
| Pane seca | origem, combustível | pedido pré-configurado |
| Colisão | origem, fotos, condição do veículo | pedido especializado |
| Agendar transporte | data/hora, origem/destino | pedido agendado futuro |

Na versão 1, esses cards podem apenas pré-selecionar `tipo_problema`. Não é necessário criar seis motores de negócio diferentes.

---

# 7. Guincho — comparação e especificação final

## 7.1 Referência

Os screenshots do motorista/proprietário oferecem:

- status online;
- painel com contadores por estado;
- ofertas em cards;
- mapa resumido;
- distância/ETA/preço;
- aceitar/recusar;
- navegação até o cliente;
- botão “cheguei ao local”;
- carteira e saque;
- perfil/frota/documentos;
- histórico.

## 7.2 O que já existe

- toggle disponível;
- SSE e fallback de polling;
- fila de pedidos;
- aceite transacional;
- atendimento com mapa;
- chat;
- GPS por `watchPosition()`;
- avanço de status;
- fotos;
- POR backend;
- financeiro e dados bancários;
- perfil operacional.

## 7.3 Problemas atuais

- Online aparece em múltiplos lugares.
- Pedido ativo está duplicado.
- A fila mostra apenas o primeiro pedido.
- A oferta resumida não mostra distância até origem, ETA, validade ou score.
- Dados remotos entram em `innerHTML` sem escape.
- O card contém texto técnico sobre container/JavaScript.
- Há mojibake e literal `` `r`n ``.
- Badge e toggle podem divergir.
- Não existe buffer offline para GPS.
- O painel não mostra qualidade POR, precisão, idade ou sequência.
- Instruções de rota estão ocultas.

## 7.4 Dashboard final do guincho

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ Bom dia, Matos Agência.                              [Online ● | toggle] │
│ Área: 20 km · GPS bom · 3 corridas hoje                                  │
└──────────────────────────────────────────────────────────────────────────┘

[Atendimentos hoje] [Ganho hoje] [Nota] [Km comprovados]

┌────────────────────── nova oferta ────────────────────────────────────────┐
│ 01:24 para responder                            R$ 189,60                │
│ 4,8 km até o cliente · ETA 11 min · percurso total 18,2 km               │
│ Peugeot 207 · pane mecânica · Gamboa → Oficina do Diego                  │
│ [Recusar]                                               [Aceitar corrida]│
└──────────────────────────────────────────────────────────────────────────┘

┌──────── mapa operacional ──────────────┐ ┌──── fila / últimos ──────────┐
│ guincho + ofertas + raio               │ │ ofertas ordenadas            │
│                                        │ │ último atendimento           │
└────────────────────────────────────────┘ └───────────────────────────────┘
```

## 7.5 Card de oferta

```html
<article class="gf-offer-card" data-offer-id="12375">
  <header class="gf-offer-card__header">
    <div>
      <span class="gf-eyebrow">Nova solicitação</span>
      <h2>Pedido #12375</h2>
    </div>
    <div class="gf-offer-card__timer" data-role="countdown">01:24</div>
  </header>

  <dl class="gf-offer-card__metrics">
    <div><dt>Até o cliente</dt><dd>4,8 km</dd></div>
    <div><dt>Chegada</dt><dd>11 min</dd></div>
    <div><dt>Serviço</dt><dd>18,2 km</dd></div>
    <div><dt>Valor</dt><dd>R$ 189,60</dd></div>
  </dl>

  <div class="gf-route-summary">
    <p><span class="gf-dot gf-dot--origin"></span>Gamboa, Rio de Janeiro</p>
    <p><span class="gf-dot gf-dot--destination"></span>Oficina do Diego</p>
  </div>

  <p class="gf-offer-card__vehicle">Peugeot 207 · pane mecânica</p>

  <footer class="gf-offer-card__actions">
    <button class="gf-button gf-button--secondary" data-action="reject">Recusar</button>
    <button class="gf-button gf-button--primary" data-action="accept">Aceitar corrida</button>
  </footer>
</article>
```

## 7.6 Atendimento e POR visível

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ Atendimento #63 · A caminho                     GPS bom · atualizado 4 s │
└──────────────────────────────────────────────────────────────────────────┘

┌──────────────── mapa/rota 7 col ───────────────┐ ┌──── operação 5 col ─┐
│ rota restante e trilha comprovada              │ │ Cliente + telefone  │
│ instrução atual: vire à direita...              │ │ Endereços           │
│                                                 │ │ Chat                │
│                                                 │ │ POR                 │
│                                                 │ │ ✓ 18 pontos válidos│
│                                                 │ │ ✓ sequência íntegra│
│                                                 │ │ ✓ precisão 9 m     │
│                                                 │ │ ○ chegar ao raio   │
│                                                 │ │ [Cheguei ao local] │
└─────────────────────────────────────────────────┘ └─────────────────────┘
```

### Regras visuais do botão de avanço

- `a_caminho`: verde, “Cheguei ao local”; desabilitado fora da geofence.
- `no_local`: verde, “Iniciar reboque”; exige evidência de coleta.
- `em_reboque`: verde, “Finalizar atendimento”; exige destino + evidência.
- Falha de requisito aparece abaixo do botão, nunca em alert genérico.

---

# 8. Admin — comparação e especificação final

## 8.1 Referência e lacuna

Os screenshots administrativos de táxi usam o mapa como centro de comando. O admin atual do GuinchaFácil tem métricas e gráficos, mas ainda se comporta como backoffice clássico.

Faltam:

- mapa ao vivo da operação;
- pedidos ativos por fase;
- guinchos online/ocupados/offline;
- fila de incidentes;
- alertas de GPS/POR;
- jobs financeiros com falha;
- vencimentos documentais;
- campanhas/comunicados;
- estado do QA.

## 8.2 Command Center proposto

```text
┌──────────────────────────────────────────────────────────────────────────┐
│ Central Operacional · atualização há 3 s        [produção] [saúde 98%]  │
└──────────────────────────────────────────────────────────────────────────┘

[Pedidos ativos] [Guinchos online] [ETA médio] [Receita hoje] [Alertas]

┌──────────────────────────── MAPA AO VIVO 8 col ───────────┐ ┌ ALERTAS 4 ┐
│ clusters, pedidos, guinchos, rotas e geofences             │ │ GPS ruim  │
│                                                             │ │ PIX falhou│
│                                                             │ │ doc vence │
└─────────────────────────────────────────────────────────────┘ └───────────┘

┌──── operação por status ────┐ ┌──── financeiro/jobs ─────┐ ┌── QA ──────┐
│ busca 8 / caminho 4 / local │ │ 2 retries / 1 manual     │ │ 11/12 verde│
└─────────────────────────────┘ └───────────────────────────┘ └─────────────┘
```

## 8.3 Central de Comunicados

O novo controlador é uma decisão correta, mas o módulo precisa ser concluído.

### O que já existe

- `AdminComunicadoController`.
- `ComunicadoController`.
- `Comunicado`.
- `ComunicadoService`.
- `MediaUploadService`.
- migration das tabelas.
- listagem, formulário e preview.
- placements de cliente e guincho.

### O que ainda falta

- O componente ignora as imagens.
- Não usa `<picture>` nem a imagem mobile.
- Não aplica formato, tema ou focal point.
- Não usa duração individual.
- Não respeita frequência por sessão/dia.
- Não permite fechar/dismiss.
- Não registra impressão, clique e fechamento.
- Não há controles, pausa, visibilidade ou `prefers-reduced-motion`.
- O formulário administrativo não contém todos os campos da tabela.
- O POST normal recebe JSON em vez de redirect/flash ou tratamento AJAX.
- O upload não reprocessa a imagem e fica público.
- Exceções do model são silenciosas.

## 8.4 Dimensões de mídia

| Placement | Desktop recomendado | Mobile recomendado | Aspect ratio | Peso alvo |
|---|---:|---:|---:|---:|
| Cliente topo | 1440 × 420 | 1080 × 608 | 24:7 / 16:9 | ≤ 350 KB WebP |
| Guincho após stats | 1440 × 360 | 1080 × 608 | 4:1 / 16:9 | ≤ 320 KB WebP |
| Card duplo | 640 × 360 | 720 × 720 | 16:9 / 1:1 | ≤ 220 KB |
| Admin interno | 1200 × 300 | não aplicável | 4:1 | ≤ 300 KB |

### Regras

- Rejeitar largura menor que o mínimo.
- Gerar variantes WebP server-side.
- Preservar original fora de `public` somente se necessário.
- Guardar `width`, `height`, `mime`, `bytes`, `sha256`.
- Focal point X/Y de 0–100.
- Texto principal em HTML; não “assar” texto na imagem.
- Máximo cinco comunicações ativas por placement.
- Duração de 5 a 20 segundos; padrão 8.
- Não mostrar propaganda durante pagamento, aceite, atendimento ou evidência.

---

# 9. Design system técnico por perfil

## 9.1 Escala global

```css
:root {
  --gf-space-1: 4px;
  --gf-space-2: 8px;
  --gf-space-3: 12px;
  --gf-space-4: 16px;
  --gf-space-5: 20px;
  --gf-space-6: 24px;
  --gf-space-8: 32px;
  --gf-space-10: 40px;
  --gf-space-12: 48px;
  --gf-space-16: 64px;

  --gf-radius-control: 14px;
  --gf-radius-card: 24px;
  --gf-radius-hero: 30px;
  --gf-radius-pill: 999px;

  --gf-topbar-h: 64px;
  --gf-sidebar-w: 248px;
  --gf-mobile-nav-h: 72px;
  --gf-control-h: 48px;
  --gf-control-h-lg: 56px;
  --gf-content-max: 1440px;

  --gf-shadow-sm: 0 8px 24px rgba(12, 28, 17, .08);
  --gf-shadow-md: 0 20px 60px rgba(12, 28, 17, .12);
  --gf-shadow-focus: 0 0 0 4px rgba(47,179,74,.20);

  --gf-brand: #2fb34a;
  --gf-brand-hover: #248f3a;
  --gf-danger: #dc3f45;
  --gf-warning: #e7a51a;
  --gf-info: #3186dc;
  --gf-success: #1eaa50;
}
```

## 9.2 Cliente — branco acolhedor

```css
body.theme-client {
  --gf-bg: #f4f8f5;
  --gf-surface: #ffffff;
  --gf-surface-2: #edf6ef;
  --gf-surface-3: #e3f1e6;
  --gf-border: #d4e6d8;
  --gf-text: #142018;
  --gf-muted: #607066;
  --gf-nav: #ffffff;
  --gf-sidebar: #edf6ef;
  --gf-accent: #2fb34a;
  --gf-on-accent: #ffffff;
}
```

### Linguagem visual

- superfícies brancas;
- bordas verdes muito suaves;
- CTA verde como único foco;
- vermelho apenas para cancelar/erro;
- cards de serviço com tons pastel e ícones grandes;
- mapa claro.

## 9.3 Guincho — verde operacional

```css
body.theme-tow {
  --gf-bg: #061209;
  --gf-surface: #0d2413;
  --gf-surface-2: #14351c;
  --gf-surface-3: #1a4324;
  --gf-border: #285632;
  --gf-text: #f5fff7;
  --gf-muted: #b3d7bb;
  --gf-nav: #050f07;
  --gf-sidebar: #07150a;
  --gf-accent: #42d564;
  --gf-on-accent: #061209;

  --gf-light-surface: #f7fbf8;
  --gf-light-text: #142018;
  --gf-light-muted: #607066;
}
```

### Linguagem visual

- fundo escuro de baixa luminância;
- ofertas podem usar superfície clara para contraste, mas com tokens locais;
- verde neon controlado apenas em ações e status;
- âmbar em alerta/timer;
- vermelho somente para destrutivo;
- mapa com filtro noturno opcional, sem reduzir legibilidade.

## 9.4 Admin — preto de comando

```css
body.theme-admin {
  --gf-bg: #07090c;
  --gf-surface: #11151a;
  --gf-surface-2: #171d24;
  --gf-surface-3: #202831;
  --gf-border: #29313a;
  --gf-text: #f7f9fb;
  --gf-muted: #a6b0ba;
  --gf-nav: #030405;
  --gf-sidebar: #0a0d10;
  --gf-accent: #2fb34a;
  --gf-on-accent: #051208;
}
```

### Linguagem visual

- densidade maior;
- cards menores;
- gráficos e mapas em grade;
- verde sinaliza saúde/ação;
- azul para informação;
- âmbar para atenção;
- vermelho para incidente;
- nenhum card branco flutuante sem necessidade.

---

# 10. Shell e responsividade

## 10.1 HTML base

```html
<body class="theme-client">
  <header class="gf-topbar">...</header>
  <div class="gf-shell">
    <aside class="gf-sidebar">...</aside>
    <main class="gf-main">
      <div class="gf-content">...</div>
    </main>
  </div>
  <nav class="gf-bottom-nav" aria-label="Navegação principal mobile">...</nav>
</body>
```

## 10.2 CSS base

```css
.gf-shell {
  min-height: calc(100dvh - var(--gf-topbar-h));
  display: grid;
  grid-template-columns: var(--gf-sidebar-w) minmax(0, 1fr);
}

.gf-main {
  min-width: 0;
  background: var(--gf-bg);
  color: var(--gf-text);
}

.gf-content {
  width: min(100%, var(--gf-content-max));
  margin-inline: auto;
  padding: 32px;
}

.gf-card {
  border: 1px solid var(--gf-border);
  border-radius: var(--gf-radius-card);
  background: var(--gf-surface);
  box-shadow: var(--gf-shadow-sm);
}

@media (max-width: 991.98px) {
  .gf-shell { grid-template-columns: 1fr; }
  .gf-sidebar { display: none; }
  .gf-content { padding: 20px; }
}

@media (max-width: 767.98px) {
  .gf-content {
    padding: 16px 16px calc(var(--gf-mobile-nav-h) + 24px);
  }
  .gf-bottom-nav { display: grid; }
}
```

## 10.3 Breakpoints de aceite

Testar obrigatoriamente:

- 360 × 800;
- 390 × 844;
- 768 × 1024;
- 1024 × 768;
- 1366 × 768;
- 1440 × 900;
- 1920 × 1080.

---

# 11. JavaScript — arquitetura obrigatória

Não concentrar lógica em `<script>` dentro das views. Cada módulo deve possuir estados, erros e logs explícitos.

## 11.1 `QuickRescueController`

Estados:

```text
idle → locating → resolved → submitting → redirected
  └──────────────→ error ───────────────→ retry
```

Contrato:

```js
class QuickRescueController {
  constructor(root, deps) {
    this.root = root;
    this.api = deps.api;
    this.logger = deps.logger;
    this.state = 'idle';
    this.abortController = null;
  }

  async locate() {
    this.setState('locating');
    try {
      const position = await this.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 12000,
        maximumAge: 15000
      });
      const address = await this.api.reverseGeocode(
        position.coords.latitude,
        position.coords.longitude
      );
      this.fillLocation(position.coords, address);
      this.setState('resolved');
    } catch (error) {
      this.fail('QRS-LOC-001', 'Não foi possível obter sua localização.', error);
    }
  }

  async submit(event) {
    event.preventDefault();
    if (!this.hasCoordinates()) {
      await this.resolveTypedAddress();
    }
    this.setState('submitting');
    try {
      const result = await this.api.postForm('/cliente/pedido/rascunho', this.form);
      window.location.assign(result.redirect);
    } catch (error) {
      this.fail('QRS-DRF-002', 'Não foi possível preparar o pedido.', error);
    }
  }
}
```

Requisitos:

- `AbortController` em geocode/autocomplete.
- Debounce de 350–500 ms.
- Nunca fingir que texto digitado foi geocodificado.
- Botão permanece verde; estado de loading não vira vermelho.
- Status em região `aria-live`.
- Log com classe, função, fase e código.

## 11.2 `TowOfferStream`

```js
class TowOfferStream {
  start() {
    this.source = new EventSource(this.url);
    this.source.addEventListener('offers_snapshot', e => {
      const payload = JSON.parse(e.data);
      this.renderOffers(payload.offers);
    });
    this.source.addEventListener('offer_removed', e => {
      this.removeOffer(JSON.parse(e.data).pedido_id);
    });
    this.source.onerror = () => this.scheduleReconnect();
  }

  renderOffer(offer) {
    const card = this.template.content.firstElementChild.cloneNode(true);
    card.querySelector('[data-field="problem"]').textContent = offer.tipo_problema;
    card.querySelector('[data-field="origin"]').textContent = offer.endereco_origem;
    card.querySelector('[data-field="price"]').textContent = offer.custo_formatado;
    // Nunca inserir valor remoto com innerHTML.
    return card;
  }
}
```

Requisitos:

- snapshot completo, não só primeiro pedido;
- `offer_id`/`version`;
- timer de validade sincronizado pelo servidor;
- reconexão exponencial com jitter;
- fallback polling após limite;
- remoção imediata quando outro guincho aceitar;
- `textContent` em todo campo remoto;
- toast construído com DOM, não string HTML.

## 11.3 `ProofOfRoadTracker`

Estados:

```text
starting → tracking → degraded → offline-buffering → syncing → stopped
```

Requisitos:

- fila local IndexedDB;
- UUID por ponto;
- sequência persistida;
- reenvio idempotente;
- indicador de precisão;
- idade do último envio;
- quantidade em fila;
- confirmação do servidor com último `sequence_accepted`;
- backoff;
- não perder ponto só porque uma requisição falhou.

## 11.4 `CommunicationCarousel`

```js
class CommunicationCarousel {
  constructor(root, metrics) {
    this.slides = [...root.querySelectorAll('[data-slide]')];
    this.metrics = metrics;
    this.index = 0;
    this.reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  duration(slide) {
    return Math.min(20, Math.max(5, Number(slide.dataset.duration || 8))) * 1000;
  }

  show(index, reason = 'auto') {
    // hidden/aria-current, pause em foco/hover, timer por slide.
  }

  track(type, slide) {
    navigator.sendBeacon('/comunicados/metricas', JSON.stringify({
      comunicado_id: Number(slide.dataset.id),
      type,
      placement: this.root.dataset.placement
    }));
  }
}
```

Requisitos:

- `<picture>` desktop/mobile;
- duração por slide;
- pausa em hover/foco/aba oculta;
- reduced motion;
- controles e indicadores;
- teclado;
- dismiss por sessão/dia/TTL;
- impressão só quando `IntersectionObserver` confirmar visibilidade;
- clique e fechamento com `sendBeacon`.

## 11.5 `AdminOpsStream`

O dashboard admin deve consumir um endpoint/SSE agregado, não efetuar dezenas de consultas independentes:

```json
{
  "server_time": "2026-07-14T11:32:09-03:00",
  "orders": {"active": 12, "searching": 4, "tow": 3},
  "fleet": {"online": 28, "busy": 12, "offline": 46},
  "alerts": [{"code":"POR-QUAL-004","severity":"warning","pedido_id":63}],
  "map": {"tow_points": [], "order_points": [], "routes": []},
  "payments": {"failed_jobs": 1},
  "qa": {"status":"failed","failed_suite":"E2E-ORD-001"}
}
```

---

# 12. Arquitetura de arquivos recomendada

```text
public/assets/css/
  tokens.css
  base.css
  shell.css
  utilities.css
  themes/
    client.css
    tow.css
    admin.css
  components/
    buttons.css
    cards.css
    stats.css
    forms.css
    status.css
    timeline.css
    map.css
    communications.css
    bottom-nav.css
  pages/
    client-dashboard.css
    client-request.css
    client-tracking.css
    tow-dashboard.css
    tow-offer.css
    tow-attendance.css
    admin-dashboard.css
    admin-communications.css

public/assets/js/
  core/
    api-client.js
    logger.js
    dom.js
    session-manager.js
  components/
    communication-carousel.js
    bottom-nav.js
    toast.js
  client/
    quick-rescue-controller.js
    request-wizard.js
    tracking-controller.js
  tow/
    offer-stream.js
    proof-of-road-tracker.js
    attendance-controller.js
  admin/
    ops-stream.js
    communication-editor.js
```

## Regra de transição

Não reescrever tudo de uma vez. Criar os novos componentes, migrar página por página e remover a classe antiga apenas depois do teste visual e funcional.

---

# 13. Logs obrigatórios para os novos sistemas

Formato mínimo:

```json
{
  "level": "error",
  "system": "quick_rescue",
  "class": "QuickRescueController",
  "function": "submit",
  "file": "quick-rescue-controller.js",
  "phase": "draft_create",
  "code": "QRS-DRF-002",
  "request_id": "req_...",
  "pedido_id": null,
  "message": "Falha ao criar rascunho",
  "context": {"http_status": 422}
}
```

Códigos iniciais:

| Sistema | Código | Fase |
|---|---|---|
| Quick Rescue | `QRS-LOC-001` | geolocalização |
| Quick Rescue | `QRS-GEO-002` | geocodificação |
| Quick Rescue | `QRS-DRF-003` | rascunho |
| Communications | `COM-MED-001` | upload |
| Communications | `COM-PUB-002` | publicação |
| Communications | `COM-MET-003` | métrica |
| Tow Offer | `DSP-SSE-001` | stream |
| Tow Offer | `DSP-ACC-002` | aceite |
| POR | `POR-BUF-001` | buffer offline |
| POR | `POR-SYN-002` | sincronização |
| Evidence | `EVD-UPL-001` | upload |
| Evidence | `EVD-TRN-002` | transição |
| Admin Ops | `OPS-SSE-001` | stream |

---

# 14. Critérios gráficos e funcionais de aceite

## 14.1 HTML

- Zero IDs duplicados.
- Heading order lógico.
- Um `main` por página.
- Botões reais para ações; links para navegação.
- `aria-live` para status assíncrono.
- Labels em todos os inputs.
- `alt` administrativo obrigatório para banners.
- Sem texto técnico na UI final.

## 14.2 CSS

- Zero `style="..."` nas views migradas.
- Contraste AA mínimo 4,5:1.
- Foco visível.
- Alvo de toque mínimo 44 × 44 px.
- Sem overflow horizontal em 360 px.
- Cards do guincho claros usam tokens locais de contraste.
- Um CTA dominante por tela.
- Sem hero maior que o conteúdo necessário.

## 14.3 JavaScript

- Sem `innerHTML` com resposta da API.
- Sem catch vazio.
- Sem URL de ambiente hardcoded.
- Reconexão e timeout explícitos.
- Estado de loading/error visível.
- Operações idempotentes.
- Módulos testáveis sem depender do DOM global inteiro.

## 14.4 Performance

Metas de produção:

- LCP ≤ 2,5 s em 4G intermediário.
- CLS ≤ 0,1.
- JS inicial do dashboard ≤ 180 KB gzip, excluindo mapa carregado sob demanda.
- Imagens de banner com `width/height` para reservar espaço.
- Leaflet e Chart.js locais, versionados.
- Mapas inicializados apenas quando visíveis.

## 14.5 Playwright

Suítes mínimas de release:

1. login/sessão;
2. cliente cria pedido freeflow;
3. cliente cria pedido produção com pagamento simulado;
4. dois guinchos disputam pedido;
5. chat bilateral;
6. POR válido;
7. POR rejeita teleporte/replay;
8. coleta com evidência;
9. entrega com evidência;
10. cancelamento cliente;
11. desistência guincho/reassign;
12. repasse job/retry;
13. comunicados agenda/frequência/métrica;
14. screenshots de cliente/guincho/admin nos breakpoints.

Baseline exigido: **14/14 verde em uma mesma execução**, não resultados verdes isolados em datas diferentes.

---

# 15. Plano de conclusão recomendado

## Fase 1 — estabilização constitucional

- segredos;
- migrations limpas;
- erro E2E final;
- evidência privada/atômica;
- POR transacional;
- cancelamento snapshot;
- QA portável;
- baseline verde.

**Saída:** fluxo cliente → guincho → chat → percurso → evidências → conclusão integralmente confiável.

## Fase 2 — unificação visual

- tokens e temas definitivos;
- shell;
- dashboard cliente;
- dashboard guincho;
- atendimento;
- oferta;
- admin command center;
- bottom nav;
- remover inline CSS e CDNs.

**Saída:** três perfis visualmente coerentes e personalizados.

## Fase 3 — diferenciação CabME adaptada

- compositor rápido;
- cards de serviços;
- card ativo completo;
- carteira/payment drawer;
- oferta rica;
- POR visível;
- comunicados completos;
- notificações.

**Saída:** experiência no nível das referências, sem copiar funcionalidades irrelevantes de táxi.

## Fase 4 — governança e release

- definir Android/PWA;
- implementar pacote jurídico constitucional se continuar obrigatório;
- carga/performance;
- backup/restore;
- observabilidade e alertas;
- runbook de produção;
- release candidate.

---

# 16. Arquivos prioritários afetados

## Cliente

- `src/Views/cliente/dashboard.php`
- `src/Views/cliente/pedidonovo.php`
- `src/Views/cliente/pedidostatus.php`
- `src/Controllers/ClienteController.php`
- `public/assets/js/quick-rescue.js` — substituir
- `public/assets/js/cliente-pedido.js` — modularizar
- `public/assets/js/atendimento-status.js`
- CSS de dashboard/request/tracking

## Guincho

- `src/Views/guincho/dashboard.php`
- `src/Views/guincho/pedidoaceitar.php`
- `src/Views/guincho/atendimento.php`
- `src/Controllers/GuinchoController.php`
- novo `offer-stream.js`
- novo `proof-of-road-tracker.js`
- CSS de dashboard/offer/attendance

## Admin

- `src/Views/admin/dashboard.php`
- `src/Controllers/AdminController.php` — reduzir, não crescer
- novo `AdminOpsController`
- `AdminComunicadoController.php`
- views de comunicados
- novo editor JS
- CSS admin dashboard/comunicações

## Núcleo constitucional

- `PedidoTransitionService.php`
- `ProofOfRoadService.php`
- `EvidenceService.php`
- `CancelamentoService.php`
- `CancellationCalculationService.php`
- `PaymentJobService.php`
- `PlaywrightRunnerService.php`
- migrations relacionadas

---

# 17. Decisão final recomendada

O projeto deve ser tratado como uma **reta final de consolidação**, não como fase de adicionar telas aleatoriamente.

A ordem correta é:

1. fazer o fluxo constitucional terminar verde;
2. remover inconsistências e riscos objetivos;
3. implantar o design system único;
4. reconstruir os três painéis com a linguagem dos screenshots;
5. concluir Comunicados e Command Center;
6. fechar a decisão Android/PWA e governança jurídica.

A referência de táxi está “um nível acima” principalmente porque apresenta melhor o que o usuário precisa naquele instante. O GuinchaFácil já possui dados e regras mais complexos — especialmente POR, cancelamento e evidências. O próximo salto não é copiar o táxi; é **transformar essa complexidade em uma interface simples, rápida e confiável**.

---

# Apêndice A — problemas concretos encontrados

- `.env.local` incluído no ZIP.
- `cliente/dashboard.php` com BOM e dois `id="map"`.
- `guincho/dashboard.php` com BOM, mojibake e literal `` `r`n ``.
- formulário rápido usa rota POST inexistente.
- JS rápido não geocodifica endereço digitado.
- CSS BEM divergente do HTML.
- pedido ativo duplicado nos dois dashboards.
- online duplicado no guincho.
- `innerHTML` inseguro na fila/toast.
- `run_all_migrations.php` com catch duplicado.
- migration QA com índice duplicado.
- componentes de comunicação ignoram imagem e metadados.
- formulário de comunicação incompleto.
- Playwright atual falha na entrega.
- runner Playwright dependente de Windows.
- nenhum projeto Android.
- nenhum pacote `SEG-01/CT-01/PU-01/GOV-01`.

# Apêndice B — tamanho dos maiores arquivos

| Arquivo | Linhas |
|---|---:|
| `AdminController.php` | 1.827 |
| `AuthController.php` | 1.709 |
| `GuinchoController.php` | 882 |
| `ClienteController.php` | 757 |
| `admin/pedidodetalhe.php` | 959 |
| `cliente/pedidostatus.php` | 953 |
| `guincho/atendimento.php` | 755 |
| `cliente/pedidonovo.php` | 725 |
| `style.css` | 778 |

Esses números não são defeito por si só, mas indicam concentração de responsabilidades e tornam a reta final mais arriscada.
