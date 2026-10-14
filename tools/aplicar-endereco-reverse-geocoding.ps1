param(
    [Parameter(Mandatory = $true)]
    [string]$Repositorio
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$phpPath = 'C:\xampp\php\php.exe'
$payloadRoot = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$changes = New-Object System.Collections.ArrayList
$files = @(
    'src\Controllers\PreCotacaoApiController.php',
    'src\Services\GeocodingService.php'
)

function Install-File {
    param([string]$Relative)

    $source = Join-Path $payloadRoot $Relative
    $target = Join-Path $Repositorio $Relative

    if (-not (Test-Path -LiteralPath $source)) {
        throw ('[REVERSE-GEOCODING][PAYLOAD-FAIL] Arquivo ausente: ' + $source)
    }

    $content = [System.IO.File]::ReadAllText($source)
    if (Test-Path -LiteralPath $target) {
        if ([System.IO.File]::ReadAllText($target) -ceq $content) {
            Write-Host ('[REVERSE-GEOCODING][SKIP] ' + $Relative)
            return
        }

        $backup = $target + '.bak-' + $stamp
        Copy-Item -LiteralPath $target -Destination $backup -Force
        [void]$script:changes.Add(@{ Target = $target; Backup = $backup; Existed = $true })
        Write-Host ('[REVERSE-GEOCODING][BACKUP] ' + $backup)
    } else {
        [void]$script:changes.Add(@{ Target = $target; Backup = $null; Existed = $false })
    }

    [System.IO.File]::WriteAllText($target, $content, $script:utf8NoBom)
    Write-Host ('[REVERSE-GEOCODING][WRITE] ' + $Relative)
}

try {
    if (-not (Test-Path -LiteralPath $Repositorio)) {
        throw ('[REVERSE-GEOCODING][CONFIG-FAIL] Repositorio nao encontrado: ' + $Repositorio)
    }
    if (-not (Test-Path -LiteralPath $phpPath)) {
        throw ('[REVERSE-GEOCODING][CONFIG-FAIL] PHP nao encontrado: ' + $phpPath)
    }

    foreach ($file in $files) {
        Install-File $file
    }

    foreach ($file in $files) {
        $target = Join-Path $Repositorio $file
        & $phpPath -l $target
        if ($LASTEXITCODE -ne 0) {
            throw ('[REVERSE-GEOCODING][LINT-FAIL] ' + $file)
        }
        Write-Host ('[REVERSE-GEOCODING][LINT-OK] ' + $file)
    }

    Write-Host '[REVERSE-GEOCODING][DONE] Endpoint /api/pre-cotacao/endereco instalado.'
} catch {
    Write-Host ('[REVERSE-GEOCODING][FAIL] ' + $_.Exception.Message)
    for ($index = $changes.Count - 1; $index -ge 0; $index--) {
        $change = $changes[$index]
        if ($change.Existed) {
            Copy-Item -LiteralPath $change.Backup -Destination $change.Target -Force
            Write-Host ('[REVERSE-GEOCODING][ROLLBACK] Restaurado: ' + $change.Target)
        } elseif (Test-Path -LiteralPath $change.Target) {
            Remove-Item -LiteralPath $change.Target -Force
            Write-Host ('[REVERSE-GEOCODING][ROLLBACK] Removido: ' + $change.Target)
        }
    }
    exit 1
}
