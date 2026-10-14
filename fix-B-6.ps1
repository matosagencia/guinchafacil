$ErrorActionPreference = 'Stop'
Set-Location 'C:\xampp\htdocs\guinchafacil'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$php = 'C:\xampp\php\php.exe'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

$ctrlPath = (Resolve-Path 'src\Controllers\AdminController.php').Path
$ctrlBak  = "$ctrlPath.bak-fix8-$stamp"
Copy-Item $ctrlPath $ctrlBak
Write-Host "[backup] $ctrlBak" -ForegroundColor Cyan

$c = [IO.File]::ReadAllText($ctrlPath)

if ($c.Contains('admin_link_pagamento_gerado')) {
    Write-Host "SKIP: fix-B-8 ja aplicado." -ForegroundColor Yellow
    exit 0
}

# Bloco antigo: os 8 linhas do msgMap + flash
$oldBloco = @'
            $msgMap = [
                'online'          => 'Pedido #' . $pedidoId . ' criado. Envie este link ao cliente: ' . (string)($resPag['link'] ?? ''),
                'pago_agora'      => 'Pedido #' . $pedidoId . ' criado com baixa manual registrada.',
                'pago_na_chegada' => 'Pedido #' . $pedidoId . ' criado. Cobranca combinada com o cliente.',
                'sem_cobranca'    => 'Pedido #' . $pedidoId . ' criado com isencao registrada.',
            ];
            $_SESSION['_flash'][] = [
                'message' => $msgMap[$destinoPag] ?? ('Pedido #' . $pedidoId . ' criado.'),
                'type' => 'success',
            ];
'@ -replace "`r?`n", "`r`n"

# Bloco novo: consome link_externo + guarda em sessao one-shot
$newBloco = @'
            // CONTRATO-LINK-EXTERNO (2026-10-04): consome 'link_externo' que a
            // Faixa A passa a devolver em Pagamento::aplicarDestinoPagamento()
            // para destino='online'. Se ausente, cai no 'link' interno com
            // aviso de que o cliente precisa estar autenticado para pagar.
            $linkExterno = isset($resPag['link_externo']) ? trim((string)$resPag['link_externo']) : '';
            $linkInterno = isset($resPag['link'])         ? trim((string)$resPag['link'])         : '';
            $msgOnline = '';

            if ($destinoPag === 'online') {
                if ($linkExterno !== '') {
                    $msgOnline = 'Pedido #' . $pedidoId . ' criado. Link do gateway (copie e envie ao cliente): ' . $linkExterno;
                    $linkUsado = $linkExterno;
                    $linkEhExterno = true;
                } elseif ($linkInterno !== '') {
                    $msgOnline = 'Pedido #' . $pedidoId . ' criado. Link interno: ' . $linkInterno
                               . ' - o cliente precisa entrar na conta dele para pagar.';
                    $linkUsado = $linkInterno;
                    $linkEhExterno = false;
                } else {
                    $msgOnline = 'Pedido #' . $pedidoId . ' criado, mas o gateway nao retornou link de pagamento.'
                               . ' Abra o pedido para investigar ou tente reenviar a operacao.';
                    $linkUsado = '';
                    $linkEhExterno = false;
                }

                $_SESSION['admin_link_pagamento_gerado'] = [
                    'pedido_id' => $pedidoId,
                    'link'      => $linkUsado,
                    'externo'   => $linkEhExterno,
                    'provedor'  => $provedorOnline,
                    'criado_em' => time(),
                ];
            }

            $msgMap = [
                'online'          => $msgOnline !== '' ? $msgOnline : ('Pedido #' . $pedidoId . ' criado.'),
                'pago_agora'      => 'Pedido #' . $pedidoId . ' criado com baixa manual registrada.',
                'pago_na_chegada' => 'Pedido #' . $pedidoId . ' criado. Cobranca combinada com o cliente.',
                'sem_cobranca'    => 'Pedido #' . $pedidoId . ' criado com isencao registrada.',
            ];
            $_SESSION['_flash'][] = [
                'message' => $msgMap[$destinoPag] ?? ('Pedido #' . $pedidoId . ' criado.'),
                'type' => 'success',
            ];
'@ -replace "`r?`n", "`r`n"

if (-not $c.Contains($oldBloco)) {
    Write-Host "Bloco original nao casou exatamente. Tentando versao LF..." -ForegroundColor Yellow
    $oldBlocoLF = $oldBloco -replace "`r`n", "`n"
    if ($c.Contains($oldBlocoLF)) {
        $newBlocoLF = $newBloco -replace "`r`n", "`n"
        $c = $c.Replace($oldBlocoLF, $newBlocoLF)
        Write-Host "OK (via LF)" -ForegroundColor Green
    } else {
        Write-Host "Nao casou. Abortando." -ForegroundColor Red
        Write-Host "Procura por 'msgMap' no arquivo e me envia as 8 linhas antes e depois." -ForegroundColor Yellow
        Copy-Item $ctrlBak $ctrlPath -Force
        exit 1
    }
} else {
    $c = $c.Replace($oldBloco, $newBloco)
    Write-Host "OK (via CRLF)" -ForegroundColor Green
}

[IO.File]::WriteAllText($ctrlPath, $c, $utf8NoBom)

$lint = (& $php -l $ctrlPath 2>&1 | Out-String)
if ($lint -match 'No syntax errors') {
    Write-Host ""
    Write-Host "OK - fix-B-8 aplicado." -ForegroundColor Green
    Write-Host "Backup: $ctrlBak" -ForegroundColor Cyan
} else {
    Copy-Item $ctrlBak $ctrlPath -Force
    Write-Host "php -l falhou - revertido." -ForegroundColor Red
    Write-Host $lint
    exit 1
}