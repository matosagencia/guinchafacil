# fix-B-stid-v5c.ps1
# Substitui o bloco [B-STID-01] antigo (linhas 1624-1643) por chamada ao
# ServiceTypeResolver, SEM regex -- trabalha por indice de linha.
# Cenario A: AdminController sem namespace/use (require_once + \ na frente).
# Data: 2026-10-08

param(
    [string]$Repositorio = 'C:\xampp\htdocs\guinchafacil'
)

$ErrorActionPreference = 'Stop'

$controller = Join-Path $Repositorio 'src\Controllers\AdminController.php'
$resolver   = Join-Path $Repositorio 'src\Services\Catalog\ServiceTypeResolver.php'

if (-not (Test-Path -LiteralPath $controller)) {
    Write-Host "[ERRO] AdminController.php nao encontrado em $controller" -ForegroundColor Red
    exit 1
}
if (-not (Test-Path -LiteralPath $resolver)) {
    Write-Host "[ERRO] ServiceTypeResolver.php nao encontrado em $resolver" -ForegroundColor Red
    exit 1
}

# ------------------------------------------------------------
# 1. Backup
# ------------------------------------------------------------
$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$controller.bak-fix-B-stid-v5c-$stamp"
Copy-Item -LiteralPath $controller -Destination $backup -Force
Write-Host "[BACKUP] $backup" -ForegroundColor Green

# ------------------------------------------------------------
# 2. Ler linhas (preserva CRLF no Join no final)
# ------------------------------------------------------------
$linhas = Get-Content -LiteralPath $controller

# ------------------------------------------------------------
# 3. Sanidade: confirmar que o bloco antigo esta onde esperamos
# ------------------------------------------------------------
# Linhas 1624-1643 (1-based) = indices 1623-1642 (0-based)
$idxInicio = 1623
$idxFim    = 1642

if ($linhas.Count -lt ($idxFim + 1)) {
    Write-Host "[ERRO] Arquivo tem so $($linhas.Count) linhas; esperava pelo menos $($idxFim + 1)." -ForegroundColor Red
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

$linha1624 = $linhas[$idxInicio]
$linha1643 = $linhas[$idxFim]

if ($linha1624 -notmatch '\[B-STID-01\]\s*Fallback quando o POST') {
    Write-Host "[ERRO] Linha 1624 nao tem o marcador [B-STID-01]. Tem:" -ForegroundColor Red
    Write-Host "       $linha1624" -ForegroundColor Yellow
    Write-Host "       Nada foi alterado." -ForegroundColor Yellow
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

if ($linha1643.Trim() -ne '}') {
    Write-Host "[ERRO] Linha 1643 nao e o fechamento esperado. Tem:" -ForegroundColor Red
    Write-Host "       $linha1643" -ForegroundColor Yellow
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

Write-Host "[SANIDADE] Bloco antigo confirmado nas linhas 1624-1643." -ForegroundColor Green

# ------------------------------------------------------------
# 4. Construir o bloco novo (array de linhas, sem here-string)
# ------------------------------------------------------------
$blocoNovo = @(
    '        // [B-STID-01] Fallback via ServiceTypeResolver (Faixa A)'
    '        // AdminController esta no namespace global -> chamada com \ na frente.'
    '        // O resolver traduz o slug publico para o code interno e consulta'
    '        // o id real de service_types. Substitui a query antiga que nao'
    '        // resolvia `eletrica` (service_types nao tem coluna slug, e o name'
    '        // tem acento).'
    '        if ($serviceTypeId === null) {'
    '            $tipoParaBusca = trim((string)($_POST[''tipo_problema''] ?? ''''));'
    '            if ($tipoParaBusca !== '''' && $tipoParaBusca !== ''outro'') {'
    '                $svcFallback = \App\Services\Catalog\ServiceTypeResolver::porSlug($tipoParaBusca);'
    '                if ($svcFallback !== null && $svcFallback > 0) {'
    '                    $serviceTypeId = $svcFallback;'
    '                    error_log(''[B-STID-01] service_type_id resolvido via ServiceTypeResolver: '' . $svcFallback . '' (tipo_problema='' . $tipoParaBusca . '')'');'
    '                } else {'
    '                    error_log(''[B-STID-01] ServiceTypeResolver retornou null para tipo_problema='' . $tipoParaBusca);'
    '                }'
    '            }'
    '        }'
)

# ------------------------------------------------------------
# 5. Reconstruir: antes + bloco novo + depois
# ------------------------------------------------------------
$antes  = if ($idxInicio -gt 0) { $linhas[0..($idxInicio - 1)] } else { @() }
$depois = if (($idxFim + 1) -lt $linhas.Count) { $linhas[($idxFim + 1)..($linhas.Count - 1)] } else { @() }

$novo = @()
$novo += $antes
$novo += $blocoNovo
$novo += $depois

# ------------------------------------------------------------
# 6. Inserir require_once do resolver (se nao existir)
# ------------------------------------------------------------
$marcadorRequire = "require_once __DIR__ . '/../Services/Catalog/ServiceTypeResolver.php';"
$textoAtual = $novo -join "`r`n"
$temRequire = $textoAtual.Contains($marcadorRequire)

if (-not $temRequire) {
    # Encontrar a linha 'class AdminController' no array reconstruido
    $idxClasse = -1
    for ($i = 0; $i -lt $novo.Count; $i++) {
        if ($novo[$i] -match '^\s*class\s+AdminController\b') {
            $idxClasse = $i
            break
        }
    }

    if ($idxClasse -lt 0) {
        Write-Host "[ERRO] Nao achei 'class AdminController' apos reconstruir." -ForegroundColor Red
        Copy-Item -LiteralPath $backup -Destination $controller -Force
        exit 1
    }

    $novoFinal = @()
    $novoFinal += $novo[0..($idxClasse - 1)]
    $novoFinal += "require_once __DIR__ . '/../Services/Catalog/ServiceTypeResolver.php';"
    $novoFinal += $novo[$idxClasse..($novo.Count - 1)]
    $novo = $novoFinal

    Write-Host "[REQUIRE] Adicionado antes da linha $($idxClasse + 1) (class AdminController)." -ForegroundColor Green
} else {
    Write-Host "[REQUIRE] Ja existe." -ForegroundColor Yellow
}

# ------------------------------------------------------------
# 7. Gravar (UTF-8 sem BOM, CRLF)
# ------------------------------------------------------------
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$conteudoFinal = ($novo -join "`r`n") + "`r`n"
[System.IO.File]::WriteAllText($controller, $conteudoFinal, $utf8NoBom)
Write-Host "[WRITE] $controller" -ForegroundColor Green

# ------------------------------------------------------------
# 8. Validar sintaxe
# ------------------------------------------------------------
$php = 'C:\xampp\php\php.exe'
if (Test-Path -LiteralPath $php) {
    & $php -l $controller
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERRO] php -l falhou. Restaurando backup..." -ForegroundColor Red
        Copy-Item -LiteralPath $backup -Destination $controller -Force
        exit 1
    }
} else {
    Write-Host "[AVISO] php.exe nao encontrado; pulando php -l." -ForegroundColor Yellow
}

# ------------------------------------------------------------
# 9. Resumo
# ------------------------------------------------------------
Write-Host ""
Write-Host "=== RESUMO ===" -ForegroundColor Cyan
Write-Host "Linhas antes:  $($linhas.Count)"
Write-Host "Linhas depois: $($novo.Count)"
Write-Host "Backup:        $backup"
Write-Host ""
Write-Host "Para reverter:" -ForegroundColor Yellow
Write-Host "  Copy-Item '$backup' '$controller' -Force"
Write-Host ""
Write-Host "[DONE] fix-B-stid-v5c aplicado." -ForegroundColor Green