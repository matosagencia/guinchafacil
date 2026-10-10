# fix-lanes-json-d10.ps1
# Move os 4 arquivos sem faixa para a chave "B" do doc/lanes.json.
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$lanes = Join-Path $Repositorio 'doc\lanes.json'
if (-not (Test-Path -LiteralPath $lanes)) {
    Write-Host "[ERRO] lanes.json nao encontrado em $lanes" -ForegroundColor Red
    exit 1
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$bak = "$lanes.bak-fix-lanes-d10-$stamp"
Copy-Item -LiteralPath $lanes -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$json = Get-Content -LiteralPath $lanes -Raw -Encoding UTF8

# 1. Remove os 4 arquivos da chave _sem_faixa_definida
$semFaixaAntigo = @'
  "_sem_faixa_definida": [
    "src/Models/Configuracao.php",
    "src/Services/Address/*",
    "src/Services/PreCotacao/PreCotacaoOpcoesService.php",
    "src/Services/CoberturaService.php"
  ]
'@

$semFaixaNovo = @'
  "_sem_faixa_definida": []
'@

if ($json.Contains($semFaixaAntigo)) {
    $json = $json.Replace($semFaixaAntigo, $semFaixaNovo)
    Write-Host "[LANES] _sem_faixa_definida esvaziada." -ForegroundColor Green
} else {
    Write-Host "[LANES] AVISO: bloco _sem_faixa_definida nao encontrado exatamente." -ForegroundColor Yellow
    Write-Host "       Verifica manualmente se os 4 arquivos estao la." -ForegroundColor Yellow
    Copy-Item -LiteralPath $bak -Destination $lanes -Force
    exit 1
}

# 2. Adiciona os 4 arquivos na chave "B"
# Procura o fechamento da chave "B" (ultima entrada antes de "C")
$marcador = @'
    "docs/lanes/B.md",
    "docs/BLOCKERS.md"
  ],
  "C": [
'@

$novoMarcador = @'
    "docs/lanes/B.md",
    "docs/BLOCKERS.md",
    "src/Models/Configuracao.php",
    "src/Services/Address/*",
    "src/Services/PreCotacao/PreCotacaoOpcoesService.php",
    "src/Services/CoberturaService.php"
  ],
  "C": [
'@

if ($json.Contains($marcador)) {
    $json = $json.Replace($marcador, $novoMarcador)
    Write-Host "[LANES] 4 arquivos adicionados na chave B." -ForegroundColor Green
} else {
    Write-Host "[LANES] AVISO: marcador de fechamento da chave B nao encontrado." -ForegroundColor Yellow
    Write-Host "       Verifica manualmente o formato do lanes.json." -ForegroundColor Yellow
    Copy-Item -LiteralPath $bak -Destination $lanes -Force
    exit 1
}

# 3. Grava
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($lanes, $json, $utf8NoBom)
Write-Host "[WRITE] $lanes" -ForegroundColor Green

# 4. Valida JSON
try {
    $test = Get-Content -LiteralPath $lanes -Raw -Encoding UTF8 | ConvertFrom-Json
    Write-Host "[OK] JSON valido." -ForegroundColor Green
} catch {
    Write-Host "[ERRO] JSON invalido: $($_.Exception.Message)" -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $lanes -Force
    exit 1
}

Write-Host ''
Write-Host '[DONE] lanes.json atualizado.' -ForegroundColor Green
Write-Host "Para reverter: Copy-Item '$bak' '$lanes' -Force"