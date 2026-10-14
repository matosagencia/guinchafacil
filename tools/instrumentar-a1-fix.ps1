# instrumentar-a1-fix.ps1
# Corrige os 3 patches que falharam por escape de apostrofo.
# Idempotente. Backup por arquivo.

$ErrorActionPreference = 'Stop'
$script:log = @()

function Add-Linhas {
    param(
        [string]$Arquivo,
        [string]$Padrao,
        [ValidateSet('Antes','Depois')][string]$Modo,
        [int]$Offset = 0,
        [string[]]$Linhas,
        [string]$Nome
    )

    if (-not (Test-Path $Arquivo)) {
        $script:log += "SKIP  $Nome"; return
    }
    $path = (Resolve-Path $Arquivo).Path
    $todas = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)

    $idx = -1
    for ($i = 0; $i -lt $todas.Count; $i++) {
        if ($todas[$i] -match $Padrao) { $idx = $i; break }
    }
    if ($idx -lt 0) { $script:log += "FAIL  $Nome - padrao nao encontrado"; return }

    $idx = $idx + $Offset

    $ini = [Math]::Max(0, $idx - 15)
    $fim = [Math]::Min($todas.Count - 1, $idx + 15)
    if (($todas[$ini..$fim] -join "`n") -match [regex]::Escape('PedidoEventoService::registrar')) {
        $script:log += "OK    $Nome - ja aplicado"; return
    }

    $indent = ([regex]::Match($todas[$idx], '^\s*')).Value
    $novas = @($Linhas | ForEach-Object { $indent + $_ })

    $out = New-Object System.Collections.Generic.List[string]
    if ($Modo -eq 'Antes') {
        for ($i = 0; $i -lt $idx; $i++) { $out.Add($todas[$i]) }
        foreach ($n in $novas) { $out.Add($n) }
        for ($i = $idx; $i -lt $todas.Count; $i++) { $out.Add($todas[$i]) }
    } else {
        for ($i = 0; $i -le $idx; $i++) { $out.Add($todas[$i]) }
        foreach ($n in $novas) { $out.Add($n) }
        for ($i = $idx + 1; $i -lt $todas.Count; $i++) { $out.Add($todas[$i]) }
    }

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    Copy-Item $path "$path.bak-a1fix-$stamp" -Force

    $utf8SemBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllLines($path, $out, $utf8SemBom)
    $script:log += "PATCH $Nome"
}

# ----- Corrigido: regex com um apostrofo apenas -----

# 5. ClienteController L1651 — orcamento aprovado
Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  "orcamento_aprovado' WHERE id = \?" `
    -Modo    'Depois' `
    -Offset  1 `
    -Linhas  @(
        "require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        'PedidoEventoService::registrar(',
        '    $pdo, $pedidoId, ''orcamento_aprovado'',',
        '    null, ''orcamento_aprovado'', ''cliente'', (int)($uid ?? 0),',
        '    ''aprovacao via /cliente/orcamento/decidir''',
        ');'
    ) `
    -Nome 'Cliente:orc-aprov'

# 6. ClienteController L1670 — conversao reboque pendente
Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  "conversao_reboque_pendente' WHERE id = \?" `
    -Modo    'Depois' `
    -Offset  1 `
    -Linhas  @(
        "require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        'PedidoEventoService::registrar(',
        '    $pdo, $pedidoId, ''conversao_reboque_pendente'',',
        '    null, ''conversao_reboque_pendente'', ''cliente'', (int)($uid ?? 0),',
        '    ''aguardando confirmacao do cliente''',
        ');'
    ) `
    -Nome 'Cliente:conv-pend'

# 8. AdminController L1754 — aguardando_guincho
Add-Linhas `
    -Arquivo 'src\Controllers\AdminController.php' `
    -Padrao  "UPDATE pedidos SET status = 'aguardando_guincho' WHERE id = \?" `
    -Modo    'Depois' `
    -Offset  1 `
    -Linhas  @(
        "require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        'PedidoEventoService::registrar(',
        '    $pdo, $pedidoId, ''pedido_criado_admin_aguardando_guincho'',',
        '    null, ''aguardando_guincho'', ''admin'',',
        '    (int)($actorId ?? 0), null',
        ');'
    ) `
    -Nome 'Admin:aguardando'

Write-Host "`n=== Relatorio fix ==="
$script:log | ForEach-Object { Write-Host $_ }
$p = ($script:log | Where-Object { $_ -like 'PATCH*' }).Count
$o = ($script:log | Where-Object { $_ -like 'OK*'    }).Count
$f = ($script:log | Where-Object { $_ -like 'FAIL*'  }).Count
Write-Host "`nAplicados: $p | Ja aplicados: $o | Falharam: $f"