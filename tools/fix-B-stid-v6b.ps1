# fix-B-stid-v6b.ps1
# [B-STID-02] Resolve modalidade a partir do catalogo.
# Versao 2: corrige o erro de sintaxe do -notmatch com aspas e \$ .
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
$backup = "$controller.bak-fix-B-stid-v6b-$stamp"
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
# 2. Inserir o bloco [B-STID-02] depois do [B-STID-01]
# ------------------------------------------------------------
$blocoNovo = @(
    ''
    '        // [B-STID-02] Resolve modalidade a partir do catalogo (service_types.attendance_mode)'
    '        // O ModalidadeResolver do PedidoCoreService decide por tipoProblema (texto),'
    '        // que nao cobre `eletrica`. O catalogo e a fonte de verdade. Passamos'
    '        // modalidade_socorro explicito quando o service_type_id foi resolvido.'
    '        $modalidadeSocorro = null;'
    '        if ($serviceTypeId !== null && $serviceTypeId > 0) {'
    '            try {'
    '                $stmtMode = getPDO()->prepare('
    '                    ''SELECT attendance_mode FROM service_types WHERE id = ? AND active = 1 LIMIT 1'''
    '                );'
    '                $stmtMode->execute([$serviceTypeId]);'
    '                $mode = $stmtMode->fetchColumn();'
    '                if ($mode === ''ON_SITE'') {'
    '                    $modalidadeSocorro = ''SOCORRO_LOCAL'';'
    '                } elseif ($mode === ''TOWING'') {'
    '                    $modalidadeSocorro = ''REBOQUE_PRANCHA'';'
    '                }'
    '                error_log(''[B-STID-02] service_type_id='' . $serviceTypeId . '' attendance_mode='' . ($mode ?: ''null'') . '' -> modalidade_socorro='' . ($modalidadeSocorro ?: ''null''));'
    '            } catch (\Throwable $eMode) {'
    '                error_log(''[B-STID-02] falha ao ler attendance_mode: '' . $eMode->getMessage());'
    '            }'
    '        }'
)

$antes  = @($linhas[0..$idxFim])
$depois = @($linhas[($idxFim + 1)..($linhas.Count - 1)])

$novo = @()
$novo += $antes
$novo += $blocoNovo
$novo += $depois

# ------------------------------------------------------------
# 3. Adicionar modalidade_socorro no PedidoCreateRequest
#    Usa -like (string) e -replace (regex). Sem \$ problematico.
# ------------------------------------------------------------
$textoAtual = $novo -join "`r`n"

# Linha alvo (com indentacao):
#     'service_type_id'    => $serviceTypeId,
$marcador = "'service_type_id'    => `$serviceTypeId,"
$novoMarcador = "'service_type_id'    => `$serviceTypeId,`r`n                'modalidade_socorro' => `$modalidadeSocorro,"

if ($textoAtual.Contains($marcador)) {
    $textoAtual = $textoAtual.Replace($marcador, $novoMarcador)
    Write-Host "[PEDIDO] modalidade_socorro adicionado no PedidoCreateRequest." -ForegroundColor Green
} else {
    Write-Host "[PEDIDO] marcador 'service_type_id' => \$serviceTypeId nao encontrado." -ForegroundColor Yellow
    Write-Host "         Verifique se a indentacao mudou. Nada sera alterado." -ForegroundColor Yellow
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
Write-Host "[DONE] fix-B-stid-v6b aplicado." -ForegroundColor Green