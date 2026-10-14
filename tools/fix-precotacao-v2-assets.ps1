# fix-precotacao-v2-assets.ps1
# 1) Patch _header-public.php para suportar $extra_css
# 2) Reescreve pre-cotacao.php com CSS + Leaflet + flow JS na ordem correta

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[fix-pc] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[fix-pc][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[fix-pc][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[fix-pc][FAIL] $m" -ForegroundColor Red }

$headerPath = Join-Path $ProjectRoot 'src\Views\public\_header-public.php'
$prePath    = Join-Path $ProjectRoot 'src\Views\public\pre-cotacao.php'
$funilPath  = Join-Path $ProjectRoot 'src\Views\public\partials\_precotacao_funil.php'
$leafletJs  = Join-Path $ProjectRoot 'public\assets\vendor\leaflet\leaflet.js'
$leafletCss = Join-Path $ProjectRoot 'public\assets\vendor\leaflet\leaflet.css'
$flowJs     = Join-Path $ProjectRoot 'public\assets\js\public-pre-cotacao-flow.js'

Write-Host ""
Write-Step "=== 0. Sanidade ==="
foreach ($p in @($headerPath, $prePath, $funilPath)) {
    if (-not (Test-Path $p)) { Write-Fail "Nao existe: $p"; exit 1 }
}
if (-not (Test-Path $leafletJs))  { Write-Fail "Falta Leaflet JS: $leafletJs"; exit 1 }
if (-not (Test-Path $leafletCss)) { Write-Fail "Falta Leaflet CSS: $leafletCss"; exit 1 }
if (-not (Test-Path $flowJs))     { Write-Fail "Falta flow JS: $flowJs"; exit 1 }
Write-Ok "Todos os arquivos presentes"

# --- 1. Patch _header-public.php: suporte a $extra_css ---
Write-Host ""
Write-Step "=== 1. Adicionando suporte a extra_css no _header-public.php ==="

$utf8 = New-Object System.Text.UTF8Encoding($false, $true)
$header = $utf8.GetString([System.IO.File]::ReadAllBytes($headerPath))

if ($header.Contains('extra_css')) {
    Write-Skip "header ja suporta extra_css"
} else {
    $needle = '</head>'
    if (-not $header.Contains($needle)) {
        Write-Fail "Nao achei </head> no header"
        exit 1
    }
    $inject = "<?php if (!empty(`$extra_css)) foreach ((array)`$extra_css as `$_x) { echo '    <link rel=`"stylesheet`" href=`"' . htmlspecialchars(`$bp . `$_x, ENT_QUOTES, 'UTF-8') . '`">' . PHP_EOL; } ?>`n</head>"
    $header = $header.Replace($needle, $inject)

    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    $bak = "$headerPath.bak-$ts"
    Copy-Item -LiteralPath $headerPath -Destination $bak -Force
    Write-Step "Backup: $bak"

    $utf8Write = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllBytes($headerPath, $utf8Write.GetBytes($header))

    $out = & $PhpExe -l $headerPath 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "php -l falhou no header: $($out -join ' ')"
        Copy-Item -LiteralPath $bak -Destination $headerPath -Force
        exit 1
    }
    Write-Ok "header patcheado"
}

# --- 2. Reescreve pre-cotacao.php ---
Write-Host ""
Write-Step "=== 2. Reescrevendo pre-cotacao.php ==="

$content = @'
<?php
/**
 * Pre-cotacao publica - Onda 5.3 (funil V2 + Leaflet + flow JS).
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
    '/public/assets/css/pages/public-pre-cotacao.css',
    '/public/assets/css/pages/public-pre-cotacao-map.css',
    '/public/assets/css/components/precotacao-trust.css',
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

<style>
.gf-precotacao__intro { max-width: 720px; margin-bottom: 40px; }
.gf-precotacao__grid {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr);
    gap: 40px;
    align-items: start;
}
@media (max-width: 991.98px) {
    .gf-precotacao__grid { grid-template-columns: 1fr; gap: 32px; }
}
.gf-precotacao__aside { position: sticky; top: 24px; }
.gf-precotacao__trust {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 24px;
    background: var(--gf-cinza-100, #F8FAFC);
    border-radius: var(--gf-radius-lg, 16px);
    border: 1px solid var(--gf-borda-light, #E5E7EB);
}
</style>

<main class="gf-precotacao">
    <section class="gf-section gf-precotacao__section">
        <div class="gf-container">
            <div class="gf-precotacao__intro">
                <div class="gf-eyebrow">PRE-COTACAO</div>
                <h1 class="gf-section-title">Vamos colocar voce de volta no caminho.</h1>
                <p class="gf-section-description">Conte o que aconteceu. Em poucos passos, voce ve uma estimativa para decidir com tranquilidade.</p>
            </div>

            <div class="gf-precotacao__grid">
                <div class="gf-precotacao__form">
                    <?php require __DIR__ . '/partials/_precotacao_funil.php'; ?>
                </div>
                <aside class="gf-precotacao__aside">
                    <div class="gf-precotacao__trust">
                        <div class="gf-trust-item">
                            <i class="fas fa-clock" aria-hidden="true"></i>
                            <div><strong>Atendimento 24h</strong><span>Todos os dias, a qualquer hora.</span></div>
                        </div>
                        <div class="gf-trust-item">
                            <i class="fas fa-shield-halved" aria-hidden="true"></i>
                            <div><strong>Preco antes de pagar</strong><span>Estimativa clara. Nada cobrado agora.</span></div>
                        </div>
                        <div class="gf-trust-item">
                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                            <div><strong>Parceiros na sua regiao</strong><span>Quem esta perto, chega mais rapido.</span></div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/public-pre-cotacao-flow.js"></script>

<?php require __DIR__ . '/_footer-public.php'; ?>
'@

$content = $content -replace "`r`n", "`n"

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$bak = "$prePath.bak-$ts"
Copy-Item -LiteralPath $prePath -Destination $bak -Force
Write-Step "Backup: $bak"

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllBytes($prePath, $utf8NoBom.GetBytes($content))

$out = & $PhpExe -l $prePath 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Fail "php -l falhou: $($out -join ' ')"
    Copy-Item -LiteralPath $bak -Destination $prePath -Force
    exit 1
}
Write-Ok "pre-cotacao.php reescrito"

Write-Host ""
Write-Ok "Fix concluido. Recarregue /pre-cotacao com Ctrl+Shift+R."
exit 0