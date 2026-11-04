# GuinchaFacil final gate: encoding, PHP syntax and backoffice shell.
# Run from the repository root before commit or deploy.
[CmdletBinding()]
param(
    [switch]$AllTracked,
    [switch]$VisualOnly,
    [string]$BaseUrl = $(if ($env:BASE_URL) { $env:BASE_URL } else { 'http://localhost:8080' })
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $root
$failures = New-Object System.Collections.Generic.List[string]

function Fail([string]$message) {
    $script:failures.Add($message)
    Write-Host "[FAIL] $message" -ForegroundColor Red
}

if (-not $VisualOnly) {
    $files = if ($AllTracked) {
        git ls-files -- '*.php' '*.js' '*.css' '*.html'
    } else {
        git diff --name-only --diff-filter=ACM -- '*.php' '*.js' '*.css' '*.html'
    }

    foreach ($relative in @($files)) {
        if ([string]::IsNullOrWhiteSpace($relative)) { continue }
        $path = Join-Path $root $relative
        if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { continue }
        $bytes = [IO.File]::ReadAllBytes($path)
        try {
            $text = [Text.UTF8Encoding]::new($false, $true).GetString($bytes)
        } catch {
            Fail "UTF-8 invalido: $relative"
            continue
        }
        if ($text -match 'Ã|Â|â€|ï¿½|�') {
            Fail "mojibake detectado: $relative"
        }
        if ($relative -like '*.php') {
            $lint = & C:\xampp\php84\php.exe -l $path 2>&1
            if ($LASTEXITCODE -ne 0) { Fail "PHP lint falhou: $relative`n$lint" }
        }
    }
}

if (-not $VisualOnly) {
    $required = @(
        'src/Views/admin/partials/_panel_topbar_admin.php',
        'src/Views/layouts/backoffice_shell.php',
        'public/assets/css/backoffice-layout.css'
    )
    foreach ($relative in $required) {
        if (-not (Test-Path -LiteralPath (Join-Path $root $relative))) {
            Fail "contrato ausente: $relative"
        }
    }
}

$env:BASE_URL = $BaseUrl
& npx.cmd playwright test tests/e2e/theme-mojibake-agent.spec.js --reporter=line
if ($LASTEXITCODE -ne 0) { Fail "auditoria visual Playwright falhou" }

if ($failures.Count -gt 0) {
    Write-Host "`nAGENTE BLOQUEOU: $($failures.Count) falha(s)." -ForegroundColor Red
    exit 1
}
Write-Host "AGENTE OK: encoding, lint e shell visual aprovados." -ForegroundColor Green
exit 0
