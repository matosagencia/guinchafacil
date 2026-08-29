# Correções: cancelamento, polling AJAX, sessão e Leaflet

## Arquivos alterados
- `index.php`: CSP passa a permitir a imagem do Leaflet Routing Machine em `unpkg.com`; novas rotas de cancelamento JSON.
- `src/Services/AuthService.php`: APIs AJAX recebem JSON 401/403, sem HTML de login.
- `src/Services/PedidoService.php`: cancelamento transacional para cliente e guincho, liberação do guincho, retorno estruturado e logs.
- `src/Controllers/ClienteController.php`: cancelamento retorna JSON consistente e registra exceções.
- `src/Controllers/GuinchoController.php`: endpoint de cancelamento, polling sem cache, fallback de localização operacional e correção de bloco PIX que possuía chaves inconsistentes.
- `src/Views/guincho/dashboard.php`: container específico para pedidos, polling imediato, `no-store`, credenciais e tratamento de sessão.
- `public/assets/js/atendimento-status.js`: valida Content-Type, trata 401 e impede tentativa de interpretar HTML como JSON.

## Arquivo novo
- `install/migration_fix_cancelamento_polling.sql`: tabela aditiva `pedido_cancelamentos` para auditoria.

## Aplicação
1. Faça backup de arquivos e banco.
2. Execute `install/migration_fix_cancelamento_polling.sql` no banco ativo.
3. Substitua os arquivos mantendo a hierarquia.
4. Limpe cache do navegador/CDN.
5. Teste login, polling, cancelamento do cliente e cancelamento do guincho.
6. Confira `logs/app.log` em caso de falha.

## Resultado esperado
- O polling atualiza o card sem refresh.
- Sessão expirada retorna JSON 401 e redireciona para login de forma controlada.
- Cancelamento do cliente retorna `ok`, `taxa` e `estorno`.
- Cancelamento do guincho devolve o pedido para `aguardando_guincho` e retorna `penalidade`.
- O ícone do Leaflet Routing Machine deixa de ser bloqueado pela CSP.
