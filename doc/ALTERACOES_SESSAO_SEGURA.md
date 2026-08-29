# Correção de expiração de sessão

## Arquivos alterados

- `config.php`
- `index.php`
- `src/Services/AuthService.php`
- `src/Controllers/AuthController.php`
- `src/Controllers/GuinchoController.php`
- `src/Controllers/ClienteController.php`
- `src/Views/layouts/header.php`
- `src/Views/auth/login.php`
- `src/Views/guincho/dashboard.php`
- `src/Views/guincho/atendimento.php`
- `src/Views/cliente/pedidostatus.php`
- `public/assets/js/app.js`
- `public/assets/js/atendimento-status.js`
- `public/assets/js/cliente-pedido.js`

## Arquivos novos

- `public/assets/js/session-manager.js`
- `tests/Integration/SessionExpirationTest.php`
- `tests/e2e/session-expiration.spec.js`

## Comportamento implementado

- APIs/AJAX retornam HTTP 401 com JSON quando a sessão expira.
- O frontend interrompe pollings e mostra modal de sessão expirada.
- O login preserva e valida a URL interna de retorno.
- Sessão possui limite por inatividade e limite absoluto.
- Pollings GET passivos não renovam o tempo de atividade humana.
- Ações autenticadas continuam renovando a atividade.
- Admin continua podendo visualizar áreas de cliente e guincho.

## Configuração opcional no `.env`

```env
SESSION_IDLE_TIMEOUT=3600
SESSION_ABSOLUTE_TIMEOUT=43200
SESSION_WARNING_SECONDS=120
SESSION_COOKIE_LIFETIME=43200
```

## Aplicação segura

1. Faça backup dos arquivos atuais.
2. Substitua os arquivos mantendo a hierarquia.
3. Não é necessária migration SQL.
4. Reinicie Apache/PHP para recarregar configurações de sessão.
5. Limpe cache do navegador/CDN.
6. Faça logout e login novamente.
7. Teste dashboard, atendimento, chat, cancelamento e pagamento.
8. Valide o teste: `php tests/Integration/SessionExpirationTest.php`.

## Rollback

Restaure os arquivos do backup. Não há alteração de banco de dados.
