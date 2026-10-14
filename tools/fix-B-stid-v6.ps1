# fix-B-stid-v6.ps1
# [B-STID-02] Resolve modalidade a partir do catalogo.
# O ModalidadeResolver (Faixa A) decide por tipoProblema (texto), que nao
# cobre `eletrica`. O catalogo service_types.attendance_mode e a fonte
# de verdade. Passamos modalidade_socorro explicito no PedidoCreateRequest
# quando o service_type_id foi resolvido.
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
$backup = "$controller.bak-fix-B-stid-v6-$stamp"
Copy-Item -LiteralPath $controller -Destination $backup -Force
Write-Host "[BACKUP] $backup" -ForegroundColor Green

$linhas = Get-Content -LiteralPath $controller

# ------------------------------------------------------------
# 1. Localizar o bloco B-STID-01 (fim dele)
#    Termina com:
#        }
#    }   <- fecha o if ($serviceTypeId === null)
# ------------------------------------------------------------
$idxBstid01Fim = -1
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($linhas[$i] -match '\[B-STID-01\] Fallback via ServiceTypeResolver') {
        # Avanca ate achar o fechamento do if externo
        for ($j = $i; $j -lt [Math]::Min($i + 30, $linhas.Count); $j++) {
            if ($linhas[$j] -match '^\s*\}\s*$' -and $linhas[$j - 1] -match '^\s*\}\s*$') {
                $idxBstid01Fim = $j
                break
            }
        }
        break
    }
}

if ($idxBstid01Fim -lt 0) {
    Write-Host "[ERRO] Nao achei o fim do bloco [B-STID-01]." -ForegroundColor Red
    Copy-Item -LiteralPath $backup -Destination $controller -Force
    exit 1
}

Write-Host "[SANIDADE] Fim do [B-STID-01] na linha $($idxBstid01Fim + 1)." -ForegroundColor Green

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

$antes  = if ($idxBstid01Fim -ge 0) { $linhas[0..$idxBstid01Fim] } else { @() }
$depois = if (($idxBstid01Fim + 1) -lt $linhas.Count) { $linhas[($idxBstid01Fim + 1)..($linhas.Count - 1)] } else { @() }

$novo = @()
$novo += $antes
$novo += $blocoNovo
$novo += $depois

$textoAtual = $novo -join "`r`n"

# ------------------------------------------------------------
# 3. Adicionar modalidade_socorro no PedidoCreateRequest
# ------------------------------------------------------------
$padrao = "('s|')service_type_id('s|')\s*=>\s*\$serviceTypeId,\s*\r?\n"
$substituicao = "'service_type_id'    => \$serviceTypeId,`r`n                'modalidade_socorro' => \$modalidadeSocorro,`r`n"

if ($textoAtual -notmatch "modalidade_socorro' => \$modalidadeSocorro") {
    $textoAtual = $textoAtual -replace $padrao, $substituicao
    Write-Host "[PEDIDO] modalidade_socorro adicionado no PedidoCreateRequest." -ForegroundColor Green
} else {
    Write-Host "[PEDIDO] modalidade_socorro ja existe." -ForegroundColor Yellow
}

# ------------------------------------------------------------
# 4. Gravar
# ------------------------------------------------------------
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($controller, $textoAtual, $utf8NoBom)
Write-Host "[WRITE] $controller" -ForegroundColor Green

# ------------------------------------------------------------
# 5. Validar
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
Write-Host "[DONE] fix-B-stid-v6 aplicado." -ForegroundColor Green