# tools/smoke.ps1 - Teste de fumaça e lint PHP
param()

Write-Host "=== INICIANDO SMOKE TEST (PHP LINT) ===" -ForegroundColor Cyan

$errors = 0
Get-ChildItem -Recurse -Filter "*.php" | ForEach-Object {
    $result = & C:\xampp\php\php.exe -l $_.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERRO DE SINTAXE: $($_.FullName)" -ForegroundColor Red
        Write-Host $result
        $errors++
    }
}

if ($errors -gt 0) {
    Write-Host "Smoke test falhou com $errors erro(s)." -ForegroundColor Red
    exit 1
} else {
    Write-Host "Smoke test concluído com sucesso: nenhum erro de sintaxe PHP encontrado." -ForegroundColor Green
}