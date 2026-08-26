Implementar o ticket `ticket:[CRÍTICO] Implementar transferência Pix real ao guincheiro após conclusão do serviço` — Transferência Pix real ao guincheiro após conclusão do serviço.

Consulte também a spec de constituição `spec:GuinchaFácil — Constituição do Sistema: Arquitetura, Regras de Negócio e Segurança` para entender as regras de negócio financeiras.

## Contexto do Projeto

PHP puro (sem framework/Composer), arquitetura MVC com front-controller em `index.php`. Banco MySQL. Funções globais `getPDO()` disponível em todo o projeto. Sem namespaces.

## O que implementar

### 1. Migration SQL — adicionar colunas Pix em `pagamentos`

No arquivo `install/migration_fix.sql`, adicionar ao final (usando `ADD COLUMN IF NOT EXISTS` para ser idempotente):

- `id_transacao_pix VARCHAR(100) NULL` — ID retornado pela API do MercadoPago
- `status_pix ENUM('pendente','processando','concluido','falha') NOT NULL DEFAULT 'pendente'` — estado da transferência Pix

### 2. Criar `src/Services/PixService.php`

Serviço estático (sem namespace, sem Composer) que encapsula a transferência Pix via API do MercadoPago.

A API do MercadoPago para transferências Pix usa o endpoint `POST /v1/payments` com `payment_method_id: "pix"` e `operation_type: "money_transfer"`. O corpo deve incluir:

- `transaction_amount`: valor em float
- `payment_method_id`: "pix"
- `payer`: dados do pagador (a plataforma)
- `receiver`: chave Pix do destinatário (guincheiro)

O serviço deve ter o método estático `transferir(int $pedidoId, float $valor, string $chavePix, string $chaveTipo): array` que retorna `['sucesso' => bool, 'id_transacao' => string|null, 'erro' => string|null]`.

Usar cURL para chamar a API. Usar `MP_ACCESS_TOKEN` definido em `config.php`. Registrar resultado via `error_log()`. Em caso de falha da API (HTTP != 201), retornar `sucesso = false` com a mensagem de erro.

Também criar método estático `reprocessar(int $pedidoId): array` que busca o pagamento com `status_pix = 'falha'` e tenta novamente.

### 3. Modificar `src/Controllers/GuinchoController.php` — método `atualizarStatus()`

Quando `$novoStatus === 'concluido'`, após calcular `$valorGuincho`:

1. Buscar a chave Pix do guincheiro via `$guincho['chave_pix']` e `$guincho['chave_pix_tipo']`
2. Chamar `PixService::transferir($id, $valorGuincho, $chavePix, $chaveTipo)`
3. Se sucesso:
  - UPDATE em `pagamentos`: `id_transacao_pix = ?`, `status_pix = 'concluido'`, `pago_guincho = 1`, `data_pagamento_guincho = NOW()`, `valor_guincho = ?`, `valor_plataforma = ?`
4. Se falha:
  - UPDATE em `pagamentos`: `status_pix = 'falha'`, `pago_guincho = 0`, `valor_guincho = ?`, `valor_plataforma = ?`
  - Registrar em `error_log` com detalhes do pedido e erro
  - Enviar email de alerta ao admin (buscar email do admin na tabela `usuarios` onde `tipo = 'admin'`)

O `require_once` do `PixService.php` deve ser adicionado no topo do arquivo.

### 4. Modificar `src/Services/NotificacaoService.php` — método `pedidoConcluido()`

Atualmente calcula `$pedido['custo_final'] * 0.8` hardcoded para o email do guincheiro. Corrigir para:

- Buscar o registro de `pagamentos` pelo `pedido_id` para obter `valor_guincho` real
- Usar esse valor no email
- Se não encontrar o pagamento, usar `$pedido['custo_final'] * (1 - 0.15)` como fallback

### 5. Criar endpoint admin para reprocessamento

No `src/Controllers/AdminController.php`, adicionar método `pixReprocessar(int $pedidoId)`:

- Requer auth admin
- Valida CSRF (POST)
- Chama `PixService::reprocessar($pedidoId)`
- Redireciona para `/admin/pedido/{pedidoId}?msg=pix_reprocessado` ou `?msg=pix_falha`

No `index.php`, adicionar rota:

```
POST /admin/pix/reprocessar/ → AdminController::pixReprocessar (dinâmica com id)
```

### 6. Adicionar botão de reprocessamento na view de detalhe do pedido admin

No arquivo `src/Views/admin/pedidodetalhe.php`, verificar se existe um pagamento com `status_pix = 'falha'` e exibir um botão "Reprocessar Pix" com formulário POST para `/admin/pix/reprocessar/{pedido_id}`.

## Regras importantes

- Nunca marcar `pago_guincho = 1` sem confirmação da API
- O fluxo de conclusão do pedido (mudança de status) deve ocorrer mesmo se o Pix falhar — o pedido é concluído, mas o pagamento fica pendente
- Usar `require_once` para incluir o PixService nos controllers que precisam dele
- Todos os erros devem ser registrados via `error_log()`
- O `MP_ACCESS_TOKEN` já está definido em `config.php`