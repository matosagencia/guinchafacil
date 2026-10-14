# fix-precotacao-v2-scripts.ps1
# Carrega address-picker.js + configura __gfBasePath/__gfFlowOptions na ordem correta.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[fix-scripts] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[fix-scripts][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[fix-scripts][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[fix-scripts][FAIL] $m" -ForegroundColor Red }

$prePath = Join-Path $ProjectRoot 'src\Views\public\pre-cotacao.php'
if (-not (Test-Path $prePath)) { Write-Fail "Nao existe: $prePath"; exit 1 }

$utf8 = New-Object System.Text.UTF8Encoding($false, $true)
$content = $utf8.GetString([System.IO.File]::ReadAllBytes($prePath))

if ($content.Contains('components/address-picker.js')) {
    Write-Skip "address-picker.js ja esta carregado"
    exit 0
}

$old = @'
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/public-pre-cotacao-flow.js"></script>
'@ -replace "`r`n", "`n"

$new = @'
<script<?php echo $nonceAttr ?? ''; ?>>
    window.__gfBasePath = '<?php echo $e($bp); ?>';
    window.__gfFlowOptions = {};
</script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/components/address-picker.js"></script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/public-pre-cotacao-flow.js"></script>
'@ -replace "`r`n", "`n"

if (-not $content.Contains($old)) {
    Write-Fail "Bloco de scripts antigo nao encontrado."
    Write-Host "Conteudo atual esperado:" -ForegroundColor Gray
    Write-Host $old -ForegroundColor DarkGray
    exit 1
}

$content = $content.Replace($old, $new)

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
Copy-Item -LiteralPath $prePath -Destination "$prePath.bak-$ts" -Force
Write-Step "Backup: $prePath.bak-$ts"

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllBytes($prePath, $utf8NoBom.GetBytes($content))

$out = & $PhpExe -l $prePath 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Fail "php -l falhou: $($out -join ' ')"
    Copy-Item -LiteralPath "$prePath.bak-$ts" -Destination $prePath -Force
    exit 1
}
Write-Ok "pre-cotacao.php: address-picker + config global carregados"
Write-Host ""
Write-Ok "Recarregue /pre-cotacao com Ctrl+Shift+R"
exit 0