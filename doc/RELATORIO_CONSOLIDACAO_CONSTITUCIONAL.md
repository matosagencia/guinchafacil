# GuinchaFácil — Consolidação Constitucional da Raiz

**Data:** 2026-07-12  
**Base adotada:** raiz do projeto recebido  
**Fonte parcial aproveitada:** `files/GuinchaFacil-atualizado/GuinchaFacil/`  
**Patch equivalente encontrado:** `files/GuinchaFacil-patch-fluxo-cancelamento/`

## 1. Estratégia aplicada

A consolidação foi executada **por sistema funcional**, e não por diretório inteiro. A raiz foi mantida como versão principal porque contém alterações posteriores de segurança de sessão, governança, CSP, logging, aliases de rotas e tratamento JSON de AJAX.

Os arquivos da cópia interna foram usados apenas como fonte para transplante dos blocos constitucionais que estavam ausentes ou revertidos na raiz.

## 2. Implementações transplantadas

### Rastreamento parcial / base do Proof-of-Road

- `navigator.geolocation.watchPosition()` no atendimento do guincheiro.
- Envio automático da posição para `POST /guincho/localizacao` com throttle de 10 segundos.
- Badge visual de estado do GPS.
- Rota dinâmica do guincho até a origem durante `a_caminho`.
- Atualização dos waypoints com `setWaypoints()` conforme o guincho se desloca.
- Polling do cliente e do guincheiro para sincronização bilateral de status.
- Movimento do marcador do guincho no mapa do cliente.
- Preservação do gerenciador de sessão da raiz (`apiFetch` e `SessionManager`).

Arquivos:

- `src/Views/guincho/atendimento.php`
- `src/Views/cliente/pedidostatus.php`
- `src/Controllers/ClienteController.php`
- `src/Controllers/GuinchoController.php`

### Cancelamento constitucional

- Preview de cancelamento no status do cliente.
- Modal com taxa antes da confirmação.
- Cancelamento pelo cliente usando `CancelamentoService`.
- Cancelamento pelo guincheiro apenas em `a_caminho`.
- Retorno do pedido para a fila quando o guincheiro cancela.
- Penalidade reputacional configurável.
- Auditoria e notificação já existentes no serviço central.
- Eliminação do conflito entre `PedidoService` e `CancelamentoService`: os métodos legados agora delegam ao serviço constitucional.

Arquivos:

- `src/Controllers/ClienteController.php`
- `src/Controllers/GuinchoController.php`
- `src/Services/PedidoService.php`
- `src/Services/CancelamentoService.php` (preservado como fonte única)
- `src/Views/cliente/pedidostatus.php`
- `src/Views/guincho/atendimento.php`

### Estorno parcial

- `EstornoService::estornar(int $pedidoId, ?float $valorParcial = null)` restaurado.
- Mercado Pago recebe `amount` quando o estorno é parcial.
- PagSeguro recebe `refundValue` quando aplicável.

Arquivo:

- `src/Services/EstornoService.php`

## 3. Implementações da raiz preservadas

- Expiração segura de sessão e retorno HTTP 401 JSON.
- `public/assets/js/session-manager.js`.
- `window.apiFetch()` com validação de Content-Type.
- Bloqueio e encerramento de pollings após expiração.
- CSP corrigida para assets do Leaflet Routing Machine.
- Rotas alternativas de cancelamento/status.
- Logs estruturados com classe, função, fase e contexto.
- Backoffice, governança, health check, simulação e tarifas já presentes na raiz.
- Migrations adicionais posteriores ao pacote de 2026-07-08.

## 4. Estado real do Proof-of-Road após a consolidação

O material restaurado é **a base parcial do POR**, não a implementação antifraude completa.

### Agora existente

- GPS contínuo no navegador.
- Última posição do guincho.
- Rota dinâmica por status.
- Atualização visual para o cliente.

### Ainda inexistente

- tabela append-only de pontos por pedido;
- histórico reconstruível da rota;
- distância efetivamente percorrida;
- map matching;
- geofence obrigatória para as transições;
- vínculo criptográfico entre ponto, foto e status;
- validação de precisão, velocidade, saltos e lacunas;
- nomes das ruas efetivamente percorridas;
- cálculo de cancelamento baseado no percurso comprovado.

Portanto, o próximo sistema a implementar deve ser o **POR persistente**, começando pelo banco e pelo endpoint de ingestão de pontos.

## 5. Validações executadas

- `php -l` em todos os arquivos PHP fora de `vendor/` e das cópias arquivadas: **PASS**.
- `node --check` nos scripts inline extraídos de:
  - `src/Views/cliente/pedidostatus.php`;
  - `src/Views/guincho/atendimento.php`.
- `node --check` em:
  - `public/assets/js/app.js`;
  - `public/assets/js/session-manager.js`;
  - `public/assets/js/atendimento-status.js`.
- Rotas conferidas em `index.php`:
  - `/guincho/localizacao`;
  - `/cliente/cancelar/{id}`;
  - `/guincho/cancelar/{id}`;
  - status JSON e atualização de status.

### Limitação do ambiente de teste

A suíte PHPUnit não pôde ser executada neste ambiente porque faltam extensões PHP exigidas pelo PHPUnit (`dom`, `mbstring`, `xml` e `xmlwriter`). Isso não é falha do código. A validação estática PHP/JS foi concluída com sucesso.

## 6. Ordem recomendada daqui em diante

1. **POR persistente e antifraude** — banco, ingestão, validação e geofences.
2. **Visualização do trajeto real** — trilha percorrida separada da rota prevista.
3. **Ruas em português e ETA** — formatter/localização controlada e resumo da rota.
4. **Cancelamento por distância e tempo comprovados** — substitui a regra atual baseada em percentual/proximidade.
5. **Testes Playwright multiusuário** — cliente, guincheiro, GPS, chat, fotos e cancelamento.

## 7. Arquivos efetivamente alterados nesta consolidação

- `src/Controllers/ClienteController.php`
- `src/Controllers/GuinchoController.php`
- `src/Services/EstornoService.php`
- `src/Services/PedidoService.php`
- `src/Views/cliente/pedidostatus.php`
- `src/Views/guincho/atendimento.php`
- `doc/RELATORIO_CONSOLIDACAO_CONSTITUCIONAL.md`
