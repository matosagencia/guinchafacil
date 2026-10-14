# onda2-B-tokens-nas-views.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Aplica tokens.css, substitui cores hardcoded, adiciona rodape de bandeiras,
# troca badges de cidade e corrige "Você" -> "Você" nas views da Faixa B.
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[onda2-B] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[onda2-B][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[onda2-B][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[onda2-B][FAIL] $m" -ForegroundColor Red }

# ---------------------------------------------------------------------------
# Byte helpers (evitam depender de encoding do parser PS 5.1)
# ---------------------------------------------------------------------------
function Convert-StrToBytes([string]$s) {
    return [System.Text.Encoding]::UTF8.GetBytes($s)
}
function Convert-BytesToStr([byte[]]$b) {
    $utf8 = New-Object System.Text.UTF8Encoding($false, $true)
    return $utf8.GetString($b)
}
function Test-Match([byte[]]$hay, [int]$i, [byte[]]$needle) {
    if ($i + $needle.Length -gt $hay.Length) { return $false }
    for ($j = 0; $j -lt $needle.Length; $j++) {
        if ($hay[$i + $j] -ne $needle[$j]) { return $false }
    }
    return $true
}
function Apply-Subs([byte[]]$raw, [array]$subs) {
    $list = New-Object System.Collections.Generic.List[byte]
    $i = 0
    $applied = 0
    while ($i -lt $raw.Length) {
        $hit = $false
        foreach ($s in $subs) {
            if (Test-Match $raw $i $s.Old) {
                foreach ($b in $s.New) { $list.Add($b) }
                $i += $s.Old.Length
                $applied++
                $hit = $true
                break
            }
        }
        if (-not $hit) {
            $list.Add($raw[$i])
            $i++
        }
    }
    return @{ Bytes = $list.ToArray(); Applied = $applied }
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
function Has-Bom([string]$path) {
    $b = [System.IO.File]::ReadAllBytes($path)
    return ($b.Length -ge 3 -and $b[0] -eq 0xEF -and $b[1] -eq 0xBB -and $b[2] -eq 0xBF)
}

# ---------------------------------------------------------------------------
# Definicao dos arquivos alvo
# ---------------------------------------------------------------------------
$arquivos = @{
    'registro_simples'  = Join-Path $ProjectRoot 'src\Views\auth\registro_simples.php'
    'perfil_completar'  = Join-Path $ProjectRoot 'src\Views\auth\perfil_completar.php'
    'pre_cotacao'       = Join-Path $ProjectRoot 'src\Views\public\pre-cotacao.php'
    'landing'           = Join-Path $ProjectRoot 'src\Views\public\landing.php'
    'cidade'            = Join-Path $ProjectRoot 'src\Views\public\cidade.php'
    'cidades'           = Join-Path $ProjectRoot 'src\Views\public\cidades.php'
}

foreach ($k in $arquivos.Keys) {
    if (-not (Test-Path $arquivos[$k])) {
        Write-Fail "Arquivo nao existe: $($arquivos[$k])"
        exit 1
    }
}

Write-Step "Todos os arquivos alvo existem."

# ---------------------------------------------------------------------------
# TABELA DE SUBSTITUICOES POR BYTES
# ---------------------------------------------------------------------------
# 1) "Você" (V + o + c + e-agudo C3 A9) -> "Você" (V + o + c + e-circ C3 AA)
$subsVoce = @(
    @{
        Nome = 'Voce (V+e-agudo -> V+e-circ)'
        Old  = [byte[]](0x56,0x6F,0x63,0xC3,0xA9)
        New  = [byte[]](0x56,0x6F,0x63,0xC3,0xAA)
    }
)

# 2) Cores hardcoded -> tokens
$subsCores = @(
    @{ Nome='verde';        Old = (Convert-StrToBytes '#2fb34a');                New = (Convert-StrToBytes 'var(--gf-verde)') },
    @{ Nome='verde-dark';   Old = (Convert-StrToBytes '#1f8a36');                New = (Convert-StrToBytes 'var(--gf-verde-dark)') },
    @{ Nome='preto';        Old = (Convert-StrToBytes '#0A0A0A');                New = (Convert-StrToBytes 'var(--gf-preto)') },
    @{ Nome='preto-lower';  Old = (Convert-StrToBytes '#0a0a0a');                New = (Convert-StrToBytes 'var(--gf-preto)') },
    @{ Nome='grafite';      Old = (Convert-StrToBytes '#111827');                New = (Convert-StrToBytes 'var(--gf-grafite)') },
    @{ Nome='borda-light';  Old = (Convert-StrToBytes '#E5E7EB');                New = (Convert-StrToBytes 'var(--gf-borda-light)') },
    @{ Nome='borda-light-l';Old = (Convert-StrToBytes '#e5e7eb');                New = (Convert-StrToBytes 'var(--gf-borda-light)') },
    @{ Nome='borda-dark';   Old = (Convert-StrToBytes '#262626');                New = (Convert-StrToBytes 'var(--gf-borda-dark)') }
)

# 3) Import de tokens.css nas 2 views auth
#    Ancora: apos "<meta name=\"viewport\"...>" adicionar a linha
$importTokens = '<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">'

# ---------------------------------------------------------------------------
# Funcoes de manipulacao de arquivo (com backup/rollback)
# ---------------------------------------------------------------------------
function Process-File {
    param(
        [string]$Path,
        [scriptblock]$Mutate
    )
    Write-Step "Processando: $Path"
    $raw = [System.IO.File]::ReadAllBytes($Path)
    $orig = $raw
    $result = & $Mutate $raw
    if ($null -eq $result) {
        Write-Skip "Sem alteracao: $Path"
        return $false
    }
    $newBytes = $result.Bytes
    $applied = $result.Applied
    if ($applied -eq 0) {
        Write-Skip "Nenhuma substituicao aplicada: $Path"
        return $false
    }
    Write-Step "Substituicoes: $applied | antes: $($orig.Length) | depois: $($newBytes.Length)"

    $bak = Backup-File $Path
    Write-Step "Backup: $bak"

    try {
        [System.IO.File]::WriteAllBytes($Path, $newBytes)
    } catch {
        Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
        Copy-Item -LiteralPath $bak -Destination $Path -Force
        return $false
    }

    if (Has-Bom $Path) {
        Write-Fail "BOM detectado! Restaurando."
        Copy-Item -LiteralPath $bak -Destination $Path -Force
        return $false
    }

    $lint = Lint-Php $Path $PhpExe
    if (-not $lint.Ok) {
        Write-Fail "php -l falhou:`n$($lint.Out)`nRestaurando."
        Copy-Item -LiteralPath $bak -Destination $Path -Force
        return $false
    }
    Write-Ok "php -l OK: $Path"
    return $true
}

# ---------------------------------------------------------------------------
# 1) Fix "Você" -> "Você" em 2 views auth
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== B-Onda-2.5: fix Voce ==="
foreach ($nome in @('registro_simples','perfil_completar')) {
    $path = $arquivos[$nome]
    Process-File -Path $path -Mutate {
        param($raw)
        $subs = $subsVoce
        return (Apply-Subs $raw $subs)
    } | Out-Null
}

# ---------------------------------------------------------------------------
# 2) Import tokens.css nas 2 views auth
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== B-Onda-2.1: import tokens.css nas views auth ==="
foreach ($nome in @('registro_simples','perfil_completar')) {
    $path = $arquivos[$nome]
    $content = Convert-BytesToStr ([System.IO.File]::ReadAllBytes($path))
    if ($content.Contains('tokens.css')) {
        Write-Skip "Ja importa tokens.css: $nome"
        continue
    }
    # Ancora: apos a primeira linha do <head> que contenha "viewport"
    $rx = [regex]'(<meta[^>]*name="viewport"[^>]*>)'
    $m = $rx.Match($content)
    if (-not $m.Success) {
        Write-Fail "Ancora viewport nao encontrada em $nome"
        continue
    }
    $insertAt = $m.Index + $m.Length
    $newContent = $content.Substring(0, $insertAt) + "`n" + $importTokens + $content.Substring($insertAt)

    $bak = Backup-File $path
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllBytes($path, $utf8NoBom.GetBytes($newContent))
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
    Write-Ok "Import tokens.css aplicado: $nome"
}

# ---------------------------------------------------------------------------
# 3) Cores hardcoded -> tokens (auth + pre-cotacao)
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== B-Onda-2.2: cores hardcoded -> var(--gf-*) ==="
foreach ($nome in @('registro_simples','perfil_completar','pre_cotacao')) {
    $path = $arquivos[$nome]
    Process-File -Path $path -Mutate {
        param($raw)
        return (Apply-Subs $raw $subsCores)
    } | Out-Null
}

# ---------------------------------------------------------------------------
# 4) Rodape de bandeiras em views publicas
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== B-Onda-2.3: rodape de bandeiras ==="
$requireFooter = "<?php require __DIR__ . '/../components/gf-footer-payments.php'; ?>"
foreach ($nome in @('landing','cidade','cidades')) {
    $path = $arquivos[$nome]
    $content = Convert-BytesToStr ([System.IO.File]::ReadAllBytes($path))
    if ($content.Contains('gf-footer-payments.php')) {
        Write-Skip "Ja tem rodape de bandeiras: $nome"
        continue
    }
    # Ancora: logo antes de "</body>"
    $idx = $content.LastIndexOf('</body>')
    if ($idx -lt 0) {
        Write-Fail "Ancora </body> nao encontrada em $nome"
        continue
    }
    $newContent = $content.Substring(0, $idx) + $requireFooter + "`n" + $content.Substring($idx)

    $bak = Backup-File $path
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllBytes($path, $utf8NoBom.GetBytes($newContent))
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
    Write-Ok "Rodape aplicado: $nome"
}

# ---------------------------------------------------------------------------
# 5) Badges amigaveis em cidade.php (Pedra Viva/Morta -> badges)
# ---------------------------------------------------------------------------
Write-Host ""
Write-Step "=== B-Onda-2.4: badges amigaveis em cidade.php ==="
$cidadePath = $arquivos['cidade']
$cidade = Convert-BytesToStr ([System.IO.File]::ReadAllBytes($cidadePath))

# Substituicoes de texto (por string, sem acento):
$oldStatusLinha = "`$status = (`$zona['status_expansao'] ?? '') === 'pedra_viva' ? 'Pedra Viva — atendimento ativo' : ((`$zona['status_expansao'] ?? '') === 'pedra_morta' ? 'Pedra Morta — em expansão' : 'Área mapeada');"
$newStatusLinha = "`$badgeAtivo = '<span class=\"badge-status-ativo\">Atendimento Imediato (Guincho a postos)</span>'; `$badgeExpansao = '<span class=\"badge-status-expansao\">Sob Consulta / Agendamento</span>'; `$status = (`$zona['status_expansao'] ?? '') === 'pedra_viva' ? `$badgeAtivo : ((`$zona['status_expansao'] ?? '') === 'pedra_morta' ? `$badgeExpansao : 'Área mapeada');"

if ($cidade.Contains($oldStatusLinha)) {
    $cidade = $cidade.Replace($oldStatusLinha, $newStatusLinha)
    Write-Step "Substituida linha de status (Pedra Viva/Morta)"
} else {
    Write-Skip "Linha de status nao encontrada (talvez ja foi alterada)"
}

# Paragrafo mencionando "Pedras Vivas e Pedras Mortas"
$oldParagrafo = 'Pedras Vivas e Pedras Mortas fazem parte do nosso mapa de opera&ccedil;&atilde;o em'
$newParagrafo = 'Veja abaixo as zonas com atendimento ativo e as areas em expansao em'
if ($cidade.Contains($oldParagrafo)) {
    $cidade = $cidade.Replace($oldParagrafo, $newParagrafo)
    Write-Step "Substituido paragrafo de mencao a 'Pedras Vivas e Mortas'"
} else {
    Write-Skip "Paragrafo 'Pedras Vivas e Mortas' nao encontrado"
}

# So escreve se mudou
if ($cidade -ne (Convert-BytesToStr ([System.IO.File]::ReadAllBytes($cidadePath)))) {
    $bak = Backup-File $cidadePath
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    try {
        [System.IO.File]::WriteAllBytes($cidadePath, $utf8NoBom.GetBytes($cidade))
    } catch {
        Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
        Copy-Item -LiteralPath $bak -Destination $cidadePath -Force
    }
    if (Has-Bom $cidadePath) {
        Write-Fail "BOM detectado! Restaurando."
        Copy-Item -LiteralPath $bak -Destination $cidadePath -Force
    } else {
        $lint = Lint-Php $cidadePath $PhpExe
        if (-not $lint.Ok) {
            Write-Fail "php -l falhou:`n$($lint.Out)`nRestaurando."
            Copy-Item -LiteralPath $bak -Destination $cidadePath -Force
        } else {
            Write-Ok "Badges amigaveis aplicados: cidade.php"
        }
    }
}

Write-Host ""
Write-Ok "Onda 2 da Faixa B concluida."
Write-Ok "Reverter tudo: cada arquivo tem .bak-<timestamp> ao lado."
exit 0