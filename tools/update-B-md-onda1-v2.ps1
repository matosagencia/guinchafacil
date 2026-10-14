# update-B-md-onda1-v2.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Insere marcador de verificacao + 3 pendencias em doc/lanes/B.md
# Idempotente. Backup. UTF-8 sem BOM no destino.
# Autorizado pelo dono em 2026-10-10.

[CmdletBinding()]
param(
    [string]$ProjectRoot  = 'C:\xampp\htdocs\guinchafacil',
    [string]$RelativeFile = 'doc\lanes\B.md'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[update-B-md-v2] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[update-B-md-v2][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[update-B-md-v2][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[update-B-md-v2][FAIL] $m" -ForegroundColor Red }

if (-not (Test-Path $ProjectRoot)) { Write-Fail "ProjectRoot invalido: $ProjectRoot"; exit 1 }

$target = Join-Path $ProjectRoot $RelativeFile
if (-not (Test-Path $target)) { Write-Fail "Arquivo alvo nao existe: $target"; exit 1 }

Write-Step "Alvo: $target"

# --- Le UTF-8 sem BOM (bytes -> string) ---
$rawBytes  = [System.IO.File]::ReadAllBytes($target)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false, $true)
try {
    $content = $utf8NoBom.GetString($rawBytes)
} catch {
    Write-Fail "Arquivo nao e UTF-8 valido: $($_.Exception.Message)"
    exit 1
}

# --- Idempotencia ---
$marker = '<!-- B-ONDA1-2026-10-10 -->'
if ($content.Contains($marker)) {
    Write-Skip "Marcador ja existe. Nada a fazer."
    exit 0
}

# --- Ancoras por REGEX (sem acento literal) ---
$rxSec2 = [regex]'##\s+2\.\s+Estado\s+atual'
$rxSec7 = [regex]'##\s+7\.\s+Pend\S*ncias\s+e\s+bloqueios'
$rxEnd7 = [regex]'Ao\s+terminar\s+uma\s+entrega'

$mSec2 = $rxSec2.Match($content)
$mSec7 = $rxSec7.Match($content)
$mEnd7 = $rxEnd7.Match($content)

if (-not $mSec2.Success) { Write-Fail "Ancora '## 2. Estado atual' nao encontrada"; exit 1 }
if (-not $mSec7.Success) { Write-Fail "Ancora '## 7. Pend* e bloqueios' nao encontrada"; exit 1 }
if (-not $mEnd7.Success) { Write-Fail "Ancora 'Ao terminar uma entrega' nao encontrada"; exit 1 }

Write-Step "Ancoras OK: sec2=$($mSec2.Index) sec7=$($mSec7.Index) end7=$($mEnd7.Index)"

# --- Constroi strings com acentos via [char] ---
# 0x00E3 = a-til, 0x00EA = e-circ, 0x00E7 = c-ced, 0x00F5 = o-til,
# 0x00ED = i-agudo, 0x00E1 = a-agudo, 0x00E9 = e-agudo, 0x00F3 = o-agudo
$cA   = [char]0x00E3  # a~
$cE   = [char]0x00EA  # e^
$cC   = [char]0x00E7  # c,
$cO   = [char]0x00F5  # o~
$cI   = [char]0x00ED  # i'
$cA1  = [char]0x00E1  # a'
$cE1  = [char]0x00E9  # e'
$cO1  = [char]0x00F3  # o'

# Palavras montadas
$verificacao = "Verifica${cC}${cA}o"     # Verificacao
$sessao      = "sess${cA}o"              # sessao
$missao      = "MISS${cA}O"              # MISSAO
$estao       = "est${cA}o"               # estao
$codigo      = "c${cO}digo"              # codigo
$necessario  = "necess${cA}rio"          # necessario
$repositorio = "reposit${cO}rio"         # repositorio
$nao         = "n${cA}o"                 # nao
$integro     = "${cI}ntegro"             # integro
$so          = "s${cO}"                  # so
$acao        = "a${cC}${cA}o"            # acao
$pendencias  = "Pend${cE}ncias"          # Pendencias
$verificado  = "verificado"              # sem acento

# --- Bloco 1 (nota de verificacao) ---
$nl = [Environment]::NewLine
$block1 = @(
    $marker,
    "> **${verificacao} 2026-10-10 (${sessao} Onda 1 ${missao}):** os 5 itens da ${missao} colada",
    "> ${estao} **integralmente implementados**. Nenhum diff de ${codigo} ${necessario}.",
    "> Os 4 assets referenciados por ``oficina/dashboard.php`` existem no ${repositorio}",
    "> (``communications.css``, ``communications.js``, ``atendimento-status.js``",
    "> confirmados por anexo nesta ${sessao}; ``offline-queue.js`` **${nao} ${verificado}**).",
    "> ``MagicLinkService.php`` existe, ${integro}, 200+ linhas.",
    "> ``_pedido_oficina_detalhe.php`` existe, consome ${so} Contrato Pedido v1, sem",
    "> ``JOIN``/``SELECT`` direto.",
    "",
    ""
) -join $nl

# --- Bloco 2 (3 pendencias) ---
$block2 = @(
    "| **P-B-01** - ``MagicLinkService`` depende de ``RateLimiter``, ``Configuracao``, ``AuthService::sanitizeReturnPath``, constantes SMTP/Brevo e envs WAPI. Nenhum foi ${verificado} nesta ${sessao}. Faixa C precisa testar ``POST /login`` com magic link e confirmar que ``RateLimiter`` carrega via autoload. | **Aberto - teste da Faixa C** |",
    "| **P-B-02** - ``_pedido_oficina_detalhe.php`` tinha encoding quebrado (UTF-8 duplo-encodado). **${verificado} em 2026-10-10**: arquivo no disco j${cA} est${cA} em UTF-8 correto; mojibake era artefato de transporte. | **RESOLVIDO 2026-10-10** |",
    "| **P-B-03** - ``_pedido_oficina_detalhe.php`` usa ``\$bp`` sem guard. Hoje funciona porque ``pedidodetalhe.php`` define ``\$bp`` antes do ``require``. Se o partial for reusado em outro contexto, quebra. | **Informativo, sem ${acao}** |",
    "",
    ""
) -join $nl

# --- Aplica bloco 1 (apos a linha de sec2) ---
$eol = $content.IndexOf("`n", $mSec2.Index)
if ($eol -lt 0) { Write-Fail "Nao consegui achar fim da linha sec2"; exit 1 }
$insert1At = $eol + 1
$content = $content.Substring(0, $insert1At) + $block1 + $content.Substring($insert1At)

# --- Aplica bloco 2 (antes de "Ao terminar uma entrega") ---
$mEnd7b = $rxEnd7.Match($content)
if (-not $mEnd7b.Success) { Write-Fail "Ancora end7 perdida"; exit 1 }
$content = $content.Substring(0, $mEnd7b.Index) + $block2 + $content.Substring($mEnd7b.Index)

# --- Backup + escrita UTF-8 sem BOM ---
$ts      = Get-Date -Format 'yyyyMMdd-HHmmss'
$bakPath = "$target.bak-$ts"
Copy-Item -LiteralPath $target -Destination $bakPath -Force
Write-Step "Backup: $bakPath"

try {
    $newBytes = $utf8NoBom.GetBytes($content)
    [System.IO.File]::WriteAllBytes($target, $newBytes)
    Write-Step "Escrito UTF-8 sem BOM ($($newBytes.Length) bytes)."
} catch {
    Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
    Copy-Item -LiteralPath $bakPath -Destination $target -Force
    exit 1
}

# --- Confere BOM no destino ---
$checkBytes = [System.IO.File]::ReadAllBytes($target)
$hasBom = ($checkBytes.Length -ge 3 -and $checkBytes[0] -eq 0xEF -and $checkBytes[1] -eq 0xBB -and $checkBytes[2] -eq 0xBF)
if ($hasBom) {
    Write-Fail "BOM detectado apos escrita! Restaurando."
    Copy-Item -LiteralPath $bakPath -Destination $target -Force
    exit 1
}

Write-Ok "B.md atualizado. Backup: $bakPath"
Write-Ok "Reverter: Copy-Item -LiteralPath '$bakPath' -Destination '$target' -Force"
exit 0