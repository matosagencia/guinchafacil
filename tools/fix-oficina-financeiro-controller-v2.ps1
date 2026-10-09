# fix-oficina-financeiro-controller-v2.ps1
# Versao 2: localiza o fim do metodo financeiro() contando chaves.
# Substitui financeiro() com JOIN (A1) e apaga financeiroPage() (duplicata).
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$controller = Join-Path $Repositorio 'src\Controllers\OficinaController.php'
if (-not (Test-Path -LiteralPath $controller)) {
    Write-Host "[ERRO] OficinaController.php nao encontrado." -ForegroundColor Red
    exit 1
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bak = "$controller.bak-fix-oficina-financeiro-v2-$stamp"
Copy-Item -LiteralPath $controller -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$linhas = Get-Content -LiteralPath $controller -Encoding UTF8

# ------------------------------------------------------------
# 1. Localizar 'public function financeiro(): void'
# ------------------------------------------------------------
$idxInicio = -1
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($linhas[$i] -match '^\s*public function\s+financeiro\s*\(\s*\)\s*:\s*void') {
        $idxInicio = $i
        break
    }
}
if ($idxInicio -lt 0) {
    Write-Host "[ERRO] 'public function financeiro(): void' nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}
Write-Host "[INFO] financeiro() comeca na linha $($idxInicio + 1)." -ForegroundColor Cyan

# ------------------------------------------------------------
# 2. Localizar '}' de fechamento (contando chaves)
# ------------------------------------------------------------
$abre = 0
$fecha = 0
$idxFim = -1
$comecou = $false
for ($j = $idxInicio; $j -lt $linhas.Count; $j++) {
    $linha = $linhas[$j]

    # Ignora chaves dentro de strings simples/duplas e comentarios (aproximacao)
    $semString = $linha -replace "'[^']*'", "''" -replace '"[^"]*"', '""'
    $semString = $semString -replace '//.*$', ''

    $abre  += ([regex]::Matches($semString, '\{')).Count
    $fecha += ([regex]::Matches($semString, '\}')).Count

    if ($abre -gt 0) { $comecou = $true }
    if ($comecou -and $abre -eq $fecha) {
        $idxFim = $j
        break
    }
}

if ($idxFim -lt 0) {
    Write-Host "[ERRO] Fim do financeiro() nao encontrado (contagem de chaves)." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}
Write-Host "[INFO] financeiro() termina na linha $($idxFim + 1)." -ForegroundColor Cyan

# ------------------------------------------------------------
# 3. Bloco novo do financeiro() (arrays de strings)
# ------------------------------------------------------------
$bloco = @(
    '    // --- FINANCEIRO ---'
    '    public function financeiro(): void'
    '    {'
    '        $oficina = $this->getOficina();'
    '        $oficinaId = (int)$oficina[''id''];'
    '        $oficinaCompleta = $oficina;'
    ''
    '        $mes = (int)($_GET[''mes''] ?? date(''m''));'
    '        $ano = (int)($_GET[''ano''] ?? date(''Y''));'
    '        $inicio = sprintf(''%04d-%02d-01'', $ano, $mes);'
    '        $fim    = date(''Y-m-t'', strtotime($inicio));'
    ''
    '        $pdo = getPDO();'
    ''
    '        $stmt = $pdo->prepare('
    '            "SELECT'
    '                r.id,'
    '                r.pedido_id,'
    '                r.valor_bruto,'
    '                r.taxa_plataforma,'
    '                r.valor_liquido,'
    '                r.status,'
    '                r.pix_enviado_em,'
    '                r.criado_em,'
    '                p.tipo_problema,'
    '                p.endereco_origem,'
    '                p.endereco_destino,'
    '                p.status       AS pedido_status,'
    '                p.criado_em    AS pedido_em,'
    '                p.motivo_cancelamento,'
    '                p.taxa_cancelamento,'
    '                u.nome         AS cliente_nome'
    '             FROM oficina_repasses r'
    '             LEFT JOIN pedidos p ON p.id = r.pedido_id'
    '             LEFT JOIN usuarios u ON u.id = p.cliente_id'
    '             WHERE r.oficina_id = ? AND r.criado_em BETWEEN ? AND ?'
    '             ORDER BY r.criado_em DESC"'
    '        );'
    '        $stmt->execute([$oficinaId, $inicio . '' 00:00:00'', $fim . '' 23:59:59'']);'
    '        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];'
    ''
    '        $totais = ['
    '            ''valor_bruto''                  => 0.0,'
    '            ''taxa_plataforma''              => 0.0,'
    '            ''valor_liquido''                => 0.0,'
    '            ''valor_pago_oficina''           => 0.0,'
    '            ''valor_pendente_oficina''       => 0.0,'
    '            ''valor_estornado''              => 0.0,'
    '            ''taxa_retida_cancelamento''     => 0.0,'
    '            ''concluidos''                   => 0,'
    '            ''cancelados''                   => 0,'
    '        ];'
    ''
    '        foreach ($pagamentos as $r) {'
    '            $bruto   = (float)($r[''valor_bruto''] ?? 0);'
    '            $taxa    = (float)($r[''taxa_plataforma''] ?? 0);'
    '            $liquido = (float)($r[''valor_liquido''] ?? 0);'
    '            $status  = (string)($r[''status''] ?? '''');'
    '            $pedidoStatus = (string)($r[''pedido_status''] ?? '''');'
    ''
    '            $totais[''valor_bruto'']     += $bruto;'
    '            $totais[''taxa_plataforma''] += $taxa;'
    '            $totais[''valor_liquido'']   += $liquido;'
    ''
    '            if ($status === ''pago'') {'
    '                $totais[''valor_pago_oficina''] += $liquido;'
    '            } elseif ($status === ''pendente'') {'
    '                $totais[''valor_pendente_oficina''] += $liquido;'
    '            } elseif ($status === ''estornado'') {'
    '                $totais[''valor_estornado''] += $liquido;'
    '            }'
    ''
    '            if ($pedidoStatus === ''concluido'') {'
    '                $totais[''concluidos'']++;'
    '            } elseif ($pedidoStatus === ''cancelado'') {'
    '                $totais[''cancelados'']++;'
    '                $totais[''taxa_retida_cancelamento''] += (float)($r[''taxa_cancelamento''] ?? 0);'
    '            }'
    '        }'
    ''
    '        require_once __DIR__ . ''/../Models/Configuracao.php'';'
    '        $cfg = Configuracao::getAll();'
    '        $systemMode = (string)($cfg[''system_mode''] ?? ''production'');'
    '        $comissaoPercent = (float)($cfg[''comissao_plataforma''] ?? 0.15) * 100;'
    ''
    '        $csrfToken = AuthService::gerarCsrfToken();'
    ''
    '        require __DIR__ . ''/../Views/oficina/financeiro.php'';'
    '    }'
)

$novo = @()
$novo += $linhas[0..($idxInicio - 1)]
$novo += $bloco
$novo += $linhas[($idxFim + 1)..($linhas.Count - 1)]
$texto = $novo -join "`r`n"

# ------------------------------------------------------------
# 4. Apagar financeiroPage() duplicado
#    Localiza 'public function financeiroPage()' e remove do inicio do
#    comentario /** acima ate o '}' de fechamento (contagem de chaves).
# ------------------------------------------------------------
$linhas2 = $texto -split "`r?`n"
$idxPage = -1
for ($i = 0; $i -lt $linhas2.Count; $i++) {
    if ($linhas2[$i] -match '^\s*public function\s+financeiroPage\s*\(\s*\)\s*:\s*void') {
        $idxPage = $i
        break
    }
}

if ($idxPage -ge 0) {
    Write-Host "[INFO] financeiroPage() encontrado na linha $($idxPage + 1). Removendo..." -ForegroundColor Yellow

    # Volta para tras para incluir o comentario /** */ acima, se existir
    $idxPageIni = $idxPage
    for ($k = $idxPage - 1; $k -ge 0; $k--) {
        if ($linhas2[$k] -match '^\s*/\*\*') {
            $idxPageIni = $k
            break
        }
        if ($linhas2[$k] -match '\S' -and $linhas2[$k] -notmatch '^\s*\*' -and $linhas2[$k] -notmatch '^\s*/\*\*') {
            break
        }
    }

    # Conta chaves para achar o fim
    $abre2 = 0; $fecha2 = 0; $comecou2 = $false; $idxPageFim = -1
    for ($j = $idxPage; $j -lt $linhas2.Count; $j++) {
        $s = $linhas2[$j] -replace "'[^']*'", "''" -replace '"[^"]*"', '""'
        $s = $s -replace '//.*$', ''
        $abre2  += ([regex]::Matches($s, '\{')).Count
        $fecha2 += ([regex]::Matches($s, '\}')).Count
        if ($abre2 -gt 0) { $comecou2 = $true }
        if ($comecou2 -and $abre2 -eq $fecha2) { $idxPageFim = $j; break }
    }

    if ($idxPageFim -ge 0) {
        $novo2 = @()
        if ($idxPageIni -gt 0) { $novo2 += $linhas2[0..($idxPageIni - 1)] }
        if (($idxPageFim + 1) -lt $linhas2.Count) { $novo2 += $linhas2[($idxPageFim + 1)..($linhas2.Count - 1)] }
        $texto = $novo2 -join "`r`n"
        Write-Host "[INFO] financeiroPage() removido (linhas $($idxPageIni + 1) a $($idxPageFim + 1))." -ForegroundColor Green
    } else {
        Write-Host "[AVISO] Fim do financeiroPage() nao encontrado; nada removido." -ForegroundColor Yellow
    }
} else {
    Write-Host "[INFO] financeiroPage() ja nao existia." -ForegroundColor Yellow
}

# ------------------------------------------------------------
# 5. Gravar + validar
# ------------------------------------------------------------
[System.IO.File]::WriteAllText($controller, $texto, $utf8NoBom)

& 'C:\xampp\php\php.exe' -l $controller
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup..." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}

Write-Host ''
Write-Host "[DONE] Controller refatorado." -ForegroundColor Green
Write-Host "Para reverter: Copy-Item '$bak' '$controller' -Force" -ForegroundColor Yellow