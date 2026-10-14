# instrumentar-a1.ps1
# Insere eventos em 9 pontos de transicao de status fora do
# PedidoTransitionService. Idempotente, backup por arquivo, UTF-8 sem BOM.

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
        $script:log += "SKIP  $Nome - $Arquivo nao existe"
        return
    }
    $path = (Resolve-Path $Arquivo).Path
    $todas = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)

    $idx = -1
    for ($i = 0; $i -lt $todas.Count; $i++) {
        if ($todas[$i] -match $Padrao) { $idx = $i; break }
    }
    if ($idx -lt 0) {
        $script:log += "FAIL  $Nome - padrao nao encontrado"
        return
    }

    $idx = $idx + $Offset
    if ($idx -lt 0 -or $idx -ge $todas.Count) {
        $script:log += "FAIL  $Nome - offset invalido"
        return
    }

    # Idempotencia: 15 linhas ao redor contem o registrar?
    $ini = [Math]::Max(0, $idx - 15)
    $fim = [Math]::Min($todas.Count - 1, $idx + 15)
    $trecho = ($todas[$ini..$fim] -join "`n")
    if ($trecho -match [regex]::Escape('PedidoEventoService::registrar')) {
        $script:log += "OK    $Nome - ja aplicado"
        return
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
    Copy-Item $path "$path.bak-a1-$stamp" -Force

    $utf8SemBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllLines($path, $out, $utf8SemBom)
    $script:log += "PATCH $Nome"
}

function Replace-Linha {
    param(
        [string]$Arquivo,
        [string]$Padrao,
        [string[]]$Novas,
        [string]$Nome
    )
    if (-not (Test-Path $Arquivo)) { $script:log += "SKIP  $Nome"; return }
    $path = (Resolve-Path $Arquivo).Path
    $todas = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)

    $idx = -1
    for ($i = 0; $i -lt $todas.Count; $i++) {
        if ($todas[$i] -match $Padrao) { $idx = $i; break }
    }
    if ($idx -lt 0) { $script:log += "FAIL  $Nome - padrao nao encontrado"; return }

    $indent = ([regex]::Match($todas[$idx], '^\s*')).Value
    $subst = @($Novas | ForEach-Object { $indent + $_ })

    $out = New-Object System.Collections.Generic.List[string]
    for ($i = 0; $i -lt $idx; $i++) { $out.Add($todas[$i]) }
    foreach ($s in $subst) { $out.Add($s) }
    for ($i = $idx + 1; $i -lt $todas.Count; $i++) { $out.Add($todas[$i]) }

    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    Copy-Item $path "$path.bak-a1-$stamp" -Force

    $utf8SemBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllLines($path, $out, $utf8SemBom)
    $script:log += "PATCH $Nome"
}

# ============================================================
# 1. OficinaController L128 — aceitar pedido
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\OficinaController.php' `
    -Padrao  'rowCount\(\) === 0' `
    -Modo    'Antes' `
    -Linhas  @(
        '$afetadosOf = $stmt->rowCount();',
        'if ($afetadosOf > 0) {',
        "    require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        '    PedidoEventoService::registrar(',
        '        $pdo, $id, ''oficina_aceitou_pedido'',',
        '        null, ''oficina_aceitou'', ''oficina'',',
        '        (int)$oficina[''id''], null',
        '    );',
        '}'
    ) `
    -Nome 'Oficina:aceitar'

# ============================================================
# 2. OficinaController L225 — atualizarStatus generico
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\OficinaController.php' `
    -Padrao  'getPDO\(\)->prepare\("UPDATE pedidos SET status = \? WHERE id = \?"\)' `
    -Modo    'Antes' `
    -Linhas  @(
        '$pdoOfSt = getPDO();',
        '$stOfSt = $pdoOfSt->prepare("SELECT status FROM pedidos WHERE id = ?");',
        '$stOfSt->execute([$id]);',
        '$statusAntesOf = (string)($stOfSt->fetchColumn() ?: '''');'
    ) `
    -Nome 'Oficina:status-snap'

Add-Linhas `
    -Arquivo 'src\Controllers\OficinaController.php' `
    -Padrao  'getPDO\(\)->prepare\("UPDATE pedidos SET status = \? WHERE id = \?"\)' `
    -Modo    'Depois' `
    -Offset  1 `
    -Linhas  @(
        "require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        'PedidoEventoService::registrar(',
        '    $pdoOfSt, $id, ''oficina_atualizou_status'',',
        '    $statusAntesOf, (string)$novo, ''oficina'',',
        '    (int)($_SESSION[''oficina_id''] ?? 0), null',
        ');'
    ) `
    -Nome 'Oficina:status-reg'

# ============================================================
# 3. OficinaController L654 — orcamento enviado
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\OficinaController.php' `
    -Padrao  "orcamento_enviado' WHERE id = \?" `
    -Modo    'Depois' `
    -Offset  1 `
    -Linhas  @(
        "require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        'PedidoEventoService::registrar(',
        '    $pdo, $pedidoId, ''orcamento_enviado'',',
        '    null, ''orcamento_enviado'', ''oficina'',',
        '    (int)($oficina[''id''] ?? 0), null',
        ');'
    ) `
    -Nome 'Oficina:orcamento'

# ============================================================
# 4. ClienteController L966-969 — reabre fila
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  'if \(\$pService->podeIniciarAtendimento\(\)\) \{' `
    -Modo    'Antes' `
    -Linhas  @('$reabriuFila = false;') `
    -Nome 'Cliente:reabre-flag'

Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  '\$sets\[\] = "status = ''aguardando_guincho''";' `
    -Modo    'Depois' `
    -Linhas  @('$reabriuFila = true;') `
    -Nome 'Cliente:reabre-set'

Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  'UPDATE pedidos SET '' \. implode' `
    -Modo    'Depois' `
    -Linhas  @(
        'if ($reabriuFila) {',
        "    require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        '    PedidoEventoService::registrar(',
        '        $pdo, $pedidoId, ''pedido_reaberto_fila'',',
        '        null, ''aguardando_guincho'', ''cliente'', (int)$uid,',
        '        ''podeIniciarAtendimento''',
        '    );',
        '}'
    ) `
    -Nome 'Cliente:reabre-reg'

# ============================================================
# 5. ClienteController L1651 — orcamento aprovado
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  "orcamento_aprovado'' WHERE id = \?" `
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

# ============================================================
# 6. ClienteController L1670 — conversao reboque pendente
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\ClienteController.php' `
    -Padrao  "conversao_reboque_pendente'' WHERE id = \?" `
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

# ============================================================
# 7. TowEscalationService L21 — fallback para guincho
# ============================================================
Add-Linhas `
    -Arquivo 'src\Services\TowEscalationService.php' `
    -Padrao  "SET status='aguardando_guincho'" `
    -Modo    'Antes' `
    -Linhas  @(
        '$stPedEsc = $pdo->prepare("SELECT id, status FROM pedidos WHERE incidente_id=? LIMIT 1");',
        '$stPedEsc->execute([$incidenteId]);',
        '$pedEsc = $stPedEsc->fetch(PDO::FETCH_ASSOC);'
    ) `
    -Nome 'Tow:snap'

Add-Linhas `
    -Arquivo 'src\Services\TowEscalationService.php' `
    -Padrao  "SET status='aguardando_guincho'" `
    -Modo    'Depois' `
    -Offset  1 `
    -Linhas  @(
        'if ($pedEsc) {',
        "    require_once __DIR__ . '/PedidoEventoService.php';",
        '    PedidoEventoService::registrar(',
        '        $pdo, (int)$pedEsc[''id''], ''pedido_escalado_para_reboque'',',
        '        (string)($pedEsc[''status''] ?? ''''), ''aguardando_guincho'',',
        '        ''system'', 0, ''incidente '' . $incidenteId . '' necessita_reboque''',
        '    );',
        '}'
    ) `
    -Nome 'Tow:reg'

# ============================================================
# 8. AdminController L1754 — aguardando_guincho
# ============================================================
Add-Linhas `
    -Arquivo 'src\Controllers\AdminController.php' `
    -Padrao  "UPDATE pedidos SET status = ''aguardando_guincho'' WHERE id = \?" `
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

# ============================================================
# 9. Pagamento L455 — substituir INSERT direto pelo service
# ============================================================
Replace-Linha `
    -Arquivo 'src\Models\Pagamento.php' `
    -Padrao  "INSERT INTO pedido_eventos \(pedido_id, evento, context_json\)" `
    -Novas   @(
        "require_once __DIR__ . '/../Services/PedidoEventoService.php';",
        'PedidoEventoService::registrar(',
        '    $pdo, $pedidoId, (string)$evento,',
        '    null, $statusDestinoFila,',
        '    ''admin'', $adminId,',
        '    ''destino financeiro: '' . $destino,',
        '    [''destino_pagamento'' => $destino, ''provedor_online'' => $provedor, ''pagamento_id'' => $pagamentoId]',
        ');'
    ) `
    -Nome 'Pagamento:aplicarDestino'

# ============================================================
Write-Host "`n=== Relatorio ==="
$script:log | ForEach-Object { Write-Host $_ }

$p = ($script:log | Where-Object { $_ -like 'PATCH*' }).Count
$o = ($script:log | Where-Object { $_ -like 'OK*'    }).Count
$f = ($script:log | Where-Object { $_ -like 'FAIL*'  }).Count
$s = ($script:log | Where-Object { $_ -like 'SKIP*'  }).Count
Write-Host "`nAplicados: $p | Ja aplicados: $o | Falharam: $f | Skipped: $s"