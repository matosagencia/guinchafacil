# fix-B-stid-v6c.ps1
# [B-STID-02] Resolve modalidade a partir do catalogo (via Model ServiceType).
# Reusa ServiceType::buscarPorId() + ServiceType::isTowing().
# Zero SQL cru, zero comparacao manual de attendance_mode.
#
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$controller = Join-Path $Repositorio 'src\Controllers\AdminController.php'
if (-not (Test-Path -LiteralPath $controller)) {
    Write-Host "[ERRO] AdminController.php nao encontrado em $controller" -ForegroundColor Red
    exit 1
}

$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = "$controller.bak-fix-B-stid-v6c-$stamp"
Copy-Item -LiteralPath $controller -Destination $backup -Force
Write-Host "[BACKUP] $backup" -ForegroundColor Green

$linhas = Get-Content -LiteralPath $controller

# ------------------------------------------------------------
# 1. Localizar o fim do bloco [B-STID-01]
# ------------------------------------------------------------
$idxFim = -1
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($linhas[$i] -match '\[B-STID-01\] Fallback via ServiceTypeResolver') {
        for ($j = $i; $j -lt [Math]::Min($i + 30, $linhas.Count); $j++) {
            if ($linhas[$j] -match '^\s*\}\s*$' -and $linhas[$j - 1] -match '^\s*\}\s*$') {
                $idxFim = $j
                break
            }
        }
        break
    }
}

if ($idxFim -lt 0) {
    Write-Host "[ERRO] Nao achei o fim do bloco [B-STID-01]." -ForegroundColor Red
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

Write-Host "[SANIDADE] Fim do [B-STID-01] na linha $($idxFim + 1)." -ForegroundColor Green

# ------------------------------------------------------------
# 2. Inserir o bloco [B-STID-02] (versao limpa, via Model ServiceType)
# ------------------------------------------------------------
$blocoNovo = @(
    ''
    '        // [B-STID-02] Resolve modalidade a partir do catalogo (via Model ServiceType)'
    '        // O ModalidadeResolver do PedidoCoreService decide por tipoProblema (texto),'
    '        // que nao cobre `eletrica`. O catalogo e a fonte de verdade. Reusamos'
    '        // ServiceType::buscarPorId() + ServiceType::isTowing() em vez de SQL cru.'
    '        $modalidadeSocorro = null;'
    '        if ($serviceTypeId !== null && $serviceTypeId > 0) {'
    '            $st = ServiceType::buscarPorId($serviceTypeId);'
    '            if ($st && !empty($st[''active''])) {'
    '                $modalidadeSocorro = ServiceType::isTowing($st)'
    '                    ? ''REBOQUE_PRANCHA'''
    '                    : ''SOCORRO_LOCAL'';'
    '                error_log(''[B-STID-02] service_type_id='' . $serviceTypeId'
    '                    . '' attendance_mode='' . ($st[''attendance_mode''] ?? ''null'')'
    '                    . '' -> modalidade_socorro='' . $modalidadeSocorro);'
    '            }'
    '        }'
)

$antes  = @($linhas[0..$idxFim])
$depois = @($linhas[($idxFim + 1)..($linhas.Count - 1)])

$novo = @()
$novo += $antes
$novo += $blocoNovo
$novo += $depois

$textoAtual = $novo -join "`r`n"

# ------------------------------------------------------------
# 3. Adicionar modalidade_socorro no PedidoCreateRequest
#    Usa string literal + .Replace() (sem regex, sem escapes)
# ------------------------------------------------------------
$marcador     = "'service_type_id'    => `$serviceTypeId,"
$novoMarcador = "'service_type_id'    => `$serviceTypeId,`r`n                'modalidade_socorro' => `$modalidadeSocorro,"

if ($textoAtual.Contains($marcador)) {
    $textoAtual = $textoAtual.Replace($marcador, $novoMarcador)
    Write-Host "[PEDIDO] modalidade_socorro adicionado no PedidoCreateRequest." -ForegroundColor Green
} else {
    Write-Host "[PEDIDO] marcador 'service_type_id' => \$serviceTypeId nao encontrado." -ForegroundColor Yellow
    Write-Host "         Verifique a indentacao. Nada sera alterado." -ForegroundColor Yellow
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

# ------------------------------------------------------------
# 4. Gravar
# ------------------------------------------------------------
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($controller, $textoAtual, $utf8NoBom)
Write-Host "[WRITE] $controller" -ForegroundColor Green

# ------------------------------------------------------------
# 5. Validar sintaxe
# ------------------------------------------------------------
& 'C:\xampp\php\php.exe' -l $controller
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup..." -ForegroundColor Red
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

Write-Host ''
Write-Host "=== RESUMO ===" -ForegroundColor Cyan
Write-Host "Backup: $backup"
Write-Host "Para reverter: Copy-Item '$backup' '$controller' -Force"
Write-Host "[DONE] fix-B-stid-v6c aplicado." -ForegroundColor Green