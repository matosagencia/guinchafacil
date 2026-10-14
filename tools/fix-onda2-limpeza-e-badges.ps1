# fix-onda2-limpeza-e-badges.ps1 -- 100% ASCII, PowerShell 5.1 safe
# 1) Remove <link tokens.css> duplicado (deixa so 1) em 2 views auth.
# 2) Aplica badges amigaveis em cidade.php (Pedra Viva/Morta -> badges).
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[fix-onda2] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[fix-onda2][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[fix-onda2][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[fix-onda2][FAIL] $m" -ForegroundColor Red }

function Convert-BytesToStr([byte[]]$b) {
    $utf8 = New-Object System.Text.UTF8Encoding($false, $true)
    return $utf8.GetString($b)
}
function Convert-StrToBytes([string]$s) { return [System.Text.Encoding]::UTF8.GetBytes($s) }
function Has-Bom([string]$path) {
    $b = [System.IO.File]::ReadAllBytes($path)
    return ($b.Length -ge 3 -and $b[0] -eq 0xEF -and $b[1] -eq 0xBB -and $b[2] -eq 0xBF)
}
function Backup-File([string]$path) {
    $ts = Get-Date -Format 'yyyyMMdd-HHmmss'
    $bak = "$path.bak-$ts"
    Copy-Item -LiteralPath $path -Destination $bak -Force
    return $bak
}
function Lint-Php([string]$path, [string]$php) {
    $out = & $php -l $path 2>&1
    return @{ Ok = ($LASTEXITCODE -eq 0); Out = ($out -join "`n") }
}

# ===========================================================================
# PARTE 1: remover duplicata de <link tokens.css>
# ===========================================================================
Write-Host ""
Write-Step "=== Limpeza: remover <link tokens.css> duplicado ==="

$arquivos = @(
    (Join-Path $ProjectRoot 'src\Views\auth\registro_simples.php'),
    (Join-Path $ProjectRoot 'src\Views\auth\perfil_completar.php')
)

# regex: 2 linhas consecutivas identicas de <link tokens.css>
# (o arquivo tem o <link> quebrado em 2 linhas por causa do echo htmlspecialchars)
$pattern = "(<link href=`"<\?php echo htmlspecialchars\(\`$bp\); \?>/public/assets/css/tokens\.css`" rel=`"stylesheet`">`r?`n){2,}"

foreach ($path in $arquivos) {
    if (-not (Test-Path $path)) {
        Write-Fail "Arquivo nao existe: $path"
        continue
    }
    Write-Step "Verificando: $path"
    $content = Convert-BytesToStr ([System.IO.File]::ReadAllBytes($path))

    $rx = [regex]$pattern
    $matches = $rx.Matches($content)
    if ($matches.Count -eq 0) {
        Write-Skip "Sem duplicata: $path"
        continue
    }

    # Substituir cada bloco de N linhas por 1 linha
    $single = '<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">'
    $novo = $rx.Replace($content, $single)

    $bak = Backup-File $path
    Write-Step "Backup: $bak"

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllBytes($path, $utf8NoBom.GetBytes($novo))
    } catch {
        Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
        Copy-Item -LiteralPath $bak -Destination $path -Force
        continue
    }
    if (Has-Bom $path) {
        Write-Fail "BOM detectado! Restaurando."
        Copy-Item -LiteralPath $bak -Destination $path -Force
        continue
    }
    $lint = Lint-Php $path $PhpExe
    if (-not $lint.Ok) {
        Write-Fail "php -l falhou:`n$($lint.Out)`nRestaurando."
        Copy-Item -LiteralPath $bak -Destination $path -Force
        continue
    }
    Write-Ok "Duplicata removida: $path"
}

# ===========================================================================
# PARTE 2: badges amigaveis em cidade.php (Pedra Viva/Morta -> badges)
# ===========================================================================
Write-Host ""
Write-Step "=== Badges amigaveis em cidade.php ==="

$cidadePath = Join-Path $ProjectRoot 'src\Views\public\cidade.php'
if (-not (Test-Path $cidadePath)) {
    Write-Fail "cidade.php nao existe: $cidadePath"
    exit 1
}

$content = Convert-BytesToStr ([System.IO.File]::ReadAllBytes($cidadePath))
$mudou = $false

# --- Sub A: linha de status ---
# Alvo (bytes, com em-dash e a-til):
#   ? 'Pedra Viva \u2014 atendimento ativo' : ((... ) ? 'Pedra Morta \u2014 em expans\u00e3o' : '\u00c1rea mapeada');
# Os caracteres sao montados via [char] para nao esbarrar no parser PS 5.1.
$dash    = [char]0x2014        # em dash
$atil    = [char]0x00E3        # a-til
$Aagudo  = [char]0x00C1        # A-agudo

$oldStatus = "'Pedra Viva " + $dash + " atendimento ativo' : ((`$zona['status_expansao'] ?? '') === 'pedra_morta' ? 'Pedra Morta " + $dash + " em expans" + $atil + "o' : '" + $Aagudo + "rea mapeada');"

# Nova versao: define badges HTML como variaveis (nao usa $e() para renderizar)
# Vai virar:
#   $badgeAtivo = '<span class="badge-status-ativo">Atendimento Imediato (Guincho a postos)</span>';
#   $badgeExpansao = '<span class="badge-status-expansao">Sob Consulta / Agendamento</span>';
#   $statusHtml = ($zona['status_expansao'] ?? '') === 'pedra_viva' ? $badgeAtivo : ((($zona['status_expansao'] ?? '') === 'pedra_morta') ? $badgeExpansao : '<span class="badge-status-mapeada">Area mapeada</span>');

$newStatus = "`$badgeAtivo = '<span class=`"badge-status-ativo`">Atendimento Imediato (Guincho a postos)</span>'; " +
             "`$badgeExpansao = '<span class=`"badge-status-expansao`">Sob Consulta / Agendamento</span>'; " +
             "`$statusHtml = (`$zona['status_expansao'] ?? '') === 'pedra_viva' ? `$badgeAtivo : ((`$zona['status_expansao'] ?? '') === 'pedra_morta' ? `$badgeExpansao : '<span class=`"badge-status-mapeada`">Area mapeada</span>');"

if ($content.Contains($oldStatus)) {
    $content = $content.Replace($oldStatus, $newStatus)
    $mudou = $true
    Write-Step "Linha de status substituida por badges HTML"
} else {
    Write-Skip "Linha de status nao encontrada (talvez ja foi alterada)"
}

# --- Sub B: paragrafo "Pedras Vivas e Pedras Mortas fazem parte..." ---
$oldPar = 'Pedras Vivas e Pedras Mortas fazem parte do nosso mapa de opera&ccedil;&atilde;o em'
$newPar = 'Veja abaixo as zonas com atendimento ativo e as areas em expansao em'
if ($content.Contains($oldPar)) {
    $content = $content.Replace($oldPar, $newPar)
    $mudou = $true
    Write-Step "Paragrafo 'Pedras Vivas e Mortas' substituido"
} else {
    Write-Skip "Paragrafo 'Pedras Vivas e Mortas' nao encontrado"
}

# --- Sub C: trocar `<?= $e($status) ?>` por `<?= $statusHtml ?>` (sem escape) ---
$oldRender = '<?= $e($status) ?>'
$newRender = '<?= $statusHtml ?>'
if ($content.Contains($oldRender)) {
    $content = $content.Replace($oldRender, $newRender)
    $mudou = $true
    Write-Step "Renderizacao de `$status` trocada por `$statusHtml` (sem escape)"
} else {
    Write-Skip "Renderizacao `<?= `$e(`$status) ?>` nao encontrada"
}

# --- Grava se mudou ---
if ($mudou) {
    $bak = Backup-File $cidadePath
    Write-Step "Backup: $bak"

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllBytes($cidadePath, $utf8NoBom.GetBytes($content))
    } catch {
        Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
        Copy-Item -LiteralPath $bak -Destination $cidadePath -Force
        exit 1
    }
    if (Has-Bom $cidadePath) {
        Write-Fail "BOM detectado! Restaurando."
        Copy-Item -LiteralPath $bak -Destination $cidadePath -Force
        exit 1
    }
    $lint = Lint-Php $cidadePath $PhpExe
    if (-not $lint.Ok) {
        Write-Fail "php -l falhou:`n$($lint.Out)`nRestaurando."
        Copy-Item -LiteralPath $bak -Destination $cidadePath -Force
        exit 1
    }
    Write-Ok "Badges amigaveis aplicados: cidade.php"
} else {
    Write-Skip "cidade.php: nada a alterar"
}

Write-Host ""
Write-Ok "Limpeza + badges concluidos."
exit 0