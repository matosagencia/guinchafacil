# onda53-B-precotacao-v2.ps1
# Substitui o pre-cotacao.php classico por wrapper do _precotacao_funil.php (V2)

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[O5.3] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[O5.3][OK] $m" -ForegroundColor Green }
function Write-Fail([string]$m) { Write-Host "[O5.3][FAIL] $m" -ForegroundColor Red }

$prePath    = Join-Path $ProjectRoot 'src\Views\public\pre-cotacao.php'
$funilPath  = Join-Path $ProjectRoot 'src\Views\public\partials\_precotacao_funil.php'

if (-not (Test-Path $prePath))   { Write-Fail "Nao existe: $prePath";   exit 1 }
if (-not (Test-Path $funilPath)) { Write-Fail "Nao existe: $funilPath"; exit 1 }

$content = @'
<?php
/**
 * Pre-cotacao publica - Onda 5.3 (funil V2 na veia).
 * Wrapper que inclui o _precotacao_funil.php + JS do motor de estagios.
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

            <div class="gf-precotacao__grid">
                <div class="gf-precotacao__form">
                    <?php require __DIR__ . '/partials/_precotacao_funil.php'; ?>
                </div>
                <div class="gf-precotacao__aside">
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
                </div>
            </div>
        </div>
    </section>
</main>

<script<?php echo $nonceAttr ?? ''; ?> src="<?php echo $e($bp); ?>/public/assets/js/public-pre-cotacao-flow.js"></script>

<?php require __DIR__ . '/_footer-public.php'; ?>
'@

$content = $content -replace "`r`n", "`n"

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
Write-Ok "pre-cotacao.php agora e wrapper do V2"
Write-Host ""
Write-Host "IMPORTANTE: adicionar no _header-public.php (antes do </head>):" -ForegroundColor Yellow
Write-Host '  <link rel="stylesheet" href="<?php echo $e($bp); ?>/public/assets/css/pages/public-pre-cotacao-flow.css">' -ForegroundColor Gray
Write-Host "  (ou o nome real do CSS que voce descobrir no passo de verificacao)" -ForegroundColor Gray
exit 0