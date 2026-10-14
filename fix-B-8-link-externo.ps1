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

$newBloco = @'
            // §CONTRATO-LINK-EXTERNO (2026-10-04): consumir 'link_externo' que a
            // Faixa A passa a devolver em Pagamento::aplicarDestinoPagamento()
            // para destino='online'. Se ausente (A ainda nao entregou ou
            // gateway nao gerou URL), cai no 'link' interno com aviso de que
            // o cliente precisa estar autenticado para pagar.
            $linkExterno = isset($resPag['link_externo']) ? trim((string)$resPag['link_externo']) : '';
            $linkInterno = isset($resPag['link'])         ? trim((string)$resPag['link'])         : '';

            if ($destinoPag === 'online') {
                if ($linkExterno !== '') {
                    $msgOnline = 'Pedido #' . $pedidoId . ' criado. Link do gateway (copie e envie ao cliente): ' . $linkExterno;
                    $linkUsado = $linkExterno;
                    $linkEhExterno = true;
                } elseif ($linkInterno !== '') {
                    $msgOnline = 'Pedido #' . $pedidoId . ' criado. Link interno: ' . $linkInterno
                               . ' — o cliente precisa entrar na conta dele para pagar.';
                    $linkUsado = $linkInterno;
                    $linkEhExterno = false;
                } else {
                    $msgOnline = 'Pedido #' . $pedidoId . ' criado, mas o gateway nao retornou link de pagamento.'
                               . ' Abra o pedido para investigar ou tente reenviar a operacao.';
                    $linkUsado = '';
                    $linkEhExterno = false;
                }

                // One-shot para a UI (modal em /admin/pedidos). Consumido
                // pelo proximo render de pedidos(); ver fix-B-8 Parte 2.
                $_SESSION['admin_link_pagamento_gerado'] = [
                    'pedido_id'   => $pedidoId,
                    'link'        => $linkUsado,
                    'externo'     => $linkEhExterno,
                    'provedor'    => $provedorOnline,
                    'criado_em'   => time(),
                ];
            }

            $msgMap = [
                'online'          => $msgOnline ?? ('Pedido #' . $pedidoId . ' criado.'),
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
    Write-Host "Bloco original nao casou exatamente. Tentando versao normalizada..." -ForegroundColor Yellow
    # Tenta com \n em vez de \r\n (o arquivo pode estar LF-only)
    $oldBlocoLF = $oldBloco -replace "`r`n", "`n"
    if ($c.Contains($oldBlocoLF)) {
        $c = $c.Replace($oldBlocoLF, $newBloco -replace "`r`n", "`n")
        Write-Host "OK (via LF)" -ForegroundColor Green
    } else {
        Write-Host "Nao casou. Abortando — precisamos localizar o bloco manualmente." -ForegroundColor Red
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
    Write-Host "OK — fix-B-8 aplicado." -ForegroundColor Green
    Write-Host "Backup: $ctrlBak" -ForegroundColor Cyan
} else {
    Copy-Item $ctrlBak $ctrlPath -Force
    Write-Host "php -l falhou — revertido." -ForegroundColor Red
    Write-Host $lint
    exit 1
}