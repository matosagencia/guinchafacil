# fix-B-cliente-pedido-novo-motor-unico.ps1
# Execute preferencialmente assim (não por duplo clique):
#   powershell -NoExit -ExecutionPolicy Bypass -File ".\fix-B-cliente-pedido-novo-motor-unico.ps1"

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

function Wait-Exit {
    param([int]$Code = 0)
    Write-Host ""
    Write-Host "Pressione ENTER para fechar..." -ForegroundColor DarkGray
    try { [void](Read-Host) } catch { Start-Sleep -Seconds 8 }
    exit $Code
}

try {
    Set-Location 'C:\xampp\htdocs\guinchafacil'

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    $php       = 'C:\xampp\php\php.exe'
    $stamp     = Get-Date -Format 'yyyyMMdd-HHmmss'

    if (-not (Test-Path $php)) {
        throw "PHP nao encontrado: $php"
    }

    $ctrlPath  = (Resolve-Path 'src\Controllers\ClienteController.php').Path
    $viewPath  = (Resolve-Path 'src\Views\cliente\pedidonovo.php').Path
    $partDir   = 'src\Views\cliente\partials'
    $partPath  = Join-Path $partDir '_precotacao_funil_cliente.php'

    $ctrlBak = "$ctrlPath.bak-motorunico-$stamp"
    $viewBak = "$viewPath.bak-motorunico-$stamp"
    Copy-Item $ctrlPath $ctrlBak -Force
    Copy-Item $viewPath $viewBak -Force
    Write-Host "[backup] $ctrlBak" -ForegroundColor Cyan
    Write-Host "[backup] $viewBak" -ForegroundColor Cyan

    New-Item -ItemType Directory -Force -Path $partDir | Out-Null

    function Test-Php([string]$p) {
        $out = & $php -l $p 2>&1 | Out-String
        return ($out -match 'No syntax errors')
    }

    function RollbackAll {
        Copy-Item $ctrlBak $ctrlPath -Force
        Copy-Item $viewBak $viewPath -Force
        if (Test-Path $partPath) { Remove-Item $partPath -Force -ErrorAction SilentlyContinue }
        Write-Host "    ROLLBACK TOTAL aplicado." -ForegroundColor Red
    }

    # ═════════════════════════════════════════════════════════════════
    # [1] Partial novo
    # ═════════════════════════════════════════════════════════════════
    Write-Host ""
    Write-Host "==> [1/4] Criar partial _precotacao_funil_cliente.php" -ForegroundColor Cyan

    $partial = @'
<?php
/**
 * Partial: funil cliente v2 — clone do publico _precotacao_funil.php
 * com action=/cliente/pedido/criar.
 *
 * Duplicacao deliberada, mesmo padrao de _precotacao_funil_admin.php:
 * o partial publico tem action hardcoded para /pre-cotacao; cliente
 * precisa postar em /cliente/pedido/criar (que ja tem o business logic).
 *
 * Consome 100% do motor public/assets/js/public-pre-cotacao-flow.js.
 * Nenhuma regra de estado/stage aqui — so HTML.
 *
 * Depende de: $bp, $csrf_token, $veiculo (opcional), $veiculoCategoria,
 *             $triagemServiceType (opcional).
 */
$bp = $bp ?? (defined('BASE_PATH') ? BASE_PATH : '');
$csrf_token = $csrf_token ?? '';
$veiculo = $veiculo ?? null;
$triagemServiceType = $triagemServiceType ?? null;
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<form method="post"
      action="<?= $e($bp) ?>/cliente/pedido/criar"
      class="row g-3"
      data-precotacao-form
      data-marketing-event="create_order">

    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
    <?php if ($veiculo): ?>
    <input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>">
    <?php endif; ?>
    <?php if (!empty($triagemServiceType)): ?>
    <input type="hidden" name="service_type_id" value="<?= (int)$triagemServiceType['id'] ?>">
    <?php endif; ?>

    <!-- ESTAGIO 1: ENDERECO -->
    <div class="col-12" id="stage-endereco">
        <fieldset class="choice-fieldset">
            <legend class="label">Onde esta o veiculo?</legend>
            <div class="gf-ap" data-role="origem"
                 data-ap-init-lat="-22.9068"
                 data-ap-init-lng="-43.1729">
                <div class="gf-ap__map" data-ap-map></div>
                <div class="gf-ap__pin-overlay"><i class="fas fa-location-dot"></i></div>
                <div class="gf-ap__map-title">
                    <strong>Confirme o ponto no mapa</strong>
                    <span>Arraste o pin ate o local exato.</span>
                </div>
                <div class="gf-ap__panel" data-ap-panel>
                    <button type="button" class="gf-ap__panel-toggle" data-ap-toggle>
                        <i class="fas fa-chevron-up"></i> Recolher busca
                    </button>
                    <label class="gf-ap__label">Onde esta o veiculo?</label>
                    <button type="button" class="gf-ap__gps" data-ap-gps>
                        <i class="fas fa-location-crosshairs" aria-hidden="true"></i>
                        Usar minha localizacao
                    </button>
                    <div class="gf-ap__row">
                        <div class="gf-ap__field">
                            <label class="gf-ap__field-label">Rua e cidade</label>
                            <input type="text" class="gf-ap__input" data-ap-input
                                   placeholder="Rua, bairro e cidade" autocomplete="street-address">
                        </div>
                        <div class="gf-ap__field">
                            <label class="gf-ap__field-label">Numero</label>
                            <input type="text" class="gf-ap__number" data-ap-number
                                   inputmode="numeric" placeholder="Ex.: 280" autocomplete="address-line2">
                        </div>
                    </div>
                    <small class="gf-ap__status" data-ap-status></small>
                    <div class="gf-ap__confirm" data-ap-confirm hidden>
                        <div class="gf-ap__confirm-text" data-ap-confirm-text></div>
                        <button type="button" class="gf-ap__confirm-btn" data-ap-confirm-btn>Confirmar</button>
                    </div>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- ESTAGIO 2: MODO -->
    <div class="col-12" id="stage-modo" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">O que aconteceu?</legend>
            <div class="choice-grid choice-grid-help">
                <button type="button" class="choice-card" data-mode="me_orientem" aria-pressed="false">
                    <i class="fas fa-comments" aria-hidden="true"></i>
                    <strong>Me orientem</strong>
                    <small>Nao sei qual caminho escolher.</small>
                </button>
                <button type="button" class="choice-card" data-mode="local" aria-pressed="false">
                    <i class="fas fa-user-cog" aria-hidden="true"></i>
                    <strong>Resolver no local</strong>
                    <small>Quero uma avaliacao onde estou.</small>
                </button>
                <button type="button" class="choice-card" data-mode="levar_carro" aria-pressed="false">
                    <i class="fas fa-truck-pickup" aria-hidden="true"></i>
                    <strong>Levar o carro</strong>
                    <small>Preciso de uma oficina ou destino.</small>
                </button>
            </div>
        </fieldset>
    </div>

    <!-- ESTAGIO 3: VEICULO (pulado via __gfFlowOptions.skipVeiculo) -->
    <div class="col-12" id="stage-veiculo" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Qual e o tipo de veiculo?</legend>
            <div class="choice-grid choice-grid-vehicle">
                <button type="button" class="choice-card is-selected" data-categoria="popular" aria-pressed="true">
                    <i class="fas fa-car" aria-hidden="true"></i><strong>Carro</strong>
                    <small>Veiculo de passeio comum.</small>
                </button>
                <button type="button" class="choice-card" data-categoria="moto" aria-pressed="false">
                    <i class="fas fa-motorcycle" aria-hidden="true"></i><strong>Moto</strong>
                    <small>Motocicleta ou scooter.</small>
                </button>
                <button type="button" class="choice-card" data-categoria="suv" aria-pressed="false">
                    <i class="fas fa-car-side" aria-hidden="true"></i><strong>SUV</strong>
                    <small>Veiculo alto ou utilitario esportivo.</small>
                </button>
                <button type="button" class="choice-card" data-categoria="caminhonete" aria-pressed="false">
                    <i class="fas fa-truck-pickup" aria-hidden="true"></i><strong>Caminhonete</strong>
                    <small>Picape ou veiculo de carga leve.</small>
                </button>
                <button type="button" class="choice-card" data-categoria="eletrico" aria-pressed="false">
                    <i class="fas fa-charging-station" aria-hidden="true"></i><strong>Eletrico</strong>
                    <small>Veiculo 100% eletrico.</small>
                </button>
            </div>
        </fieldset>
    </div>

    <!-- ESTAGIO 3.5: SINTOMA -->
    <div class="col-12" id="stage-sintoma" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Qual e o problema?</legend>
            <div class="choice-grid choice-grid-sintoma" id="triagem-servicos" aria-live="polite"></div>
            <div class="mt-3">
                <button type="button" class="btn btn-outline-secondary" data-action="voltar">Voltar</button>
            </div>
        </fieldset>
    </div>

    <!-- ESTAGIO 4: OPCOES -->
    <div class="col-12" id="stage-opcoes" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Escolha como quer seguir</legend>
            <div id="opcoes-wrap" aria-live="polite"></div>
        </fieldset>
    </div>

    <!-- ESTAGIO 5: DESTINO -->
    <div class="col-12" id="stage-destino" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Para onde deve levar o veiculo?</legend>
            <div class="gf-ap" data-role="destino">
                <div class="gf-ap__map" data-ap-map></div>
                <div class="gf-ap__pin-overlay"><i class="fas fa-location-dot"></i></div>
                <div class="gf-ap__map-title">
                    <strong>Para onde levar o veiculo?</strong>
                    <span>Arraste o pin ate o destino exato.</span>
                </div>
                <div class="gf-ap__panel" data-ap-panel>
                    <div class="gf-ap__row">
                        <div class="gf-ap__field">
                            <label class="gf-ap__field-label">Rua e cidade</label>
                            <input type="text" class="gf-ap__input" data-ap-input
                                   placeholder="Rua, bairro e cidade" autocomplete="street-address">
                        </div>
                        <div class="gf-ap__field">
                            <label class="gf-ap__field-label">Numero</label>
                            <input type="text" class="gf-ap__number" data-ap-number
                                   inputmode="numeric" placeholder="Ex.: 247" autocomplete="address-line2">
                        </div>
                    </div>
                    <small class="gf-ap__status" data-ap-status></small>
                    <div class="gf-ap__confirm" data-ap-confirm hidden>
                        <div class="gf-ap__confirm-text" data-ap-confirm-text></div>
                        <button type="button" class="gf-ap__confirm-btn" data-ap-confirm-btn>Confirmar</button>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-outline-secondary" data-action="voltar">Voltar</button>
            </div>
        </fieldset>
    </div>

    <!-- ESTAGIO 6: SUBMIT -->
    <div class="col-12" id="stage-cotacao" hidden>
        <button type="submit" class="btn-main w-100" id="gfBtnFinal">
            Confirmar pedido de socorro
        </button>
    </div>

    <input type="hidden" name="lat_origem" value="">
    <input type="hidden" name="lng_origem" value="">
    <input type="hidden" name="localizacao" value="">
    <input type="hidden" name="numero_origem" value="">
    <input type="hidden" name="lat_destino" value="">
    <input type="hidden" name="lng_destino" value="">
    <input type="hidden" name="destino" value="">
    <input type="hidden" name="numero_destino" value="">
    <input type="hidden" name="categoria" value="<?= $e($veiculoCategoria ?? 'popular') ?>">
    <input type="hidden" name="tipo_problema" value="">
    <input type="hidden" name="decisao_atendimento" value="">
</form>
'@

    [System.IO.File]::WriteAllText($partPath, $partial, $utf8NoBom)
    if (-not (Test-Php $partPath)) {
        RollbackAll
        & $php -l $partPath
        throw "Syntax error no partial"
    }
    Write-Host "    OK: partial criado" -ForegroundColor Green

    # ═════════════════════════════════════════════════════════════════
    # [2] pedidonovo.php
    # ═════════════════════════════════════════════════════════════════
    Write-Host ""
    Write-Host "==> [2/4] Substituir src/Views/cliente/pedidonovo.php" -ForegroundColor Cyan

    $view = @'
<?php
/**
 * /cliente/pedido/novo — wrapper do motor unico public-pre-cotacao-flow.js.
 */
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$csrf_token = $csrfToken ?? '';
$veiculo = $veiculo ?? null;
$veiculoCategoria = $veiculoCategoria ?? 'popular';
$triagemServiceType = $triagemServiceType ?? null;

$veiculoResumo = '';
if ($veiculo) {
    $veiculoResumo = trim(((string)($veiculo['marca'] ?? '')) . ' ' . ((string)($veiculo['modelo'] ?? '')));
    if (!empty($veiculo['placa'])) { $veiculoResumo .= ' — ' . $veiculo['placa']; }
}

include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao.css?v=20260812-3">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao-map.css?v=20260924-1">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/components/address-picker.css?v=20260929-11">

<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Pedir socorro</span>
            <h1><i class="fas fa-truck-pickup me-2 text-primary-custom"></i>Novo pedido</h1>
            <?php if ($veiculoResumo !== ''): ?>
            <p class="text-muted mb-0">Veículo: <strong><?= $e($veiculoResumo) ?></strong></p>
            <?php endif; ?>
        </div>
    </header>

    <div class="card">
        <?php require __DIR__ . '/partials/_precotacao_funil_cliente.php'; ?>
    </div>

</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
window.__gfBasePath = <?= json_encode($bp) ?>;
window.__gfFlowOptions = {
    skipVeiculo: true,
    veiculoCategoria: <?= json_encode($veiculoCategoria) ?>,
    consultarReboques: false
};
</script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/js/components/address-picker.js?v=20260929-11"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-flow.js?v=20260929-11"></script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
'@

    [System.IO.File]::WriteAllText($viewPath, $view, $utf8NoBom)
    if (-not (Test-Php $viewPath)) {
        RollbackAll
        & $php -l $viewPath
        throw "Syntax error em pedidonovo.php"
    }
    Write-Host "    OK: pedidonovo.php substituido" -ForegroundColor Green

    # ═════════════════════════════════════════════════════════════════
    # [3] pedidoNovo()
    # ═════════════════════════════════════════════════════════════════
    Write-Host ""
    Write-Host "==> [3/4] Atualizar ClienteController::pedidoNovo()" -ForegroundColor Cyan

    $ctrl = [IO.File]::ReadAllText($ctrlPath)

    $novoPedidoNovo = @'
    public function pedidoNovo(): void
    {
        AuthService::requireAuth('cliente');
        $uid = $this->usuarioId();

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
'@

    $pattern = '(?s)    public function pedidoNovo\(\): void\s*\{.*?\n    \}\r?\n'
    $novo = [regex]::Replace($ctrl, $pattern, ($novoPedidoNovo.TrimEnd() + "`r`n"), 1)

    if ($novo -eq $ctrl) {
        RollbackAll
        throw "pedidoNovo nao localizado — abortando."
    }

    $ctrl = $novo
    [IO.File]::WriteAllText($ctrlPath, $ctrl, $utf8NoBom)
    if (-not (Test-Php $ctrlPath)) {
        RollbackAll
        & $php -l $ctrlPath
        throw "Syntax error apos pedidoNovo"
    }
    Write-Host "    OK: pedidoNovo atualizado" -ForegroundColor Green

    # ═════════════════════════════════════════════════════════════════
    # [4] pedidoCriar() — aliases + map slug
    # ═════════════════════════════════════════════════════════════════
    Write-Host ""
    Write-Host "==> [4/4] Atualizar ClienteController::pedidoCriar()" -ForegroundColor Cyan

    # (a) alias localizacao
    $oldEndOrig = "        `$endOrigem = EnderecoFormatter::comNumeroNoTexto(`r`n            (string)(`$_POST['endereco_origem'] ?? ''),`r`n            `$numeroOrigem !== '' ? `$numeroOrigem : null`r`n        );"
    $newEndOrig = "        // MOTOR-UNICO-01: motor publico preenche 'localizacao'/'destino'`r`n        `$endOrigem = EnderecoFormatter::comNumeroNoTexto(`r`n            (string)(`$_POST['endereco_origem'] ?? `$_POST['localizacao'] ?? ''),`r`n            `$numeroOrigem !== '' ? `$numeroOrigem : null`r`n        );"

    if ($ctrl.Contains($oldEndOrig)) {
        $ctrl = $ctrl.Replace($oldEndOrig, $newEndOrig)
        Write-Host "    OK: alias localizacao -> endereco_origem" -ForegroundColor Green
    } else {
        Write-Host "    AVISO: bloco endOrigem nao casou — tentando regex flexivel" -ForegroundColor Yellow
        $ctrl = [regex]::Replace(
            $ctrl,
            "(?s)\`$endOrigem\s*=\s*EnderecoFormatter::comNumeroNoTexto\(\s*\(string\)\(\`$_POST\['endereco_origem'\]\s*\?\?\s*''\),",
            "`$endOrigem = EnderecoFormatter::comNumeroNoTexto(`r`n            (string)(`$_POST['endereco_origem'] ?? `$_POST['localizacao'] ?? ''),",
            1
        )
    }

    # (b) alias destino
    $oldEndDest = "        `$endDest   = EnderecoFormatter::comNumeroNoTexto(`r`n            (string)(`$_POST['endereco_destino'] ?? ''),`r`n            `$numeroDestino !== '' ? `$numeroDestino : null`r`n        );"
    $newEndDest = "        `$endDest   = EnderecoFormatter::comNumeroNoTexto(`r`n            (string)(`$_POST['endereco_destino'] ?? `$_POST['destino'] ?? ''),`r`n            `$numeroDestino !== '' ? `$numeroDestino : null`r`n        );"

    if ($ctrl.Contains($oldEndDest)) {
        $ctrl = $ctrl.Replace($oldEndDest, $newEndDest)
        Write-Host "    OK: alias destino -> endereco_destino" -ForegroundColor Green
    } else {
        Write-Host "    AVISO: bloco endDest nao casou — tentando regex flexivel" -ForegroundColor Yellow
        $ctrl = [regex]::Replace(
            $ctrl,
            "(?s)\`$endDest\s*=\s*EnderecoFormatter::comNumeroNoTexto\(\s*\(string\)\(\`$_POST\['endereco_destino'\]\s*\?\?\s*''\),",
            "`$endDest   = EnderecoFormatter::comNumeroNoTexto(`r`n            (string)(`$_POST['endereco_destino'] ?? `$_POST['destino'] ?? ''),",
            1
        )
    }

    # (c) map slug
    $anchorTipo = "        `$tiposValidos = ['eletrica','pneu','colisao','bateria','combustivel','outro'];"
    $blocoMap = @'
        // MOTOR-UNICO-01: tipo_problema como SLUG do motor publico
        $serviceTypeIdPostMotor = (int)($_POST['service_type_id'] ?? 0);
        if ($serviceTypeIdPostMotor > 0) {
            $tipoServicoMotor = ServiceType::buscarPorId($serviceTypeIdPostMotor);
            if ($tipoServicoMotor && !empty($tipoServicoMotor['active'])) {
                $serviceTypeId = (int)$tipoServicoMotor['id'];
                $attendanceMode = (string)($tipoServicoMotor['attendance_mode'] ?? 'TOWING');
            }
        } else {
            $slugMapMotor = [
                'pneu'        => 'TIRE_CHANGE',
                'eletrica'    => 'ELECTRICAL_DIAGNOSIS',
                'bateria'     => 'JUMP_START',
                'combustivel' => 'FUEL_DELIVERY',
                'colisao'     => 'TOW_CAR',
                'reboque'     => 'TOW_CAR',
                'mecanica'    => 'MECHANICAL_ASSISTANCE',
                'me_orientem' => 'MECHANICAL_ASSISTANCE',
                'outro'       => 'MECHANICAL_ASSISTANCE',
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
'@

    if ($ctrl.Contains($anchorTipo)) {
        $ctrl = $ctrl.Replace($anchorTipo, $blocoMap + "`r`n" + $anchorTipo)
        Write-Host "    OK: map slug -> service_type_id inserido" -ForegroundColor Green
    } else {
        Write-Host "    AVISO: ancora tiposValidos nao encontrada — map NAO inserido." -ForegroundColor Yellow
    }

    [IO.File]::WriteAllText($ctrlPath, $ctrl, $utf8NoBom)

    if (-not (Test-Php $ctrlPath)) {
        RollbackAll
        & $php -l $ctrlPath
        throw "Syntax error final no controller"
    }
    Write-Host "    php -l OK" -ForegroundColor Green

    # ═════════════════════════════════════════════════════════════════
    # VALIDACAO FINAL
    # ═════════════════════════════════════════════════════════════════
    Write-Host ""
    Write-Host "==> VALIDACAO FINAL" -ForegroundColor Green
    & $php -l $ctrlPath
    & $php -l $viewPath
    & $php -l $partPath

    Write-Host ""
    Write-Host "Backups desta execucao:" -ForegroundColor Cyan
    Write-Host "  $ctrlBak"
    Write-Host "  $viewBak"
    Write-Host ""
    Write-Host "Arquivo novo (remover no rollback):" -ForegroundColor Cyan
    Write-Host "  $partPath"
    Write-Host ""
    Write-Host "CONCLUIDO COM SUCESSO." -ForegroundColor Green

    Wait-Exit 0
}
catch {
    Write-Host ""
    Write-Host "ERRO: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.ScriptStackTrace) {
        Write-Host $_.ScriptStackTrace -ForegroundColor DarkRed
    }
    Wait-Exit 1
}