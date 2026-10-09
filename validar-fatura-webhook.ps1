[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Repositorio,
    [string]$PhpExe = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path -LiteralPath $Repositorio).Path

if (-not (Test-Path -LiteralPath $PhpExe)) {
    throw "[FAIL][FaturaWebhook] PHP CLI nao encontrado: $PhpExe"
}

$files = @(
    (Join-Path $repo 'src\Controllers\FaturaWebhookController.php'),
    (Join-Path $repo 'src\Services\FaturaService.php')
)

foreach ($file in $files) {
    if (-not (Test-Path -LiteralPath $file)) {
        throw "[FAIL][FaturaWebhook] Arquivo nao encontrado: $file"
    }

    & $PhpExe -l $file
    if ($LASTEXITCODE -ne 0) {
        throw "[FAIL][FaturaWebhook] php -l falhou: $file"
    }

    Write-Host "[PASS][FaturaWebhook][syntax] $file"
}

Write-Host '[DONE][FaturaWebhook] Sintaxe PHP validada.'
