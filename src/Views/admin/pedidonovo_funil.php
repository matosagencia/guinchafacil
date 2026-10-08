<?php
/**
 * /admin/pedido/novo/funil - Etapa 2 do funil admin (Caminho 3).
 *
 * Reaproveita 100% do funil publico v2:
 *   - public-pre-cotacao.css / public-pre-cotacao-map.css
 *   - components/address-picker.css + .js
 *   - public-pre-cotacao-flow.js (motor de estagios)
 *   - /api/pre-cotacao/* (endpoints publicos, sem auth)
 *
 * Diferencas em relacao ao /pre-cotacao publico:
 *   - header.php + sidebar_admin.php + footer.php (shell admin)
 *   - banner "criando em nome de: <cliente>"
 *   - 3 hidden inputs (cliente_id, veiculo_id, guincho_id) do contexto
 *   - form action aponta pra /admin/pedido/criar
 *
 * Variaveis do controller:
 *   $bp, $csrf_token, $contexto (cliente_nome, veiculo_resumo, guincho_nome)
 *   $adminCtx (cliente_id, veiculo_id, guincho_id)
 */
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$ctx = $contexto ?? [];
$adminCtx = $adminCtx ?? [];
$csrf_token = $csrf_token ?? '';

include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao.css?v=20260812-3">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/public-pre-cotacao-map.css?v=20260924-1">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/components/address-picker.css?v=20260929-11">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/admin-pedido-flow.css?v=20261002-1">

<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <div class="admin-flow-context">
        <div>
            <span class="admin-flow-context__label">Criando pedido em nome de:</span>
            <strong><?= $e($ctx['cliente_nome'] ?? '') ?></strong>
            <span class="text-muted">
                 Veículo: <?= $e($ctx['veiculo_resumo'] ?? '') ?>
            </span>
        </div>
        <a href="<?= $e($bp) ?>/admin/pedido/novo/v2" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-pen me-1"></i>Trocar contexto
        </a>
    </div>

    <div class="card">
        <p class="eyebrow">Passo 2 de 2</p>
        <h1 class="title" id="admin-flow-title">Onde está o veículo?</h1>
        <p class="muted mb-0">Funil idêntico ao público. Ao final, o pedido é criado em nome do cliente acima.</p>

        <div class="mt-4">
            <?php require __DIR__ . '/partials/_precotacao_funil_admin.php'; ?>
        </div>
    </div>

</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>window.__gfBasePath = <?= json_encode($bp) ?>;</script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/js/components/address-picker.js?v=20260929-11"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
window.__gfFlowOptions = window.__gfFlowOptions || {};
<?php if (!empty($adminCtx['veiculo_id']) && !empty($adminCtx['veiculo_categoria'])): ?>
window.__gfFlowOptions.skipVeiculo = true;
window.__gfFlowOptions.veiculoCategoria = <?= json_encode($adminCtx['veiculo_categoria']) ?>;
<?php endif; ?>
</script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $e($bp) ?>/public/assets/js/public-pre-cotacao-flow.js?v=20260929-11"></script>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
    'use strict';
    var TITLES = {
        'endereco': 'Onde está o veículo?',
        'modo':     'O que você precisa?',
        'veiculo':  'Qual o tipo do veículo?',
        'sintoma':  'O que está acontecendo?',
        'opcoes':   'Como você quer prosseguir?',
        'destino':  'Para onde levar o veículo?',
        'cotacao':  'Confirme seu pedido'
    };
    document.addEventListener('gf:flow-stage', function (ev) {
        var stage = ev && ev.detail && ev.detail.stage;
        var el = document.getElementById('admin-flow-title');
        if (!el || !stage || !TITLES[stage]) return;
        el.textContent = TITLES[stage];
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>