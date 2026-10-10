# tools/fix-session-isolation.ps1
# Isola sessoes por perfil (admin so ve admin, guincho so ve guincho, etc).
# ASCII-only. Faixa B.

param([string]$Repositorio = 'C:\xampp\htdocs\guinchafacil')
$ErrorActionPreference = 'Stop'

$Repositorio = (Resolve-Path -LiteralPath $Repositorio).Path
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

# ─────────────────────────────────────────────────────────────
# 1. AuthService::requireAuth  — tira bypass de admin, adiciona redirect
# ─────────────────────────────────────────────────────────────
$auth = Join-Path $Repositorio 'src\Services\AuthService.php'
if (-not (Test-Path -LiteralPath $auth)) { Write-Host "[ERRO] AuthService.php nao existe." -ForegroundColor Red; exit 1 }

Copy-Item -LiteralPath $auth -Destination "$auth.bak-session-$stamp" -Force
Write-Host "[BACKUP] $auth.bak-session-$stamp" -ForegroundColor Green

$t = [System.IO.File]::ReadAllText($auth, [System.Text.Encoding]::UTF8)

# Substitui o bloco de compatibilidade + forbidden() por logica nova
$blocoAntigo = @'
        // Mantém compatibilidade: admin pode visualizar áreas cliente/guincho.
        $tipo = (string)($user['tipo'] ?? '');
        if ($perfil !== null && $tipo !== $perfil && $tipo !== 'admin') {
            self::forbidden();
        }

        return $user;
'@

$blocoNovo = @'
        // Isolamento de perfil (D1=a, D2=a): usuario so acessa a area do
        // proprio tipo. Sem bypass de admin, sem "ver como". Redireciona
        // para o dashboard real quando for HTML; 403 quando for JSON.
        $tipo = (string)($user['tipo'] ?? '');
        if ($perfil !== null && $tipo !== $perfil) {
            $destino = self::dashboardDoTipo($tipo);
            if (self::isJsonRequest() || $destino === null) {
                self::forbidden();
            }
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $base . $destino, true, 302);
            exit;
        }

        return $user;
'@

$nl = if ($t.Contains("`r`n")) { "`r`n" } else { "`n" }
$blocoAntigoN = $blocoAntigo -replace "`r?`n", $nl
$blocoNovoN   = $blocoNovo   -replace "`r?`n", $nl

if (-not $t.Contains($blocoAntigoN)) {
    Write-Host "[ERRO] Bloco antigo de requireAuth nao encontrado (line endings?)." -ForegroundColor Red
    Copy-Item -LiteralPath "$auth.bak-session-$stamp" -Destination $auth -Force
    exit 1
}
$t = $t.Replace($blocoAntigoN, $blocoNovoN)
Write-Host "[PATCH] requireAuth: bypass de admin removido; redirect por perfil adicionado." -ForegroundColor Green

# Adiciona helper dashboardDoTipo() logo antes do fechamento de classe
$fechamento = "}" + $nl
$idxFim = $t.LastIndexOf($fechamento)
if ($idxFim -lt 0) {
    Write-Host "[ERRO] Nao achei o fim da classe AuthService." -ForegroundColor Red
    Copy-Item -LiteralPath "$auth.bak-session-$stamp" -Destination $auth -Force
    exit 1
}

$helper = @'
    /**
     * Mapa tipo -> rota de dashboard. Usado pelo guard de perfil em
     * requireAuth() para redirecionar o usuario para a area correta.
     * Retorna null se o tipo nao tiver dashboard (fallback para 403).
     */
    public static function dashboardDoTipo(?string $tipo): ?string
    {
        return match ($tipo) {
            'admin'        => '/admin/dashboard',
            'guincho'      => '/guincho/dashboard',
            'oficina'      => '/oficina/dashboard',
            'cliente'      => '/cliente/dashboard',
            'funcionario'  => '/funcionario/dashboard',
            'gerente'      => '/gerente/dashboard',
            'especialista' => '/especialista/dashboard',
            default        => null,
        };
    }
'@
$helperN = $helper -replace "`r?`n", $nl

$t = $t.Substring(0, $idxFim) + $helperN + $nl + $t.Substring($idxFim)
Write-Host "[ADD] dashboardDoTipo() adicionado." -ForegroundColor Green

[System.IO.File]::WriteAllText($auth, $t, $utf8NoBom)
& 'C:\xampp\php\php.exe' -l $auth
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERRO] php -l falhou em AuthService. Restaurando." -ForegroundColor Red
    Copy-Item -LiteralPath "$auth.bak-session-$stamp" -Destination $auth -Force
    exit 1
}
Write-Host "[OK] AuthService atualizado." -ForegroundColor Green

# ─────────────────────────────────────────────────────────────
# 2. header.php — remove bloco "Ver como"
# ─────────────────────────────────────────────────────────────
$header = Join-Path $Repositorio 'src\Views\layouts\header.php'
if (Test-Path -LiteralPath $header) {
    Copy-Item -LiteralPath $header -Destination "$header.bak-session-$stamp" -Force
    Write-Host "[BACKUP] $header.bak-session-$stamp" -ForegroundColor Green

    $h = [System.IO.File]::ReadAllText($header, [System.Text.Encoding]::UTF8)

    # Bloco comeca com <!-- VER COMO - admin visualiza outras areas --> e termina no </li> seguinte.
    $ini = $h.IndexOf('<!-- VER COMO - admin visualiza outras areas -->')
    if ($ini -ge 0) {
        # Acha o </li> DEPOIS do inicio, que fecha o dropdown
        $fim = $h.IndexOf('</li>', $ini)
        if ($fim -ge 0) {
            $fim += '</li>'.Length
            # Come tambem os espacos em branco / newline que sobraram
            while ($fim -lt $h.Length -and ($h[$fim] -eq ' ' -or $h[$fim] -eq "`t")) { $fim++ }
            $h = $h.Substring(0, $ini) + $h.Substring($fim)
            Write-Host "[PATCH] header.php: bloco 'Ver como' removido." -ForegroundColor Green
        } else {
            Write-Host "[AVISO] Nao achei </li> de fechamento. header.php intacto." -ForegroundColor Yellow
        }
    } else {
        Write-Host "[SKIP] header.php nao tem bloco 'Ver como'." -ForegroundColor Cyan
    }

    [System.IO.File]::WriteAllText($header, $h, $utf8NoBom)
    & 'C:\xampp\php\php.exe' -l $header
    if ($LASTEXITCODE -ne 0) {
        Write-Host "[ERRO] php -l falhou em header.php. Restaurando." -ForegroundColor Red
        Copy-Item -LiteralPath "$header.bak-session-$stamp" -Destination $header -Force
        exit 1
    }
    Write-Host "[OK] header.php atualizado." -ForegroundColor Green
} else {
    Write-Host "[SKIP] header.php nao encontrado." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[DONE] Isolamento de sessao aplicado." -ForegroundColor Green
Write-Host "Backups: *.bak-session-$stamp"
Write-Host "Reverter:"
Write-Host "  Copy-Item '$auth.bak-session-$stamp' '$auth' -Force"
if (Test-Path "$header.bak-session-$stamp") {
    Write-Host "  Copy-Item '$header.bak-session-$stamp' '$header' -Force"
}