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
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/components/communications.css">
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

    // [B-OFICINA-AUDIO-UNLOCK] Desbloqueia AudioContext no 1o clique
    // (o Chrome bloqueia som ate o usuario interagir com a pagina).
    (function () {
        var unlocked = false;
        function unlock() {
            if (unlocked) return;
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                var ctx = new AC();
                if (ctx.state === 'suspended') { ctx.resume(); }
                var o = ctx.createOscillator();
                var g = ctx.createGain();
                g.gain.value = 0.0001;
                o.connect(g).connect(ctx.destination);
                o.start();
                o.stop(ctx.currentTime + 0.05);
                unlocked = true;
                document.removeEventListener('click', unlock);
                document.removeEventListener('keydown', unlock);
                document.removeEventListener('touchstart', unlock);
            } catch (e) {}
        }
        document.addEventListener('click', unlock);
        document.addEventListener('keydown', unlock);
        document.addEventListener('touchstart', unlock);
    })();
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
        if (!ONLINE) return [];
        try {
            var r = await fetch(BP + '/oficina/pedidos?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            // pedidos-disponiveis é JSON; /oficina/pedidos é HTML.
            var r2 = await fetch(BP + '/oficina/pedidos-disponiveis?_=' + Date.now(), { headers: { 'Accept':'application/json' }});
            var j = await r2.json();
            var container = document.getElementById('ofertaAtivaContainer');
            if (!container || !j.ok) return [];
            var pedidos = (j.pedidos || []);
            if (pedidos.length) {
                var atual = container.querySelector('[data-pedido-id]');
                var atualId = atual ? atual.getAttribute('data-pedido-id') : null;
                if (String(pedidos[0].id) !== String(atualId)) {
                    // [B-OFICINA-ALERTA-INLINE] Alerta + re-render SEM reload.
                    // O reload matava o AudioContext e o toast nunca saía.
                    if (window.__gfOficinaChecarAlerta) {
                        try { window.__gfOficinaChecarAlerta(pedidos); } catch (e) {}
                    }
                    renderizarOferta(pedidos[0]);
                }
            }
            return pedidos;
        } catch (e) { return []; }
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

    // [B-OFICINA-INLINE-RENDER] Re-renderiza o card sem reload,
    // preservando AudioContext e o toast em andamento.
    function renderizarOferta(p) {
        var container = document.getElementById('ofertaAtivaContainer');
        if (!container || !p) return;
        var pedidoId = p.id;
        var tipo = p.tipo_problema || 'Socorro';
        var dist = Number(p.distancia_km || 0).toFixed(1).replace('.', ',');
        var valor = Number(p.custo_estimado || 0).toFixed(2).replace('.', ',');
        var cat = p.categoria || '—';
        var expira = p.expira_em || '';
        container.innerHTML = ''
            + '<div class="tow-offer" data-pedido-id="' + pedidoId + '">'
            +   '<div class="tow-offer-head d-flex justify-content-between align-items-start">'
            +     '<div>'
            +       '<span class="tow-offer-eyebrow"><i class="fas fa-bolt me-1"></i>Nova solicita\u00e7\u00e3o</span>'
            +       '<h4 class="tow-offer-title">Pedido #' + pedidoId + '</h4>'
            +       '<p class="tow-offer-subtitle mb-0">' + tipo + '</p>'
            +     '</div>'
            +     (expira ? '<span class="tow-offer-timer" data-expira="' + expira + '">--:--</span>' : '')
            +   '</div>'
            +   '<div class="tow-offer-metrics">'
            +     '<div class="tow-offer-metric"><span>Dist\u00e2ncia</span><strong>' + dist + ' km</strong></div>'
            +     '<div class="tow-offer-metric"><span>Valor estimado</span><strong>R$ ' + valor + '</strong></div>'
            +     '<div class="tow-offer-metric"><span>Categoria</span><strong>' + cat + '</strong></div>'
            +   '</div>'
            +   '<div class="tow-offer-actions">'
            +     '<form method="post" action="' + BP + '/oficina/recusar/' + pedidoId + '" class="flex-grow-1 m-0">'
            +       '<input type="hidden" name="csrf_token" value="' + CSRF + '">'
            +       '<button type="submit" class="btn btn-outline-secondary w-100"><i class="fas fa-xmark me-1"></i>Recusar</button>'
            +     '</form>'
            +     '<form method="post" action="' + BP + '/oficina/pedido/' + pedidoId + '/aceitar" class="flex-grow-1 m-0">'
            +       '<input type="hidden" name="csrf_token" value="' + CSRF + '">'
            +       '<button type="submit" class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Aceitar</button>'
            +     '</form>'
            +   '</div>'
            + '</div>';
        iniciarTimer();
    }

    iniciarTimer();    // [B-OFICINA-ALERTA-01] Fallback de alerta (2026-10-07):
    (function () {
        'use strict';
        var SS_KEY = 'oficina_pedidos_vistos_v1';
        var vistos = {};
        try { vistos = JSON.parse(sessionStorage.getItem(SS_KEY) || '{}') || {}; } catch (e) { vistos = {}; }

        function tocarAlerta() {
            try {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                var ctx = new AC();
                var now = ctx.currentTime;
                var freqs = [880, 880];
                var dur = 0.12;
                var gap = 0.08;
                for (var i = 0; i < freqs.length; i++) {
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = freqs[i];
                    var start = now + i * (dur + gap);
                    var stop = start + dur;
                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(0.25, start + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, stop);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(start);
                    osc.stop(stop + 0.02);
                }
                setTimeout(function () { try { ctx.close(); } catch (e) {} }, 1500);
            } catch (e) {}
            var orig = document.title;
            var blink = 0;
            var timer = setInterval(function () {
                document.title = (blink % 2 === 0) ? 'NOVO PEDIDO! ' + orig : orig;
                blink++;
                if (blink >= 10) { clearInterval(timer); document.title = orig; }
            }, 800);
        }

        function mostrarToast(msg) {
            var el = document.getElementById('oficina-toast-novo-pedido');
            if (!el) {
                el = document.createElement('div');
                el.id = 'oficina-toast-novo-pedido';
                el.style.cssText = 'position:fixed;top:20px;right:20px;background:#2fb34a;color:#fff;padding:14px 20px;border-radius:10px;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.2);z-index:9999;font-size:.95rem;transition:opacity .3s;';
                document.body.appendChild(el);
            }
            el.textContent = msg;
            el.style.opacity = '1';
            clearTimeout(el.__hideTimer);
            el.__hideTimer = setTimeout(function () {
                el.style.opacity = '0';
                setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
            }, 8000);
        }

        // [B-OFICINA-TOAST-CLICK] Toast clicavel que rola ate o card
        // (clone do gfToastNovos do guincho, adaptado).
        function mostrarToastNovoPedido(qtd, pedido) {
            var id = 'oficina-toast-novo-pedido';
            var old = document.getElementById(id);
            if (old) old.remove();
            var box = document.createElement('div');
            box.id = id;
            box.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);'
                + 'background:#2fb34a;color:#fff;padding:12px 20px;border-radius:10px;'
                + 'box-shadow:0 8px 24px rgba(0,0,0,.35);z-index:2147483647;'
                + 'font:600 14px/1.3 system-ui,sans-serif;cursor:pointer;max-width:92vw';
            var titulo = qtd === 1 ? 'Novo pedido dispon\u00edvel!' : qtd + ' novos pedidos dispon\u00edveis!';
            var sub = pedido ? [pedido.marca, pedido.modelo].filter(Boolean).join(' ') : '';
            var rodape = 'Toque para ir ao pedido';
            box.innerHTML = '<div>' + titulo + '</div>'
                + (sub ? '<div style="font-weight:400;font-size:12px;margin-top:3px;opacity:.9">' + sub + '</div>' : '')
                + '<div style="font-weight:400;font-size:11px;margin-top:6px;opacity:.85">' + rodape + '</div>';
            box.addEventListener('click', function () {
                var el = document.querySelector('[data-pedido-id]');
                if (el && el.scrollIntoView) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                box.remove();
            });
            document.body.appendChild(box);
            setTimeout(function () { if (box.parentNode) box.parentNode.remove(); }, 20000);
        }

        window.__gfOficinaChecarAlerta = function (pedidos) {
            if (!Array.isArray(pedidos)) return;
            var totalNovos = 0;
            var primeiroNovo = null;
            pedidos.forEach(function (p) {
                var id = String(p.id || p.pedido_id || '');
                if (id && !vistos[id]) {
                    vistos[id] = true;
                    totalNovos++;
                    if (!primeiroNovo) primeiroNovo = p;
                }
            });
            try { sessionStorage.setItem(SS_KEY, JSON.stringify(vistos)); } catch (e) {}
            if (totalNovos > 0) {
                tocarAlerta();
                mostrarToastNovoPedido(totalNovos, primeiroNovo);
            }
        };
    })();

    setInterval(function () {
        checarPedidos().then(function (pedidos) {
            if (window.__gfOficinaChecarAlerta) {
                window.__gfOficinaChecarAlerta(pedidos);
            }
        });
    }, 15000);
    checarPedidos();
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $bp ?>/public/assets/js/core/offline-queue.js"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $bp ?>/public/assets/js/atendimento-status.js"></script>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?= $bp ?>/public/assets/js/communications.js"></script>