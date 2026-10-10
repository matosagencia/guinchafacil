# tools/fix-index-rotas-registro-v2.ps1
# Formato real: 'path' => ['AuthController', 'metodo', null].
# ASCII-only. Arquivo compartilhado (dono decide rodar).

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$Repositorio = (Resolve-Path -LiteralPath $Repositorio).Path
$arquivo  = Join-Path $Repositorio 'index.php'
$authPath = Join-Path $Repositorio 'src\Controllers\AuthController.php'

if (-not (Test-Path -LiteralPath $arquivo))  { Write-Host "[ERRO] index.php nao existe." -ForegroundColor Red; exit 1 }
if (-not (Test-Path -LiteralPath $authPath)) { Write-Host "[ERRO] AuthController.php nao existe." -ForegroundColor Red; exit 1 }

# Preflight: metodos existem?
$authTexto = [System.IO.File]::ReadAllText($authPath, [System.Text.Encoding]::UTF8)
$faltando = @()
foreach ($m in 'registroOficinaForm','registroOficinaSave','registroClienteSimplesForm','registroGuinchoSimplesForm') {
    if (-not $authTexto.Contains($m)) { $faltando += $m }
}
if ($faltando.Count -gt 0) {
    Write-Host "[ERRO] Metodos ausentes no AuthController: $($faltando -join ', ')" -ForegroundColor Red
    exit 1
}
Write-Host "[OK] Metodos do AuthController conferidos." -ForegroundColor Green

# Idempotencia
$textoAtual = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)
if ($textoAtual -match "'/registro/oficina'\s*=>") {
    Write-Host "[SKIP] /registro/oficina ja registrado." -ForegroundColor Cyan
    exit 0
}

# Backup
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bak = "$arquivo.bak-rotas-oficina-$stamp"
Copy-Item -LiteralPath $arquivo -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

# Processa linha a linha
$linhas = $textoAtual -split "`r?`n", -1
$out = New-Object System.Collections.Generic.List[string]
$trocasGet = 0
$insGet = 0
$insPost = 0

foreach ($linha in $linhas) {
    $linhaNova = $linha

    # 1) GET /registro/cliente -> metodo simples
    if ($linha -match "'/registro/cliente'\s*=>\s*\[\s*'AuthController'\s*,\s*'registroClienteForm'") {
        $linhaNova = $linha -replace "'registroClienteForm'", "'registroClienteSimplesForm'"
        $trocasGet++
    }
    # 2) GET /registro/guincho -> metodo simples
    if ($linha -match "'/registro/guincho'\s*=>\s*\[\s*'AuthController'\s*,\s*'registroGuinchoForm'") {
        $linhaNova = $linha -replace "'registroGuinchoForm'", "'registroGuinchoSimplesForm'"
        $trocasGet++
    }

    [void]$out.Add($linhaNova)

    # 3) Insere /registro/oficina logo apos o GET /registro/guincho
    if ($linhaNova -match "'/registro/guincho'\s*=>\s*\[\s*'AuthController'\s*,\s*'registroGuinchoSimplesForm'") {
        $indent = [regex]::Match($linhaNova, '^([ \t]*)').Groups[1].Value
        [void]$out.Add("${indent}'/registro/oficina'  => ['AuthController', 'registroOficinaForm', null],")
        [void]$out.Add("${indent}'/registro/oficinas' => ['AuthController', 'registroOficinaForm', null],")
        $insGet += 2
    }

    # 4) Insere /registro/oficina logo apos o POST /registro/guincho (Save)
    if ($linhaNova -match "'/registro/guincho'\s*=>\s*\[\s*'AuthController'\s*,\s*'registroGuinchoSimplesSave'") {
        $indent = [regex]::Match($linhaNova, '^([ \t]*)').Groups[1].Value
        [void]$out.Add("${indent}'/registro/oficina'  => ['AuthController', 'registroOficinaSave', null],")
        [void]$out.Add("${indent}'/registro/oficinas' => ['AuthController', 'registroOficinaSave', null],")
        $insPost += 2
    }
}

Write-Host ("[FIX] {0} GET(s) redirecionado(s) para metodos simples." -f $trocasGet) -ForegroundColor Green
Write-Host ("[INS] {0} linha(s) GET inserida(s)." -f $insGet) -ForegroundColor Green
Write-Host ("[INS] {0} linha(s) POST inserida(s)." -f $insPost) -ForegroundColor Green

if ($trocasGet -eq 0 -and $insGet -eq 0 -and $insPost -eq 0) {
    Write-Host "[ERRO] Nada foi alterado. Envie este comando e cole a saida:" -ForegroundColor Red
    Write-Host "       Select-String -Path index.php -Pattern '/registro/guincho','/registro/cliente'" -ForegroundColor Yellow
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}

$texto = $out -join "`r`n"
[System.IO.File]::WriteAllText($arquivo, $texto, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}
Write-Host ""
Write-Host "[DONE] index.php atualizado." -ForegroundColor Green
Write-Host "Backup: $bak" -ForegroundColor Yellow
Write-Host "Reverter: Copy-Item '$bak' '$arquivo' -Force"