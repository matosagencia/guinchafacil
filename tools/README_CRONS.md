# Jobs operacionais

Os scripts deste diretório são executáveis apenas pela CLI do PHP. Eles não
devem ser expostos como endpoints HTTP.

## Agenda recomendada

| Job | Frequência | Script |
| --- | --- | --- |
| Cancelar pedidos expirados | a cada minuto | `tools/cron_cancelar_pedidos_expirados.php` |
| Reprocessar repasses Pix | a cada 5 minutos | `tools/cron_reprocessar_pix.php` |
| Expirar ofertas de especialista | a cada minuto | `tools/cron_expirar_ofertas_especialista.php` |
| Limpar tokens de senha | diariamente às 03:00 | `tools/cron_limpar_tokens.php` |
| Limpar logs e cache | diariamente às 00:30 | `tools/cron_limpar_logs.php` |
| Retenção operacional | diariamente às 01:30 | `tools/cron_retencao_operacional.php` |

Os horários acima correspondem às definições de `CronMonitorService` e são
exibidos também no painel administrativo de saúde.

## Linux / cPanel

Substitua `/caminho/do/projeto` pelo diretório real da aplicação e ajuste o
binário do PHP para a versão usada em produção:

```cron
* * * * * /usr/bin/php /caminho/do/projeto/tools/cron_cancelar_pedidos_expirados.php >> /caminho/do/projeto/storage/logs/cron_cancelar_pedidos_expirados.log 2>&1
* * * * * /usr/bin/php /caminho/do/projeto/tools/cron_expirar_ofertas_especialista.php >> /caminho/do/projeto/storage/logs/cron_expirar_ofertas_especialista.log 2>&1
*/5 * * * * /usr/bin/php /caminho/do/projeto/tools/cron_reprocessar_pix.php >> /caminho/do/projeto/storage/logs/cron_reprocessar_pix.log 2>&1
0 3 * * * /usr/bin/php /caminho/do/projeto/tools/cron_limpar_tokens.php >> /caminho/do/projeto/storage/logs/cron_limpar_tokens.log 2>&1
30 0 * * * /usr/bin/php /caminho/do/projeto/tools/cron_limpar_logs.php >> /caminho/do/projeto/storage/logs/cron_limpar_logs.log 2>&1
30 1 * * * /usr/bin/php /caminho/do/projeto/tools/cron_retencao_operacional.php >> /caminho/do/projeto/storage/logs/cron_retencao_operacional.log 2>&1
```

## Windows / XAMPP

No Agendador de Tarefas do Windows, crie uma tarefa por script com:

- **Programa:** `C:\xampp\php\php.exe`
- **Argumentos:** `C:\xampp\htdocs\guinchafacil\tools\NOME_DO_SCRIPT.php`
- **Iniciar em:** `C:\xampp\htdocs\guinchafacil`

Use os mesmos intervalos da tabela. O processo deve executar com uma conta que
tenha acesso ao projeto, ao banco local e aos diretórios de logs.

## Validação segura

Antes de agendar, valide a sintaxe sem executar o job:

```powershell
$php = 'C:\xampp\php\php.exe'
Get-ChildItem .\tools\cron_*.php | ForEach-Object { & $php -l $_.FullName }
```

Não abra esses arquivos pelo navegador para testar. A execução real pode
cancelar pedidos, reprocessar pagamentos ou remover dados de retenção.

O `CronMonitorService` registra heartbeat, duração, status e métricas em
`cron_jobs` e `cron_executions`; o painel de saúde deve ser usado para confirmar
que cada tarefa está executando dentro da tolerância configurada.
