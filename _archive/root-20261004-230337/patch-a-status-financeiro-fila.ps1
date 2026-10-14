[CmdletBinding()]
param(
    [Parameter()]
    [string]$AppRoot = (Get-Location).Path
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$phpExe = 'C:\xampp\php\php.exe'
$pagamentoPath = Join-Path $AppRoot 'src\Models\Pagamento.php'
$lanePath = Join-Path $AppRoot 'doc\lanes\A.md'
$marker = 'FINANCIAL-FILA-01'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$backupDir = Join-Path $AppRoot ('.patch-backup-a-status-financeiro-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
$backupPagamento = Join-Path $backupDir 'Pagamento.php'
$backupLane = Join-Path $backupDir 'A.md'

function Assert-File([string]$Path, [string]$Label) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "$Label nao encontrado: $Path"
    }
}

function Test-PhpSyntax([string]$Path) {
    & $phpExe -l $Path
    if ($LASTEXITCODE -ne 0) {
        throw "php -l falhou: $Path"
    }
}

function Restore-Backups {
    if (Test-Path -LiteralPath $backupPagamento) {
        Copy-Item -LiteralPath $backupPagamento -Destination $pagamentoPath -Force
    }
    if (Test-Path -LiteralPath $backupLane) {
        Copy-Item -LiteralPath $backupLane -Destination $lanePath -Force
    }
}

try {
    Assert-File $phpExe 'PHP do XAMPP'
    Assert-File $pagamentoPath 'src/Models/Pagamento.php'
    Assert-File $lanePath 'doc/lanes/A.md'
    Test-PhpSyntax $pagamentoPath

    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
    Copy-Item -LiteralPath $pagamentoPath -Destination $backupPagamento -Force
    Copy-Item -LiteralPath $lanePath -Destination $backupLane -Force

    $source = [System.IO.File]::ReadAllText($pagamentoPath)
    $newline = if ($source.Contains("`r`n")) { "`r`n" } else { "`n" }
    $changed = $false

    if (-not $source.Contains($marker)) {
        # Aceita variacoes de espacos e quebras de linha existentes no arquivo local.
        $eventPattern = '(?ms)\$eventos\s*=\s*\[.*?^\s*\];(?=\s*if\s*\(\s*!isset\(\$eventos\[\$destino\]\)\s*\))'
        $eventMatches = [System.Text.RegularExpressions.Regex]::Matches($source, $eventPattern)
        if ($eventMatches.Count -ne 1) {
            throw "Ancora de destinos financeiros encontrada $($eventMatches.Count) vezes; esperado 1. Nenhuma alteracao foi aplicada."
        }
        $eventAdd = $newline +
            '        // §FINANCIAL-FILA-01: somente destinos que dispensam a aprovacao online liberam a fila.' + $newline +
            '        // O UPDATE abaixo so promove aguardando_pagamento; nunca regride ou reabre outro status.' + $newline +
            '        $statusDestinoFila = in_array($destino, [''pago_agora'', ''pago_na_chegada'', ''sem_cobranca''], true)' + $newline +
            '            ? ''aguardando_guincho''' + $newline +
            '            : null;'
        $eventMatch = $eventMatches[0]
        $source = $source.Substring(0, $eventMatch.Index) + $eventMatch.Value + $eventAdd + $source.Substring($eventMatch.Index + $eventMatch.Length)

        $oldSelect = '$stmt = $pdo->prepare(''SELECT id, custo_estimado, forma_pagamento_escolhida, provedor_pagamento_escolhido, pagamento_destino_evento FROM pedidos WHERE id = :id'' . $lock);'
        $newSelect = '$stmt = $pdo->prepare(''SELECT id, status, custo_estimado, forma_pagamento_escolhida, provedor_pagamento_escolhido, pagamento_destino_evento FROM pedidos WHERE id = :id'' . $lock);'
        if ($source.IndexOf($oldSelect, [System.StringComparison]::Ordinal) -lt 0) {
            throw 'Ancora SELECT do pedido nao encontrada. Nenhuma alteracao foi aplicada.'
        }
        if ($source.IndexOf($oldSelect, [System.StringComparison]::Ordinal) -ne $source.LastIndexOf($oldSelect, [System.StringComparison]::Ordinal)) {
            throw 'Ancora SELECT do pedido nao e unica. Nenhuma alteracao foi aplicada.'
        }
        $source = $source.Replace($oldSelect, $newSelect)

        $oldIdempotent = '                if ($escolhaExistente === $destino && $provedorExistente === (string)($provedor ?? '''')) {' + $newline +
            '                    if ($started) {'
        $newIdempotent = '                if ($escolhaExistente === $destino && $provedorExistente === (string)($provedor ?? '''')) {' + $newline +
            '                    // Corrige registros criados antes desta regra sem duplicar o evento financeiro.' + $newline +
            '                    if ($statusDestinoFila !== null) {' + $newline +
            '                        $stmtStatus = $pdo->prepare("UPDATE pedidos SET status = :status_destino WHERE id = :id AND status = ''aguardando_pagamento''");' + $newline +
            '                        $stmtStatus->execute(['':status_destino'' => $statusDestinoFila, '':id'' => $pedidoId]);' + $newline +
            '                    }' + $newline +
            '                    if ($started) {'
        if ($source.IndexOf($oldIdempotent, [System.StringComparison]::Ordinal) -lt 0) {
            throw 'Ancora idempotente nao encontrada. Nenhuma alteracao foi aplicada.'
        }
        if ($source.IndexOf($oldIdempotent, [System.StringComparison]::Ordinal) -ne $source.LastIndexOf($oldIdempotent, [System.StringComparison]::Ordinal)) {
            throw 'Ancora idempotente nao e unica. Nenhuma alteracao foi aplicada.'
        }
        $source = $source.Replace($oldIdempotent, $newIdempotent)

        $oldUpdate = '$pdo->prepare(''UPDATE pedidos SET forma_pagamento_escolhida = :destino, provedor_pagamento_escolhido = :provedor, pagamento_destino_admin_id = :admin_id, pagamento_destino_registrado_em = NOW(), pagamento_destino_evento = :evento WHERE id = :id'')' + $newline +
            '                ->execute(['':destino'' => $destino, '':provedor'' => $provedor, '':admin_id'' => $adminId, '':evento'' => $evento, '':id'' => $pedidoId]);'
        $newUpdate = '$setsPedido = ''forma_pagamento_escolhida = :destino, provedor_pagamento_escolhido = :provedor, pagamento_destino_admin_id = :admin_id, pagamento_destino_registrado_em = NOW(), pagamento_destino_evento = :evento'';' + $newline +
            '$paramsPedido = ['':destino'' => $destino, '':provedor'' => $provedor, '':admin_id'' => $adminId, '':evento'' => $evento, '':id'' => $pedidoId];' + $newline +
            '            if ($statusDestinoFila !== null) {' + $newline +
            '                $setsPedido .= '', status = CASE WHEN status = ''''aguardando_pagamento'''' THEN :status_destino ELSE status END'';' + $newline +
            '                $paramsPedido['':status_destino''] = $statusDestinoFila;' + $newline +
            '            }' + $newline +
            '            $pdo->prepare(''UPDATE pedidos SET '' . $setsPedido . '' WHERE id = :id'')->execute($paramsPedido);'
        if ($source.IndexOf($oldUpdate, [System.StringComparison]::Ordinal) -lt 0) {
            throw 'Ancora UPDATE financeiro nao encontrada. Nenhuma alteracao foi aplicada.'
        }
        if ($source.IndexOf($oldUpdate, [System.StringComparison]::Ordinal) -ne $source.LastIndexOf($oldUpdate, [System.StringComparison]::Ordinal)) {
            throw 'Ancora UPDATE financeiro nao e unica. Nenhuma alteracao foi aplicada.'
        }
        $source = $source.Replace($oldUpdate, $newUpdate)

        foreach ($required in @($marker, 'statusDestinoFila', 'SELECT id, status, custo_estimado', 'status = CASE WHEN status = ''aguardando_pagamento'' THEN :status_destino ELSE status END')) {
            if ($source.IndexOf($required, [System.StringComparison]::Ordinal) -lt 0) {
                throw "Verificacao pos-patch falhou: $required"
            }
        }

        [System.IO.File]::WriteAllText($pagamentoPath, $source, $utf8NoBom)
        $changed = $true
        Test-PhpSyntax $pagamentoPath
    }

    $lane = [System.IO.File]::ReadAllText($lanePath)
    if (-not $lane.Contains($marker)) {
        $laneAppend = $newline + 'IMPLEMENTACAO_' + $marker + ':' + $newline +
            '  estado: entregue_para_teste_C' + $newline +
            '  arquivo: src/Models/Pagamento.php' + $newline +
            '  regra: pago_agora, pago_na_chegada e sem_cobranca promovem somente aguardando_pagamento para aguardando_guincho; online permanece aguardando_pagamento.' + $newline +
            '  idempotencia: chamada repetida nao cria evento financeiro adicional e pode promover registro legado ainda aguardando_pagamento.' + $newline +
            '  aceite_C:' + $newline +
            '    - pago_agora, pago_na_chegada e sem_cobranca entram na fila do guincho.' + $newline +
            '    - online permanece aguardando_pagamento ate aprovacao.' + $newline +
            '    - repetir o mesmo destino nao duplica pedido_eventos nem regride status.' + $newline
        [System.IO.File]::WriteAllText($lanePath, $lane + $laneAppend, $utf8NoBom)
    }

    if ($changed) {
        Write-Host 'OK: FINANCIAL-FILA-01 aplicado.' -ForegroundColor Green
    } else {
        Write-Host 'OK: FINANCIAL-FILA-01 ja estava aplicado; documentacao conferida.' -ForegroundColor Green
    }
    Write-Host "Backup: $backupDir" -ForegroundColor DarkGray
    exit 0
}
catch {
    $failure = $_.Exception.Message
    try {
        Restore-Backups
        if (Test-Path -LiteralPath $pagamentoPath -PathType Leaf) {
            & $phpExe -l $pagamentoPath | Out-Host
        }
    }
    catch {
        Write-Host "ERRO no rollback: $($_.Exception.Message)" -ForegroundColor Red
    }
    Write-Host "ERRO: $failure" -ForegroundColor Red
    exit 1
}
