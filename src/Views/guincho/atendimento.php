<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$statusLabels = [
    'a_caminho'  => ['label' => 'A Caminho',   'icon' => 'fa-truck-fast',     'cor' => 'warning'],
    'no_local'   => ['label' => 'No Local',     'icon' => 'fa-map-pin',        'cor' => 'info'],
    'em_reboque' => ['label' => 'Em Reboque',   'icon' => 'fa-truck-ramp-box', 'cor' => 'primary'],
    'concluido'  => ['label' => 'Concluído',    'icon' => 'fa-circle-check',   'cor' => 'success'],
];
$proximoStatus = [
    'a_caminho'  => ['acao' => 'Cheguei ao Local',    'icone' => 'fa-map-pin'],
    'no_local'   => ['acao' => 'Iniciar Reboque',     'icone' => 'fa-truck-ramp-box'],
    'em_reboque' => ['acao' => 'Finalizar Corrida',   'icone' => 'fa-flag-checkered'],
];
$statusAtual = $pedido['status'] ?? 'a_caminho';
$info = $statusLabels[$statusAtual] ?? $statusLabels['a_caminho'];
$proximo = $proximoStatus[$statusAtual] ?? null;
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-truck-fast me-2 text-primary-custom"></i>Atendimento #<?php echo (int)$pedido['id']; ?>
            </div>
            <div class="page-subtitle">
                <span class="badge bg-<?php echo $info['cor']; ?>">
                    <i class="fas <?php echo $info['icon']; ?> me-1"></i><?php echo $info['label']; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- COLUNA ESQUERDA: mapa -->
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-map-location-dot me-2"></i>Rota</div>
                <div class="card-body p-0">
                    <div id="map" style="height:380px;border-radius:0 0 12px 12px;min-height:240px"></div>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA: pedido + ações + chat -->
        <div class="col-lg-5 d-flex flex-column gap-3">

            <!-- Dados do pedido -->
            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Pedido</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] ?? '-'); ?></p>
                    <p class="mb-1"><strong>Tel:</strong> <?php echo htmlspecialchars($pedido['cliente_telefone'] ?? '-'); ?></p>
                    <p class="mb-1"><strong>Veículo:</strong> <?php echo htmlspecialchars(($pedido['marca'] ?? '') . ' ' . ($pedido['modelo'] ?? '') . ' · ' . ($pedido['placa'] ?? '')); ?></p>
                    <p class="mb-1"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($pedido['endereco_origem'] ?? '-'); ?></p>
                    <p class="mb-1"><i class="fas fa-map-pin text-success me-1"></i><?php echo htmlspecialchars($pedido['endereco_destino'] ?? '-'); ?></p>
                    <p class="mb-0"><strong class="text-success fs-5">R$ <?php echo number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.'); ?></strong>
                       <span class="text-muted small"> estimado</span></p>
                </div>
            </div>

            <!-- Ação de status -->
            <?php if ($proximo && $statusAtual !== 'concluido'): ?>
            <div class="card border-<?php echo $info['cor']; ?>">
                <div class="card-body text-center">
                    <button id="btnAtualizarStatus" class="btn btn-<?php echo $info['cor']; ?> btn-lg w-100 py-3"
                            data-pedido-id="<?php echo (int)$pedido['id']; ?>"
                            data-csrf="<?php echo htmlspecialchars($csrfToken); ?>">
                        <i class="fas <?php echo $proximo['icone']; ?> me-2"></i>
                        <?php echo htmlspecialchars($proximo['acao']); ?>
                    </button>
                    <div id="statusMsg" class="mt-2 text-muted small"></div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-success text-center mb-0">
                <i class="fas fa-circle-check fa-2x mb-2 d-block"></i>
                <strong>Corrida Concluída!</strong><br>
                O PIX será processado em breve.<br>
                <a href="<?php echo $bp; ?>/guincho/financeiro" class="btn btn-success btn-sm mt-2">Ver Financeiro</a>
            </div>
            <?php endif; ?>

            <!-- Chat -->
            <div class="card flex-grow-1">
                <div class="card-header"><i class="fas fa-comments me-2"></i>Chat com Cliente</div>
                <div class="card-body d-flex flex-column p-2" style="min-height:220px">
                    <div id="chatBox" class="flex-grow-1 overflow-auto mb-2" style="max-height:240px">
                        <?php foreach ($mensagens ?? [] as $msg): ?>
                        <div class="chat-msg <?php echo ((int)$msg['usuario_id'] === (int)($_SESSION['user']['id'] ?? 0)) ? 'mine' : 'other'; ?> mb-1">
                            <?php if ((int)$msg['usuario_id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                            <div class="sender small text-muted"><?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente'); ?></div>
                            <?php endif; ?>
                            <div class="bubble"><?php echo htmlspecialchars($msg['mensagem']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($mensagens)): ?>
                        <div class="text-center text-muted small py-3">Nenhuma mensagem ainda.</div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="text" id="msgInput" class="form-control form-control-sm" placeholder="Mensagem...">
                        <button id="btnEnviarMsg" class="btn btn-primary btn-sm px-3"
                                data-pedido-id="<?php echo (int)$pedido['id']; ?>"
                                data-csrf="<?php echo htmlspecialchars($csrfToken); ?>">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /row -->

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const BP = '<?php echo $bp; ?>';
const PEDIDO_ID = <?php echo (int)$pedido['id']; ?>;
const LAT_ORIGEM = <?php echo (float)($pedido['lat_origem'] ?? -23.5505); ?>;
const LNG_ORIGEM = <?php echo (float)($pedido['lng_origem'] ?? -46.6333); ?>;
const LAT_DEST   = <?php echo (float)($pedido['lat_destino'] ?? -23.5505); ?>;
const LNG_DEST   = <?php echo (float)($pedido['lng_destino'] ?? -46.6333); ?>;

// ── Mapa ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const map = L.map('map').setView([LAT_ORIGEM, LNG_ORIGEM], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    L.marker([LAT_ORIGEM, LNG_ORIGEM]).addTo(map).bindPopup('Origem').openPopup();
    L.marker([LAT_DEST, LNG_DEST]).addTo(map).bindPopup('Destino');
    if (LAT_ORIGEM !== LAT_DEST || LNG_ORIGEM !== LNG_DEST) {
        const bounds = L.latLngBounds([[LAT_ORIGEM, LNG_ORIGEM], [LAT_DEST, LNG_DEST]]);
        map.fitBounds(bounds, { padding: [40, 40] });
    }
});

// ── Botão de atualizar status ─────────────────────────────────────
const btnStatus = document.getElementById('btnAtualizarStatus');
if (btnStatus) {
    btnStatus.addEventListener('click', async () => {
        btnStatus.disabled = true;
        btnStatus.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aguarde...';
        try {
            const fd = new FormData();
            fd.append('csrf_token', btnStatus.dataset.csrf);
            const resp = await fetch(`${BP}/guincho/status/${PEDIDO_ID}`, { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.ok) {
                document.getElementById('statusMsg').textContent = 'Status atualizado! Recarregando...';
                setTimeout(() => location.reload(), 1200);
            } else {
                document.getElementById('statusMsg').textContent = 'Erro: ' + (data.erro || 'tente novamente');
                btnStatus.disabled = false;
                btnStatus.innerHTML = btnStatus.textContent;
            }
        } catch(e) {
            document.getElementById('statusMsg').textContent = 'Falha na conexão.';
            btnStatus.disabled = false;
        }
    });
}

// ── Chat ─────────────────────────────────────────────────────────
let ultimoMsgId = <?php echo !empty($mensagens) ? (int)end($mensagens)['id'] : 0; ?>;

async function carregarMensagens() {
    try {
        const resp = await fetch(`${BP}/guincho/chat/${PEDIDO_ID}?desde_id=${ultimoMsgId}`);
        const data = await resp.json();
        if (data.ok && data.mensagens.length > 0) {
            const box = document.getElementById('chatBox');
            data.mensagens.forEach(msg => {
                ultimoMsgId = Math.max(ultimoMsgId, msg.id);
                const div = document.createElement('div');
                div.className = 'chat-msg other mb-1';
                div.innerHTML = `<div class="sender small text-muted">Cliente</div><div class="bubble">${msg.mensagem.replace(/</g,'&lt;')}</div>`;
                box.appendChild(div);
            });
            box.scrollTop = box.scrollHeight;
        }
    } catch(e) {}
}
setInterval(carregarMensagens, 5000);

document.getElementById('btnEnviarMsg')?.addEventListener('click', async () => {
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    const btn = document.getElementById('btnEnviarMsg');
    if (!msg) return;
    input.disabled = true; btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('mensagem', msg);
        fd.append('csrf_token', btn.dataset.csrf);
        const resp = await fetch(`${BP}/guincho/chat/${PEDIDO_ID}`, { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.ok) {
            const box = document.getElementById('chatBox');
            const div = document.createElement('div');
            div.className = 'chat-msg mine mb-1';
            div.innerHTML = `<div class="bubble">${msg.replace(/</g,'&lt;')}</div>`;
            box.appendChild(div);
            box.scrollTop = box.scrollHeight;
            input.value = '';
        }
    } catch(e) {}
    input.disabled = false; btn.disabled = false;
    input.focus();
});

// Scroll inicial do chat
document.getElementById('chatBox').scrollTop = 99999;
</script>
