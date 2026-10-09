# fix-oficina-financeiro-controller.ps1
# Reescreve OficinaController::financeiro() com JOIN em pedidos+usuarios.
# Apaga OficinaController::financeiroPage() (duplicata).
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
$bak = "$controller.bak-fix-oficina-financeiro-$stamp"
Copy-Item -LiteralPath $controller -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$linhas = Get-Content -LiteralPath $controller -Encoding UTF8

# 1. Localizar inicio do financeiro()
$idxInicio = -1
for ($i = 0; $i -lt $linhas.Count; $i++) {
    if ($linhas[$i] -match '^\s*//\s*---\s*FINANCEIRO\s*---') {
        $idxInicio = $i
        break
    }
}
if ($idxInicio -lt 0) {
    Write-Host "[ERRO] Marcador '// --- FINANCEIRO ---' nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}

# 2. Localizar fim do financeiro() (primeiro 'public function' depois)
$idxFim = -1
for ($j = $idxInicio + 1; $j -lt $linhas.Count; $j++) {
    if ($linhas[$j] -match '^\s*public function\s+\w+') {
        $idxFim = $j - 1
        break
    }
}
if ($idxFim -lt 0) {
    Write-Host "[ERRO] Fim do financeiro() nao encontrado." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}

Write-Host "[INFO] financeiro() entre linhas $($idxInicio + 1) e $($idxFim + 1)." -ForegroundColor Cyan

# 3. Bloco novo do financeiro() (arrays de strings, sem here-string gigante)
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

# 4. Apagar financeiroPage() duplicado
if ($texto -match 'public function financeiroPage\(\)') {
    Write-Host "[INFO] financeiroPage() duplicado encontrado; removendo..." -ForegroundColor Yellow
    $texto = [regex]::Replace(
        $texto,
        '(?s)\s*/\*\*[^*]*\*/\s*public function financeiroPage\(\): void\s*\{.*?\n    \}\s*\r?\n',
        "`r`n",
        1
    )
    Write-Host "[INFO] financeiroPage() removido." -ForegroundColor Green
} else {
    Write-Host "[INFO] financeiroPage() ja nao existia." -ForegroundColor Yellow
}

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