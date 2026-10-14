# fix-precotacao-v2-final.ps1
# 1) Cria CSS dedicado para o partial V2 (.gf-ap__*)
# 2) Reescreve pre-cotacao.php: coluna unica, mapa full-width, trust bar horizontal, WhatsApp float
# 3) Patcheia a CSP do index.php para liberar Google Tag Manager

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[fix-pcv2] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[fix-pcv2][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[fix-pcv2][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[fix-pcv2][FAIL] $m" -ForegroundColor Red }

$newCssPath  = Join-Path $ProjectRoot 'public\assets\css\pages\precotacao-v2.css'
$prePath     = Join-Path $ProjectRoot 'src\Views\public\pre-cotacao.php'
$indexPath   = Join-Path $ProjectRoot 'index.php'
$funilPath   = Join-Path $ProjectRoot 'src\Views\public\partials\_precotacao_funil.php'

Write-Host ""
Write-Step "=== 0. Sanidade ==="
foreach ($p in @($prePath, $indexPath, $funilPath)) {
    if (-not (Test-Path $p)) { Write-Fail "Nao existe: $p"; exit 1 }
}
Write-Ok "Arquivos OK"

# ---------------------------------------------------------------------------
# 1. Criar CSS dedicado V2
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== 1. Criando precotacao-v2.css ==="

$css = @'
/* ============================================================
   precotacao-v2.css - Estilos do funil V2 (_precotacao_funil.php)
   Onda 5.3 - classes .gf-ap__*
   ============================================================ */

/* ---------- Container do mapa ---------- */
.gf-ap {
    position: relative;
    width: 100%;
    margin-bottom: 24px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #E5E7EB;
    background: #F4F8F5;
}
.gf-ap__map {
    width: 100%;
    height: 520px;
    min-height: 520px;
    background: #EDF4EE;
}
@media (max-width: 767.98px) {
    .gf-ap__map { height: 380px; min-height: 380px; }
}

/* Pin verde no centro */
.gf-ap__pin-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -100%);
    z-index: 500;
    font-size: 34px;
    color: #2fb34a;
    text-shadow: 0 2px 8px rgba(0,0,0,.4);
    pointer-events: none;
}

/* Badge topo esquerdo */
.gf-ap__map-title {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 500;
    background: rgba(255,255,255,.96);
    padding: 10px 14px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
    max-width: 280px;
    pointer-events: none;
}
.gf-ap__map-title strong {
    display: block;
    color: #0A0A0A;
    font-size: .9rem;
    line-height: 1.3;
}
.gf-ap__map-title span {
    display: block;
    color: #6B7280;
    font-size: .78rem;
    margin-top: 2px;
    line-height: 1.3;
}

/* ---------- Painel de endereco ---------- */
.gf-ap__panel {
    position: absolute;
    top: 24px;
    right: 24px;
    z-index: 600;
    width: 420px;
    max-width: calc(100% - 48px);
    padding: 20px;
    background: rgba(234, 247, 237, .98);
    border: 1px solid #2fb34a;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(20, 80, 35, .18);
    transition: transform .2s;
}
@media (max-width: 767.98px) {
    .gf-ap__panel {
        position: static;
        width: auto;
        max-width: none;
        margin-top: 16px;
        border-radius: 12px;
    }
}
.gf-ap__panel-toggle {
    display: block;
    width: 100%;
    padding: 6px 10px;
    margin-bottom: 10px;
    background: #176d2c;
    color: #fff;
    border: 0;
    border-radius: 8px;
    font-size: .78rem;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
    transition: background .15s;
}
.gf-ap__panel-toggle:hover { background: #0F5132; }
.gf-ap__label {
    display: block;
    font-size: .88rem;
    font-weight: 700;
    color: #176d2c;
    margin-bottom: 10px;
}
.gf-ap__gps {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    margin-bottom: 12px;
    background: #0A0A0A;
    color: #2fb34a;
    border: 1px solid #2fb34a;
    border-radius: 10px;
    font-weight: 700;
    font-size: .85rem;
    cursor: pointer;
    transition: background .15s;
}
.gf-ap__gps:hover { background: #111827; }

/* Linha de campos */
.gf-ap__row {
    display: grid;
    grid-template-columns: 1fr 120px;
    gap: 12px;
    margin-bottom: 10px;
}
@media (max-width: 575.98px) {
    .gf-ap__row { grid-template-columns: 1fr; }
}
.gf-ap__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.gf-ap__field-label {
    font-size: .78rem;
    font-weight: 700;
    color: #176d2c;
}
.gf-ap__input,
.gf-ap__number {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #cfe3d3;
    border-radius: 8px;
    font-size: .92rem;
    font-family: inherit;
    background: #fff;
    color: #0A0A0A;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.gf-ap__input:focus,
.gf-ap__number:focus {
    border-color: #2fb34a;
    box-shadow: 0 0 0 3px rgba(47,179,74,.15);
}
.gf-ap__status {
    display: block;
    font-size: .78rem;
    color: #55705b;
    min-height: 18px;
    margin-top: 4px;
    line-height: 1.35;
}
.gf-ap__confirm {
    margin-top: 12px;
    padding: 12px 14px;
    background: #D1E7DD;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.gf-ap__confirm-text {
    font-size: .85rem;
    color: #0F5132;
    line-height: 1.4;
}
.gf-ap__confirm-btn {
    background: #2fb34a;
    color: #fff;
    border: 0;
    border-radius: 8px;
    padding: 9px 18px;
    font-weight: 800;
    font-size: .85rem;
    cursor: pointer;
    transition: background .15s;
    flex-shrink: 0;
}
.gf-ap__confirm-btn:hover { background: #1f8a36; }

/* ---------- Trust bar horizontal (abaixo do mapa) ---------- */
.preco-trust-bar {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
    margin-top: 20px;
    padding: 14px 20px;
    background: rgba(47, 179, 74, .06);
    border: 1px solid rgba(47, 179, 74, .18);
    border-radius: 12px;
    font-size: .85rem;
    color: #55705b;
}
.preco-trust-item {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    white-space: nowrap;
}
.preco-trust-item i { color: #2fb34a; font-size: 1.05rem; }
.preco-trust-item strong { color: #172018; }
@media (max-width: 575.98px) {
    .preco-trust-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: .55rem;
        padding: 14px;
    }
}

/* ---------- WhatsApp float ---------- */
.pc-whats-float {
    position: fixed;
    right: 20px;
    bottom: 20px;
    z-index: 9500;
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    padding: .75rem 1.1rem;
    background: #25D366;
    color: #fff !important;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    font-size: .9rem;
    box-shadow: 0 6px 20px rgba(37, 211, 102, .35), 0 2px 6px rgba(0,0,0,.15);
    transition: transform .15s, box-shadow .15s;
    max-width: calc(100vw - 40px);
}
.pc-whats-float:hover {
    background: #20b359;
    color: #fff !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 26px rgba(37, 211, 102, .45);
}
.pc-whats-float .pc-whats-eyebrow {
    display: block;
    font-size: .68rem;
    font-weight: 400;
    opacity: .9;
    line-height: 1;
    margin-bottom: 2px;
}
.pc-whats-float .pc-whats-label {
    display: inline-block;
    line-height: 1.2;
}
@media (max-width: 575.98px) {
    .pc-whats-float { padding: .85rem; }
    .pc-whats-float .pc-whats-label { display: none; }
}

/* ---------- Wrapper do funnel ---------- */
.gf-precotacao { padding: 40px 0 80px; }
.gf-precotacao__intro { max-width: 720px; margin-bottom: 32px; }
.gf-precotacao form.row.g-3 { margin: 0; }
'@

$css = $css -replace "`r`n", "`n"
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$cssDir = Split-Path $newCssPath -Parent
if (-not (Test-Path $cssDir)) { New-Item -ItemType Directory -Path $cssDir -Force | Out-Null }

if (Test-Path $newCssPath) {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    Copy-Item -LiteralPath $newCssPath -Destination "$newCssPath.bak-$ts" -Force
    Write-Step "Backup: $newCssPath.bak-$ts"
}
[System.IO.File]::WriteAllBytes($newCssPath, $utf8NoBom.GetBytes($css))
Write-Ok "precotacao-v2.css criado ($($css.Length) bytes)"

# ---------------------------------------------------------------------------
# 2. Reescrever pre-cotacao.php
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== 2. Reescrevendo pre-cotacao.php ==="

$php = @'
<?php
/**
 * Pre-cotacao publica - Onda 5.3 (funil V2 + Leaflet + flow JS).
 * Coluna unica, mapa full-width, painel flutuante, trust bar horizontal, WhatsApp float.
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e  = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = (string)$_SESSION['_csrf_token'];

$meta_title = 'Pre-cotacao de guincho';
$meta_description = 'Selecione sua localizacao, informe o imprevisto e veja a estimativa antes de criar sua conta.';
$meta_canonical = 'https://guinchafacil.com.br/pre-cotacao';
$body_class = 'gf-landing gf-precotacao-page';
$nav_atual = 'pre-cotacao';

$extra_css = [
    '/public/assets/vendor/leaflet/leaflet.css',
    '/public/assets/css/pages/precotacao-v2.css',
];

$meta_json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $meta_title,
    'url' => $meta_canonical,
    'description' => $meta_description,
];

require __DIR__ . '/_header-public.php';
?>

<main class="gf-precotacao">
    <section class="gf-section">
        <div class="gf-container">

            <div class="gf-precotacao__intro">
                <div class="gf-eyebrow">PRE-COTACAO</div>
                <h1 class="gf-section-title">Vamos colocar voce de volta no caminho.</h1>
                <p class="gf-section-description">Conte o que aconteceu. Em poucos passos, voce ve uma estimativa para decidir com tranquilidade.</p>
            </div>

            <?php require __DIR__ . '/partials/_precotacao_funil.php'; ?>

            <div class="preco-trust-bar" aria-label="Compromissos do atendimento">
                <span class="preco-trust-item">
                    <i class="fas fa-star" aria-hidden="true"></i>
                    <strong>Nota media dos mecanicos:</strong> 4,8 de 5
                </span>
                <span class="preco-trust-item">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <strong>Tempo medio de resposta:</strong> 12 min
                </span>
                <span class="preco-trust-item">
                    <i class="fas fa-users" aria-hidden="true"></i>
                    <strong>Mais de 300</strong> atendimentos realizados
                </span>
            </div>

        </div>
    </section>
</main>

<a class="pc-whats-float" href="https://wa.me/5521999999999?text=Ola%2C%20preciso%20de%20ajuda%20com%20a%20pre-cotacao" target="_blank" rel="noopener">
    <i class="fab fa-whatsapp" aria-hidden="true"></i>
    <span class="pc-whats-label">
        <span class="pc-whats-eyebrow">Precisa de ajuda urgente?</span>
        Falar com alguem agora
    </span>
</a>

<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/public-pre-cotacao-flow.js"></script>

<?php require __DIR__ . '/_footer-public.php'; ?>
'@

$php = $php -replace "`r`n", "`n"

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
Copy-Item -LiteralPath $prePath -Destination "$prePath.bak-$ts" -Force
Write-Step "Backup: $prePath.bak-$ts"

[System.IO.File]::WriteAllBytes($prePath, $utf8NoBom.GetBytes($php))

$out = & $PhpExe -l $prePath 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Fail "php -l falhou: $($out -join ' ')"
    Copy-Item -LiteralPath "$prePath.bak-$ts" -Destination $prePath -Force
    exit 1
}
Write-Ok "pre-cotacao.php reescrito"

# ---------------------------------------------------------------------------
# 3. Patch CSP no index.php
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== 3. Patch CSP no index.php ==="

$utf8Read = New-Object System.Text.UTF8Encoding($false, $true)
$indexContent = $utf8Read.GetString([System.IO.File]::ReadAllBytes($indexPath))

if ($indexContent.Contains('googletagmanager.com')) {
    Write-Skip "CSP ja libera googletagmanager.com"
} else {
    # Encontrar a linha da CSP e substituir a parte de script-src, img-src, connect-src
    $oldScript = "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com;"
    $newScript = "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com;"

    $oldImg = "img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://unpkg.com;"
    $newImg = "img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com;"

    $oldConn = "connect-src 'self' https://viacep.com.br https://nominatim.openstreetmap.org https://router.project-osrm.org https://api.mercadopago.com https://ws.pagseguro.uol.com.br https://ws.sandbox.pagseguro.uol.com.br https://cdn.jsdelivr.net https://unpkg.com;"
    $newConn = "connect-src 'self' https://viacep.com.br https://nominatim.openstreetmap.org https://router.project-osrm.org https://api.mercadopago.com https://ws.pagseguro.uol.com.br https://ws.sandbox.pagseguro.uol.com.br https://cdn.jsdelivr.net https://unpkg.com https://www.googletagmanager.com https://www.google-analytics.com;"

    $mudou = $false
    if ($indexContent.Contains($oldScript)) { $indexContent = $indexContent.Replace($oldScript, $newScript); $mudou = $true }
    if ($indexContent.Contains($oldImg))    { $indexContent = $indexContent.Replace($oldImg, $newImg);       $mudou = $true }
    if ($indexContent.Contains($oldConn))   { $indexContent = $indexContent.Replace($oldConn, $newConn);     $mudou = $true }

    if (-not $mudou) {
        Write-Fail "Nao encontrei os blocos da CSP para substituir. Confira manualmente:"
        Write-Host "  Procurar por: script-src 'self' 'unsafe-inline' 'unsafe-eval'" -ForegroundColor Gray
    } else {
        $ts2 = Get-Date -Format 'yyyyMMdd-HHmmss'
        Copy-Item -LiteralPath $indexPath -Destination "$indexPath.bak-$ts2" -Force
        Write-Step "Backup: $indexPath.bak-$ts2"

        [System.IO.File]::WriteAllBytes($indexPath, $utf8NoBom.GetBytes($indexContent))

        $out2 = & $PhpExe -l $indexPath 2>&1
        if ($LASTEXITCODE -ne 0) {
            Write-Fail "php -l falhou no index.php: $($out2 -join ' ')"
            Copy-Item -LiteralPath "$indexPath.bak-$ts2" -Destination $indexPath -Force
            exit 1
        }
        Write-Ok "index.php CSP atualizada"
    }
}

Write-Host ""
Write-Ok "Fix completo. Recarregue /pre-cotacao com Ctrl+Shift+R."
Write-Host ""
Write-Host "DEPOIS DE RODAR:" -ForegroundColor Yellow
Write-Host "  - Se o mapa ainda nao aparecer, o flow JS pode usar MapManager" -ForegroundColor Gray
Write-Host "    que precisa do leaflet.js carregado ANTES dele (ja esta na ordem)." -ForegroundColor Gray
Write-Host "  - Se o painel ainda nao flutuar em desktop, mande screenshot + console." -ForegroundColor Gray
exit 0