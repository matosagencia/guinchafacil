# onda52-B-partials.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Cria _header-public.php e _footer-public.php em src/Views/public/
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[O5.2-B] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[O5.2-B][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[O5.2-B][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[O5.2-B][FAIL] $m" -ForegroundColor Red }

function Save-File([string]$path, [string]$content, [string]$label) {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    if (Test-Path $path) {
        $bak = "$path.bak-$ts"
        Copy-Item -LiteralPath $path -Destination $bak -Force
        Write-Step "Backup: $bak"
    }
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllBytes($path, $utf8NoBom.GetBytes($content))
    if ((Get-Item $path).Length -eq 0) {
        Write-Fail "$label escrito vazio"
        return $false
    }
    $b = [System.IO.File]::ReadAllBytes($path)
    if ($b.Length -ge 3 -and $b[0] -eq 0xEF -and $b[1] -eq 0xBB -and $b[2] -eq 0xBF) {
        Write-Fail "$label com BOM"
        return $false
    }
    $out = & $PhpExe -l $path 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "php -l falhou em $label : $($out -join ' ')"
        if (Test-Path "$path.bak-$ts") { Copy-Item -LiteralPath "$path.bak-$ts" -Destination $path -Force }
        return $false
    }
    Write-Ok "$label OK"
    return $true
}

$publicDir = Join-Path $ProjectRoot 'src\Views\public'
if (-not (Test-Path $publicDir)) { Write-Fail "Diretorio nao existe: $publicDir"; exit 1 }

# ============================================================================
# 1. _header-public.php
# ============================================================================
$headerPath = Join-Path $publicDir '_header-public.php'

$headerContent = @'
<?php
/**
 * Header publico centralizado (Onda 5.2 - Abordagem A).
 *
 * Variaveis opcionais que cada view pode definir ANTES do require:
 *   $meta_title        - <title> sem o sufixo " | GuinchaFacil"
 *   $meta_description  - <meta name="description">
 *   $meta_canonical    - <link rel="canonical"> e og:url
 *   $meta_og_image     - og:image (default: tow-hero.jpg)
 *   $meta_json_ld      - array associativo para JSON-LD
 *   $body_class        - classe do <body> (default: gf-landing)
 *   $utility_cidade    - texto do link de cidade (default: Niteroi, RJ)
 *   $utility_cidade_url- URL do link (default: /guincho/niteroi-rj)
 *   $nav_atual         - 'inicio' | 'pre-cotacao' | 'onde' | 'parceiros'
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';

$meta_title         = $meta_title         ?? 'GuinchaFacil - Socorro 24h';
$meta_description   = $meta_description   ?? 'Socorro imediato 24h, cotacao rapida e uma rede de guinchos e oficinas.';
$meta_canonical     = $meta_canonical     ?? '';
$meta_og_image      = $meta_og_image      ?? ($bp . '/public/assets/img/landing/tow-hero.jpg');
$meta_json_ld       = $meta_json_ld       ?? null;
$body_class         = $body_class         ?? 'gf-landing';
$utility_cidade     = $utility_cidade     ?? 'Niteroi, RJ';
$utility_cidade_url = $utility_cidade_url ?? ($bp . '/guincho/niteroi-rj');
$nav_atual          = $nav_atual          ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?> | GuinchaFacil</title>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($meta_canonical): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($meta_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="GuinchaFacil">
    <meta property="og:title" content="<?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($meta_og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($meta_canonical): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($meta_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <?php require __DIR__ . '/../components/marketing_tracking.php'; ?>

    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/gf-layout.css" rel="stylesheet">

    <?php if ($meta_json_ld): ?>
    <script<?php echo csp_script_nonce_attr(); ?> type="application/ld+json"><?php echo json_encode($meta_json_ld, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
    <?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8'); ?>" data-base-path="<?php echo htmlspecialchars($bp); ?>">

<div class="gf-utility-bar">
    <div class="gf-container">
        <span><span class="gf-status-dot"></span> Socorro 24h. Todos os dias. Em qualquer imprevisto.</span>
        <a href="<?php echo htmlspecialchars($utility_cidade_url, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <?php echo htmlspecialchars($utility_cidade, ENT_QUOTES, 'UTF-8'); ?>
            <i class="fas fa-arrow-up-right-from-square" aria-hidden="true" style="font-size:.7rem"></i>
        </a>
    </div>
</div>

<header class="gf-site-header">
    <div class="gf-container">
        <a class="gf-brand" href="<?php echo htmlspecialchars($bp); ?>/">Guincha<span>Facil</span>.</a>
        <nav class="gf-nav" aria-label="Navegacao principal">
            <a href="<?php echo htmlspecialchars($bp); ?>/"<?php echo $nav_atual === 'inicio' ? ' class="is-active"' : ''; ?>>Inicio</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/pre-cotacao"<?php echo $nav_atual === 'pre-cotacao' ? ' class="is-active"' : ''; ?>>Como funciona</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/guincho/niteroi-rj"<?php echo $nav_atual === 'onde' ? ' class="is-active"' : ''; ?>>Onde atendemos</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/parceiros"<?php echo $nav_atual === 'parceiros' ? ' class="is-active"' : ''; ?>>Seja um parceiro &nearr;</a>
        </nav>
        <a class="gf-btn gf-btn--primary gf-btn--sm" href="<?php echo htmlspecialchars($bp); ?>/pre-cotacao">Pedir guincho &nearr;</a>
    </div>
</header>
'@

$headerContent = $headerContent -replace "`r`n", "`n"

Write-Host ""
Write-Step "=== 1) Criando _header-public.php ==="
Save-File $headerPath $headerContent "_header-public.php" | Out-Null

# ============================================================================
# 2. _footer-public.php
# ============================================================================
$footerPath = Join-Path $publicDir '_footer-public.php'

$footerContent = @'
<?php
/**
 * Footer publico centralizado (Onda 5.2 - Abordagem A).
 * Fecha <body> e </html>.
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
?>
<footer class="gf-site-footer">
    <div class="gf-container">
        <div class="gf-footer-top">
            <div>
                <a class="gf-brand" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>">Guincha<span>Facil</span>.</a>
                <p>Seu caminho continua aqui.</p>
                <span class="gf-status-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6h4"/>
                    </svg>
                    Assistencia 24 horas
                </span>
            </div>
            <div>
                <h3>Para voce</h3>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/pre-cotacao">Pedir guincho</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/guincho/niteroi-rj">Guincho em Niteroi</a>
            </div>
            <div>
                <h3>Para parceiros</h3>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/parceiros">Rede de parceiros</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/parceiros/guinchos">Sou guincheiro</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/parceiros/oficinas">Tenho uma oficina</a>
            </div>
            <div>
                <h3>GuinchaFacil</h3>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/mapa-do-site">Mapa do site &nearr;</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/pre-cotacao">Faca uma cotacao &rarr;</a>
            </div>
        </div>

        <?php
        $paymentsView = __DIR__ . '/../components/gf-footer-payments.php';
        if (is_file($paymentsView)) {
            require $paymentsView;
        }
        ?>

        <div class="gf-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> GuinchaFacil. Todos os direitos reservados.</span>
            <span>Assistencia 24 horas &middot; Niteroi, RJ</span>
        </div>
    </div>
</footer>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
'@

$footerContent = $footerContent -replace "`r`n", "`n"

Write-Host ""
Write-Step "=== 2) Criando _footer-public.php ==="
Save-File $footerPath $footerContent "_footer-public.php" | Out-Null

Write-Host ""
Write-Ok "Onda 5.2 Fase 1 concluida."
Write-Ok "Reverter: cada arquivo tem .bak-<timestamp> ao lado."
exit 0