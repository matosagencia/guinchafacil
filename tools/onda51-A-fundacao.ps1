$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$repo = Split-Path -Parent $PSScriptRoot
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$changes = New-Object System.Collections.ArrayList

function Write-A51Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $directory = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    $existed = Test-Path -LiteralPath $Path
    $backup = $null
    if ($existed) {
        $backup = $Path + '.bak-' + $stamp
        Copy-Item -LiteralPath $Path -Destination $backup -Force
        Write-Host ('[A5.1][BACKUP] ' + $backup)
    }

    [void]$script:changes.Add(@{
        Path = $Path
        Existed = $existed
        Backup = $backup
    })

    [System.IO.File]::WriteAllText($Path, $Content, $script:utf8NoBom)
    $relative = $Path.Substring($script:repo.Length).TrimStart([char]'\', [char]'/' )
    Write-Host ('[A5.1][WRITE] ' + $relative)
}

function Write-A51IfChanged {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Content
    )

    if (Test-Path -LiteralPath $Path) {
        $current = [System.IO.File]::ReadAllText($Path)
        if ($current -ceq $Content) {
            $relative = $Path.Substring($script:repo.Length).TrimStart([char]'\', [char]'/' )
            Write-Host ('[A5.1][SKIP] ' + $relative + ' already current')
            return
        }
    }

    Write-A51Utf8NoBom -Path $Path -Content $Content
}

$fontTokens = @'

/* Onda 5.1: official typography and semantic color variants. */
:root {
  --gf-font-sans: 'Manrope', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --gf-font-weight-regular: 400;
  --gf-font-weight-medium: 500;
  --gf-font-weight-semibold: 600;
  --gf-font-weight-bold: 700;
  --gf-font-weight-extrabold: 800;
  --gf-text-on-dark: rgba(255, 255, 255, .85);
  --gf-text-on-dark-muted: rgba(255, 255, 255, .70);
  --gf-text-on-dark-subtle: rgba(255, 255, 255, .50);
  --gf-border-on-dark: rgba(255, 255, 255, .08);
  --gf-overlay-dark: rgba(10, 10, 10, .55);
}

body, .gf-font-sans {
  font-family: var(--gf-font-sans);
}
'@

$layoutCss = @'
/* ============================================================
   gf-layout.css - Lovable port layout foundation (Onda 5.1)
   Depends on: tokens.css
   ============================================================ */

*,
*::before,
*::after {
  box-sizing: border-box;
}

.gf-container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 var(--gf-space-4);
}

@media (max-width: 575.98px) {
  .gf-container {
    padding: 0 var(--gf-space-3);
  }
}

.gf-utility-bar {
  background: var(--gf-preto);
  color: var(--gf-branco);
  font-size: .82rem;
  padding: var(--gf-space-2) 0;
}

.gf-utility-bar .gf-container {
  align-items: center;
  display: flex;
  justify-content: space-between;
}

.gf-utility-bar a {
  align-items: center;
  color: var(--gf-branco);
  display: inline-flex;
  gap: var(--gf-space-2);
  text-decoration: none;
}

.gf-status-dot {
  background: var(--gf-verde);
  border-radius: 50%;
  display: inline-block;
  flex: 0 0 auto;
  height: 6px;
  width: 6px;
}

.gf-site-header {
  background: var(--gf-branco);
  border-bottom: 1px solid var(--gf-borda-light);
}

.gf-site-header .gf-container {
  align-items: center;
  display: flex;
  gap: var(--gf-space-4);
  height: 84px;
  justify-content: space-between;
}

.gf-brand {
  color: var(--gf-preto);
  font-size: 1.5rem;
  font-weight: var(--gf-font-weight-extrabold);
  letter-spacing: -.02em;
  text-decoration: none;
}

.gf-brand span {
  color: var(--gf-verde);
}

.gf-nav {
  align-items: center;
  display: flex;
  gap: var(--gf-space-5);
}

.gf-nav a {
  color: var(--gf-preto);
  font-weight: var(--gf-font-weight-medium);
  text-decoration: none;
  transition: color .15s ease;
}

.gf-nav a:hover,
.gf-nav a.is-active {
  color: var(--gf-verde-dark);
}

.gf-nav a.is-active {
  font-weight: var(--gf-font-weight-bold);
}

@media (max-width: 991.98px) {
  .gf-nav {
    display: none;
  }
}

.gf-btn {
  align-items: center;
  border: 1px solid transparent;
  border-radius: var(--gf-radius-sm);
  cursor: pointer;
  display: inline-flex;
  font-size: .95rem;
  font-weight: var(--gf-font-weight-bold);
  gap: var(--gf-space-2);
  justify-content: center;
  padding: 12px 22px;
  text-decoration: none;
  transition: background .15s ease, border-color .15s ease, color .15s ease, transform .12s ease;
}

.gf-btn--primary {
  background: var(--gf-verde);
  color: var(--gf-branco);
}

.gf-btn--primary:hover {
  background: var(--gf-verde-dark);
  color: var(--gf-branco);
  transform: translateY(-1px);
}

.gf-btn--outline {
  background: transparent;
  border-color: var(--gf-borda-light);
  color: var(--gf-preto);
}

.gf-btn--outline:hover {
  border-color: var(--gf-verde);
  color: var(--gf-verde-dark);
}

.gf-btn--dark {
  background: var(--gf-preto);
  color: var(--gf-branco);
}

.gf-btn--dark:hover {
  background: var(--gf-grafite);
  color: var(--gf-branco);
}

.gf-btn--lg {
  font-size: 1rem;
  padding: 14px 26px;
}

.gf-btn--sm {
  font-size: .85rem;
  padding: 8px 16px;
}

.gf-hero {
  background: var(--gf-grafite);
  color: var(--gf-branco);
  overflow: hidden;
  position: relative;
}

.gf-hero::after {
  background: var(--gf-overlay-dark);
  content: '';
  inset: 0;
  position: absolute;
}

.gf-hero__photo {
  height: 100%;
  inset: 0;
  object-fit: cover;
  position: absolute;
  width: 100%;
}

.gf-hero__content {
  padding: 96px 0;
  position: relative;
  z-index: 1;
}

.gf-hero__eyebrow,
.gf-eyebrow {
  font-size: .75rem;
  font-weight: var(--gf-font-weight-extrabold);
  letter-spacing: .1em;
  text-transform: uppercase;
}

.gf-hero__eyebrow {
  align-items: center;
  background: var(--gf-grafite);
  border: 1px solid var(--gf-border-on-dark);
  border-radius: 999px;
  color: var(--gf-branco);
  display: inline-flex;
  gap: var(--gf-space-2);
  margin-bottom: var(--gf-space-4);
  padding: 6px 14px;
}

.gf-hero h1 {
  font-size: clamp(2rem, 5vw, 3.5rem);
  font-weight: var(--gf-font-weight-extrabold);
  letter-spacing: -.02em;
  line-height: 1.1;
  margin: 0 0 var(--gf-space-4);
}

.gf-hero h1 em {
  color: var(--gf-verde);
  font-style: normal;
}

.gf-hero p {
  color: var(--gf-text-on-dark);
  font-size: 1.05rem;
  line-height: 1.6;
  margin: 0 0 var(--gf-space-5);
  max-width: 560px;
}

.gf-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin-bottom: var(--gf-space-4);
}

.gf-hero__note {
  align-items: center;
  color: var(--gf-text-on-dark-muted);
  display: flex;
  font-size: .82rem;
  gap: var(--gf-space-2);
}

@media (max-width: 767.98px) {
  .gf-hero__content {
    padding: 64px 0;
  }
}

.gf-trust-strip {
  background: var(--gf-cinza-100);
  border-bottom: 1px solid var(--gf-borda-light);
  padding: var(--gf-space-5) 0;
}

.gf-trust-grid {
  display: grid;
  gap: var(--gf-space-4);
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.gf-trust-item {
  align-items: flex-start;
  display: flex;
  gap: 12px;
}

.gf-trust-item svg {
  color: var(--gf-verde-dark);
  flex: 0 0 auto;
}

.gf-trust-item strong {
  color: var(--gf-preto);
  display: block;
  font-weight: var(--gf-font-weight-bold);
  margin-bottom: 2px;
}

.gf-trust-item span {
  color: var(--gf-cinza-500);
  display: block;
  font-size: .85rem;
}

@media (max-width: 767.98px) {
  .gf-trust-grid {
    gap: var(--gf-space-3);
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

.gf-section {
  padding: 80px 0;
}

.gf-section--alt {
  background: var(--gf-cinza-100);
}

.gf-eyebrow {
  color: var(--gf-verde-dark);
  margin-bottom: 12px;
}

.gf-section-title {
  color: var(--gf-preto);
  font-size: clamp(1.6rem, 3.5vw, 2.4rem);
  font-weight: var(--gf-font-weight-extrabold);
  letter-spacing: -.02em;
  line-height: 1.15;
  margin: 0 0 var(--gf-space-4);
}

.gf-section-description {
  color: var(--gf-cinza-500);
  font-size: 1rem;
  line-height: 1.6;
  margin: 0 0 var(--gf-space-4);
  max-width: 640px;
}

@media (max-width: 767.98px) {
  .gf-section {
    padding: 56px 0;
  }
}

.gf-feature-grid {
  display: grid;
  gap: var(--gf-space-4);
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.gf-feature-item {
  background: var(--gf-branco);
  border: 1px solid var(--gf-borda-light);
  border-radius: var(--gf-radius-lg);
  padding: 28px var(--gf-space-4);
}

.gf-feature-item .gf-icon-tile {
  align-items: center;
  background: var(--gf-verde-light);
  border-radius: var(--gf-radius);
  color: var(--gf-verde-dark);
  display: inline-flex;
  height: 48px;
  justify-content: center;
  margin-bottom: var(--gf-space-3);
  width: 48px;
}

.gf-feature-item h3 {
  color: var(--gf-preto);
  font-size: 1.15rem;
  font-weight: var(--gf-font-weight-bold);
  margin: 0 0 10px;
}

.gf-feature-item p {
  color: var(--gf-cinza-500);
  font-size: .92rem;
  line-height: 1.55;
  margin: 0;
}

@media (max-width: 767.98px) {
  .gf-feature-grid {
    grid-template-columns: 1fr;
  }
}

.gf-form-panel {
  background: var(--gf-branco);
  border: 1px solid var(--gf-borda-light);
  border-radius: var(--gf-radius-lg);
  box-shadow: var(--gf-shadow);
  padding: var(--gf-space-5) 28px;
}

.gf-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: var(--gf-space-3);
}

.gf-field-label {
  color: var(--gf-cinza-700);
  font-size: .85rem;
  font-weight: var(--gf-font-weight-semibold);
}

.gf-field-control {
  background: var(--gf-branco);
  border: 1px solid var(--gf-borda-light);
  border-radius: var(--gf-radius-sm);
  color: var(--gf-preto);
  font-size: .95rem;
  outline: none;
  padding: 12px 14px;
  transition: border-color .15s ease, box-shadow .15s ease;
  width: 100%;
}

.gf-field-control:focus {
  border-color: var(--gf-verde);
  box-shadow: 0 0 0 3px var(--gf-verde-light);
}

.gf-form-note {
  align-items: center;
  color: var(--gf-cinza-500);
  display: flex;
  font-size: .8rem;
  gap: 6px;
  margin-top: 12px;
}

.gf-partner-band {
  background: var(--gf-preto);
  color: var(--gf-branco);
  padding: 64px 0;
}

.gf-partner-band .gf-container {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: var(--gf-space-4);
  justify-content: space-between;
}

.gf-partner-band h2 {
  color: var(--gf-branco);
  font-size: clamp(1.5rem, 3vw, 2rem);
  margin: 0 0 10px;
}

.gf-partner-band p {
  color: var(--gf-text-on-dark-muted);
  margin: 0;
}

.gf-site-footer {
  background: var(--gf-preto);
  color: var(--gf-text-on-dark);
  padding: 56px 0 var(--gf-space-4);
}

.gf-footer-top {
  display: grid;
  gap: 40px;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  margin-bottom: 40px;
}

.gf-footer-top h3 {
  color: var(--gf-branco);
  font-size: .85rem;
  font-weight: var(--gf-font-weight-bold);
  letter-spacing: .1em;
  margin: 0 0 14px;
  text-transform: uppercase;
}

.gf-footer-top a {
  color: var(--gf-text-on-dark-muted);
  display: block;
  font-size: .9rem;
  padding: var(--gf-space-1) 0;
  text-decoration: none;
}

.gf-footer-top a:hover {
  color: var(--gf-verde);
}

.gf-footer-bottom {
  border-top: 1px solid var(--gf-border-on-dark);
  color: var(--gf-text-on-dark-subtle);
  display: flex;
  flex-wrap: wrap;
  font-size: .82rem;
  gap: 12px;
  justify-content: space-between;
  padding-top: var(--gf-space-4);
}

@media (max-width: 767.98px) {
  .gf-footer-top {
    gap: 28px;
    grid-template-columns: 1fr;
  }
}

.gf-steps {
  display: flex;
  flex-wrap: wrap;
  font-size: .85rem;
  gap: var(--gf-space-3);
  margin-bottom: var(--gf-space-4);
}

.gf-steps span {
  align-items: center;
  color: var(--gf-cinza-500);
  display: flex;
  gap: 6px;
}

.gf-steps span.is-active {
  color: var(--gf-verde-dark);
  font-weight: var(--gf-font-weight-bold);
}

.gf-steps span.is-done {
  color: var(--gf-verde);
}

.gf-map-wrap {
  background: var(--gf-cinza-100);
  border-radius: var(--gf-radius-lg);
  box-shadow: var(--gf-shadow-lg);
  min-height: 460px;
  overflow: hidden;
  position: relative;
}

.gf-map-caption {
  align-items: center;
  background: var(--gf-branco);
  border-radius: var(--gf-radius);
  bottom: var(--gf-space-3);
  box-shadow: var(--gf-shadow);
  display: flex;
  font-size: .85rem;
  gap: var(--gf-space-2);
  left: var(--gf-space-3);
  padding: 12px var(--gf-space-3);
  position: absolute;
  right: var(--gf-space-3);
}

.gf-price-result {
  background: var(--gf-verde-light);
  border-radius: var(--gf-radius-lg);
  margin-top: var(--gf-space-4);
  padding: var(--gf-space-4);
}

.gf-price-result .gf-price-value {
  color: var(--gf-verde-dark);
  display: block;
  font-size: clamp(1.8rem, 4vw, 2.5rem);
  font-weight: var(--gf-font-weight-extrabold);
  margin: var(--gf-space-2) 0;
}
'@

try {
    $tokensPath = Join-Path $repo 'public\assets\css\tokens.css'
    if (-not (Test-Path -LiteralPath $tokensPath)) {
        throw ('[A5.1][TOKENS] Missing required file: ' + $tokensPath)
    }

    $tokensCurrent = [System.IO.File]::ReadAllText($tokensPath)
    if ($tokensCurrent.IndexOf('--gf-font-sans:') -lt 0) {
        $tokensNext = $tokensCurrent.TrimEnd() + [Environment]::NewLine + $fontTokens.Trim() + [Environment]::NewLine
        Write-A51Utf8NoBom -Path $tokensPath -Content $tokensNext
    }
    else {
        Write-Host '[A5.1][SKIP] public\assets\css\tokens.css font tokens already present'
    }

    $layoutPath = Join-Path $repo 'public\assets\css\gf-layout.css'
    Write-A51IfChanged -Path $layoutPath -Content ($layoutCss.Trim() + [Environment]::NewLine)

    Write-Host '[A5.1][DONE] Visual foundation installed successfully.'
}
catch {
    Write-Host ('[A5.1][ERROR] ' + $_.Exception.Message)
    for ($i = $changes.Count - 1; $i -ge 0; $i--) {
        $change = $changes[$i]
        if ($change.Existed) {
            Copy-Item -LiteralPath $change.Backup -Destination $change.Path -Force
            Write-Host ('[A5.1][ROLLBACK] Restored ' + $change.Path)
        }
        elseif (Test-Path -LiteralPath $change.Path) {
            Remove-Item -LiteralPath $change.Path -Force
            Write-Host ('[A5.1][ROLLBACK] Removed ' + $change.Path)
        }
    }
    exit 1
}
