# tools/patch-authcontroller-oficina.ps1
# Adiciona wrappers registroSimples por URL limpa. ASCII-only. Faixa B.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$arquivo = Join-Path $Repositorio 'src\Controllers\AuthController.php'
if (-not (Test-Path -LiteralPath $arquivo)) { Write-Host "[ERRO] $arquivo"; exit 1 }

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bak = "$arquivo.bak-oficina-$stamp"
Copy-Item -LiteralPath $arquivo -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

if ($texto.Contains('public function registroOficinaForm(')) {
    Write-Host "[SKIP] wrappers ja existem." -ForegroundColor Cyan
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 0
}

if (-not $texto.Contains('public function registroSimplesForm(')) {
    Write-Host "[ERRO] registroSimplesForm nao existe. Rode patch-authcontroller-simples.ps1 antes." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}

$marker = '    // --- SIGNUP SIMPLIFICADO (3 campos) + PERFIL INTERNO ---------------'
$idx = $texto.IndexOf($marker)
if ($idx -lt 0) {
    Write-Host "[ERRO] marcador do bloco simples nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}

$novo = @'
    // --- WRAPPERS: URL limpa /registro/{tipo} --------------------------

    public function registroClienteSimplesForm(): void { $this->registroSimplesForm('cliente'); }
    public function registroClienteSimplesSave(): void { $this->registroSimples('cliente'); }

    public function registroGuinchoSimplesForm(): void { $this->registroSimplesForm('guincho'); }
    public function registroGuinchoSimplesSave(): void { $this->registroSimples('guincho'); }

    public function registroOficinaForm(): void { $this->registroSimplesForm('oficina'); }
    public function registroOficinaSave(): void { $this->registroSimples('oficina'); }

'@

$texto = $texto.Substring(0, $idx) + $novo + $texto.Substring($idx)

[System.IO.File]::WriteAllText($arquivo, $texto, $utf8NoBom)
Write-Host "[WRITE] $arquivo" -ForegroundColor Green

& 'C:\xampp\php\php.exe' -l $arquivo
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $arquivo -Force
    exit 1
}
Write-Host "[OK] AuthController atualizado." -ForegroundColor Green
Write-Host "Backup: $bak" -ForegroundColor Yellow