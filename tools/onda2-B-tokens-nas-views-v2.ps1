# onda2-B-tokens-nas-views-v2.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Tarefas: 2.5 (fix Voce), 2.1 (import tokens), 2.2 (cores -> var), 2.3 (rodape bandeiras)
# Item 2.4 (badges cidade.php) sera entregue em patch dedicado separado.
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[onda2-B-v2] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[onda2-B-v2][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[onda2-B-v2][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[onda2-B-v2][FAIL] $m" -ForegroundColor Red }

# ---------------------------------------------------------------------------
# Byte helpers
# ---------------------------------------------------------------------------
function Convert-StrToBytes([string]$s) { return [System.Text.Encoding]::UTF8.GetBytes($s) }
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
# Arquivos alvo
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
# SUBSTITUICOES POR BYTES
# ---------------------------------------------------------------------------

# "Você" (V + o + c + e-agudo C3 A9) -> "Você" (V + o + c + e-circ C3 AA)
$subsVoce = @(
    @{
        Nome = 'Voce (V + e-agudo -> V + e-circ)'
        Old  = [byte[]](0x56,0x6F,0x63,0xC3,0xA9)
        New  = [byte[]](0x56,0x6F,0x63,0xC3,0xAA)
    }
)

# Cores hardcoded -> tokens
$subsCores = @(
    @{ Nome='verde';        Old = (Convert-StrToBytes '#2fb34a'); New = (Convert-StrToBytes 'var(--gf-verde)') },
    @{ Nome='verde-dark';   Old = (Convert-StrToBytes '#1f8a36'); New = (Convert-StrToBytes 'var(--gf-verde-dark)') },
    @{ Nome='preto';        Old = (Convert-StrToBytes '#0A0A0A'); New = (Convert-StrToBytes 'var(--gf-preto)') },
    @{ Nome='preto-lower';  Old = (Convert-StrToBytes '#0a0a0a'); New = (Convert-StrToBytes 'var(--gf-preto)') },
    @{ Nome='grafite';      Old = (Convert-StrToBytes '#111827'); New = (Convert-StrToBytes 'var(--gf-grafite)') },
    @{ Nome='borda-light';  Old = (Convert-StrToBytes '#E5E7EB'); New = (Convert-StrToBytes 'var(--gf-borda-light)') },
    @{ Nome='borda-light-l';Old = (Convert-StrToBytes '#e5e7eb'); New = (Convert-StrToBytes 'var(--gf-borda-light)') },
    @{ Nome='borda-dark';   Old = (Convert-StrToBytes '#262626'); New = (Convert-StrToBytes 'var(--gf-borda-dark)') }
)

# Import de tokens.css
$importTokens = '<link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">'

# ---------------------------------------------------------------------------
# Process-File (backup + escrita + lint + rollback)
# ---------------------------------------------------------------------------
function Process-File {
    param([string]$Path, [scriptblock]$Mutate)
    Write-Step "Processando: $Path"
    $raw = [System.IO.File]::ReadAllBytes($Path)
    $origLen = $raw.Length
    $result = & $Mutate $raw
    if ($null -eq $result -or $result.Applied -eq 0) {
        Write-Skip "Nenhuma substituicao aplicada: $Path"
        return $false
    }
    $newBytes = $result.Bytes
    Write-Step "Substituicoes: $($result.Applied) | antes: $origLen | depois: $($newBytes.Length)"

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

# ===========================================================================
# B-Onda-2.5: fix "Você" -> "Você"
# ===========================================================================
Write-Host ""
Write-Step "=== B-Onda-2.5: fix Voce ==="
foreach ($nome in @('registro_simples','perfil_completar')) {
    $path = $arquivos[$nome]
    Process-File -Path $path -Mutate {
        param($raw)
        return (Apply-Subs $raw $subsVoce)
    } | Out-Null
}

# ===========================================================================
# B-Onda-2.1: import tokens.css nas views auth
# ===========================================================================
Write-Host ""
Write-Step "=== B-Onda-2.1: import tokens.css nas views auth ==="
foreach ($nome in @('registro_simples','perfil_completar')) {
    $path = $arquivos[$nome]
    $content = Convert-BytesToStr ([System.IO.File]::ReadAllBytes($path))
    if ($content.Contains('tokens.css')) {
        Write-Skip "Ja importa tokens.css: $nome"
        continue
    }
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

# ===========================================================================
# B-Onda-2.2: cores hardcoded -> tokens
# ===========================================================================
Write-Host ""
Write-Step "=== B-Onda-2.2: cores hardcoded -> var(--gf-*) ==="
foreach ($nome in @('registro_simples','perfil_completar','pre_cotacao')) {
    $path = $arquivos[$nome]
    Process-File -Path $path -Mutate {
        param($raw)
        return (Apply-Subs $raw $subsCores)
    } | Out-Null
}

# ===========================================================================
# B-Onda-2.3: rodape de bandeiras
# ===========================================================================
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

Write-Host ""
Write-Ok "Onda 2 da Faixa B concluida (itens 2.1, 2.2, 2.3, 2.5)."
Write-Ok "Item 2.4 (badges cidade.php) fica para patch dedicado."
Write-Ok "Reverter: cada arquivo tem .bak-<timestamp> ao lado."
exit 0