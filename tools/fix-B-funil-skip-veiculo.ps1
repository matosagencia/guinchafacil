# fix-B-funil-skip-veiculo.ps1
# Pula o estagio "veiculo" no funil admin porque o veiculo ja foi
# escolhido no Passo 1 (/admin/pedido/novo/v2). Passa veiculo_categoria
# no $adminCtx e seta window.__gfFlowOptions.skipVeiculo = true.
#
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$controller = Join-Path $Repositorio 'src\Controllers\AdminController.php'
$view       = Join-Path $Repositorio 'src\Views\admin\pedidonovo_funil.php'

foreach ($f in @($controller, $view)) {
    if (-not (Test-Path -LiteralPath $f)) {
        Write-Host "[ERRO] $f nao encontrado." -ForegroundColor Red
        exit 1
    }
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

# ============================================================
# PARTE 1 — AdminController::pedidoNovoFunil()
# ============================================================
Write-Host "[1/2] Controller..." -ForegroundColor Cyan
$bakC = "$controller.bak-fix-B-funil-skip-veiculo-$stamp"
Copy-Item -LiteralPath $controller -Destination $bakC -Force
Write-Host "  [BACKUP] $bakC" -ForegroundColor DarkGray

$linhas = Get-Content -LiteralPath $controller -Encoding UTF8

# Localizar o bloco $adminCtx dentro de pedidoNovoFunil (linha ~4327)
$idxAdminCtx = -1
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($linhas[$i] -match 'function pedidoNovoFunil') {
        for ($j = $i; $j -lt [Math]::Min($i + 80, $linhas.Count); $j++) {
            if ($linhas[$j] -match '^\s*\$adminCtx\s*=\s*\[') {
                $idxAdminCtx = $j
                break
            }
        }
        break
    }
}

if ($idxAdminCtx -lt 0) {
    Write-Host "  [ERRO] Nao achei `$adminCtx em pedidoNovoFunil." -ForegroundColor Red
    Copy-Item -LiteralPath $bakC -Destination $controller -Force
    exit 1
}

# O bloco termina na primeira linha que tem '];' depois de $adminCtx
$idxFim = -1
for ($k = $idxAdminCtx; $k -lt [Math]::Min($idxAdminCtx + 20, $linhas.Count); $k++) {
    if ($linhas[$k] -match '^\s*\];\s*$') {
        $idxFim = $k
        break
    }
}

if ($idxFim -lt 0) {
    Write-Host "  [ERRO] Nao achei o fechamento `$adminCtx." -ForegroundColor Red
    Copy-Item -LiteralPath $bakC -Destination $controller -Force
    exit 1
}

# Verificar se 'veiculo_categoria' ja existe
$jaTem = $false
for ($k = $idxAdminCtx; $k -le $idxFim; $k++) {
    if ($linhas[$k] -match 'veiculo_categoria') {
        $jaTem = $true
        break
    }
}

if ($jaTem) {
    Write-Host "  [CTRL] veiculo_categoria ja existe; nada a fazer." -ForegroundColor Yellow
} else {
    # Inserir antes do '];' que fecha o array
    $novaLinha = "            'veiculo_categoria'  => `$veiculo ? TarifaService::categoriaDeVeiculo(`$veiculo) : null,"
    $novoBloco = @()
    $novoBloco += $linhas[0..($idxFim - 1)]
    $novoBloco += $novaLinha
    $novoBloco += $linhas[$idxFim..($linhas.Count - 1)]
    $linhas = $novoBloco
    Write-Host "  [CTRL] veiculo_categoria adicionado em `$adminCtx." -ForegroundColor Green
}

[System.IO.File]::WriteAllText($controller, ($linhas -join "`r`n"), $utf8NoBom)

# Validar
& 'C:\xampp\php\php.exe' -l $controller
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [ERRO] php -l falhou. Restaurando..." -ForegroundColor Red
    Copy-Item -LiteralPath $bakC -Destination $controller -Force
    exit 1
}

# ============================================================
# PARTE 2 — View: window.__gfFlowOptions
# ============================================================
Write-Host "[2/2] View..." -ForegroundColor Cyan
$bakV = "$view.bak-fix-B-funil-skip-veiculo-$stamp"
Copy-Item -LiteralPath $view -Destination $bakV -Force
Write-Host "  [BACKUP] $bakV" -ForegroundColor DarkGray

$conteudoView = Get-Content -LiteralPath $view -Raw -Encoding UTF8

if ($conteudoView.Contains('skipVeiculo')) {
    Write-Host "  [VIEW] skipVeiculo ja existe; nada a fazer." -ForegroundColor Yellow
} else {
    $blocoNovo = @'
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
window.__gfFlowOptions = window.__gfFlowOptions || {};
<?php if (!empty($adminCtx['veiculo_id']) && !empty($adminCtx['veiculo_categoria'])): ?>
window.__gfFlowOptions.skipVeiculo = true;
window.__gfFlowOptions.veiculoCategoria = <?= json_encode($adminCtx['veiculo_categoria']) ?>;
<?php endif; ?>
</script>
'@

    # Inserir ANTES do <script src="...public-pre-cotacao-flow.js">
    $marcador = '<script<?= function_exists(''csp_script_nonce_attr'') ? csp_script_nonce_attr() : '''' ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-flow.js'
    if ($conteudoView.Contains($marcador)) {
        $conteudoView = $conteudoView.Replace($marcador, $blocoNovo + "`r`n" + $marcador)
        Write-Host "  [VIEW] window.__gfFlowOptions inserido antes do flow.js." -ForegroundColor Green
    } else {
        Write-Host "  [VIEW] marcador do flow.js nao encontrado." -ForegroundColor Red
        Copy-Item -LiteralPath $bakV -Destination $view -Force
        Copy-Item -LiteralPath $bakC -Destination $controller -Force
        exit 1
    }
}

[System.IO.File]::WriteAllText($view, $conteudoView, $utf8NoBom)

& 'C:\xampp\php\php.exe' -l $view
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [ERRO] php -l falhou. Restaurando ambos..." -ForegroundColor Red
    Copy-Item -LiteralPath $bakV -Destination $view -Force
    Copy-Item -LiteralPath $bakC -Destination $controller -Force
    exit 1
}

# ============================================================
# Resumo
# ============================================================
Write-Host ''
Write-Host "=== RESUMO ===" -ForegroundColor Cyan
Write-Host "Backups:"
Write-Host "  $bakC"
Write-Host "  $bakV"
Write-Host ''
Write-Host 'Para reverter:' -ForegroundColor Yellow
Write-Host "  Copy-Item '$bakC' '$controller' -Force"
Write-Host "  Copy-Item '$bakV' '$view' -Force"
Write-Host ''
Write-Host '[DONE] fix-B-funil-skip-veiculo aplicado.' -ForegroundColor Green