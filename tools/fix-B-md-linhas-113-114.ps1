# fix-B-md-linhas-113-114.ps1 -- 100% ASCII, PowerShell 5.1 safe
# Corrige mojibake residual e $bp perdido nas linhas 113-114 de doc/lanes/B.md
# Autorizado pelo dono em 2026-10-10.

[CmdletBinding()]
param(
    [string]$ProjectRoot  = 'C:\xampp\htdocs\guinchafacil',
    [string]$RelativeFile = 'doc\lanes\B.md'
)

$ErrorActionPreference = 'Stop'

function Write-Step([string]$m) { Write-Host "[fix-B-md] $m" -ForegroundColor Cyan }
function Write-Ok  ([string]$m) { Write-Host "[fix-B-md][OK] $m" -ForegroundColor Green }
function Write-Skip([string]$m) { Write-Host "[fix-B-md][SKIP] $m" -ForegroundColor Yellow }
function Write-Fail([string]$m) { Write-Host "[fix-B-md][FAIL] $m" -ForegroundColor Red }

if (-not (Test-Path $ProjectRoot)) { Write-Fail "ProjectRoot invalido"; exit 1 }

$target = Join-Path $ProjectRoot $RelativeFile
if (-not (Test-Path $target)) { Write-Fail "Arquivo alvo nao existe: $target"; exit 1 }

Write-Step "Alvo: $target"

$rawBytes  = [System.IO.File]::ReadAllBytes($target)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false, $true)
try {
    $content = $utf8NoBom.GetString($rawBytes)
} catch {
    Write-Fail "Arquivo nao e UTF-8 valido: $($_.Exception.Message)"
    exit 1
}

# --- 4 substituicoes por BYTES (evita parser PS 5.1) ---
# Padrao: cada sub e' [Old byte[], New byte[]]
# UTF-8 de "a-til" = C3 A3 ; UTF-8 de "a-agudo" = C3 A1

$subs = @(
    # "jã estã" -> "já está"
    @{
        Nome = 'ja esta (a-til -> a-agudo)'
        # "j" + C3 A3 (a-til) + " est" + C3 A3 (a-til)
        Old  = [byte[]](0x6A,0xC3,0xA3,0x20,0x65,0x73,0x74,0xC3,0xA3)
        # "j" + C3 A1 (a-agudo) + " est" + C3 A1 (a-agudo)
        New  = [byte[]](0x6A,0xC3,0xA1,0x20,0x65,0x73,0x74,0xC3,0xA1)
    },
    # "usa `\` sem guard" -> "usa `$bp` sem guard"
    # No B.md atual: "usa `\`" (backtick barra backtick)
    # No B.md correto: "usa `$bp`" (backtick dollar b p backtick)
    @{
        Nome = 'bp perdido (usa)'
        Old  = [byte[]](0x75,0x73,0x61,0x20,0x60,0x5C,0x60)          # "usa `\`"
        New  = [byte[]](0x75,0x73,0x61,0x20,0x60,0x24,0x62,0x70,0x60) # "usa `$bp`"
    },
    # "define `\` antes" -> "define `$bp` antes"
    @{
        Nome = 'bp perdido (define)'
        Old  = [byte[]](0x64,0x65,0x66,0x69,0x6E,0x65,0x20,0x60,0x5C,0x60)
        New  = [byte[]](0x64,0x65,0x66,0x69,0x6E,0x65,0x20,0x60,0x24,0x62,0x70,0x60)
    }
)

# --- Conta ocorrencias ---
$totalSubs = 0
foreach ($s in $subs) {
    $count = 0
    for ($i = 0; $i -le $rawBytes.Length - $s.Old.Length; $i++) {
        $match = $true
        for ($j = 0; $j -lt $s.Old.Length; $j++) {
            if ($rawBytes[$i + $j] -ne $s.Old[$j]) { $match = $false; break }
        }
        if ($match) { $count++ }
    }
    Write-Step "  [$($s.Nome)] ocorrencias: $count"
    $totalSubs += $count
}

if ($totalSubs -eq 0) {
    Write-Skip "Nenhuma ocorrencia encontrada. Nada a fazer."
    exit 0
}

# --- Aplica substituicoes byte-a-byte ---
$newList = New-Object System.Collections.Generic.List[byte]
$i = 0
$applied = 0
while ($i -lt $rawBytes.Length) {
    $matched = $false
    foreach ($s in $subs) {
        if ($i -le $rawBytes.Length - $s.Old.Length) {
            $match = $true
            for ($j = 0; $j -lt $s.Old.Length; $j++) {
                if ($rawBytes[$i + $j] -ne $s.Old[$j]) { $match = $false; break }
            }
            if ($match) {
                foreach ($b in $s.New) { $newList.Add($b) }
                $i += $s.Old.Length
                $applied++
                $matched = $true
                break
            }
        }
    }
    if (-not $matched) {
        $newList.Add($rawBytes[$i])
        $i++
    }
}
$newBytes = $newList.ToArray()
Write-Step "Bytes antes: $($rawBytes.Length) | depois: $($newBytes.Length) | substituicoes: $applied"

# --- Backup ---
$ts      = Get-Date -Format 'yyyyMMdd-HHmmss'
$bakPath = "$target.bak-$ts"
Copy-Item -LiteralPath $target -Destination $bakPath -Force
Write-Step "Backup: $bakPath"

# --- Escrita ---
try {
    [System.IO.File]::WriteAllBytes($target, $newBytes)
    Write-Step "Escrito ($($newBytes.Length) bytes)."
} catch {
    Write-Fail "Falha ao escrever: $($_.Exception.Message). Restaurando..."
    Copy-Item -LiteralPath $bakPath -Destination $target -Force
    exit 1
}

# --- Verifica BOM ---
$checkBytes = [System.IO.File]::ReadAllBytes($target)
$hasBom = ($checkBytes.Length -ge 3 -and $checkBytes[0] -eq 0xEF -and $checkBytes[1] -eq 0xBB -and $checkBytes[2] -eq 0xBF)
if ($hasBom) {
    Write-Fail "BOM detectado! Restaurando."
    Copy-Item -LiteralPath $bakPath -Destination $target -Force
    exit 1
}

Write-Ok "B.md corrigido. Backup: $bakPath"
Write-Ok "Reverter: Copy-Item -LiteralPath '$bakPath' -Destination '$target' -Force"
exit 0