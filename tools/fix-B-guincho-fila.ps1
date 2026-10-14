$target = 'C:\xampp\htdocs\guinchafacil\src\Controllers\GuinchoController.php'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$target.bak-$stamp"
Copy-Item -LiteralPath $target -Destination $backup -Force
Write-Host "[ok] Backup: $backup" -ForegroundColor Green

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$content = [System.IO.File]::ReadAllText($target, $utf8NoBom)
$normalized = $content -replace "`r`n", "`n"

$a1 = '            if (!$semLocalizacao && isset($pedido[''score_minimo_atual'']) && $score < (float)$pedido[''score_minimo_atual'']) continue;'
if ($normalized.IndexOf($a1) -lt 0) { Write-Host "[ERRO] Ancora 1 (score filter) nao encontrada." -ForegroundColor Red; exit 2 }
$r1 = @'
            $scoreMinimoAtual = (float)($pedido['score_minimo_atual'] ?? 0.0);
            $abaixoDoScore = !$semLocalizacao && $scoreMinimoAtual > 0.0 && $score < $scoreMinimoAtual;
'@
$r1 = $r1 -replace "`r`n", "`n"
$normalized = $normalized.Replace($a1, $r1)

$a2 = @'
        $ofertas = [];
        foreach ($aguardando as $pedido) {
'@
$a2 = $a2 -replace "`r`n", "`n"
if ($normalized.IndexOf($a2) -lt 0) { Write-Host "[ERRO] Ancora 2 (init lista) nao encontrada." -ForegroundColor Red; exit 3 }
$r2 = @'
        $ofertas = [];
        $ofertasAbaixoDoScore = [];
        foreach ($aguardando as $pedido) {
'@
$r2 = $r2 -replace "`r`n", "`n"
$normalized = $normalized.Replace($a2, $r2)

$a3 = @'
            $ofertas[] = [
                'id'                 => (int)$pedido['id'],
'@
$a3 = $a3 -replace "`r`n", "`n"
if ($normalized.IndexOf($a3) -lt 0) { Write-Host "[ERRO] Ancora 3 (oferta) nao encontrada." -ForegroundColor Red; Copy-Item -LiteralPath $backup -Destination $target -Force; exit 4 }
$r3 = @'
            $ofertaItem = [
                'id'                 => (int)$pedido['id'],
'@
$r3 = $r3 -replace "`r`n", "`n"
$normalized = $normalized.Replace($a3, $r3)

$a4 = @'
                'compat_warnings'    => $compatWarnings,
            ];
'@
$a4 = $a4 -replace "`r`n", "`n"
if ($normalized.IndexOf($a4) -lt 0) { Write-Host "[ERRO] Ancora 4 (fechamento) nao encontrada." -ForegroundColor Red; Copy-Item -LiteralPath $backup -Destination $target -Force; exit 5 }
$r4 = @'
                'compat_warnings'    => $compatWarnings,
            ];
            if ($abaixoDoScore) { $ofertasAbaixoDoScore[] = $ofertaItem; } else { $ofertas[] = $ofertaItem; }
'@
$r4 = $r4 -replace "`r`n", "`n"
$normalized = $normalized.Replace($a4, $r4)

$a5 = @'
        usort($ofertas, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($ofertas, 0, $limite);
'@
$a5 = $a5 -replace "`r`n", "`n"
if ($normalized.IndexOf($a5) -lt 0) { Write-Host "[ERRO] Ancora 5 (usort/return) nao encontrada." -ForegroundColor Red; Copy-Item -LiteralPath $backup -Destination $target -Force; exit 6 }
$r5 = @'
        if (empty($ofertas) && !empty($ofertasAbaixoDoScore)) {
            error_log(sprintf('[GuinchoController::montarOfertasDisponiveis] FALLBACK SCORE: %d pedido(s) abaixo do score minimo oferecidos por falta de elegiveis (guincho=%d)', count($ofertasAbaixoDoScore), (int)$guincho['id']));
            $ofertas = $ofertasAbaixoDoScore;
        }
        usort($ofertas, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($ofertas, 0, $limite);
'@
$r5 = $r5 -replace "`r`n", "`n"
$normalized = $normalized.Replace($a5, $r5)

$newContent = $normalized -replace "`n", "`r`n"
[System.IO.File]::WriteAllText($target, $newContent, $utf8NoBom)
Write-Host "[ok] Patch aplicado" -ForegroundColor Green

$phpExe = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpExe)) { $phpExe = 'php' }
$lintOutput = & $phpExe -l $target 2>&1
Write-Host $lintOutput

if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup..." -ForegroundColor Red
    Copy-Item -LiteralPath $backup -Destination $target -Force
    exit 7
}

Write-Host "[ok] Sintaxe PHP OK" -ForegroundColor Green
Write-Host " PATCH B-GUINCHO-FILA APLICADO COM SUCESSO" -ForegroundColor Cyan