<?php
$bp        = defined('BASE_PATH') ? BASE_PATH : '';
$pedidoId  = (int)($pedido['id'] ?? 0);
$status    = $pedido['status'] ?? '';

// Mapa de progresso dos steps
$statusOrder = ['aguardando_pagamento', 'aguardando_guincho', 'a_caminho', 'no_local', 'em_reboque', 'concluido'];
$currentIdx  = array_search($status, $statusOrder, true);
$currentIdx  = $currentIdx === false ? 0 : (int)$currentIdx;

$steps = [
    ['label' => 'Pedido Criado',   'icon' => 'fa-check',          'key' => 'aguardando_pagamento'],
    ['label' => 'Guincho Aceito',  'icon' => 'fa-truck',          'key' => 'aguardando_guincho'],
    ['label' => 'A Caminho',       'icon' => 'fa-route',          'key' => 'a_caminho'],
    ['label' => 'No Local',        'icon' => 'fa-map-pin',        'key' => 'no_local'],
    ['label' => 'Concluído',       'icon' => 'fa-flag-checkered', 'key' => 'concluido'],
];

$latOrigem  = (float)($pedido['lat_origem']   ?? -23.5505);
$lngOrigem  = (float)($pedido['lng_origem']   ?? -46.6333);
$latDestino = (float)($pedido['lat_destino']  ?? -23.5505);
$lngDestino = (float)($pedido['lng_destino']  ?? -46.6333);
$latGuincho = $pedido['lat_guincho'] ?? null;
$lngGuincho = $pedido['lng_guincho'] ?? null;

include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div><div class="page-title"><i class="fas fa-truck-fast me-2 text-primary-custom"></i>Acompanhe seu Pedido #<?php echo $pedidoId; ?></div></div>
    </div>

    <!-- Status steps dinâmicos -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="status-steps">
                <?php foreach ($steps as $i => $step):
                    $stepIdx = (int)array_search($step['key'], $statusOrder, true);
                    $cls = $stepIdx < $currentIdx ? 'done' : ($stepIdx === $currentIdx ? 'active' : '');
                ?>
                <div class="step <?php echo $cls; ?>">
                    <div class="step-icon"><i class="fas <?php echo $step['icon']; ?>"></i></div>
                    <div class="step-label"><?php echo $step['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-map me-2"></i>Rastreamento em Tempo Real</div>
                <div class="card-body p-0">
                    <div id="map" class="map-container" style="height:380px;border-radius:0 0 12px 12px;min-height:240px"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 d-flex flex-column gap-4">
            <div class="card" id="cardGuincho">
                <div class="card-header"><i class="fas fa-id-card me-2"></i>Seu Guincho</div>
                <div class="card-body">
                    <?php if (!empty($pedido['guincho_operador'])): ?>
                    <p><strong>Nome:</strong> <span id="gNome"><?php echo htmlspecialchars($pedido['guincho_operador']); ?></span></p>
                    <p><strong>Contato:</strong> <span id="gTel"><?php echo htmlspecialchars($pedido['guincho_telefone'] ?? '—'); ?></span></p>
                    <p><strong>Placa:</strong> <span id="gPlaca"><?php echo htmlspecialchars($pedido['guincho_placa'] ?? '—'); ?></span></p>
                    <?php else: ?>
                    <p id="guinchoAguardando" class="text-muted">Aguardando atribuição de guincho...</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card flex-fill">
                <div class="card-header"><i class="fas fa-comments me-2"></i>Chat com o Guincho</div>
                <div class="card-body p-2">
                    <div class="chat-box" id="chatBox" style="max-height:260px;overflow-y:auto;">
                        <?php foreach ($mensagens ?? [] as $msg):
                            $ehCliente = ($msg['remetente_tipo'] === 'cliente');
                            $cls       = $ehCliente ? 'mine' : 'other';
                            $nome      = $ehCliente ? 'Você' : htmlspecialchars($msg['remetente_nome'] ?? 'Guincho');
                        ?>
                        <div class="chat-msg <?php echo $cls; ?>" data-id="<?php echo (int)$msg['id']; ?>">
                            <div class="sender"><?php echo $nome; ?></div>
                            <div class="bubble"><?php echo htmlspecialchars($msg['mensagem']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <input type="text" id="msgInput" class="form-control form-control-sm"
                               placeholder="Mensagem..." maxlength="1000"
                               <?php echo in_array($status, ['concluido','cancelado']) ? 'disabled' : ''; ?>>
                        <button id="btnEnviar" class="btn btn-primary btn-sm px-3"
                                <?php echo in_array($status, ['concluido','cancelado']) ? 'disabled' : ''; ?>>
                            Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?php echo $bp; ?>/public/assets/js/app.js"></script>
<script>
(function () {
    var PEDIDO_ID   = <?php echo $pedidoId; ?>;
    var BP          = <?php echo json_encode($bp); ?>;
    var CSRF        = <?php echo json_encode($csrfToken ?? ''); ?>;
    var STATUS      = <?php echo json_encode($status); ?>;
    var latOrigem   = <?php echo json_encode($latOrigem); ?>;
    var lngOrigem   = <?php echo json_encode($lngOrigem); ?>;
    var latDestino  = <?php echo json_encode($latDestino); ?>;
    var lngDestino  = <?php echo json_encode($lngDestino); ?>;
    var latGuincho  = <?php echo json_encode($latGuincho); ?>;
    var lngGuincho  = <?php echo json_encode($lngGuincho); ?>;

    var map, markerOrigem, markerDestino, markerGuincho;
    var ultimoMsgId = 0;
    var pollingChat, pollingStatus;

    // ── Inicializa mapa ──────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        map = L.map('map').setView([latOrigem, lngOrigem], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        markerOrigem  = L.marker([latOrigem, lngOrigem]).addTo(map).bindPopup('Origem');
        markerDestino = L.marker([latDestino, lngDestino]).addTo(map).bindPopup('Destino');

        if (latGuincho && lngGuincho) {
            markerGuincho = L.marker([latGuincho, lngGuincho], {
                icon: L.divIcon({ className: '', html: '<i class="fas fa-truck" style="color:#f90;font-size:1.4rem"></i>' })
            }).addTo(map).bindPopup('Guincho');
        }

        var bounds = L.latLngBounds([[latOrigem, lngOrigem],[latDestino, lngDestino]]);
        map.fitBounds(bounds.pad(0.25));

        // inicia polls apenas para pedidos ativos
        if (!['concluido','cancelado'].includes(STATUS)) {
            iniciarPollingStatus();
            iniciarPollingChat();
        }

        // rola chat para o final
        scrollChat();

        // captura último id já carregado
        document.querySelectorAll('#chatBox .chat-msg[data-id]').forEach(function (el) {
            var id = parseInt(el.dataset.id, 10);
            if (id > ultimoMsgId) ultimoMsgId = id;
        });

        // envio de mensagem
        document.getElementById('btnEnviar').addEventListener('click', enviarMensagem);
        document.getElementById('msgInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensagem(); }
        });
    });

    // ── Chat ─────────────────────────────────────────────────
    function enviarMensagem() {
        var input = document.getElementById('msgInput');
        var texto = input.value.trim();
        if (!texto) return;
        input.disabled = true;
        fetch(BP + '/cliente/chat/' + PEDIDO_ID + '/enviar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(CSRF) + '&mensagem=' + encodeURIComponent(texto)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                input.value = '';
                buscarMensagens();
            }
        })
        .finally(function () { input.disabled = false; input.focus(); });
    }

    function buscarMensagens() {
        fetch(BP + '/cliente/chat/' + PEDIDO_ID + '/mensagens?desde_id=' + ultimoMsgId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok || !data.mensagens.length) return;
            var box = document.getElementById('chatBox');
            data.mensagens.forEach(function (msg) {
                var ehCliente = (msg.remetente_tipo === 'cliente');
                var div = document.createElement('div');
                div.className = 'chat-msg ' + (ehCliente ? 'mine' : 'other');
                div.dataset.id = msg.id;
                div.innerHTML = '<div class="sender">' + (ehCliente ? 'Você' : escHtml(msg.remetente_nome || 'Guincho')) + '</div>'
                              + '<div class="bubble">' + escHtml(msg.mensagem) + '</div>';
                box.appendChild(div);
                if (msg.id > ultimoMsgId) ultimoMsgId = msg.id;
            });
            scrollChat();
        });
    }

    function iniciarPollingChat() {
        pollingChat = setInterval(buscarMensagens, 5000);
    }

    function scrollChat() {
        var box = document.getElementById('chatBox');
        box.scrollTop = box.scrollHeight;
    }

    // ── Status / mapa ────────────────────────────────────────
    function iniciarPollingStatus() {
        pollingStatus = setInterval(atualizarStatus, 10000);
    }

    function atualizarStatus() {
        fetch(BP + '/cliente/pedido/' + PEDIDO_ID + '/status-ajax')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) return;

            // atualiza guincho
            if (data.guincho_nome) {
                var el = document.getElementById('gNome');
                var aw = document.getElementById('guinchoAguardando');
                if (aw) {
                    aw.style.display = 'none';
                    var body = document.querySelector('#cardGuincho .card-body');
                    body.innerHTML = '<p><strong>Nome:</strong> <span id="gNome">' + escHtml(data.guincho_nome) + '</span></p>'
                        + '<p><strong>Contato:</strong> <span id="gTel">' + escHtml(data.guincho_tel || '—') + '</span></p>'
                        + '<p><strong>Placa:</strong> <span id="gPlaca">' + escHtml(data.guincho_placa || '—') + '</span></p>';
                } else if (el) {
                    el.textContent = data.guincho_nome;
                    var tel = document.getElementById('gTel');
                    if (tel) tel.textContent = data.guincho_tel || '—';
                    var placa = document.getElementById('gPlaca');
                    if (placa) placa.textContent = data.guincho_placa || '—';
                }
            }

            // atualiza posição do guincho no mapa
            if (data.lat_guincho && data.lng_guincho) {
                var pos = [data.lat_guincho, data.lng_guincho];
                if (markerGuincho) {
                    markerGuincho.setLatLng(pos);
                } else {
                    markerGuincho = L.marker(pos, {
                        icon: L.divIcon({ className: '', html: '<i class="fas fa-truck" style="color:#f90;font-size:1.4rem"></i>' })
                    }).addTo(map).bindPopup('Guincho');
                }
            }

            // para polls quando concluído/cancelado
            if (data.status === 'concluido' || data.status === 'cancelado') {
                clearInterval(pollingChat);
                clearInterval(pollingStatus);
                document.getElementById('msgInput').disabled = true;
                document.getElementById('btnEnviar').disabled = true;
                if (data.status === 'concluido') {
                    setTimeout(function () { window.location.href = BP + '/cliente/pedido/' + PEDIDO_ID + '/avaliar'; }, 1500);
                }
            }
        });
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }
})();
</script>
