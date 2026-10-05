<?php
/**
 * /cliente/pedido/novo  wrapper do motor unico public-pre-cotacao-flow.js.
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
    if (!empty($veiculo['placa'])) { $veiculoResumo .= '  ' . $veiculo['placa']; }
}

include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao.css?v=20260812-3">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao-map.css?v=20260924-1">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/components/address-picker.css?v=20260929-11">

<style>
.btn-main{min-height:52px;border:0;border-radius:12px;background:#2fb34a;color:#fff;font-weight:800;display:block;width:100%;padding:.75rem 1rem}
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