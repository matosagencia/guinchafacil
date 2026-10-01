Write-Host "Iniciando Smoke Test..." -ForegroundColor Cyan
$php = "C:\xampp\php\php.exe"
$erros = 0

Get-ChildItem "." -Recurse -Filter *.php | Where-Object { 
    $_.FullName -notmatch '\\vendor\\' -and 
    $_.FullName -notmatch '\\node_modules\\' -and 
    $_.FullName -notmatch '\\prospeccao\\' -and
    $_.FullName -notmatch '\\install\\' -and
    $_.FullName -notmatch '\\installers\\' -and
    $_.FullName -notmatch '\.bak'
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

# Playwright @smoke (fluxo real; falha se nenhum teste @smoke existir ainda)
Write-Host "Rodando Playwright @smoke..." -ForegroundColor Cyan
npx playwright test --grep "@smoke" --reporter=line
if ($LASTEXITCODE -ne 0) {
    Write-Host "Smoke Test Playwright FALHOU (exit $LASTEXITCODE)" -ForegroundColor Red
    exit $LASTEXITCODE
}
Write-Host "Smoke Test Playwright OK!" -ForegroundColor Green