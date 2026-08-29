# GuinchaFácil — Backlog Técnico e Especificação de Implementação

> Documento gerado a partir de auditoria direta do código-fonte (`GuinchaFacil.zip`), não do resumo de terminal fornecido. Toda afirmação "implementado" ou "faltando" abaixo foi confirmada lendo os arquivos reais do projeto (controllers, services, views, JS e schema SQL).
>
> Escopo confirmado: **projeto web (PHP)**. Não há e não haverá app Android — removido do escopo.

---

## 1. Pendências gerais do projeto (auditoria de código)

| # | Item | Situação real no código | Ação necessária |
|---|------|--------------------------|------------------|
| 1 | `src/Controllers/SseController.php` | Implementação completa (261 linhas) de Server-Sent Events, com comentário próprio dizendo que "substitui polling HTTP" — mas **sem nenhuma rota registrada em `index.php`** e **sem nenhum `EventSource` no front-end**. Código morto. | Decidir: (a) apagar o arquivo, ou (b) integrar de fato (ver seção 3). |
| 2 | `termos-servico.php:71` | Placeholder literal não preenchido: `[métodos de pagamento, ex.: cartão de crédito, boleto, PIX, etc.]` | Substituir pelo texto real. |
| 3 | `politica-privacidade.php:102` | Placeholder literal: `[Insira o endereço físico da empresa, se aplicável]` | Substituir pelo endereço real. |
| 4 | `tools/cron_*.php` (4 scripts) | Scripts prontos (`cron_reprocessar_pix.php`, `cron_cancelar_pedidos_expirados.php`, `cron_limpar_logs.php`, `cron_limpar_tokens.php`) mas **sem crontab configurado** no servidor. | Agendar no cron do servidor de produção. |
| 5 | `public/assets/js/app.js` | Carregado em 3 páginas (`cliente/dashboard`, `cliente/pedidostatus`, `guincho/dashboard`) mas contém apenas classes-esqueleto vazias: `MaskUtils.applyCPF()`, `MaskUtils.applyPhone()`, `CostCalculator.calculate()`, `StatusPoller.start()`, `ChatManager.startPolling()` — todos com `console.log` e comentário, sem lógica. **Resultado prático: máscara de CPF/telefone não funciona em nenhum formulário do projeto.** | Implementar as máscaras de fato (ou remover o arquivo e usar uma lib como `imask`/`cleave.js`) e remover as demais classes mortas. |
| 6 | Cálculo de tarifa duplicado | A fórmula `taxa_fixa + tarifa_por_km × distância` está copiada em 5 lugares (`ClienteController.php:269`, `ClienteController.php:426`, `AdminController::pedidoCriar`, `SimulationService.php:125`) e existe um `GeoService::calcularCusto()` que **nunca é chamado**. | Centralizar (ver seção 6). |

---

## 2. Fluxo operacional do atendimento — estado atual vs. especificado

Mapeamento do fluxo que você descreveu contra o `status` real da tabela `pedidos` (ENUM: `aguardando_pagamento`, `aguardando_guincho`, `a_caminho`, `no_local`, `em_reboque`, `concluido`, `cancelado` — não existe `em_atendimento`, o nome real da etapa "carro na plataforma" é `em_reboque`).

| Etapa descrita | Status real | Implementado? | Observação |
|---|---|---|---|
| Cliente cria pedido | `aguardando_guincho` (ou `aguardando_pagamento` antes, se `payment_required=1`) | ✅ | `ClienteController::pedidoNovo` |
| Guincho vê pedido dentro do raio | — | ✅ | `RankingService` + raio de cobertura do guincho |
| Guincho aceita → rota até a origem | `a_caminho` | ⚠️ **Parcial** | Ver seção 3 — falta o roteamento real da posição atual do guincho até a origem |
| Cliente vê dados do guincho + foto do caminhão | — | ✅ | `pedidostatus.php` puxa nome/telefone/placa/foto via polling |
| Cliente vê nome das ruas por onde o guincho passa | — | ❌ **Não implementado** | Ver seção 3 |
| Guincho chega → `no_local` | `no_local` | ✅ | `GuinchoController::atualizarStatus` |
| Cliente vê status "no local" | `no_local` | ✅ | Refletido via polling |
| Guincho tira foto do carro na plataforma → `em_reboque` | `em_reboque` + `foto_plataforma` | ✅ | Foto **obrigatória** para transição (`atualizarStatus`) |
| Cliente vê foto na plataforma | — | ✅ | Exibida no card do pedido |
| Guincho chega ao destino, tira foto → `concluido` | `concluido` + `foto_destino` | ✅ | Foto obrigatória, dispara repasse PIX automaticamente |
| Cliente vê foto de conclusão | — | ✅ | |
| Cancelamento com taxa proporcional à distância percorrida, aviso via modal | — | ❌ **Não implementado** | Ver seção 4 |

**Resumo:** o esqueleto de status/fotos está pronto e funcionando. As duas lacunas reais são **rastreamento com nome de ruas** e **cancelamento com taxa**.

---

## 3. Especificação técnica: rastreamento em tempo real com nome das ruas

### O que existe hoje
- `src/Views/cliente/pedidostatus.php` desenha **uma única rota fixa** com `L.Routing.control()` entre `lat_origem/lng_origem` e `lat_destino/lng_destino` do pedido (ou seja, a rota **do reboque em si**, origem→destino do carro do cliente) — carregada uma vez, nunca recalculada.
- O ícone do guincho (`markerGuincho`) **se move de fato**: `atendimento-status.js`/polling a cada 10s chama `GET /cliente/pedido/status/{id}`, recebe `lat_guincho`/`lng_guincho` (colunas `guinchos.lat_atual`/`lng_atual`) e faz `markerGuincho.setLatLng(pos)`.
- **Problema 1:** durante a fase `a_caminho`, o que interessa ao cliente é a rota do **guincho até a origem** (coleta), não a rota origem→destino. Essa rota nunca é desenhada.
- **Problema 2:** mesmo se fosse desenhada, ela não seria recalculada conforme o guincho se move — hoje é "desenhe uma vez e esqueça".
- **Problema 3:** não existe painel de itinerário (nomes de rua). O Leaflet Routing Machine tem esse painel por padrão, mas como a rota nunca reflete a posição real do guincho, o painel não tem utilidade nenhuma hoje.
- **Problema 4:** não há confirmação de que o app do guincho envia posição em intervalo curto — `POST /guincho/localizacao` existe (`GuinchoController::atualizarLocalizacao`), mas confirmar se há `navigator.geolocation.watchPosition()` disparando isso automaticamente durante o trajeto, ou se depende de ação manual.

### O que precisa ser feito

**Backend**
1. `GuinchoController::atualizarLocalizacao` já existe — confirmar/gerar chamada automática via `watchPosition()` no dashboard/atendimento do guincho (não só `getCurrentPosition()` pontual).
2. Endpoint de status do cliente (`ClienteController::pedidoStatusAjax` / rota `/cliente/pedido/status/{id}`) já retorna `lat_guincho`/`lng_guincho` — não precisa mudar.
3. Opcional (recomendado, dado que `SseController.php` já existe pronto): trocar o polling de 10s por SSE real, reduzindo latência de atualização da posição de 10s para ~2s (o `SseController` já usa `LOOP_SLEEP_MS = 2000`).

**Frontend (`pedidostatus.php` / novo JS)**
1. Separar a lógica de rota em duas fases:
   - **Fase `a_caminho`**: desenhar rota `[lat_guincho, lng_guincho] → [lat_origem, lng_origem]`.
   - **Fase `no_local` em diante**: desenhar rota `[lat_origem, lng_origem] → [lat_destino, lng_destino]` (o comportamento atual).
2. Recalcular a rota a cada atualização de posição (não só mover o marcador): `routingControl.setWaypoints([...])` dentro do `then()` do polling/SSE, com um *debounce* de ~5-10s para não sobrecarregar o serviço de roteamento (o Routing Machine por padrão usa o roteador público OSRM, que tem rate limit).
3. Exibir o painel de itinerário do próprio Routing Machine (ele já lista os nomes das ruas nativamente) — hoje provavelmente está oculto/não estilizado; configurar `show: true` e estilizar o painel lateral/inferior para mobile.
4. Mostrar tempo estimado e distância restante (o Routing Machine já retorna `summary.totalDistance`/`summary.totalTime` no evento `routesfound` — hoje esse evento não é escutado).

**Arquivos a alterar:** `src/Views/cliente/pedidostatus.php`, `public/assets/js/atendimento-status.js` (ou novo `guincho-tracking.js`), `src/Controllers/GuinchoController.php` (se for adicionar `watchPosition` no template do guincho).

---

## 4. Especificação técnica: cancelamento/desistência com taxa

### O que existe hoje
`src/Services/PedidoService::cancel()`:
- Regra **binária**, não proporcional: calcula a distância do guincho até a origem via Haversine (`GeoService::haversine`) e:
  - Se `distância < 2 km` → **bloqueia completamente o cancelamento** (`return false`), sem cobrar nada, sem opção.
  - Se `distância >= 2 km` → cancela e devolve **100% do valor** via `EstornoService::estornar()` (reembolso total, sem desconto).
- Só existe cancelamento pelo **cliente** (`ClienteController::cancelarPedido`) ou pelo **admin** (`AdminController::pedidoCancelar`, sem regra de distância).
- **Não existe cancelamento pelo guincho após aceitar o pedido.** O único método relacionado, `GuinchoController::recusar()`, só funciona **antes** do aceite (não altera o pedido no banco, só redireciona — serve para "não aceitar", não para "desistir depois de aceito").
- Não existe modal de aviso — o clique em cancelar hoje redireciona (`POST /cliente/pedido/cancelar/{id}`) sem etapa de confirmação com valor a ser retido.

### O que precisa ser feito

**1. Regra de cobrança (substituir o corte binário de 2km por escala proporcional)**
Exemplo de política a definir com o cliente/negócio (ajustar valores):

| Situação | Taxa de cancelamento sugerida |
|---|---|
| Pedido ainda `aguardando_guincho` (nenhum guincho aceitou) | Sem taxa — reembolso 100% |
| Guincho aceitou, `a_caminho`, distância percorrida < 20% do trajeto até a origem | Taxa fixa mínima (ex.: `taxa_fixa` da categoria, cobre o "acionamento") |
| `a_caminho`, guincho já percorreu boa parte do trajeto (ex. > 50% ou < 2 km da origem) | Taxa proporcional à distância já rodada pelo guincho (`tarifa_km_categoria × distância_percorrida`) |
| `no_local` ou depois | Não cancelável pelo cliente — só pelo admin, manualmente (como já está documentado no código: "análise manual do admin") |

**2. Nova coluna em `pedidos`:** `distancia_guincho_percorrida DECIMAL(6,2) NULL` — preenchida no momento do cancelamento com o valor calculado, para auditoria/financeiro (hoje o dado é calculado só em memória e descartado).

**3. Reembolso parcial no `EstornoService`**
Hoje `EstornoService::estornar()` só sabe estornar 100% (chama a API de refund total do MercadoPago/PagSeguro). É preciso:
- Adicionar `EstornoService::estornarParcial(int $pedidoId, float $valorReter): array`.
- MercadoPago: a API de refunds aceita `amount` parcial no payload (hoje o código manda `'{}'`, ou seja, sempre reembolso total — trocar para `{"amount": X}` quando for parcial).
- PagSeguro: verificar se a integração atual (`estornarPagSeguro`) suporta valor parcial na função existente ou se precisa de outro endpoint.

**4. Endpoint de simulação de taxa (para o modal)**
Novo endpoint `GET /cliente/pedido/{id}/simular-cancelamento` que roda a mesma lógica de cálculo e retorna `{ pode_cancelar: bool, taxa: float, motivo: string }` **sem executar o cancelamento** — usado para preencher o modal antes da confirmação.

**5. Modal de confirmação (frontend)**
Em `pedidostatus.php`, o botão "Cancelar pedido" deve:
1. Chamar o endpoint de simulação acima.
2. Abrir um modal (Bootstrap, já usado no projeto) mostrando: *"Ao cancelar agora, será cobrada uma taxa de R$ X,XX referente ao deslocamento já realizado pelo guincho. Deseja continuar?"*
3. Só então enviar o POST real de cancelamento (`/cliente/pedido/cancelar/{id}`), incluindo a taxa aceita para conferência no backend (o backend recalcula, nunca confia no valor vindo do front).

**6. Cancelamento pelo guincho após aceitar (hoje inexistente)**
Criar `GuinchoController::desistir(int $id)`:
- Válido apenas em `a_caminho` (antes de `no_local`).
- Sem taxa ao cliente (o cliente não tem culpa da desistência do prestador).
- Devolve o pedido para `aguardando_guincho` (reentra no pool de matching do `RankingService`) em vez de cancelar — ou aplica penalidade/registro no histórico do guincho (`AuditTrailService`) para controle de qualidade.
- Notificar o cliente via `NotificacaoService` que o guincho desistiu e que o sistema está buscando outro.

**Arquivos a alterar/criar:**
`src/Services/PedidoService.php` (regra de taxa), `src/Services/EstornoService.php` (reembolso parcial), `src/Controllers/ClienteController.php` (endpoint de simulação), `src/Controllers/GuinchoController.php` (método `desistir`), `src/Views/cliente/pedidostatus.php` (modal), migration para `pedidos.distancia_guincho_percorrida`.

---

## 5. Melhoria de tarifas — categoria de veículo + turno noturno/feriado + taxa de prioridade

### Situação atual
O cálculo de custo **ignora completamente a categoria do veículo** — é sempre `taxa_fixa + tarifa_por_km × distância`, com os mesmos dois valores globais para carro popular, SUV, elétrico ou caminhonete. Coluna `veiculos.tipo` existe mas é um ENUM genérico (`carro`,`moto`,`caminhao`,`van`,`onibus`,`outro`), não uma categoria de precificação.

### O que precisa ser feito

**A. Banco de dados**
- `veiculos`: adicionar `categoria_tarifa ENUM('popular','suv','eletrico','caminhonete') NOT NULL DEFAULT 'popular'`.
- `configuracoes` (já é key-value, não precisa alterar schema): novas chaves —
  - `tarifa_{categoria}_km` / `tarifa_{categoria}_fixa` (8 chaves)
  - `tarifa_noturna_km`, `tarifa_noturna_fixa`, `turno_noturno_inicio` (`20:00`), `turno_noturno_fim` (`06:00`)
  - `taxa_prioridade_valor`
- `pedidos`: adicionar `prioridade TINYINT(1) NOT NULL DEFAULT 0` e `tarifa_aplicada VARCHAR(30)` (auditoria).
- Nova tabela `feriados` (`data DATE`, `descricao VARCHAR(100)`, `nacional TINYINT(1)`) — não existe cálculo de feriado nenhum hoje.

**B. Centralizar o cálculo**
Criar `src/Services/TarifaService.php`:
```php
TarifaService::calcular(
    float $distanciaKm,
    string $categoriaVeiculo,
    bool $prioridade,
    ?DateTimeImmutable $agora = null
): array // ['valor' => float, 'detalhe' => [...]]
```
Substituir as 5 ocorrências duplicadas da fórmula (`ClienteController.php` x2, `AdminController::pedidoCriar`, `SimulationService.php`) por chamadas a este serviço, e remover/redirecionar o `GeoService::calcularCusto()` órfão.

**C. Admin** (`AdminController::configuracoesSalvar` + `configuracoes.php`)
Trocar os 2 campos atuais por tabela com 4 categorias × 2 valores + seção de turno noturno + taxa de prioridade + tela simples de CRUD de feriados.

**D. Cliente** (`pedidonovo.php` + `ClienteController`)
- Passar `categoria_tarifa` do veículo selecionado para o cálculo.
- Checkbox "Atendimento prioritário (+R$ X)".
- Endpoint AJAX `GET /cliente/pedido/estimar` para o preview de preço bater com o valor real (hoje o JS de preview usa só os 2 valores globais hardcoded).

**E. Cadastro de veículo** (`veiculoform.php` + `VeiculoService`)
Adicionar select de `categoria_tarifa`.

**F. Testes**
Reescrever `tests/Integration/SimulationFlowTest.php` e criar teste unitário isolado para `TarifaService` cobrindo a matriz categoria × turno × prioridade × feriado.

---

## 6. Ordem sugerida de execução

1. **Correções rápidas (baixo risco, alto valor):** placeholders dos documentos legais, agendamento dos crons, decisão sobre `SseController` (apagar ou integrar), correção do `app.js` (máscaras).
2. **`TarifaService` centralizado** — pré-requisito técnico para tudo que vem depois (tarifa por categoria, noturna, prioridade e até o cálculo de taxa de cancelamento usam a mesma base de tarifa).
3. **Cancelamento com taxa** — depende do `TarifaService` para saber quanto cobrar proporcionalmente.
4. **Rastreamento com rota/nome de ruas** — independente das outras, pode ser feito em paralelo.

---

*Documento gerado a partir de leitura direta do código em `/GuinchaFacil` (sem vendor/), cruzando com o histórico de conversa sobre precificação e o fluxo operacional descrito.*
