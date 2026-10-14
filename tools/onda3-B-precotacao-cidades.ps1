# onda3-B-precotacao-cidades.ps1 -- 100% ASCII, PowerShell 5.1 safe
# B-Onda-3.1: revestir pre-cotacao.php (classico) como card + selo-prova-social
# B-Onda-3.2: revestir cidade.php e cidades.php (cores + rodape dentro do footer)
# B-Onda-3.3: revestir landing.php (botoes verde/grafite)
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[B-O3] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[B-O3][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[B-O3][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[B-O3][FAIL] $m" -ForegroundColor Red }

function Read-File([string]$p) {
    $utf8 = New-Object System.Text.UTF8Encoding($false, $true)
    return $utf8.GetString([System.IO.File]::ReadAllBytes($p))
}
function Write-File([string]$p, [string]$content) {
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllBytes($p, $utf8.GetBytes($content))
}
function Backup-File([string]$p) {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    $bak = "$p.bak-$ts"
    Copy-Item -LiteralPath $p -Destination $bak -Force
    return $bak
}
function Lint-Php([string]$p, [string]$php) {
    $out = & $php -l $p 2>&1
    return @{ Ok = ($LASTEXITCODE -eq 0); Out = ($out -join "`n") }
}
function Has-Bom([string]$p) {
    $b = [System.IO.File]::ReadAllBytes($p)
    return ($b.Length -ge 3 -and $b[0] -eq 0xEF -and $b[1] -eq 0xBB -and $b[2] -eq 0xBF)
}
function Save-WithBackup([string]$p, [string]$newContent, [string]$label) {
    $bak = Backup-File $p
    Write-Step "Backup: $bak"
    try { Write-File $p $newContent }
    catch {
        Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
        Copy-Item -LiteralPath $bak -Destination $p -Force
        return $false
    }
    if (Has-Bom $p) {
        Write-Fail "BOM detectado! Restaurando."
        Copy-Item -LiteralPath $bak -Destination $p -Force
        return $false
    }
    $lint = Lint-Php $p $PhpExe
    if (-not $lint.Ok) {
        Write-Fail "php -l falhou:`n$($lint.Out)`nRestaurando."
        Copy-Item -LiteralPath $bak -Destination $p -Force
        return $false
    }
    Write-Ok "$label"
    return $true
}

# ===========================================================================
# B-Onda-3.1: revestir pre-cotacao.php (classico)
# ===========================================================================
Write-Host ""
Write-Step "=== B-Onda-3.1: revestir pre-cotacao.php (classico) ==="

$prePath = Join-Path $ProjectRoot 'src\Views\public\pre-cotacao.php'
if (-not (Test-Path $prePath)) { Write-Fail "pre-cotacao.php nao existe"; exit 1 }

# Estrutura nova do arquivo:
# - Adicionar <style> com classes .gf-precotacao-*
# - Envolver o form em .gf-precotacao-card
# - Adicionar require do selo-prova-social.php apos o form
# - Trocar o botao Bootstrap por .gf-precotacao-btn
# - Preservar 100% dos 9 name=, id=, script, action.

$preConteudo = @'
<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; ?>
<main class="container py-5" style="max-width:760px">
  <style>
    .gf-precotacao-card{background:var(--gf-branco,#fff);border:1px solid var(--gf-borda-light,#E5E7EB);border-radius:var(--gf-radius-lg,16px);box-shadow:var(--gf-shadow-lg,0 12px 32px rgba(0,0,0,.12));overflow:hidden}
    .gf-precotacao-card__body{padding:32px 28px}
    @media (max-width:575.98px){.gf-precotacao-card__body{padding:24px 18px}}
    .gf-precotacao-eyebrow{display:inline-block;background:var(--gf-verde-light,rgba(47,179,74,.12));color:var(--gf-verde-dark,#1f8a36);font-weight:800;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border-radius:999px;margin:0 0 12px}
    .gf-precotacao-title{font-size:1.75rem;font-weight:800;color:var(--gf-preto,#0A0A0A);margin:0 0 8px;line-height:1.15}
    .gf-precotacao-lead{color:var(--gf-cinza-500,#6B7280);font-size:.95rem;margin:0 0 24px}
    .gf-precotacao-form .form-label{color:var(--gf-cinza-700,#374151);font-size:.85rem;font-weight:600;margin-bottom:.35rem}
    .gf-precotacao-form .form-control,.gf-precotacao-form .form-select{border-color:var(--gf-borda-light,#E5E7EB);border-radius:var(--gf-radius-sm,8px);padding:11px 13px;font-size:.95rem}
    .gf-precotacao-form .form-control:focus,.gf-precotacao-form .form-select:focus{border-color:var(--gf-verde,#2fb34a);box-shadow:0 0 0 3px var(--gf-verde-light,rgba(47,179,74,.12))}
    .gf-precotacao-btn{display:block;width:100%;padding:14px;border:0;border-radius:var(--gf-radius-sm,8px);background:var(--gf-verde,#2fb34a);color:var(--gf-branco,#fff);font-weight:800;font-size:1rem;cursor:pointer;transition:background .15s,transform .12s;margin-top:20px}
    .gf-precotacao-btn:hover{background:var(--gf-verde-dark,#1f8a36);transform:translateY(-1px)}
    .gf-precotacao-btn:disabled{opacity:.6;cursor:progress;transform:none}
  </style>
  <section class="gf-precotacao-card">
    <div class="gf-precotacao-card__body">
      <p class="gf-precotacao-eyebrow">GuinchaFacil</p>
      <h1 class="gf-precotacao-title">Faca sua pre-cotacao</h1>
      <p class="gf-precotacao-lead">Veja o valor exato antes de criar conta. Nada e cobrado agora.</p>
      <form id="pre-cotacao-form" class="gf-precotacao-form" action="<?php echo htmlspecialchars($bp); ?>/api/pre-cotacao/finalizar" method="post">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Tipo de problema</label><select class="form-select" name="tipo_problema" required><option value="pneu">Pneu</option><option value="bateria">Bateria</option><option value="eletrica">Eletrica</option><option value="colisao">Colisao</option><option value="combustivel">Combustivel</option><option value="outro">Outro</option></select></div>
          <div class="col-md-6"><label class="form-label">Distancia estimada (km)</label><input class="form-control" name="distancia_km" type="number" min="0" step="0.1" required></div>
          <div class="col-12"><label class="form-label">Endereco de origem</label><input class="form-control" name="endereco_origem" required></div>
          <div class="col-md-6"><label class="form-label">Latitude de origem</label><input class="form-control" name="lat_origem" type="number" step="any" required></div>
          <div class="col-md-6"><label class="form-label">Longitude de origem</label><input class="form-control" name="lng_origem" type="number" step="any" required></div>
          <div class="col-12"><label class="form-label">Endereco de destino</label><input class="form-control" name="endereco_destino" required></div>
          <div class="col-md-6"><label class="form-label">Latitude de destino</label><input class="form-control" name="lat_destino" type="number" step="any" required></div>
          <div class="col-md-6"><label class="form-label">Longitude de destino</label><input class="form-control" name="lng_destino" type="number" step="any" required></div>
          <div class="col-12"><label class="form-label">Valor estimado (R$)</label><input class="form-control" name="custo_estimado" type="number" min="0" step="0.01" required></div>
        </div>
        <p id="pre-cotacao-erro" class="text-danger small mt-3 mb-0" role="alert"></p>
        <button class="gf-precotacao-btn" type="submit">Continuar</button>
      </form>
      <?php require __DIR__ . '/../components/selo-prova-social.php'; ?>
    </div>
  </section>
</main>
<script>
document.getElementById('pre-cotacao-form').addEventListener('submit', async function (event) {
  event.preventDefault();
  const form = event.currentTarget;
  const erro = document.getElementById('pre-cotacao-erro');
  erro.textContent = '';
  try {
    const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
    const body = await response.json();
    if (!response.ok || !body.ok || !body.next_url) throw new Error(body.erro || 'Nao foi possivel finalizar a cotacao.');
    window.location.assign(body.next_url);
  } catch (error) {
    console.error('[PreCotacao][TRIAGE-SAVE-FAIL]', error);
    erro.textContent = error.message || 'Nao foi possivel finalizar a cotacao.';
  }
});
</script>
'@

$preConteudo = $preConteudo -replace "`r`n", "`n"

# Verificacao de idempotencia: se ja tem .gf-precotacao-card, pula
$preAtual = Read-File $prePath
if ($preAtual.Contains('gf-precotacao-card')) {
    Write-Skip "pre-cotacao.php ja revestido (gf-precotacao-card presente)"
} else {
    Save-WithBackup $prePath $preConteudo "pre-cotacao.php revestido (card + selo-prova-social)" | Out-Null
}

# ===========================================================================
# B-Onda-3.2: revestir cidade.php e cidades.php
# ===========================================================================
Write-Host ""
Write-Step "=== B-Onda-3.2: revestir cidade.php e cidades.php ==="

$cidadePath = Join-Path $ProjectRoot 'src\Views\public\cidade.php'
if (Test-Path $cidadePath) {
    $c = Read-File $cidadePath
    $mudou = $false

    # 1) Substituicoes de cores hardcoded remanescentes
    $coresSubs = @(
        @{ Old = '#2fb34a'; New = 'var(--gf-verde)' },
        @{ Old = '#1f8a36'; New = 'var(--gf-verde-dark)' },
        @{ Old = '#0A0A0A'; New = 'var(--gf-preto)' },
        @{ Old = '#111827'; New = 'var(--gf-grafite)' },
        @{ Old = '#E5E7EB'; New = 'var(--gf-borda-light)' },
        @{ Old = '#262626'; New = 'var(--gf-borda-dark)' }
    )
    foreach ($s in $coresSubs) {
        if ($c.Contains($s.Old)) {
            $c = $c.Replace($s.Old, $s.New)
            $mudou = $true
        }
    }

    # 2) Mover require do gf-footer-payments para DENTRO do <footer>
    # Atual: </footer><?php require __DIR__ . '/../components/gf-footer-payments.php'; ?>
    # Novo:  <?php require __DIR__ . '/../components/gf-footer-payments.php'; ?></footer>
    $oldReq = '</footer><?php require __DIR__ . ''/../components/gf-footer-payments.php''; ?>'
    $newReq = '<?php require __DIR__ . ''/../components/gf-footer-payments.php''; ?></footer>'
    if ($c.Contains($oldReq)) {
        $c = $c.Replace($oldReq, $newReq)
        $mudou = $true
        Write-Step "cidade.php: require do rodape movido para dentro do <footer>"
    }

    if ($mudou) {
        Save-WithBackup $cidadePath $c "cidade.php revestido (cores + rodape dentro do footer)" | Out-Null
    } else {
        Write-Skip "cidade.php: nada a alterar"
    }
} else {
    Write-Skip "cidade.php nao existe"
}

$cidadesPath = Join-Path $ProjectRoot 'src\Views\public\cidades.php'
if (Test-Path $cidadesPath) {
    $c = Read-File $cidadesPath
    $mudou = $false
    foreach ($s in @(
        @{ Old = '#2fb34a'; New = 'var(--gf-verde)' },
        @{ Old = '#1f8a36'; New = 'var(--gf-verde-dark)' },
        @{ Old = '#0A0A0A'; New = 'var(--gf-preto)' },
        @{ Old = '#111827'; New = 'var(--gf-grafite)' }
    )) {
        if ($c.Contains($s.Old)) { $c = $c.Replace($s.Old, $s.New); $mudou = $true }
    }
    if ($mudou) {
        Save-WithBackup $cidadesPath $c "cidades.php revestido (cores)" | Out-Null
    } else {
        Write-Skip "cidades.php: nada a alterar"
    }
} else {
    Write-Skip "cidades.php nao existe"
}

# ===========================================================================
# B-Onda-3.3: revestir landing.php (botoes verde/grafite)
# ===========================================================================
Write-Host ""
Write-Step "=== B-Onda-3.3: revestir landing.php ==="

$landingPath = Join-Path $ProjectRoot 'src\Views\public\landing.php'
if (Test-Path $landingPath) {
    $c = Read-File $landingPath
    $mudou = $false

    # Substituir os 2 botoes Bootstrap por estilo gf
    $oldBtn1 = '<a href="<?= $e($bp) ?>/registro/guincho" class="btn btn-primary">Quero ser Guincheiro &rarr;</a>'
    $newBtn1 = '<a href="<?= $e($bp) ?>/registro/guincho" class="gf-btn gf-btn--primary">Quero ser Guincheiro &rarr;</a>'
    $oldBtn2 = '<a href="<?= $e($bp) ?>/registro/especialista" class="btn btn-secondary">Quero ser prestador m&oacute;vel &rarr;</a>'
    $newBtn2 = '<a href="<?= $e($bp) ?>/registro/especialista" class="gf-btn gf-btn--outline">Quero ser prestador m&oacute;vel &rarr;</a>'

    # Tentar variantes com acento diferente
    if ($c.Contains('class="btn btn-primary">Quero ser Guincheiro')) {
        $c = $c.Replace('class="btn btn-primary">Quero ser Guincheiro', 'class="gf-btn gf-btn--primary">Quero ser Guincheiro')
        $mudou = $true
    }
    if ($c.Contains('class="btn btn-secondary">Quero ser prestador')) {
        $c = $c.Replace('class="btn btn-secondary">Quero ser prestador', 'class="gf-btn gf-btn--outline">Quero ser prestador')
        $mudou = $true
    }

    # Injetar CSS dos botoes antes do </head> se ainda nao existir
    if (-not $c.Contains('gf-btn--primary')) {
        # Nao injeta CSS se nao usou os botoes
    } elseif (-not $c.Contains('.gf-btn--primary{')) {
        $cssBtns = @'
<style>
.gf-btn{display:inline-block;padding:12px 22px;border-radius:var(--gf-radius,12px);font-weight:700;font-size:.95rem;text-decoration:none;text-align:center;transition:transform .12s,background .15s;cursor:pointer}
.gf-btn--primary{background:var(--gf-verde,#2fb34a);color:var(--gf-branco,#fff)}
.gf-btn--primary:hover{background:var(--gf-verde-dark,#1f8a36);transform:translateY(-1px)}
.gf-btn--outline{background:var(--gf-grafite,#111827);color:var(--gf-branco,#fff)}
.gf-btn--outline:hover{background:var(--gf-preto,#0A0A0A);transform:translateY(-1px)}
</style>
'@
        $idx = $c.IndexOf('</head>')
        if ($idx -ge 0) {
            $c = $c.Substring(0, $idx) + $cssBtns + "`n" + $c.Substring($idx)
            $mudou = $true
        }
    }

    if ($mudou) {
        Save-WithBackup $landingPath $c "landing.php revestido (botoes verde/grafite + CSS)" | Out-Null
    } else {
        Write-Skip "landing.php: nada a alterar"
    }
} else {
    Write-Skip "landing.php nao existe"
}

Write-Host ""
Write-Ok "B-Onda-3 concluida."
Write-Ok "Reverter: cada arquivo tem .bak-<timestamp> ao lado."
exit 0