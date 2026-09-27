<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
function ofi_saudacao(): string { $h = (int)date('G'); if ($h < 12) return 'Bom dia'; if ($h < 18) return 'Boa tarde'; return 'Boa noite'; }
$nome = trim((string)($_SESSION['user']['nome'] ?? 'Oficina'));
$online = !empty($oficina['disponivel']);
$raio = (int)($oficina['raio_atendimento_km'] ?? 10);
$recusados = $_SESSION['oficina_recusados'] ?? [];
$pedidosVisiveis = array_values(array_filter($pedidos ?? [], fn($p) => !isset($recusados[(int)$p['id']])));
$primeiroPedido = $pedidosVisiveis[0] ?? null;
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
                <span class="eyebrow">Painel operacional</span>
                <h1>Pronto para o próximo atendimento</h1>
                <p>Ofertas, atendimentos e status em tempo real.</p>
            </div>
        </header>

        <section class="tow-hero mb-4">
            <div class="tow-hero-info">
                <span class="tow-hero-eyebrow"><?= htmlspecialchars(ofi_saudacao()) ?></span>
                <h2 class="tow-hero-title"><?= htmlspecialchars($nome) ?></h2>
                <p class="tow-hero-subtitle">Raio de atendimento: <?= $raio ?> km</p>
            </div>
            <div class="tow-hero-switch">
                <span id="labelDisponivel"><?= $online ? 'Online' : 'Offline' ?></span>
                <label class="toggle-switch mb-0">
                    <input type="checkbox" id="toggleDisponivel" <?= $online ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/historico">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="tow-stat-value"><?= (int)$atendimentosHoje ?></div>
                        <div class="tow-stat-label">Atendimentos hoje</div>
                        <div class="tow-stat-hint">Concluídos no dia</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/pedidos">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-inbox"></i></div>
                        <div class="tow-stat-value"><?= count($pedidosVisiveis) ?></div>
                        <div class="tow-stat-label">Pedidos próximos</div>
                        <div class="tow-stat-hint">Dentro do seu raio</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/perfil">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-route"></i></div>
                        <div class="tow-stat-value"><?= $raio ?> km</div>
                        <div class="tow-stat-label">Raio de cobertura</div>
                        <div class="tow-stat-hint">Ajuste no Perfil</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-xl-3">
                <a class="tow-stat-link" href="<?= $bp ?>/oficina/financeiro">
                    <div class="tow-stat">
                        <div class="tow-stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="tow-stat-value">R$ --</div>
                        <div class="tow-stat-label">A receber</div>
                        <div class="tow-stat-hint">Repasses pendentes</div>
                    </div>
                </a>
            </div>
        </div>

        <section class="tow-card p-4">
            <div class="tow-panel-header mb-3">
                <div>
                    <h3 class="tow-panel-title"><i class="fas fa-bolt me-2"></i>Nova solicitação</h3>
                    <p class="tow-panel-subtitle">Aceite antes que outro prestador receba</p>
                </div>
                <a href="<?= $bp ?>/oficina/pedidos" class="btn btn-sm btn-outline-success">Ver todos</a>
            </div>

            <div id="ofertaAtivaContainer">
                <?php if ($primeiroPedido): ?>
                    <?php $p = $primeiroPedido; include __DIR__ . '/_offer_card.php'; ?>
                <?php else: ?>
                    <div class="ofi-empty">
                        <i class="fas fa-satellite-dish"></i>
                        <p class="mb-0">Aguardando novas solicitações...</p>
                        <p class="small mb-0"><?= $online ? 'Você está Online.' : 'Ative o modo Online para receber ofertas.' ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    </div>
</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    var BP = '<?= $bp ?>';
    var CSRF = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    var ONLINE = <?= $online ? 'true' : 'false' ?>;

    var toggle = document.getElementById('toggleDisponivel');
    var label = document.getElementById('labelDisponivel');

    function iniciarTimer() {
        var t = document.getElementById('ofertaTimer');
        if (!t || !t.dataset.expira) return;
        var exp = new Date(t.dataset.expira.replace(' ', 'T')).getTime();
        function tick() {
            var el = document.getElementById('ofertaTimer');
            if (!el) return;
            var s = Math.max(0, Math.floor((exp - Date.now())/1000));
            if (s === 0) { el.textContent = '00:00'; return; }
            el.textContent = String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');
            setTimeout(tick, 1000);
        }
        tick();
    }

    async function checarPedidos() {
        if (!ONLINE) return;
        try {
            var r = await fetch(BP + '/oficina/pedidos?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            // pedidos-disponiveis é JSON; /oficina/pedidos é HTML.
            var r2 = await fetch(BP + '/oficina/pedidos-disponiveis?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            var j = await r2.json();
            var container = document.getElementById('ofertaAtivaContainer');
            if (!container || !j.ok) return;
            if (j.pedidos && j.pedidos.length) {
                // Recarrega a página se houver pedido novo diferente
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(j.pedidos[0].id) !== String(atualId)) {
                    location.reload();
                }
            }
        } catch (e) { /* silencioso */ }
    }

    if (toggle) {
        toggle.addEventListener('change', async function(){
            var d = this.checked ? 1 : 0;
            this.disabled = true; label.textContent = 'Salvando...';
            try {
                var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('disponivel', d ? '1':'0');
                var r = await fetch(BP + '/oficina/disponibilidade', { method:'POST', body:fd, headers:{Accept:'application/json'} });
                var j = await r.json();
                if (j.ok) { label.textContent = j.disponivel ? 'Online':'Offline'; ONLINE = !!j.disponivel; }
                else { this.checked = !d; label.textContent = !d ? 'Online':'Offline'; }
            } catch(e) { this.checked = !d; label.textContent = !d ? 'Online':'Offline'; }
            finally { this.disabled = false; }
        });
    }

    iniciarTimer();
    setInterval(checarPedidos, 15000);
    checarPedidos();
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>