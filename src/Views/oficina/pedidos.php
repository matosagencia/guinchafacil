<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$tipoLabels = [
    'pneu'=>'Pneu','eletrica'=>'Elétrica','bateria'=>'Bateria',
    'mecanica'=>'Mecânica','chaveiro'=>'Chaveiro','reboque'=>'Reboque',
];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/dashboard.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-dashboard.css">
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>document.addEventListener('DOMContentLoaded',function(){document.body.classList.add('guincho','oficina');});</script>

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content">
    <div class="app-dashboard">

        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Ofertas</span>
                <h1>Pedidos disponíveis</h1>
                <p>Pedidos dentro do seu raio, ordenados por distância.</p>
            </div>
        </header>

        <section class="tow-card p-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-inbox me-2"></i>Lista de pedidos</h3>
                    <p class="tow-panel-subtitle"><?= count($pedidos ?? []) ?> pedido(s)</p>
                </div>
            </div>

            <?php if (empty($pedidos)): ?>
                <div class="ofi-empty">
                    <i class="fas fa-satellite-dish"></i>
                    <p class="mb-0">Nenhum pedido disponível agora.</p>
                    <p class="small mb-0">Assim que aparecer, você vê aqui pra aceitar em 1 clique.</p>
                </div>
            <?php else: foreach ($pedidos as $p): ?>
                <?php include __DIR__ . '/_offer_card.php'; ?>
            <?php endforeach; endif; ?>
        </section>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var BP = '<?= $bp ?>';
    function iniciarTimers() {
        document.querySelectorAll('.tow-offer-timer').forEach(function(t){
            if (!t.dataset.expira) return;
            var exp = new Date(t.dataset.expira.replace(' ','T')).getTime();
            function tick() {
                var s = Math.max(0, Math.floor((exp - Date.now())/1000));
                if (s === 0) { t.textContent = '00:00'; return; }
                t.textContent = String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
                setTimeout(tick, 1000);
            }
            tick();
        });
    }
    iniciarTimers();
    setInterval(function(){ location.reload(); }, 30000);
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>