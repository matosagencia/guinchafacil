[CmdletBinding()]
param(
    [ValidateSet('lane','all')]
    [string]$Mode = 'lane',
    [switch]$NoTag,
    [string]$TagName = ''
)

$ErrorActionPreference = 'Stop'
Set-Location 'C:\xampp\htdocs\guinchafacil'

if (-not (Get-Command git -ErrorAction SilentlyContinue)) { throw "git nao encontrado no PATH." }
if (-not (Test-Path '.git')) { throw ".git nao encontrado." }

$php  = 'C:\xampp\php\php.exe'
$node = 'C:\Program Files\nodejs\node.exe'

$laneBFiles = @(
    'src/Controllers/AdminController.php',
    'src/Controllers/AuthController.php',
    'src/Views/admin/pedidonovo_etapa1.php',
    'src/Views/admin/pedidonovo_funil.php',
    'src/Views/admin/pedidos.php',
    'src/Views/auth/registrocliente.php',
    'src/Views/public/checkout-cliente.php',
    'public/assets/css/pages/admin-pedido-flow.css',
    'public/assets/js/components/br-validators.js',
    'docs/lanes/B.md',
    'docs/HANDOFF-B-to-A.md',
    'docs/HANDOFF-B-to-A-validacao-precotacao.md',
    'docs/HANDOFF-B-to-C.md'
)

$idxBak = ".git\index.bak-B5-" + (Get-Date -Format 'yyyyMMdd-HHmmss')
Copy-Item '.git\index' $idxBak -Force
Write-Host "[backup] $idxBak" -ForegroundColor Cyan

function Rollback-Git {
    Copy-Item $idxBak '.git\index' -Force
    Write-Host "Index restaurado do backup." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=== Faixa B sessao 5 commit (modo: $Mode) ===" -ForegroundColor Yellow
Write-Host ""

Write-Host "==> [1/6] git status --short" -ForegroundColor Cyan
& git status --short
Write-Host ""

Write-Host "==> [2/6] git diff --check" -ForegroundColor Cyan
$diffCheck = & git diff --check 2>&1
if ($LASTEXITCODE -ne 0 -or $diffCheck) {
    Write-Host "  git diff --check reportou:" -ForegroundColor Yellow
    $diffCheck | ForEach-Object { Write-Host "    $_" -ForegroundColor Yellow }
    $r = Read-Host "  Continuar mesmo assim? (s/N)"
    if ($r -notmatch '^(s|y)$') { Rollback-Git; exit 1 }
} else { Write-Host "  OK" -ForegroundColor Green }
Write-Host ""

Write-Host "==> [3/6] php -l" -ForegroundColor Cyan
if (-not (Test-Path $php)) {
    Write-Host "  [skip] php.exe ausente" -ForegroundColor Yellow
} else {
    $phpChanged = & git diff --name-only --diff-filter=ACMR | Where-Object { $_ -match '\.php$' }
    if (-not $phpChanged) {
        Write-Host "  nenhum PHP alterado." -ForegroundColor DarkGray
    } else {
        $failed = @()
        foreach ($f in $phpChanged) {
            if (-not (Test-Path -LiteralPath $f)) { continue }
            $out = & $php -l $f 2>&1
            if ($out -match 'No syntax errors') {
                Write-Host "  OK   $f" -ForegroundColor Green
            } else {
                Write-Host "  FAIL $f" -ForegroundColor Red
                $out | ForEach-Object { Write-Host "       $_" -ForegroundColor Red }
                $failed += $f
            }
        }
        if ($failed.Count -gt 0) {
            Write-Host "  php -l falhou. Abortando." -ForegroundColor Red
            Rollback-Git; exit 1
        }
    }
}
Write-Host ""

Write-Host "==> [4/6] node --check br-validators.js" -ForegroundColor Cyan
$brv = 'public\assets\js\components\br-validators.js'
if (-not (Test-Path -LiteralPath $brv)) {
    Write-Host "  [skip] $brv nao existe" -ForegroundColor Yellow
} elseif (-not (Test-Path $node)) {
    Write-Host "  [skip] node.exe ausente" -ForegroundColor Yellow
} else {
    & $node --check $brv
    if ($LASTEXITCODE -eq 0) { Write-Host "  OK" -ForegroundColor Green }
    else { Write-Host "  FAIL" -ForegroundColor Red; Rollback-Git; exit 1 }
}
Write-Host ""

Write-Host "==> [5/6] stage" -ForegroundColor Cyan
if ($Mode -eq 'all') {
    Write-Host "  modo all: stage geral (excluindo *.bak-*)" -ForegroundColor Yellow
    & git add -A -- . ':(exclude)*.bak-*' ':(exclude)*.bak_*'
} else {
    foreach ($f in $laneBFiles) {
        if (-not (Test-Path -LiteralPath $f)) {
            $tracked = & git ls-files --error-unmatch $f 2>$null
            if (-not $tracked) { continue }
        }
        $status = & git status --porcelain -- $f
        if (-not $status) { continue }
        & git add -- $f
        if ($LASTEXITCODE -eq 0) { Write-Host "    + $f" -ForegroundColor Green }
        else { Write-Host "    ! falhou: $f" -ForegroundColor Red }
    }
}
Write-Host ""
Write-Host "  Arquivos no index:" -ForegroundColor Cyan
& git diff --cached --name-status
$staged = & git diff --cached --name-only
if (-not $staged) {
    Write-Host "  Nada staged. Encerrando." -ForegroundColor Yellow
    Rollback-Git; exit 0
}
Write-Host ""

Write-Host "==> [6/6] commit" -ForegroundColor Cyan
$msg = @"
faixa B: funil admin + modal quick-create + validacao BR

Funil admin (/admin/pedido/novo):
- FIX 1: GET /admin/pedido/novo -> pedidoNovoV2 (era pedidoCriar -> 403 CSRF)
- FIX 2: remove campo "Guincho (opcional)" do v2 e query \$guinchos
- FIX 3: titulo dinamico via listener gf:flow-stage (h1#admin-flow-title)
- limpeza: remove "Guincho: automatico" do banner

Modal quick-create cliente/veiculo:
- rotas POST /admin/pedido/novo/api/cliente e /api/veiculo
- validacao CPF (DV), e-mail e telefone BR (client + server) com mascara
- veiculo: cards tipo -> select marca -> select modelo (catalogo)

Componente compartilhado:
- public/assets/js/components/br-validators.js (novo; shared pendente em lanes.json)
- consumidores B: registrocliente.php, checkout-cliente.php
- server-side: AuthController::validarTelefoneBr()

Handoffs:
- docs/HANDOFF-B-to-A.md (link de pagamento online)
- docs/HANDOFF-B-to-A-validacao-precotacao.md
- docs/HANDOFF-B-to-C.md §10 (cenarios + mutacao)
"@
$msgFile = Join-Path $env:TEMP ("gf-commit-msg-" + (Get-Date -Format 'yyyyMMddHHmmss') + ".txt")
[System.IO.File]::WriteAllText($msgFile, $msg, $utf8NoBom)

& git commit -F $msgFile
$commitOk = ($LASTEXITCODE -eq 0)
Remove-Item $msgFile -Force -ErrorAction SilentlyContinue

if (-not $commitOk) {
    Write-Host "  commit falhou." -ForegroundColor Red
    exit 1
}
Write-Host "  commit OK" -ForegroundColor Green
Write-Host ""

if (-not $NoTag) {
    if (-not $TagName) {
        $today = Get-Date -Format 'yyyyMMdd'
        $n = 1
        while ((& git tag --list "ok-$today-$n")) { $n++ }
        $TagName = "ok-$today-$n"
    }
    Write-Host "  tag $TagName..." -ForegroundColor Cyan
    & git tag -a $TagName -m "Faixa B sessao 5 — $TagName"
    if ($LASTEXITCODE -eq 0) { Write-Host "  tag criada (local)" -ForegroundColor Green }
}
Write-Host ""

Write-Host "=== SUMARIO ===" -ForegroundColor Green
& git log -1 --stat
Write-Host ""
Write-Host "Backup index: $idxBak" -ForegroundColor Cyan
Write-Host ""
Write-Host "Proximos passos:"
Write-Host "  git push origin main"
Write-Host "  git push origin --tags"