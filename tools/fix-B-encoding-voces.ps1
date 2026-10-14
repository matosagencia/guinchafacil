# fix-B-encoding-voces.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Corrige "vocé" -> "você" e "PolÃ­tica" -> "Política"
# Autorizado pelo dono em 2026-10-11.

[CmdletBinding()]
param(
    [string]$ProjectRoot = 'C:\xampp\htdocs\guinchafacil',
    [string]$PhpExe      = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[fix-B-voces] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[fix-B-voces][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[fix-B-voces][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[fix-B-voces][FAIL] $m" -ForegroundColor Red }

$alvos = @(
    @{ Rel = 'src\Views\auth\registro_simples.php';   EhPhp = $true  },
    @{ Rel = 'src\Views\auth\perfil_completar.php';   EhPhp = $true  }
)

# --- Pares de substituicao por BYTES ---
# "vocé" (v o c e-agudo) UTF-8 = 76 6F 63 C3 A9
# "você" (v o c e-circ)  UTF-8 = 76 6F 63 C3 AA
# "PolÃ­tica" (P o l C3 83 C2 AD t i c a) UTF-8 = 50 6F 6C C3 83 C2 AD 74 69 63 61
# "Política" (P o l C3 AD t i c a) UTF-8 = 50 6F 6C C3 AD 74 69 63 61

$subs = @(
    @{
        Nome = 'voce (e-agudo -> e-circ)'
        Old  = [byte[]](0x76,0x6F,0x63,0xC3,0xA9)          # "voce-agudo"
        New  = [byte[]](0x76,0x6F,0x63,0xC3,0xAA)          # "voce-circ"
    },
    @{
        Nome = 'Politica (mojibake -> correto)'
        Old  = [byte[]](0x50,0x6F,0x6C,0xC3,0x83,0xC2,0xAD,0x74,0x69,0x63,0x61)
        New  = [byte[]](0x50,0x6F,0x6C,0xC3,0xAD,0x74,0x69,0x63,0x61)
    }
)

function Test-Match([byte[]]$hay, [int]$i, [byte[]]$needle) {
    if ($i + $needle.Length -gt $hay.Length) { return $false }
    for ($j = 0; $j -lt $needle.Length; $j++) {
        if ($hay[$i + $j] -ne $needle[$j]) { return $false }
    }
    return $true
}

function Apply-Subs([byte[]]$raw) {
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

$totalGeral = 0

foreach ($alvo in $alvos) {
    $target = Join-Path $ProjectRoot $alvo.Rel
    if (-not (Test-Path $target)) {
        Write-Fail "Arquivo nao existe: $target"
        continue
    }

    Write-Step "Alvo: $target"

    $raw = [System.IO.File]::ReadAllBytes($target)
    $result = Apply-Subs $raw

    if ($result.Applied -eq 0) {
        Write-Skip "Nenhuma ocorrencia encontrada. Nada a fazer."
        continue
    }

    Write-Step "Substituicoes: $($result.Applied) | bytes antes: $($raw.Length) | depois: $($result.Bytes.Length)"

    $ts      = Get-Date -Format 'yyyyMMdd-HHmmss'
    $bakPath = "$target.bak-$ts"
    Copy-Item -LiteralPath $target -Destination $bakPath -Force
    Write-Step "Backup: $bakPath"

    try {
        [System.IO.File]::WriteAllBytes($target, $result.Bytes)
    } catch {
        Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
        Copy-Item -LiteralPath $bakPath -Destination $target -Force
        continue
    }

    if ($alvo.EhPhp) {
        $lint = & $PhpExe -l $target 2>&1
        $lintExit = $LASTEXITCODE
        $lint | ForEach-Object { Write-Host "  $_" }
        if ($lintExit -ne 0) {
            Write-Fail "php -l falhou. Restaurando."
            Copy-Item -LiteralPath $bakPath -Destination $target -Force
            continue
        }
    }

    $checkBytes = [System.IO.File]::ReadAllBytes($target)
    $hasBom = ($checkBytes.Length -ge 3 -and $checkBytes[0] -eq 0xEF -and $checkBytes[1] -eq 0xBB -and $checkBytes[2] -eq 0xBF)
    if ($hasBom) {
        Write-Fail "BOM detectado! Restaurando."
        Copy-Item -LiteralPath $bakPath -Destination $target -Force
        continue
    }

    Write-Ok "Corrigido. Backup: $bakPath"
    $totalGeral += $result.Applied
}

Write-Host ""
if ($totalGeral -gt 0) {
    Write-Ok "Total de substituicoes: $totalGeral"
} else {
    Write-Skip "Nada a fazer em nenhum arquivo."
}
exit 0