# fix-B-admin-faturas-controller.ps1
# Adiciona 4 metodos no AdminController:
#   - faturas()            (GET /admin/faturas)      -> lista
#   - faturaDetalhe()      (GET /admin/fatura/{id})  -> detalhe
#   - faturaMarcarPaga()   (POST /admin/fatura/{id}/marcar-paga)
#   - faturaDesbloquear()  (POST /admin/fatura/{id}/desbloquear)
#
# Insere ANTES do fechamento da classe (ultimo '}' do arquivo).
# Data: 2026-10-08

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$controller = Join-Path $Repositorio 'src\Controllers\AdminController.php'
if (-not (Test-Path -LiteralPath $controller)) {
    Write-Host "[ERRO] AdminController.php nao encontrado." -ForegroundColor Red
    exit 1
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$bak = "$controller.bak-fix-B-admin-faturas-controller-$stamp"
Copy-Item -LiteralPath $controller -Destination $bak -Force
Write-Host "[BACKUP] $bak" -ForegroundColor Green

$linhas = Get-Content -LiteralPath $controller -Encoding UTF8

# 1. Localizar o ULTIMO '}' do arquivo (fechamento da classe AdminController)
$idxFim = -1
for ($i = $linhas.Count - 1; $i -ge 0; $i--) {
    if ($linhas[$i] -match '^\s*\}\s*$') {
        $idxFim = $i
        break
    }
}
if ($idxFim -lt 0) {
    Write-Host "[ERRO] Nao achei o '}' de fechamento da classe." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}
Write-Host "[INFO] Classe fecha na linha $($idxFim + 1)." -ForegroundColor Cyan

# 2. Verificar se ja tem os metodos
$texto = $linhas -join "`r`n"
if ($texto -match 'public function faturas\s*\(') {
    Write-Host "[AVISO] Metodo faturas() ja existe. Abortando." -ForegroundColor Yellow
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}

# 3. Bloco novo (4 metodos) — arrays de strings (sem here-string gigante)
$bloco = @(
    ''
    '    // ============================================================='
    '    // FATURAS (ciclo semanal de parceiros) - B, 2026-10-08'
    '    // ============================================================='
    ''
    '    /**'
    '     * GET /admin/faturas'
    '     * Lista faturas com filtros (status, parceiro_tipo, ciclo, parceiro_id).'
    '     */'
    '    public function faturas(): void'
    '    {'
    '        AuthService::requireAuth(''admin'');'
    '        require_once __DIR__ . ''/../Services/FaturaService.php'';'
    ''
    '        $filtros = ['
    '            ''status''         => trim((string)($_GET[''status''] ?? '''')),'
    '            ''parceiro_tipo''  => trim((string)($_GET[''parceiro_tipo''] ?? '''')),'
    '            ''parceiro_id''    => (int)($_GET[''parceiro_id''] ?? 0),'
    '            ''ciclo_de''       => trim((string)($_GET[''ciclo_de''] ?? '''')),'
    '            ''ciclo_ate''      => trim((string)($_GET[''ciclo_ate''] ?? '''')),'
    '        ];'
    ''
    '        $where = [];'
    '        $params = [];'
    '        if (in_array($filtros[''status''], [''aberta'',''paga'',''vencida'',''bloqueada'',''cancelada''], true)) {'
    '            $where[] = ''f.status = ?'';'
    '            $params[] = $filtros[''status''];'
    '        }'
    '        if (in_array($filtros[''parceiro_tipo''], [''guincho'',''oficina'',''especialista''], true)) {'
    '            $where[] = ''f.parceiro_tipo = ?'';'
    '            $params[] = $filtros[''parceiro_tipo''];'
    '        }'
    '        if ($filtros[''parceiro_id''] > 0) {'
    '            $where[] = ''f.parceiro_id = ?'';'
    '            $params[] = $filtros[''parceiro_id''];'
    '        }'
    '        if ($filtros[''ciclo_de''] !== '''') {'
    '            $where[] = ''f.ciclo_inicio >= ?'';'
    '            $params[] = $filtros[''ciclo_de''] . '' 00:00:00'';'
    '        }'
    '        if ($filtros[''ciclo_ate''] !== '''') {'
    '            $where[] = ''f.ciclo_fim <= ?'';'
    '            $params[] = $filtros[''ciclo_ate''] . '' 23:59:59'';'
    '        }'
    ''
    '        $sql = "SELECT f.*,'
    '                       COALESCE(u.nome, o.nome, ''(sem nome)'') AS parceiro_nome,'
    '                       COALESCE(u.email, '''') AS parceiro_email'
    '                FROM faturas_parceiro f'
    '                LEFT JOIN guinchos g ON g.id = f.parceiro_id AND f.parceiro_tipo = ''guincho'''
    '                LEFT JOIN usuarios u ON u.id = g.usuario_id'
    '                LEFT JOIN oficinas o ON o.id = f.parceiro_id AND f.parceiro_tipo = ''oficina''";'
    '        if (!empty($where)) { $sql .= '' WHERE '' . implode('' AND '', $where); }'
    '        $sql .= '' ORDER BY f.vencimento_em DESC, f.id DESC LIMIT 200'';'
    ''
    '        $stmt = getPDO()->prepare($sql);'
    '        $stmt->execute($params);'
    '        $faturas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];'
    ''
    '        $resumo = ['
    '            ''aberta''    => 0,'
    '            ''paga''      => 0,'
    '            ''vencida''   => 0,'
    '            ''bloqueada'' => 0,'
    '            ''saldo_total'' => 0.0,'
    '        ];'
    '        foreach ($faturas as $f) {'
    '            $st = (string)($f[''status''] ?? '''');'
    '            if (isset($resumo[$st])) { $resumo[$st]++; }'
    '            $resumo[''saldo_total''] += (float)($f[''saldo''] ?? 0);'
    '        }'
    ''
    '        $csrfToken = AuthService::gerarCsrfToken();'
    '        require __DIR__ . ''/../Views/admin/faturas.php'';'
    '    }'
    ''
    '    /**'
    '     * GET /admin/fatura/{id}'
    '     * Detalhe da fatura (itens + pagamentos + PIX).'
    '     */'
    '    public function faturaDetalhe(int $id): void'
    '    {'
    '        AuthService::requireAuth(''admin'');'
    '        require_once __DIR__ . ''/../Services/FaturaService.php'';'
    ''
    '        $pdo = getPDO();'
    '        $stmt = $pdo->prepare('
    '            "SELECT f.*,'
    '                    COALESCE(u.nome, o.nome, ''(sem nome)'') AS parceiro_nome,'
    '                    COALESCE(u.email, '''') AS parceiro_email'
    '             FROM faturas_parceiro f'
    '             LEFT JOIN guinchos g ON g.id = f.parceiro_id AND f.parceiro_tipo = ''guincho'''
    '             LEFT JOIN usuarios u ON u.id = g.usuario_id'
    '             LEFT JOIN oficinas o ON o.id = f.parceiro_id AND f.parceiro_tipo = ''oficina'''
    '             WHERE f.id = ? LIMIT 1"'
    '        );'
    '        $stmt->execute([$id]);'
    '        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);'
    ''
    '        if (!$fatura) {'
    '            $this->setFlashMessage(''Fatura nao encontrada.'', ''error'');'
    '            $this->redirect(''/admin/faturas'');'
    '            return;'
    '        }'
    ''
    '        $itens = $pdo->prepare('
    '            "SELECT i.*, p.tipo_problema, p.criado_em AS pedido_em,'
    '                    c.nome AS cliente_nome'
    '             FROM faturas_itens i'
    '             LEFT JOIN pedidos p ON p.id = i.pedido_id'
    '             LEFT JOIN usuarios c ON c.id = p.cliente_id'
    '             WHERE i.fatura_id = ? ORDER BY i.id ASC"'
    '        );'
    '        $itens->execute([$id]);'
    '        $itens = $itens->fetchAll(PDO::FETCH_ASSOC) ?: [];'
    ''
    '        $pagamentos = $pdo->prepare('
    '            "SELECT * FROM faturas_pagamentos WHERE fatura_id = ? ORDER BY created_at DESC"'
    '        );'
    '        $pagamentos->execute([$id]);'
    '        $pagamentos = $pagamentos->fetchAll(PDO::FETCH_ASSOC) ?: [];'
    ''
    '        $csrfToken = AuthService::gerarCsrfToken();'
    '        require __DIR__ . ''/../Views/admin/fatura_detalhe.php'';'
    '    }'
    ''
    '    /**'
    '     * POST /admin/fatura/{id}/marcar-paga'
    '     * Baixa manual (admin). Gera transacao_id interna se nao vier.'
    '     */'
    '    public function faturaMarcarPaga(int $id): void'
    '    {'
    '        AuthService::requireAuth(''admin'');'
    '        if (!AuthService::validarCsrfToken((string)($_POST[''csrf_token''] ?? ''''))) {'
    '            http_response_code(403);'
    '            exit;'
    '        }'
    '        require_once __DIR__ . ''/../Services/FaturaService.php'';'
    ''
    '        $transacaoId = trim((string)($_POST[''transacao_id''] ?? ''''));'
    '        if ($transacaoId === '''') {'
    '            $transacaoId = ''manual_admin_'' . $id . ''_'' . date(''YmdHis'');'
    '        }'
    ''
    '        $ok = FaturaService::marcarPaga($id, $transacaoId, ''manual_admin'');'
    '        if ($ok) {'
    '            $this->setFlashMessage(''Fatura #'' . $id . '' marcada como paga (transacao '' . $transacaoId . '').'', ''success'');'
    '        } else {'
    '            $this->setFlashMessage(''Nao foi possivel marcar a fatura como paga.'', ''error'');'
    '        }'
    '        $this->redirect(''/admin/fatura/'' . $id);'
    '    }'
    ''
    '    /**'
    '     * POST /admin/fatura/{id}/desbloquear'
    '     * Liberacao manual de uma fatura bloqueada.'
    '     */'
    '    public function faturaDesbloquear(int $id): void'
    '    {'
    '        AuthService::requireAuth(''admin'');'
    '        if (!AuthService::validarCsrfToken((string)($_POST[''csrf_token''] ?? ''''))) {'
    '            http_response_code(403);'
    '            exit;'
    '        }'
    '        require_once __DIR__ . ''/../Services/FaturaService.php'';'
    ''
    '        $ok = FaturaService::desbloquear($id);'
    '        if ($ok) {'
    '            $this->setFlashMessage(''Fatura #'' . $id . '' desbloqueada (liberacao manual).'', ''success'');'
    '        } else {'
    '            $this->setFlashMessage(''Fatura nao esta bloqueada ou nao foi encontrada.'', ''error'');'
    '        }'
    '        $this->redirect(''/admin/fatura/'' . $id);'
    '    }'
)

$novo = @()
$novo += $linhas[0..($idxFim - 1)]
$novo += $bloco
$novo += $linhas[$idxFim..($linhas.Count - 1)]

$textoFinal = $novo -join "`r`n"
[System.IO.File]::WriteAllText($controller, $textoFinal, $utf8NoBom)

& 'C:\xampp\php\php.exe' -l $controller
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou. Restaurando backup..." -ForegroundColor Red
    Copy-Item -LiteralPath $bak -Destination $controller -Force
    exit 1
}

Write-Host ''
Write-Host "[DONE] 4 metodos adicionados ao AdminController." -ForegroundColor Green
Write-Host "Para reverter: Copy-Item '$bak' '$controller' -Force" -ForegroundColor Yellow