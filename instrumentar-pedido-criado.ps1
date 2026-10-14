# instrumentar-pedido-criado.ps1
# Insere chamada a PedidoEventoService::registrar('pedido_criado')
# em PedidoService::create(), logo apos $this->pedidoModel->criar($dados).
# Idempotente, com backup, grava UTF-8 sem BOM.

$ErrorActionPreference = 'Stop'

$raiz    = Split-Path -Parent $MyInvocation.MyCommand.Path
$arquivo = Join-Path $raiz 'src\Services\PedidoService.php'

if (-not (Test-Path $arquivo)) {
    Write-Error "Arquivo não encontrado: $arquivo"
    exit 1
}

$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$arquivo.bak-$stamp"
Copy-Item $arquivo $backup
Write-Host "Backup: $backup"

$texto = [System.IO.File]::ReadAllText($arquivo, [System.Text.Encoding]::UTF8)

if ($texto -match "PedidoEventoService::registrar") {
    Write-Warning "Ja instrumentado (encontrado 'PedidoEventoService::registrar'). Nada foi alterado."
    exit 0
}

$antigo = @'
        return (int)$this->pedidoModel->criar($dados);
'@

$novo = @'
        $id = (int)$this->pedidoModel->criar($dados);
        if ($id > 0) {
            require_once __DIR__ . '/PedidoEventoService.php';
            PedidoEventoService::registrar(
                getPDO(), $id, 'pedido_criado',
                null, (string)($dados['status'] ?? ''),
                'cliente', (int)($dados['cliente_id'] ?? 0)
            );
        }
        return $id;
'@

# Normaliza quebras de linha para LF (compara e substitui em LF, converte de volta)
$texto  = $texto  -replace "`r`n", "`n"
$antigo = $antigo -replace "`r`n", "`n"
$novo   = $novo   -replace "`r`n", "`n"

if ($texto -notmatch [regex]::Escape($antigo)) {
    Write-Warning "Padrao 'return (int)`$this->pedidoModel->criar(`$dados);' nao encontrado. Nada foi alterado."
    Write-Host "Verifique manualmente o metodo create() em $arquivo."
    exit 1
}

$texto = $texto.Replace($antigo, $novo)
$texto = $texto -replace "`n", "`r`n"

$utf8SemBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($arquivo, $texto, $utf8SemBom)

Write-Host "OK - PedidoService.php instrumentado com pedido_criado."
Write-Host "Verifique com: Get-Content src\Services\PedidoService.php | Select-String 'pedido_criado'"