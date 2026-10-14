# fix-B-admin-faturas-menu-e-comissao.ps1
# Parte 1: item "Faturas" no admin_nav_operacional.php (secao Financeiro)
# Parte 2: card "Comissao" no _pedido_oficina_detalhe.php
#
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

$nav = Join-Path $Repositorio 'src\Views\components\admin_nav_operacional.php'
$par = Join-Path $Repositorio 'src\Views\admin\partials\_pedido_oficina_detalhe.php'

foreach ($f in @($nav, $par)) {
    if (-not (Test-Path -LiteralPath $f)) {
        Write-Host "[ERRO] $f nao encontrado." -ForegroundColor Red
        exit 1
    }
}

# Backup
$bakNav = "$nav.bak-fix-B-admin-faturas-menu-$stamp"
$bakPar = "$par.bak-fix-B-pedido-comissao-$stamp"
Copy-Item -LiteralPath $nav -Destination $bakNav -Force
Copy-Item -LiteralPath $par -Destination $bakPar -Force
Write-Host "[BACKUP] $bakNav" -ForegroundColor DarkGray
Write-Host "[BACKUP] $bakPar" -ForegroundColor DarkGray

# ============================================================
# PARTE 1: Item no menu (admin_nav_operacional.php)
# ============================================================
Write-Host "[1/2] Menu..." -ForegroundColor Cyan
$textoNav = Get-Content -LiteralPath $nav -Raw -Encoding UTF8

if ($textoNav.Contains('/admin/faturas')) {
    Write-Host "  [NAV] Link /admin/faturas ja existe. Pulando." -ForegroundColor Yellow
} else {
    # Marcador: a linha do "Receita e margem" — vamos inserir DEPOIS dela
    $marcador = @'
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/financeiro/visao-unificada" class="ops-nav-link <?php echo opsNavActive($cur, 'financeiro/visao-unificada'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-chart-pie"></i></span><span class="ops-nav-link__label">Receita e margem</span></a>
'@

    if ($textoNav.Contains($marcador)) {
        $novoLink = @'
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/faturas" class="ops-nav-link <?php echo opsNavActive($cur, '/admin/faturas'); ?>"><span class="ops-nav-link__icon"><i class="fas fa-file-invoice-dollar"></i></span><span class="ops-nav-link__label">Faturas dos parceiros</span></a>
'@
        $textoNav = $textoNav.Replace($marcador, $marcador + "`r`n" + $novoLink)
        Write-Host "  [NAV] Link 'Faturas dos parceiros' adicionado apos 'Receita e margem'." -ForegroundColor Green
    } else {
        Write-Host "  [ERRO] Marcador 'Receita e margem' nao encontrado." -ForegroundColor Red
        Copy-Item -LiteralPath $bakNav -Destination $nav -Force
        exit 1
    }
}

[System.IO.File]::WriteAllText($nav, $textoNav, $utf8NoBom)

& 'C:\xampp\php\php.exe' -l $nav
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [ERRO] php -l falhou no nav. Restaurando..." -ForegroundColor Red
    Copy-Item -LiteralPath $bakNav -Destination $nav -Force
    exit 1
}

# ============================================================
# PARTE 2: Card Comissao (_pedido_oficina_detalhe.php)
# ============================================================
Write-Host "[2/2] Card comissao..." -ForegroundColor Cyan
$textoPar = Get-Content -LiteralPath $par -Raw -Encoding UTF8

if ($textoPar.Contains('comissao_valor')) {
    Write-Host "  [PAR] Ja tem bloco comissao. Pulando." -ForegroundColor Yellow
} else {
    # 1. Adicionar $bp no topo (apos o bloco de $fmtText)
    $marcadorTopo = @'
$fmtText = static function ($v): string {
    return ($v === null || $v === '') ? '—' : (string)$v;
};
'@

    if (-not $textoPar.Contains('$bp = defined(''BASE_PATH'')')) {
        $textoPar = $textoPar.Replace(
            $marcadorTopo,
            $marcadorTopo + "`r`n`r`n" + '$bp = defined(''BASE_PATH'') ? BASE_PATH : '''';'
        )
        Write-Host "  [PAR] `$bp adicionado no topo." -ForegroundColor Green
    }

    # 2. Inserir o bloco comissao DEPOIS do </dl> (fim do card-body)
    $marcadorFim = @'
        </dl>
    </div>
</div>
'@

    $blocoComissao = @'

        <?php
        $comissaoValor = $pedido['comissao_valor'] ?? null;
        $comissaoTipo  = $pedido['comissao_tipo']  ?? null;
        $valorLiquido  = $pedido['valor_liquido_parceiro'] ?? null;
        $faturaId      = $pedido['fatura_id'] ?? null;
        ?>

        <?php if ($comissaoValor !== null): ?>
        <hr class="my-3">
        <h6 class="text-muted mb-2">Comissão da plataforma</h6>
        <dl class="row mb-0">
            <dt class="col-sm-4">Comissão</dt>
            <dd class="col-sm-8">
                <?= htmlspecialchars($fmtMoney($comissaoValor), ENT_QUOTES, 'UTF-8') ?>
                <?php if ($comissaoTipo !== null): ?>
                    <span class="badge bg-<?= $comissaoTipo === 'faturada' ? 'warning text-dark' : 'info text-dark' ?>">
                        <?= htmlspecialchars($comissaoTipo, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-4">Líquido do parceiro</dt>
            <dd class="col-sm-8"><?= htmlspecialchars($fmtMoney($valorLiquido), ENT_QUOTES, 'UTF-8') ?></dd>

            <dt class="col-sm-4">Fatura</dt>
            <dd class="col-sm-8">
                <?php if (!empty($faturaId)): ?>
                    <a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/fatura/<?= (int)$faturaId ?>">#<?= (int)$faturaId ?></a>
                <?php else: ?>
                    <span class="text-muted">Ainda não faturada</span>
                <?php endif; ?>
            </dd>
        </dl>
        <?php endif; ?>
    </div>
</div>
'@

    if ($textoPar.Contains($marcadorFim)) {
        $textoPar = $textoPar.Replace($marcadorFim, $blocoComissao)
        Write-Host "  [PAR] Card 'Comissao' adicionado." -ForegroundColor Green
    } else {
        Write-Host "  [ERRO] Marcador 'fim do card' nao encontrado." -ForegroundColor Red
        Copy-Item -LiteralPath $bakPar -Destination $par -Force
        exit 1
    }
}

[System.IO.File]::WriteAllText($par, $textoPar, $utf8NoBom)

& 'C:\xampp\php\php.exe' -l $par
if ($LASTEXITCODE -ne 0) {
    Write-Host "  [ERRO] php -l falhou no partial. Restaurando..." -ForegroundColor Red
    Copy-Item -LiteralPath $bakPar -Destination $par -Force
    exit 1
}

Write-Host ''
Write-Host "[DONE] Menu + Card Comissao aplicados." -ForegroundColor Green
Write-Host "Para reverter:" -ForegroundColor Yellow
Write-Host "  Copy-Item '$bakNav' '$nav' -Force"
Write-Host "  Copy-Item '$bakPar' '$par' -Force"