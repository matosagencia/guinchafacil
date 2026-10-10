# tools/fix-parceiros-guinchos-v2.ps1
# Hero verde/preto + redirect de /parceiros/guinchos para /registro/guincho.
# Faixa B. ASCII-only. Data: 2026-10-09.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$arquivo = Join-Path $Repositorio 'src\Views\public\parceiros\guinchos.php'
if (-not (Test-Path -LiteralPath $arquivo)) { Write-Host "[ERRO] $arquivo"; exit 1 }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bak = "$arquivo.bak-hero-verde-$stamp"
Copy-Item -LiteralPath $arquivo -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

# --- 1. CTA do topo: #interesse -> /registro/guincho ---
$antes = $texto.Length
$texto = $texto.Replace('href="#interesse"', 'href="<?= $e($bp) ?>/registro/guincho"')
Write-Host ("[CTA] {0} substituicoes." -f ($antes - $texto.Length)) -ForegroundColor Green

# --- 2. Secao #interesse vira CTA de cadastro ---
$secStart = '<section class="gf-section" id="interesse"'
$idxStart = $texto.IndexOf($secStart)
if ($idxStart -lt 0) {
    Write-Host "[ERRO] secao #interesse nao encontrada." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force; exit 1
}
$idxEnd = $texto.IndexOf('</section>', $idxStart)
if ($idxEnd -lt 0) {
    Write-Host "[ERRO] </section> de #interesse nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force; exit 1
}
$idxEnd += '</section>'.Length

$novoBloco = @'
<section class="gf-section" id="cadastro" aria-labelledby="cadastro-title"><p class="gf-eyebrow">Pr{{O_ACUTE}}ximo passo</p><h2 id="cadastro-title">Pronto para come{{C_CED}}ar?</h2><p class="gf-muted">Crie sua conta de guincho parceiro em segundos. Depois de entrar, voc{{E_ACUTE}} completa os dados do ve{{I_ACUTE}}culo e envia a documenta{{C_CED}}{{A_TILDE}}o.</p><a class="gf-cta" href="<?= $e($bp) ?>/registro/guincho"><span><b>Criar conta de guincho</b><small>Com Google ou e-mail</small></span><span class="gf-arrow">&rarr;</span></a></section>
'@

$subs = @{
    '{{O_ACUTE}}' = [string][char]0x00F3
    '{{E_ACUTE}}' = [string][char]0x00E9
    '{{I_ACUTE}}' = [string][char]0x00ED
    '{{C_CED}}'   = [string][char]0x00E7
    '{{A_TILDE}}' = [string][char]0x00E3
}
foreach ($k in $subs.Keys) { $novoBloco = $novoBloco.Replace($k, $subs[$k]) }

$texto = $texto.Substring(0, $idxStart) + $novoBloco + $texto.Substring($idxEnd)
Write-Host "[SECAO] #interesse virou CTA para /registro/guincho." -ForegroundColor Green

# --- 3. Hero verde/preto em degrade ---
$css = @'
<style>
.gf-hero{background:linear-gradient(135deg,#041a09 0%,#0a3d1a 45%,#000 100%)!important;color:#fff}
.gf-hero .gf-eyebrow{color:#7dff96!important}
.gf-hero h1{color:#fff!important}
.gf-hero h1 em{color:#2fb34a!important;font-style:normal}
.gf-hero .gf-lead{color:rgba(255,255,255,.78)!important}
.gf-hero .gf-cta{background:linear-gradient(135deg,#2fb34a,#1f8a36)!important;color:#fff!important}
</style>
'@
$idxHead = $texto.IndexOf('</head>')
if ($idxHead -lt 0) {
    Write-Host "[ERRO] </head> nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force; exit 1
}
$texto = $texto.Substring(0, $idxHead) + $css + $texto.Substring($idxHead)
Write-Host "[CSS] Hero verde/preto inserido." -ForegroundColor Green

# --- 4. Grava + valida ---
[System.IO.File]::WriteAllText($arquivo, $texto, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}
Write-Host ""
Write-Host "[DONE] guinchos.php atualizado." -ForegroundColor Green
Write-Host "Backup: $bak" -ForegroundColor Yellow
Write-Host "Reverter: Copy-Item '$bak' '$arquivo' -Force"