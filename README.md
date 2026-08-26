# GuinchaFácil — Deploy em public_html (raiz)

## Estrutura esperada no servidor

```
public_html/          ← raiz do domínio (aqui vão os arquivos)
├── .htaccess
├── index.php
├── config.php
├── src/
├── public/
└── ...

uploads/              ← fora de public_html (criado automaticamente)
logs/                 ← fora de public_html (criado automaticamente)
```

> **Uploads e logs ficam fora do webroot** por segurança.
> No cPanel isso significa `/home/seuusuario/uploads/` e `/home/seuusuario/logs/`.

---

## Passos de instalação

### 1. Upload dos arquivos
Suba **todo o conteúdo desta pasta** diretamente dentro de `public_html/`.  
Não crie uma subpasta — os arquivos devem ficar na raiz.

### 2. Banco de dados
No cPanel → MySQL → crie um banco e usuário, depois importe:
```bash
mysql -u USUARIO -p BANCO < install/guinchafacil.sql
mysql -u USUARIO -p BANCO < install/migration_fix.sql
```

### 3. Configure o `config.php`
Edite as linhas abaixo com seus dados reais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');

define('APP_URL', 'https://seudominio.com.br');  // sem barra no final
define('APP_ENV', 'production');                 // ou 'development'

define('MP_ACCESS_TOKEN', 'SEU_TOKEN_MERCADOPAGO');
define('PS_TOKEN',        'SEU_TOKEN_PAGSEGURO');
```

### 4. Permissões
```bash
chmod 755 public_html/
chmod 644 public_html/config.php
chmod 755 uploads/          # criado na primeira utilização
chmod 755 logs/             # criado na primeira utilização
```

### 5. Acesse o sistema
- `https://seudominio.com.br/login`
- Crie um admin diretamente no banco ou use o script `tools/reset_admin_password.php`

---

## Subpasta (opcional)
Se precisar rodar em subpasta (ex: `seudominio.com.br/app`), adicione em `config.php`:
```php
define('FORCE_BASEPATH', '/app');
```
E altere o `.htaccess`:
```apache
RewriteBase /app/
```

---

## Variáveis de ambiente importantes

| Constante | Descrição |
|---|---|
| `APP_URL` | URL base sem barra final |
| `APP_ENV` | `production` ou `development` |
| `UPLOAD_PATH` | Auto: `../uploads/` (fora do webroot) |
| `LOG_DIR` | Auto: `../logs/` (fora do webroot) |
