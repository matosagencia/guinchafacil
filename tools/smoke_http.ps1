# smoke_http.ps1 — testa HTTP de cada rota registrada
# Uso: .\tools\smoke_http.ps1
# Requer: XAMPP rodando em http://localhost:8080/

$ErrorActionPreference = 'Continue'
$raiz = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$baseUrl = "http://localhost:8080"
$outDir = Join-Path $raiz 'storage\audit'
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Force -Path $outDir | Out-Null }

# Lê o route_audit.json para pegar as rotas
$auditPath = Join-Path $outDir 'route_audit.json'
if (-not (Test-Path $auditPath)) {
    Write-Error "Rode primeiro: php tools\route_audit.php"
    exit 1
}
$audit = Get-Content $auditPath -Raw | ConvertFrom-Json

# Rotas GET conhecidas (vêm do índice, ele já pegou todas)
$rotasGet = @(
    '/', '/login', '/pre-cotacao', '/parceiros',
    '/admin/dashboard', '/admin/pedidos', '/admin/central',
    '/admin/financeiro', '/admin/faturas', '/admin/prestadores',
    '/admin/guinchos', '/admin/usuarios', '/admin/configuracoes',
    '/admin/catalogo-servicos/tipos',
    '/cliente/dashboard', '/cliente/veiculos', '/cliente/historico',
    '/guincho/dashboard', '/guincho/pedidos', '/guincho/financeiro',
    '/oficina/dashboard', '/oficina/pedidos'
)

Write-Host "=== smoke_http ===" -ForegroundColor Cyan
Write-Host "Base URL: $baseUrl"
Write-Host ""

$resultado = @()
$agora = Get-Date

foreach ($rota in $rotasGet) {
    $url = $baseUrl + $rota
    $inicio = Get-Date
    try {
        $statusText = (& curl.exe -sS -o NUL -w "%{http_code}" --max-time 10 $url 2>$null)
        $status = [int](($statusText -join '').Trim())
        $erro = $null
    } catch {
        $status = 0
        $erro = $_.Exception.Message
    }
    $ms = [int]((Get-Date) - $inicio).TotalMilliseconds

    $cor = switch ($status) {
        { $_ -ge 200 -and $_ -lt 300 } { 'Green' }
        { $_ -ge 300 -and $_ -lt 400 } { 'Yellow' }
        { $_ -eq 401 -or $_ -eq 403 } { 'Yellow' }
        404 { 'Red' }
        default { 'Red' }
    }
    Write-Host ("[{0,3}] {1,4}ms {2}" -f $status, $ms, $rota) -ForegroundColor $cor

    $resultado += [PSCustomObject]@{
        rota = $rota
        status = $status
        ms = $ms
        erro = $erro
    }
}

$saida = [PSCustomObject]@{
    gerado_em = $agora.ToString('yyyy-MM-ddTHH:mm:ssK')
    base_url = $baseUrl
    total = $resultado.Count
    falhas = ($resultado | Where-Object { $_.status -ge 400 -or $_.status -eq 0 }).Count
    resultado = $resultado
}
$saida | ConvertTo-Json -Depth 5 | Set-Content -Path (Join-Path $outDir 'last_smoke.json') -Encoding UTF8

Write-Host ""
Write-Host ("Falhas: " + $saida.falhas + " / " + $saida.total) -ForegroundColor $(if ($saida.falhas -eq 0) { 'Green' } else { 'Red' })
Write-Host "JSON: storage\audit\last_smoke.json"
