<#
.SYNOPSIS
    Unifica correcoes da Faixa B em um comando, com commit e rollback.

.DESCRIPTION
    Aplica:
      1. Correcao do checarPedidos() da oficina (retorna array de pedidos)
      2. Limpeza do doc/CONTRATOS.md (remove "+)." orfa, garante newline final)
      3. Bloqueio de tools/fix-*.php e tools/patch-*.patch no .gitignore
      4. php -l nos 2 dashboards
      5. 3 commits semanticos

    Rollback: restaura os 3 arquivos dos backups .bak-unif-* mais recentes
              e faz git reset --hard HEAD~3 (opcional).

.PARAMETER Repositorio
    Caminho absoluto do repositorio. Ex: C:\xampp\htdocs\guinchafacil

.PARAMETER Acao
    'deploy' (padrao) ou 'rollback'.

.PARAMETER SkipCommit
    Se passado, aplica as correcoes mas NAO faz commit.

.PARAMETER SkipGitReset
    Se passado em -Acao rollback, restaura arquivos mas NAO faz git reset.

.EXAMPLE
    .\fix-B-unificado.ps1 -Repositorio 'C:\xampp\htdocs\guinchafacil'

.EXAMPLE
    .\fix-B-unificado.ps1 -Repositorio 'C:\xampp\htdocs\guinchafacil' -Acao rollback
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Repositorio,

    [string]$Xampp = 'C:\xampp',

    [ValidateSet('deploy', 'rollback')]
    [string]$Acao = 'deploy',

    [switch]$SkipCommit,

    [switch]$SkipGitReset
)

$ErrorActionPreference = 'Stop'
$php = Join-Path $Xampp 'php\php.exe'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

# ---------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------
function Assert-File([string]$Path, [string]$Fase) {
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "[$Fase] arquivo nao encontrado: $Path"
    }
}

function Assert-Command([string]$Cmd, [string]$Fase) {
    if (-not (Get-Command $Cmd -ErrorAction SilentlyContinue)) {
        throw "[$Fase] comando nao encontrado no PATH: $Cmd"
    }
}

function Invoke-PhpLint([string]$File, [string]$Fase) {
    Assert-File $File $Fase
    $out = & $php -l $File 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "[$Fase] php -l falhou em $File`n$out"
    }
    Write-Host "[$Fase] OK $(Split-Path -Leaf $File)" -ForegroundColor Green
}

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

# ---------------------------------------------------------------------
# Prechecks
# ---------------------------------------------------------------------
Assert-Command 'git' 'PRECHECK'
# Verifica o binario exato do XAMPP
Assert-File $php 'PRECHECK'

if (-not (Test-Path -LiteralPath $Repositorio)) {
    throw "[PRECHECK] repositorio nao encontrado: $Repositorio"
}
Set-Location -LiteralPath $Repositorio

Assert-File (Join-Path $Repositorio '.git') 'PRECHECK'

$oficinaFile = Join-Path $Repositorio 'src\Views\oficina\dashboard.php'
$guinchoFile = Join-Path $Repositorio 'src\Views\guincho\dashboard.php'
$contratosFile = Join-Path $Repositorio 'doc\CONTRATOS.md'
if (-not (Test-Path -LiteralPath $contratosFile)) {
    $contratosFile = Join-Path $Repositorio 'docs\CONTRATOS.md'
}
$gitignoreFile = Join-Path $Repositorio '.gitignore'

Assert-File $oficinaFile  'PRECHECK'
Assert-File $guinchoFile  'PRECHECK'
Assert-File $gitignoreFile 'PRECHECK'

Write-Host "[PRECHECK] repositorio OK: $Repositorio" -ForegroundColor Green
Write-Host "[PRECHECK] contratos: $contratosFile" -ForegroundColor Green

# =====================================================================
# ROLLBACK
# =====================================================================
if ($Acao -eq 'rollback') {
    Write-Host ""
    Write-Host "=== ROLLBACK ===" -ForegroundColor Yellow

    $alvos = @($oficinaFile, $guinchoFile, $contratosFile, $gitignoreFile)
    foreach ($alvo in $alvos) {
        $pasta = Split-Path -Parent $alvo
        $nome  = Split-Path -Leaf $alvo
        $bkp = Get-ChildItem -LiteralPath $pasta -Filter "$nome.bak-unif-*" -File -ErrorAction SilentlyContinue |
               Sort-Object LastWriteTime -Descending |
               Select-Object -First 1

        if (-not $bkp) {
            Write-Host "[ROLLBACK] sem backup para $nome (ignorado)" -ForegroundColor DarkYellow
            continue
        }

        Copy-Item -LiteralPath $bkp.FullName -Destination $alvo -Force
        Write-Host "[ROLLBACK] restaurado $nome de $($bkp.Name)" -ForegroundColor Green
    }

    if (-not $SkipGitReset) {
        Write-Host "[ROLLBACK] git reset --hard HEAD~3" -ForegroundColor Yellow
        & git reset --hard HEAD~3
        if ($LASTEXITCODE -ne 0) {
            throw "[ROLLBACK] git reset falhou (codigo $LASTEXITCODE). Use -SkipGitReset se os commits nao existirem."
        }
    } else {
        Write-Host "[ROLLBACK] git reset ignorado (-SkipGitReset)" -ForegroundColor DarkYellow
    }

    Write-Host "[CONCLUIDO] rollback aplicado." -ForegroundColor Green
    exit 0
}

# =====================================================================
# DEPLOY
# =====================================================================
Write-Host ""
Write-Host "=== DEPLOY ===" -ForegroundColor Cyan

# ---------------------------------------------------------------------
# 1) Correcao do checarPedidos() da oficina
# ---------------------------------------------------------------------
$oficinaBackup = "$oficinaFile.bak-unif-$stamp"
Copy-Item -LiteralPath $oficinaFile -Destination $oficinaBackup -Force
Write-Host "[OFICINA-BKP] $oficinaBackup" -ForegroundColor Green

$c = [System.IO.File]::ReadAllText($oficinaFile, $utf8NoBom)

$antes = @'
    async function checarPedidos() {
        if (!ONLINE) return;
        try {
            var r = await fetch(BP + '/oficina/pedidos?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            // pedidos-disponiveis é JSON; /oficina/pedidos é HTML.
            var r2 = await fetch(BP + '/oficina/pedidos-disponiveis?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            var j = await r2.json();
            var container = document.getElementById('ofertaAtivaContainer');
            if (!container || !j.ok) return;
            if (j.pedidos && j.pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(j.pedidos[0].id) !== String(atualId)) {
                    location.reload();
                }
            }
        } catch (e) { /* silencioso */ }
    }
'@ -replace "`r`n", "`n"

$depois = @'
    async function checarPedidos() {
        if (!ONLINE) return [];
        try {
            var r = await fetch(BP + '/oficina/pedidos?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            // pedidos-disponiveis é JSON; /oficina/pedidos é HTML.
            var r2 = await fetch(BP + '/oficina/pedidos-disponiveis?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            var j = await r2.json();
            var container = document.getElementById('ofertaAtivaContainer');
            if (!container || !j.ok) return [];
            var pedidos = (j.pedidos || []);
            if (pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(pedidos[0].id) !== String(atualId)) {
                    location.reload();
                    return pedidos;
                }
            }
            return pedidos;
        } catch (e) { return []; }
    }
'@ -replace "`r`n", "`n"

if ($c.IndexOf($antes) -lt 0) {
    throw "[OFICINA] ancora checarPedidos nao encontrada. Abortando sem alterar."
}
$c = $c.Replace($antes, $depois)
Write-Utf8NoBom $oficinaFile $c
Write-Host "[OFICINA] checarPedidos corrigido" -ForegroundColor Green
Invoke-PhpLint $oficinaFile 'PHP-LINT'

# ---------------------------------------------------------------------
# 2) Limpeza do doc/CONTRATOS.md
# ---------------------------------------------------------------------
$contratosBackup = "$contratosFile.bak-unif-$stamp"
Copy-Item -LiteralPath $contratosFile -Destination $contratosBackup -Force
Write-Host "[CONTRATOS-BKP] $contratosBackup" -ForegroundColor Green

$cc = [System.IO.File]::ReadAllText($contratosFile, $utf8NoBom)

# Remove linha "+)." orfa (com qualquer espaco antes/depois)
$cc = $cc -replace "(?m)^\s*\+\)\.?\s*\r?\n", ""

# Garante newline final
if (-not $cc.EndsWith("`n")) { $cc += "`n" }

Write-Utf8NoBom $contratosFile $cc
Write-Host "[CONTRATOS] limpo e normalizado" -ForegroundColor Green

# ---------------------------------------------------------------------
# 3) .gitignore
# ---------------------------------------------------------------------
$gitignoreBackup = "$gitignoreFile.bak-unif-$stamp"
Copy-Item -LiteralPath $gitignoreFile -Destination $gitignoreBackup -Force
Write-Host "[GITIGNORE-BKP] $gitignoreBackup" -ForegroundColor Green

$gi = [System.IO.File]::ReadAllText($gitignoreFile, $utf8NoBom)
$regras = @('tools/fix-*.php','tools/patch-*.patch')
$linhas = $gi -split "`r?`n"
$faltando = @($regras | Where-Object { $linhas -notcontains $_ })

if ($faltando.Count -gt 0) {
    if (-not $gi.EndsWith("`n")) { $gi += "`n" }
    $gi += ($faltando -join "`n") + "`n"
    Write-Utf8NoBom $gitignoreFile $gi
    Write-Host "[GITIGNORE] adicionado: $($faltando -join ', ')" -ForegroundColor Green
} else {
    Write-Host "[GITIGNORE] regras ja presentes" -ForegroundColor DarkYellow
}

# ---------------------------------------------------------------------
# 4) php -l no guincho (oficina ja foi acima)
# ---------------------------------------------------------------------
Invoke-PhpLint $guinchoFile 'PHP-LINT'

# ---------------------------------------------------------------------
# 5) Commits
# ---------------------------------------------------------------------
if ($SkipCommit) {
    Write-Host ""
    Write-Host "[COMMIT] ignorado (-SkipCommit)" -ForegroundColor Yellow
    Write-Host "[CONCLUIDO] deploy aplicado sem commit." -ForegroundColor Green
    exit 0
}

Write-Host ""
Write-Host "=== COMMITS ===" -ForegroundColor Cyan

& git add $gitignoreFile
if ($LASTEXITCODE -ne 0) { throw "[COMMIT] git add .gitignore falhou" }
& git commit -m "chore: ignora tools/fix-*.php e tools/patch-*.patch" --quiet
if ($LASTEXITCODE -ne 0) { throw "[COMMIT] commit gitignore falhou" }
Write-Host "[COMMIT] .gitignore OK" -ForegroundColor Green

& git add $contratosFile
if ($LASTEXITCODE -ne 0) { throw "[COMMIT] git add CONTRATOS falhou" }
& git commit -m "docs: contratos de comissao + fatura semanal (B -> A)" --quiet
if ($LASTEXITCODE -ne 0) { throw "[COMMIT] commit CONTRATOS falhou" }
Write-Host "[COMMIT] CONTRATOS.md OK" -ForegroundColor Green

& git add $oficinaFile $guinchoFile
if ($LASTEXITCODE -ne 0) { throw "[COMMIT] git add dashboards falhou" }
& git commit -m "B: corrige checarPedidos da oficina + beeps sinteticos AudioContext" --quiet
if ($LASTEXITCODE -ne 0) { throw "[COMMIT] commit dashboards falhou" }
Write-Host "[COMMIT] dashboards OK" -ForegroundColor Green

# ---------------------------------------------------------------------
# 6) Estado final
# ---------------------------------------------------------------------
Write-Host ""
Write-Host "=== git log -6 ===" -ForegroundColor Cyan
& git log --oneline -6

Write-Host ""
Write-Host "=== git status --short ===" -ForegroundColor Cyan
& git status --short

Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host " FIX-B-UNIFICADO CONCLUIDO" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host " Backups:"
Write-Host "   $oficinaBackup"
Write-Host "   $contratosBackup"
Write-Host "   $gitignoreBackup"
Write-Host ""
Write-Host " Rollback:"
Write-Host "   .\fix-B-unificado.ps1 -Repositorio '$Repositorio' -Acao rollback"
Write-Host ""
Write-Host " Proximos passos:" -ForegroundColor Yellow
Write-Host "   1. Testar no navegador (oficina deve tocar 2 bipes)"
Write-Host "   2. git push origin main"
Write-Host "   3. Avisar Faixa A (CONTRATO_PEDIDO ja emitido)"
Write-Host ""