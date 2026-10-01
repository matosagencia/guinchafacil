Write-Host "Iniciando Smoke Test..." -ForegroundColor Cyan
$php = "C:\xampp\php\php.exe"
$erros = 0

Get-ChildItem "." -Recurse -Filter *.php | Where-Object { 
    $_.FullName -notmatch 'vendor' -and 
    $_.FullName -notmatch 'node_modules' -and 
    $_.FullName -notmatch 'prospeccao' 
} | ForEach-Object {
    $res = & $php -l $_.FullName 2>&1
    if ($res -notmatch "No syntax errors detected") {
        Write-Host "Erro de sintaxe em: $($_.FullName)" -ForegroundColor Red
        $erros++
    }
}

if ($erros -gt 0) {
    Write-Host "Smoke Test falhou com $erros erros de sintaxe." -ForegroundColor Red
    exit 1
}
Write-Host "Smoke Test PHP OK!" -ForegroundColor Green