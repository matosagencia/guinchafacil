@echo off
setlocal

rem O Apache do XAMPP roda como httpd.exe. Se estiver desligado, nao executa o cron.
tasklist /FI "IMAGENAME eq httpd.exe" /NH | find /I "httpd.exe" >NUL
if errorlevel 1 exit /b 0

"C:\xampp\php\php.exe" "C:\xampp\htdocs\guinchafacil\cron\AlertaVencimentoApolice.php"
exit /b %ERRORLEVEL%
