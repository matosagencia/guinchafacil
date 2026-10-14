# fix-pagamento-linha-463.ps1
# Remove a linha orfa ->execute(...) apos o registrar() em Pagamento.php
# Idempotente. Backup antes.

$ErrorActionPreference = 'Stop'

$arquivo = 'src\Models\Pagamento.php'
$path = (Resolve-Path $arquivo).Path
$linhas = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)

# Localiza a linha orfa: comeca com whitespace + ->execute([':pedido_id'
$idx = -1
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($linhas[$i] -match "^\s*->execute\(\[?:pedido_id") {
        $idx = $i
        break
    }
}

if ($idx -lt 0) {
    Write-Host "Nada a fazer - linha orfa nao encontrada (ja corrigido?)"
    exit 0
}

# Confirma que as 3 linhas acima contem 'PedidoEventoService::registrar'
$trecho = ($linhas[($idx-6)..($idx-1)] -join "`n")
if ($trecho -notmatch 'PedidoEventoService::registrar') {
    Write-Host "ABORTA - linha $($idx+1) nao parece ser a orfa esperada."
    Write-Host "Contexto:"
    ($idx-6)..($idx-1) | ForEach-Object { "L{0}: {1}" -f ($_+1), $linhas[$_] }
    exit 1
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
Copy-Item $path "$path.bak-fix463-$stamp" -Force
Write-Host "Backup: $path.bak-fix463-$stamp"

$out = New-Object System.Collections.Generic.List[string]
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($i -eq $idx) {
        Write-Host "Removendo linha $($i+1): $($linhas[$i].Trim())"
        continue
    }
    $out.Add($linhas[$i])
}

$utf8SemBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($path, $out, $utf8SemBom)
Write-Host "OK - linha removida."