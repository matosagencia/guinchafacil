# Correções — Fluxo do Pedido em Tempo Real e Cancelamento com Penalidade

**Data:** 2026-07-08
**Escopo:** rastreamento em tempo real, rota dinâmica por status, sincronização cliente↔guincheiro, cancelamento bilateral com penalidade de tarifa.

---

## 1. Bugs corrigidos

### 1.1 `GuinchoController::atualizarStatus` — erro de parse (CRÍTICO)
Um merge anterior deixou chaves desbalanceadas no bloco de conclusão (Pix). O arquivo inteiro não compilava (`PHP Parse error` na linha 582), derrubando com HTTP 500 **todas** as rotas do guincheiro — dashboard, aceite, atendimento, chat e "Finalizar Corrida". Este era o principal motivo do fluxo não evoluir. O bloco foi reescrito com a estrutura correta e a notificação `pedidoConcluido` agora dispara tanto em freeflow quanto em produção.

### 1.2 Guincheiro nunca enviava posição
O endpoint `POST /guincho/localizacao` existia, mas nenhum JavaScript o chamava. A tela de atendimento agora usa `navigator.geolocation.watchPosition` com throttle de 10 s, enviando a posição ao servidor. Um badge "GPS" indica o estado do envio.

### 1.3 `TypeError` matava o chat do guincheiro
Com pedido `concluido`, `#statusForm` não existe e `document.getElementById('statusForm').addEventListener(...)` lançava exceção, interrompendo todo o script seguinte (incluindo o chat). Adicionado guard.

### 1.4 `PedidoService::cancel` — `$p` nulo no fluxo admin
Quando `$isAdmin=true` e o pedido não pertencia ao "cliente", `$p['status']` era acessado com `$p = null`. Fluxo admin reescrito com busca própria, sem taxa e com estorno integral.

## 2. Rota dinâmica por status (cliente e guincheiro)

| Status | Rota exibida | Cor |
|---|---|---|
| `a_caminho` | posição do guincho → origem (local do cliente) | laranja |
| `no_local` / `em_reboque` | origem → destino (reboque) | azul |
| demais | visão geral origem → destino | azul |

A rota é recriada apenas quando o **modo** muda; enquanto o guincho se move em `a_caminho`, apenas os waypoints são atualizados (`setWaypoints`), sem piscar o mapa. Legenda textual da rota no cabeçalho do card do mapa.

## 3. Tempo real

**Cliente** (`pedidostatus.php`): polling de 7 s em `/cliente/pedido/status-json/{id}` agora atualiza steps da timeline (`Em Reboque` incluído), banner, marcador e rota do guincho, card do guincho, fotos de prova de serviço (sem reload), estado do botão de cancelar e detecta: conclusão (redireciona para avaliação), cancelamento (banner com autor e taxa) e devolução à fila quando o guincheiro cancela.

**Guincheiro** (`atendimento.php`): polling de 8 s em `/guincho/pedido/status-json/{id}` detecta cancelamento pelo cliente/admin (aviso + redirect ao dashboard) e ressincroniza a página se o status mudar por outro dispositivo.

## 4. Cancelamento bilateral com penalidade de tarifa

### 4.1 Cliente (`CancelamentoService::cancelarPorCliente`)
- `aguardando_pagamento` / `aguardando_guincho`: grátis;
- `a_caminho` dentro da janela de arrependimento (`cancelamento_gratis_min`, padrão 5 min): grátis;
- `a_caminho` fora da janela: taxa = `max(taxa_cancelamento_fixa, custo × taxa_cancelamento_percent%)`, limitada ao valor do serviço; estorno **parcial** automático (valor pago − taxa);
- guincho a menos de `km_bloqueio_cancelamento` (padrão 2 km) da origem: cancelamento bloqueado;
- `no_local` / `em_reboque`: bloqueado (somente admin).
- UI: botão "Cancelar Pedido" + modal com o valor exato da taxa antes da confirmação (preview servido pelo backend e atualizado a cada poll).

### 4.2 Guincheiro (`CancelamentoService::cancelarPorGuincho`)
- permitido apenas em `a_caminho`, com motivo obrigatório;
- o pedido **volta para a fila** (`aguardando_guincho`, `guincho_id = NULL`, expiração renovada) — o cliente não paga nada;
- penalidade: `penalidade_reputacao_cancelamento` (padrão 0,25) descontada da reputação + incremento em `guinchos.total_cancelamentos`;
- rota nova: `POST /guincho/cancelar/{id}` → `GuinchoController::cancelarAtendimento`.

### 4.3 Garantias
Ambos os fluxos usam transação com `SELECT ... FOR UPDATE` e `UPDATE ... WHERE status = ?` (idempotência sob corrida/refresh), geram trilha via `AuditTrailService` e notificam por email (falha de email não bloqueia o cancelamento).

## 5. Banco de dados — `install/migration_cancelamento.sql` (idempotente)
- `pedidos`: `cancelado_por`, `motivo_cancelamento`, `taxa_cancelamento`, `cancelado_em`;
- `guinchos`: `total_cancelamentos`;
- `configuracoes`: `cancelamento_gratis_min`, `taxa_cancelamento_percent`, `taxa_cancelamento_fixa`, `km_bloqueio_cancelamento`, `penalidade_reputacao_cancelamento`.

Executar via `install/run_all_migrations.php` (auto-descoberta por glob) ou diretamente.

## 6. Estorno parcial
`EstornoService::estornar(int $pedidoId, ?float $valorParcial = null)` — Mercado Pago recebe `{"amount": X}`; PagSeguro recebe `refundValue`. Sem o parâmetro, mantém o estorno integral (retrocompatível).

## 7. Endpoints alterados/novos
| Rota | Mudança |
|---|---|
| `GET /cliente/pedido/status-json/{id}` | + fotos de prova, preview de cancelamento, `cancelado_por`, `taxa_cancelamento` |
| `POST /cliente/cancelar/{id}` | aceita AJAX (JSON) com `motivo`; aplica penalidade via serviço |
| `GET /guincho/pedido/status-json/{id}` | + `cancelado_por`, `guincho_ainda_atribuido` |
| `POST /guincho/cancelar/{id}` | **novo** — cancelamento pelo guincheiro |

## 8. Evidências de validação
- `php -l` limpo em todos os arquivos alterados (o controller do guincheiro **não** passava antes);
- migração executada 2× contra MySQL real (idempotência comprovada);
- 15/15 testes de integração PASS cobrindo: taxa de 20%, teto no valor do serviço, janela de arrependimento, bloqueio por proximidade (<2 km), idempotência do 2º cancelamento, volta à fila, penalidade de reputação 4,50→4,25, contador de cancelamentos, liberação do guincho e negação de pedido alheio;
- JS das duas views validado com `node --check`.

## 9. Sugestões para a suíte Playwright (Suites C/D da Constituição)
- C4+: cliente cancela em `a_caminho` → verificar taxa no modal, status `cancelado`, `taxa_cancelamento` persistida e estorno parcial disparado;
- C5: guincheiro cancela em `a_caminho` → pedido reaparece em `pedidos-disponiveis`, reputação decresce, cliente vê banner "buscando outro guincho";
- C6: simular `watchPosition` (contexto com `geolocation` fake) e validar atualização do marcador/rota do lado do cliente.
