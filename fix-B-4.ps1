$ErrorActionPreference = 'Stop'
Set-Location 'C:\xampp\htdocs\guinchafacil'
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$php = 'C:\xampp\php\php.exe'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'

# ═══════════════════════════════════════════════════════════════════
# PARTE 1 — Substituir pedidoNovo() no ClienteController.php
# ═══════════════════════════════════════════════════════════════════
$ctrlPath = (Resolve-Path 'src\Controllers\ClienteController.php').Path
$ctrlBak  = "$ctrlPath.bak-fix4-$stamp"
Copy-Item $ctrlPath $ctrlBak
Write-Host "[backup] $ctrlBak" -ForegroundColor Cyan

if (-not ((& $php -l $ctrlPath 2>&1 | Out-String) -match 'No syntax errors')) {
    Write-Host "Controller corrompido ANTES de rodar — restaure primeiro." -ForegroundColor Red
    exit 1
}

$c = [IO.File]::ReadAllText($ctrlPath)

# Localiza limites do método antigo
$startMarker = '    public function pedidoNovo(): void'
$endMarker   = '    public function pedidoRascunho(): void'
$iStart = $c.IndexOf($startMarker)
$iEnd   = $c.IndexOf($endMarker, $iStart + 1)

if ($iStart -lt 0 -or $iEnd -lt 0) {
    Write-Host "Marcadores nao encontrados — abortando." -ForegroundColor Red
    Copy-Item $ctrlBak $ctrlPath -Force
    exit 1
}

$novo = @'
    public function pedidoNovo(): void
    {
        AuthService::requireAuth('cliente');
        $uid = $this->usuarioId();

        // Motor unico: reaproveita public-pre-cotacao-flow.js. Cliente ja
        // esta identificado e veiculo e passado por hidden input.
        $veiculos = Veiculo::listarPorUsuario($uid);
        if (empty($veiculos)) {
            $this->redirect('/cliente/veiculo/novo?retorno=pedido');
        }
        $veiculo = null;
        foreach ($veiculos as $v) {
            if (!empty($v['ativo'])) { $veiculo = $v; break; }
        }
        if (!$veiculo) { $veiculo = $veiculos[0]; }

        require_once __DIR__ . '/../Services/TarifaService.php';
        $veiculoCategoria = TarifaService::categoriaDeVeiculo($veiculo) ?: 'popular';

        $triagemServiceType = null;
        $tsId = (int)($_GET['service_type_id'] ?? 0);
        if ($tsId > 0) {
            $candidato = ServiceType::buscarPorId($tsId);
            if ($candidato && !empty($candidato['active'])) {
                $triagemServiceType = $candidato;
            }
        }

        $csrfToken = AuthService::gerarCsrfToken();
        require __DIR__ . '/../Views/cliente/pedidonovo.php';
    }

'@ -replace "`r?`n", "`r`n"

$c = $c.Substring(0, $iStart) + $novo + $c.Substring($iEnd)
Write-Host "[1] pedidoNovo substituido" -ForegroundColor Green

# ── Aliases localizacao/destino em pedidoCriar ──────────────────────
$before = $c.Length
$c = $c.Replace("(string)(`$_POST['endereco_origem'] ?? ''),", "(string)(`$_POST['endereco_origem'] ?? `$_POST['localizacao'] ?? ''),")
$c = $c.Replace("(string)(`$_POST['endereco_destino'] ?? ''),", "(string)(`$_POST['endereco_destino'] ?? `$_POST['destino'] ?? ''),")
if ($c.Length -ne $before) { Write-Host "[2] aliases aplicados" -ForegroundColor Green }
else { Write-Host "[2] aliases: SKIP (ja aplicados ou padrao nao casou)" -ForegroundColor Yellow }

# ── Map slug -> service_type_id (idempotente) ───────────────────────
if ($c.Contains('slugMapMotor')) {
    Write-Host "[3] map slug: SKIP (ja existe)" -ForegroundColor Yellow
} else {
    $anchorTipo = "        \$tiposValidos = ['eletrica','pneu','colisao','bateria','combustivel','outro'];"
    if (-not $c.Contains($anchorTipo)) {
        Write-Host "[3] AVISO: ancora tiposValidos nao encontrada — map NAO inserido." -ForegroundColor Yellow
    } else {
        $bloco = @'
        // §MOTOR-UNICO-01: motor publico manda tipo_problema como SLUG.
        $serviceTypeIdPostMotor = (int)($_POST['service_type_id'] ?? 0);
        if ($serviceTypeIdPostMotor > 0) {
            $tipoServicoMotor = ServiceType::buscarPorId($serviceTypeIdPostMotor);
            if ($tipoServicoMotor && !empty($tipoServicoMotor['active'])) {
                $serviceTypeId = (int)$tipoServicoMotor['id'];
                $attendanceMode = (string)($tipoServicoMotor['attendance_mode'] ?? 'TOWING');
            }
        } else {
            $slugMapMotor = [
                'pneu' => 'TIRE_CHANGE', 'eletrica' => 'ELECTRICAL_DIAGNOSIS',
                'bateria' => 'JUMP_START', 'combustivel' => 'FUEL_DELIVERY',
                'colisao' => 'TOW_CAR', 'reboque' => 'TOW_CAR',
                'mecanica' => 'MECHANICAL_ASSISTANCE',
                'me_orientem' => 'MECHANICAL_ASSISTANCE',
                'outro' => 'MECHANICAL_ASSISTANCE',
            ];
            $codigoMotor = $slugMapMotor[(string)$tipo] ?? null;
            if ($codigoMotor !== null) {
                $tipoServicoMotor = ServiceType::buscarPorCodigo($codigoMotor);
                if ($tipoServicoMotor && !empty($tipoServicoMotor['active'])) {
                    $serviceTypeId = (int)$tipoServicoMotor['id'];
                    $attendanceMode = (string)($tipoServicoMotor['attendance_mode'] ?? 'TOWING');
                }
            }
        }
'@ -replace "`r?`n", "`r`n"
        $c = $c.Replace($anchorTipo, $bloco + "`r`n" + $anchorTipo)
        Write-Host "[3] map slug inserido" -ForegroundColor Green
    }
}

[IO.File]::WriteAllText($ctrlPath, $c, $utf8NoBom)

$lint = (& $php -l $ctrlPath 2>&1 | Out-String)
if ($lint -match 'No syntax errors') {
    Write-Host "[controller] php -l OK" -ForegroundColor Green
} else {
    Copy-Item $ctrlBak $ctrlPath -Force
    Write-Host "[controller] php -l FALHOU — revertido" -ForegroundColor Red
    Write-Host $lint
    exit 1
}

# ═══════════════════════════════════════════════════════════════════
# PARTE 2 — CSS inline na view pedidonovo.php (botao + cards)
# ═══════════════════════════════════════════════════════════════════
$viewPath = (Resolve-Path 'src\Views\cliente\pedidonovo.php').Path
$viewBak  = "$viewPath.bak-fix4-$stamp"
Copy-Item $viewPath $viewBak
Write-Host "[backup] $viewBak" -ForegroundColor Cyan

$view = [IO.File]::ReadAllText($viewPath)

if ($view.Contains('.btn-main{min-height:52px')) {
    Write-Host "[view] CSS ja presente — SKIP" -ForegroundColor Yellow
} else {
    $css = @'
<style>
.btn-main{min-height:52px;border:0;border-radius:12px;background:#2fb34a;color:#fff;font-weight:800;display:block;width:100%;padding:.75rem 1rem;text-align:center}
.btn-main:hover{background:#248f3a;color:#fff}
.choice-fieldset{border:0;padding:0;margin:0}
.choice-fieldset .label{font-weight:700;font-size:.95rem;color:#405247;margin-bottom:.6rem;display:block}
.choice-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.6rem}
.choice-card{min-height:100px;padding:14px 12px;display:flex;flex-direction:column;align-items:center;text-align:center;border:1px solid #cfe3d3;border-radius:12px;background:#fff;cursor:pointer}
.choice-card i{font-size:1.5rem;color:#2fb34a;margin-bottom:.4rem}
.choice-card strong{display:block;font-size:.92rem;color:#142018;margin-bottom:.15rem}
.choice-card small{display:block;font-size:.75rem;color:#607066;line-height:1.25}
.choice-card.is-selected{border-color:#2fb34a;background:#edf8ef;box-shadow:0 0 0 2px rgba(47,179,74,.15)}
</style>
'@
    $needle = '<div class="main-wrapper shell admin-shell">'
    if ($view.Contains($needle)) {
        $view = $view.Replace($needle, $css + "`r`n" + $needle)
        [IO.File]::WriteAllText($viewPath, $view, $utf8NoBom)
        Write-Host "[view] CSS inline inserido" -ForegroundColor Green
    } else {
        Write-Host "[view] AVISO: anchor .main-wrapper nao encontrado" -ForegroundColor Yellow
    }
}

$lint = (& $php -l $viewPath 2>&1 | Out-String)
if ($lint -match 'No syntax errors') {
    Write-Host "[view] php -l OK" -ForegroundColor Green
} else {
    Copy-Item $viewBak $viewPath -Force
    Write-Host "[view] php -l FALHOU — revertido" -ForegroundColor Red
    Write-Host $lint
    exit 1
}

# ═══════════════════════════════════════════════════════════════════
Write-Host ""
Write-Host "OK — tudo aplicado." -ForegroundColor Green
Write-Host "Backups:" -ForegroundColor Cyan
Write-Host "  $ctrlBak"
Write-Host "  $viewBak"