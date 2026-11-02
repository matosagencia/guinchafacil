[CmdletBinding()]
param([string]$BaseUrl = 'http://localhost:8080',[switch]$E2E,[switch]$AllPhp,[switch]$ScanBaseline)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$php = 'C:\xampp\php84\php.exe'
$failures = New-Object System.Collections.Generic.List[string]
function Check($label, [scriptblock]$action) { try { & $action; Write-Host "[OK] $label" -ForegroundColor Green } catch { $failures.Add($label + ': ' + $_.Exception.Message); Write-Host "[FAIL] $label" -ForegroundColor Red } }
Push-Location $root
try {
    Check 'PHP lint dos arquivos do funil' {
        $files = if ($AllPhp) { Get-ChildItem src -Recurse -Filter *.php } else { @('src/Controllers/AdminController.php','src/Controllers/ClienteController.php','src/Controllers/PreCotacaoApiController.php','src/Controllers/PagamentoController.php','src/Models/Pagamento.php','src/Services/Conversion/ConversionService.php','src/Views/admin/partials/_precotacao_funil_admin.php','src/Views/cliente/partials/_precotacao_funil_cliente.php','src/Views/public/pre-cotacao.php') | ForEach-Object { Get-Item $_ -ErrorAction SilentlyContinue } }
        foreach ($file in $files) { $out = & $php -l $file.FullName 2>&1; if ($LASTEXITCODE -ne 0) { throw ($file.FullName + ': ' + ($out -join ' ')) } }
    }
    Check 'Contrato de destino financeiro' {
        foreach ($destino in @('a','b')) { $out = & $php tests/t2_destino_pagamento.php --only=$destino 2>&1; if ($LASTEXITCODE -ne 0 -or (($out -join "`n") -notmatch 'RESULTADO: PASSOU')) { throw ($out -join "`n") } }
    }
    Check 'Regressao de mojibake no funil' {
        $files = if ($ScanBaseline) { Get-ChildItem 'src/Views/public','src/Views/admin','src/Views/cliente','public/assets/js' -Recurse -File } else { @(git diff --name-only --diff-filter=ACM -- '*.php' '*.js' | ForEach-Object { Get-Item $_ -ErrorAction SilentlyContinue }) }
        $scanner = @'
<?php
$found=[];
foreach(array_slice($argv,1) as $file){$raw=@file_get_contents($file);if($raw===false)continue;foreach(["\xC3\x83","\xC3\x82","\xE2\x80\x9A","\xC3\xAF\xC2\xBF\xC2\xBD","\xEF\xBF\xBD"] as $needle){if(strpos($raw,$needle)!==false){$found[]=$file;break;}}}
echo implode(PHP_EOL,array_unique($found));
'@
        $tmp = Join-Path $env:TEMP ('gf-mojibake-' + [guid]::NewGuid().ToString('N') + '.php')
        try {
            Set-Content -LiteralPath $tmp -Value $scanner -Encoding ASCII
            if ($ScanBaseline) {
                $paths=@($files | ForEach-Object FullName)
                $hits=& $php $tmp @paths
            } else {
                $hits=@(git diff --unified=0 --diff-filter=ACM -- '*.php' '*.js' | Where-Object { $_ -match '^\+[^+]' -and ($_ -like ('*' + [char]0xFFFD + '*') -or $_ -like '*Ã*' -or $_ -like '*Â*') })
            }
            if($hits){throw (($hits | Select-Object -First 20) -join "`n")}
        } finally { Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue }
    }
    if ($E2E) { Check 'E2E admin cliente/veÃ­culo/funil' { if(-not $env:ADMIN_EMAIL -or -not $env:ADMIN_PASS){throw 'Defina ADMIN_EMAIL e ADMIN_PASS para o E2E autenticado.'}; $env:BASE_URL=$BaseUrl; & npx.cmd playwright test tests/e2e/admin-pedido-funil-modal.spec.js --reporter=line; if($LASTEXITCODE -ne 0){throw 'Playwright falhou.'} } }
} finally { Pop-Location }
if($failures.Count){Write-Host "`nAGENTE BLOQUEOU A ENTREGA:" -ForegroundColor Red;$failures|ForEach-Object{Write-Host "- $_"};exit 1}
Write-Host "`nAGENTE FUNIL/CHECKOUT: PASSOU" -ForegroundColor Green
