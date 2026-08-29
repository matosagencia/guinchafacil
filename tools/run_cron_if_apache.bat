@echo off
setlocal

rem Os crons locais do Guinchafacil dependem do Apache/XAMPP ativo.
rem O Agendador pode iniciar este wrapper mesmo com o Apache desligado;
rem nesse caso, nao abra o php.exe nem execute o script.
tasklist /FI "IMAGENAME eq httpd.exe" /NH | find /I "httpd.exe" >NUL
if errorlevel 1 exit /b 0

if "%~1"=="" exit /b 2
"C:\xampp\php\php.exe" "%~1"
exit /b %ERRORLEVEL%
