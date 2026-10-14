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
$validationPath = Join-Path $backupDir 'Pagamento.php.validacao.tmp'

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

function Replace-OneRegex([string]$Text, [string]$Pattern, [string]$Replacement, [string]$Label) {
    $matches = [System.Text.RegularExpressions.Regex]::Matches($Text, $Pattern)
    if ($matches.Count -ne 1) {
        throw "Ancora $Label encontrada $($matches.Count) vezes; esperado 1. Nenhuma alteracao foi aplicada."
    }
    $match = $matches[0]
    return $Text.Substring(0, $match.Index) + $Replacement + $Text.Substring($match.Index + $match.Length)
}

function Restore-Backups {
    if (Test-Path -LiteralPath $backupPagamento -PathType Leaf) {
        Copy-Item -LiteralPath $backupPagamento -Destination $pagamentoPath -Force
    }
    if (Test-Path -LiteralPath $backupLane -PathType Leaf) {
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
        $eventPattern = '(?ms)\$eventos\s*=\s*\[.*?^\s*\];(?=\s*if\s*\(\s*!isset\(\$eventos\[\$destino\]\)\s*\))'
        $eventMatches = [System.Text.RegularExpressions.Regex]::Matches($source, $eventPattern)
        if ($eventMatches.Count -ne 1) {
            throw "Ancora de destinos financeiros encontrada $($eventMatches.Count) vezes; esperado 1. Nenhuma alteracao foi aplicada."
        }
        $eventAdd = $newline +
            '        // FINANCIAL-FILA-01: somente destinos sem aprovacao online liberam a fila.' + $newline +
            '        $statusDestinoFila = in_array($destino, [''pago_agora'', ''pago_na_chegada'', ''sem_cobranca''], true)' + $newline +
            '            ? ''aguardando_guincho''' + $newline +
            '            : null;'
        $eventMatch = $eventMatches[0]
        $source = $source.Substring(0, $eventMatch.Index) + $eventMatch.Value + $eventAdd + $source.Substring($eventMatch.Index + $eventMatch.Length)

        $updatePattern = '(?s)\$pdo->prepare\(\s*''UPDATE\s+pedidos\s+SET\s+forma_pagamento_escolhida\s*=\s*:destino,\s*provedor_pagamento_escolhido\s*=\s*:provedor,\s*pagamento_destino_admin_id\s*=\s*:admin_id,\s*pagamento_destino_registrado_em\s*=\s*NOW\s*\(\s*\),\s*pagamento_destino_evento\s*=\s*:evento\s+WHERE\s+id\s*=\s*:id''\s*\)\s*->execute\(\s*\['':destino''\s*=>\s*\$destino,\s*'':provedor''\s*=>\s*\$provedor,\s*'':admin_id''\s*=>\s*\$adminId,\s*':evento''\s*=>\s*\$evento,\s*':id''\s*=>\s*\$pedidoId\]\s*\);'
        $newUpdate = '$setsPedido = ''forma_pagamento_escolhida = :destino, provedor_pagamento_escolhido = :provedor, pagamento_destino_admin_id = :admin_id, pagamento_destino_registrado_em = NOW(), pagamento_destino_evento = :evento'';' + $newline +
            '            $paramsPedido = ['':destino'' => $destino, '':provedor'' => $provedor, '':admin_id'' => $adminId, '':evento'' => $evento, '':id'' => $pedidoId];' + $newline +
            '            if ($statusDestinoFila !== null) {' + $newline +
            '                $setsPedido .= ", status = CASE WHEN status = ''aguardando_pagamento'' THEN :status_destino ELSE status END";' + $newline +
            '                $paramsPedido['':status_destino''] = $statusDestinoFila;' + $newline +
            '            }' + $newline +
            '            $pdo->prepare(''UPDATE pedidos SET '' . $setsPedido . '' WHERE id = :id'')->execute($paramsPedido);'
        $source = Replace-OneRegex $source $updatePattern $newUpdate 'UPDATE financeiro'

        foreach ($required in @($marker, '$statusDestinoFila', 'status = CASE WHEN status = ''aguardando_pagamento'' THEN :status_destino ELSE status END')) {
            if ($source.IndexOf($required, [System.StringComparison]::Ordinal) -lt 0) {
                throw "Verificacao pos-patch falhou: $required"
            }
        }

        # O PHP gerado e validado em arquivo temporario antes de tocar no arquivo do projeto.
        [System.IO.File]::WriteAllText($validationPath, $source, $utf8NoBom)
        Test-PhpSyntax $validationPath
        [System.IO.File]::WriteAllText($pagamentoPath, $source, $utf8NoBom)
        Test-PhpSyntax $pagamentoPath
        $changed = $true
    }

    $lane = [System.IO.File]::ReadAllText($lanePath)
    if (-not $lane.Contains($marker)) {
        $laneAppend = $newline + 'IMPLEMENTACAO_FINANCIAL-FILA-01:' + $newline +
            '  estado: entregue_para_teste_C' + $newline +
            '  arquivo: src/Models/Pagamento.php' + $newline +
            '  regra: pago_agora, pago_na_chegada e sem_cobranca promovem aguardando_pagamento para aguardando_guincho; online permanece aguardando_pagamento.' + $newline +
            '  idempotencia: repeticao do mesmo destino continua sem inserir pedido_eventos adicional nem regride o status.' + $newline +
            '  aceite_C:' + $newline +
            '    - pago_agora, pago_na_chegada e sem_cobranca entram na fila do guincho.' + $newline +
            '    - online permanece aguardando_pagamento ate aprovacao.' + $newline +
            '    - repetir o mesmo destino nao duplica pedido_eventos nem regride status.' + $newline
        [System.IO.File]::WriteAllText($lanePath, $lane + $laneAppend, $utf8NoBom)
    }

    if ($changed) {
        Write-Host 'OK: FINANCIAL-FILA-01 aplicado e validado em arquivo temporario antes da gravacao.' -ForegroundColor Green
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
