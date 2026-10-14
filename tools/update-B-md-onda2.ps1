# update-B-md-onda2.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Registra o estado final da Onda 2 da Faixa B em doc/lanes/B.md.
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot  = 'C:\xampp\htdocs\guinchafacil',
    [string]$RelativeFile = 'doc\lanes\B.md'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[B-md-onda2] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[B-md-onda2][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[B-md-onda2][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[B-md-onda2][FAIL] $m" -ForegroundColor Red }

$target = Join-Path $ProjectRoot $RelativeFile
if (-not (Test-Path $target)) { Write-Fail "Arquivo alvo nao existe: $target"; exit 1 }

$rawBytes  = [System.IO.File]::ReadAllBytes($target)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false, $true)
try { $content = $utf8NoBom.GetString($rawBytes) }
catch { Write-Fail "Arquivo nao e UTF-8 valido"; exit 1 }

$marker = '<!-- B-ONDA2-2026-10-11 -->'
if ($content.Contains($marker)) {
    Write-Skip "Marcador ja existe. Nada a fazer."
    exit 0
}

$rxEnd7 = [regex]'Ao\s+terminar\s+uma\s+entrega'
$mEnd7 = $rxEnd7.Match($content)
if (-not $mEnd7.Success) { Write-Fail "Ancora 'Ao terminar uma entrega' nao encontrada"; exit 1 }

$nl = [Environment]::NewLine
$cO  = [char]0x00F5  # o-til
$cA  = [char]0x00E1  # a-agudo
$cC  = [char]0x00E7  # c-ced
$cE  = [char]0x00EA  # e-circ
$cI  = [char]0x00ED  # i-agudo
$cAt = [char]0x00E3  # a-til
$cU  = [char]0x00FA  # u-agudo

$block = @(
    $marker,
    "| **ONDA-2-ENCERRADA-2026-10-11** - Onda 2 da Faixa B: tokens aplicados, cores convertidas, rodape de bandeiras, badges amigaveis. 6 arquivos alterados, php -l limpo em todos. | **ENCERRADA 2026-10-11** |",
    "| **B-Onda-2.1** - Import ``tokens.css`` em ``registro_simples.php`` e ``perfil_completar.php`` (1 ocorr${cE}ncia cada, sem duplicata). | **OK 2026-10-11** |",
    "| **B-Onda-2.2** - Cores hardcoded (``#2fb34a``, ``#1f8a36``, ``#0A0A0A``, ``#111827``) convertidas para ``var(--gf-*)`` em ``registro_simples.php``, ``perfil_completar.php``, ``pre-cotacao.php``. | **OK 2026-10-11** |",
    "| **B-Onda-2.3** - Rodape de bandeiras (``gf-footer-payments.php``) aplicado em ``landing.php``, ``cidade.php``, ``cidades.php``. | **OK 2026-10-11** |",
    "| **B-Onda-2.4** - Badges amigaveis em ``cidade.php``: ``Pedra Viva`` -> ``<span class=badge-status-ativo>Atendimento Imediato (Guincho a postos)</span>``; ``Pedra Morta`` -> ``<span class=badge-status-expansao>Sob Consulta / Agendamento</span>``; fallback ``<span class=badge-status-mapeada>Area mapeada</span>``. CSS inline no ``<head>``. Paragrafo ``Pedras Vivas e Mortas`` removido. | **OK 2026-10-11** |",
    "| **B-Onda-2.5** - Fix ``Voc${cE}`` (V + e-agudo) -> ``Voc${cE}`` (V + e-circ) em ``registro_simples.php`` e ``perfil_completar.php``. | **OK 2026-10-11** |",
    "| **P-B-07** - ``registro_simples.php`` e ``perfil_completar.php`` usam ``var(--gf-*)`` com import pr${cO}prio de ``tokens.css``. Independentes do ``header.php``. | **Informativo** |",
    "| **P-B-08** - ``cidade.php`` tem CSS inline das badges (``.badge-status-*``). Se a Faixa A migrar para ``public-landing.css``, ${cE} mover. | **Informativo** |",
    "| **ONDA-3-EM-ANDAMENTO-2026-10-11** - Revestimento visual do ``pre-cotacao.php`` cl${cA}ssico (card + barra de prova social). P${cA}ginas de cidade/landing com cores hardcoded remanescentes convertidas para ``var(--gf-*)``. | **Em andamento** |",
    ""
) -join $nl

$content = $content.Substring(0, $mEnd7.Index) + $block + $content.Substring($mEnd7.Index)

$ts = Get-Date -Format 'yyyyMMdd-HHmmss'
$bak = "$target.bak-$ts"
Copy-Item -LiteralPath $target -Destination $bak -Force
Write-Step "Backup: $bak"

try {
    [System.IO.File]::WriteAllBytes($target, $utf8NoBom.GetBytes($content))
} catch {
    Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
    Copy-Item -LiteralPath $bak -Destination $target -Force
    exit 1
}

$checkBytes = [System.IO.File]::ReadAllBytes($target)
$hasBom = ($checkBytes.Length -ge 3 -and $checkBytes[0] -eq 0xEF -and $checkBytes[1] -eq 0xBB -and $checkBytes[2] -eq 0xBF)
if ($hasBom) {
    Write-Fail "BOM detectado! Restaurando."
    Copy-Item -LiteralPath $bak -Destination $target -Force
    exit 1
}

Write-Ok "B.md atualizado. Backup: $bak"
Write-Ok "Reverter: Copy-Item -LiteralPath '$bak' -Destination '$target' -Force"
exit 0