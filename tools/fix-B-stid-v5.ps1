# fix-B-stid-v5.ps1 (Cenario A: AdminController sem namespace/use)
# Substitui o fallback [B-STID-01] antigo por chamada ao ServiceTypeResolver.
# AdminController esta no namespace global -> require_once + chamada com \ na frente.
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
    Write-Host "       A Faixa A precisa ter aplicado o pacote antes." -ForegroundColor Yellow
    exit 1
}

# ------------------------------------------------------------
# 1. Backup
# ------------------------------------------------------------
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$controller.bak-fix-B-stid-v5-$stamp"
Copy-Item -LiteralPath $controller -Destination $backup -Force
Write-Host "[BACKUP] $backup" -ForegroundColor Green

$conteudo = Get-Content -LiteralPath $controller -Raw -Encoding UTF8
$original = $conteudo

# ------------------------------------------------------------
# 2. Inserir require_once do ServiceTypeResolver (se nao existir)
# ------------------------------------------------------------
$marcadorRequire = "require_once __DIR__ . '/../Services/Catalog/ServiceTypeResolver.php';"
$temRequire = $conteudo.Contains($marcadorRequire)

if (-not $temRequire) {
    # Insere na linha imediatamente antes de `class AdminController`
    $padraoClasse = '(?m)^(\s*class\s+AdminController\b)'
    if ($conteudo -notmatch $padraoClasse) {
        Write-Host "[ERRO] Nao achei 'class AdminController' no arquivo." -ForegroundColor Red
        Copy-Item -LiteralPath $backup -Destination $controller -Force
        exit 1
    }

    $linhaRequire = "require_once __DIR__ . '/../Services/Catalog/ServiceTypeResolver.php';"
    $conteudo = [regex]::Replace(
        $conteudo,
        $padraoClasse,
        "$linhaRequire`r`n`$1",
        1
    )
    Write-Host "[REQUIRE] Adicionado: $linhaRequire (antes de class AdminController)" -ForegroundColor Green
} else {
    Write-Host "[REQUIRE] Ja existe." -ForegroundColor Yellow
}

# ------------------------------------------------------------
# 3. Localizar e substituir o bloco [B-STID-01] antigo
# ------------------------------------------------------------
$padraoAntigo = '(?s)//\s*\[B-STID-01\]\s*Fallback quando o POST nao trouxe service_type_id.*?error_log\(\x27\[B-STID-01\] falha no fallback:.*?\n\s*\}\s*\n\s*\}'

if ($conteudo -notmatch $padraoAntigo) {
    Write-Host "[ERRO] Bloco [B-STID-01] antigo nao encontrado." -ForegroundColor Red
    Write-Host "       Verifique se o marcador mudou. Nada foi alterado." -ForegroundColor Yellow
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

$blocoNovo = @'
// [B-STID-01] Fallback via ServiceTypeResolver (Faixa A)
// AdminController esta no namespace global -> chamada com \ na frente.
// O resolver traduz o slug publico (pneu/eletrica/bateria/mecanica/chaveiro)
// para o code interno e consulta o id real de service_types.
if ($serviceTypeId <= 0) {
    $tipoParaBusca = trim((string)($_POST['tipo_problema'] ?? ''));
    if ($tipoParaBusca !== '' && $tipoParaBusca !== 'outro') {
        $svcFallback = \App\Services\Catalog\ServiceTypeResolver::porSlug($tipoParaBusca);
        if ($svcFallback !== null && $svcFallback > 0) {
            $serviceTypeId = $svcFallback;
            error_log('[B-STID-01] service_type_id resolvido via ServiceTypeResolver: ' . $svcFallback . ' (tipo_problema=' . $tipoParaBusca . ')');
        } else {
            error_log('[B-STID-01] ServiceTypeResolver retornou null para tipo_problema=' . $tipoParaBusca);
        }
    }
}
'@

$conteudo = [regex]::Replace($conteudo, $padraoAntigo, $blocoNovo, 1)
Write-Host "[BLOCO] [B-STID-01] substituido por chamada ao ServiceTypeResolver." -ForegroundColor Green

# ------------------------------------------------------------
# 4. Gravar
# ------------------------------------------------------------
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($controller, $conteudo, $utf8NoBom)
Write-Host "[WRITE] $controller" -ForegroundColor Green

# ------------------------------------------------------------
# 5. Validar sintaxe
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
# 6. Resumo
# ------------------------------------------------------------
$antes = ($original -split "`r?`n").Count
$depois = ($conteudo -split "`r?`n").Count
Write-Host ""
Write-Host "=== RESUMO ===" -ForegroundColor Cyan
Write-Host "Linhas antes:  $antes"
Write-Host "Linhas depois: $depois"
Write-Host "Backup:        $backup"
Write-Host ""
Write-Host "Para reverter:" -ForegroundColor Yellow
Write-Host "  Copy-Item '$backup' '$controller' -Force"
Write-Host ""
Write-Host "[DONE] fix-B-stid-v5 aplicado (Cenario A)." -ForegroundColor Green