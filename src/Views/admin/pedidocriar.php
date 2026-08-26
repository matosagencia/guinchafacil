<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-plus-circle me-2" style="color:var(--primary)"></i>Criar Pedido de Socorro</div>
            <div class="page-subtitle">Abertura manual pelo administrador</div>
        </div>
        <a href="<?php echo $bp; ?>/admin/pedidos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>

    <div class="row g-4">
        <!-- Formulário -->
        <div class="col-lg-7">
            <form method="POST" action="<?php echo $bp; ?>/admin/pedido/criar" id="formPedido">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="lat_origem"   id="latOrigem"   value="">
                <input type="hidden" name="lng_origem"   id="lngOrigem"   value="">
                <input type="hidden" name="lat_destino"  id="latDestino"  value="">
                <input type="hidden" name="lng_destino"  id="lngDestino"  value="">
                <input type="hidden" name="distancia_km" id="distanciaKm" value="0">

                <!-- 1. Cliente (AJAX search) -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-user me-2"></i>1. Selecionar Cliente</div>
                    <div class="card-body">
                        <input type="hidden" name="cliente_id" id="clienteIdHidden">
                        <div class="position-relative mb-2">
                            <input type="text" id="clienteBusca" class="form-control"
                                   placeholder="Digite o nome ou e-mail do cliente..."
                                   autocomplete="off">
                            <div id="clienteSugestoes" class="position-absolute w-100 shadow-lg rounded"
                                 style="z-index:999;top:100%;left:0;display:none;background:var(--theme-card);border:1px solid var(--theme-border);max-height:240px;overflow-y:auto"></div>
                        </div>
                        <div id="clienteSelecionado" class="p-2 rounded d-none"
                             style="background:rgba(47,179,74,.1);border:1px solid rgba(47,179,74,.3)">
                            <i class="fas fa-check-circle me-2" style="color:var(--primary)"></i>
                            <span id="clienteNomeDisplay"></span>
                            <button type="button" class="btn btn-sm btn-link float-end p-0" onclick="limparCliente()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Veículo -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-car me-2"></i>2. Veículo do Cliente</div>
                    <div class="card-body">
                        <select class="form-select" name="veiculo_id" id="veiculoSelect" required>
                            <option value="">Selecione o cliente primeiro...</option>
                        </select>
                    </div>
                </div>

                <!-- 3. Problema -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-tools me-2"></i>3. Tipo de Problema</div>
                    <div class="card-body">
                        <div class="row g-2 mb-3">
                            <?php $problemas = [
                                'pneu'       => ['🔧','Pneu furado'],
                                'bateria'    => ['🔋','Bateria descarregada'],
                                'eletrica'   => ['⚡','Problema elétrico'],
                                'colisao'    => ['💥','Colisão/Acidente'],
                                'combustivel'=> ['⛽','Sem combustível'],
                                'outro'      => ['📋','Outro problema'],
                            ]; ?>
                            <?php foreach ($problemas as $val => [$emoji, $label]): ?>
                            <div class="col-6 col-md-4">
                                <label class="d-flex align-items-center gap-2 p-2 rounded border"
                                       style="cursor:pointer;border-color:var(--theme-border)!important"
                                       id="label_<?php echo $val; ?>">
                                    <input type="radio" name="tipo_problema" value="<?php echo $val; ?>"
                                           onchange="highlightProblema()" style="display:none">
                                    <span><?php echo $emoji; ?></span>
                                    <span style="font-size:.88rem"><?php echo $label; ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <textarea class="form-control" name="descricao" rows="2"
                                  placeholder="Descreva o problema em detalhes..."></textarea>
                    </div>
                </div>

                <!-- 4. Endereços -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-map-marker-alt me-2"></i>4. Origem e Destino</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Endereço de Origem *
                                <span id="coordOrigem" class="badge ms-2" style="display:none;background:var(--primary)">
                                    <i class="fas fa-check"></i> Coordenadas OK
                                </span>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="endereco_origem" id="endOrigem"
                                       placeholder="Rua, número, cidade..." required
                                       oninput="geocodeDebounce('origem', this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="setMapMode('origem')" title="Clique no mapa">
                                    <i class="fas fa-map-pin"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Endereço de Destino *
                                <span id="coordDestino" class="badge ms-2" style="display:none;background:var(--primary)">
                                    <i class="fas fa-check"></i> Coordenadas OK
                                </span>
                            </label>
                            <!-- Oficinas do cliente como destino rápido -->
                            <div id="oficinasCliente" class="mb-2" style="display:none">
                                <div style="font-size:.78rem;color:var(--theme-muted);margin-bottom:.4rem">
                                    <i class="fas fa-star me-1" style="color:#f59e0b"></i>Oficinas favoritas do cliente:
                                </div>
                                <div id="oficinasLista" class="d-flex gap-2 flex-wrap"></div>
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" name="endereco_destino" id="endDestino"
                                       placeholder="Oficina ou destino..." required
                                       oninput="geocodeDebounce('destino', this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="setMapMode('destino')" title="Clique no mapa">
                                    <i class="fas fa-flag"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Custo -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Distância Estimada</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="distanciaDisplay" value="—" readonly
                                           style="background:var(--theme-surface2)">
                                    <span class="input-group-text">km</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Custo Estimado</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control" id="custoDisplay" value="—" readonly
                                           style="background:var(--theme-surface2)">
                                </div>
                                <div class="small mt-1" style="color:var(--theme-muted)">
                                    R$ <?php echo number_format((float)($cfg['tarifa_por_km'] ?? 5),2,',','.'); ?>/km + taxa R$ <?php echo number_format((float)($cfg['taxa_fixa'] ?? 10),2,',','.'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Guincho (opcional) -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-truck me-2"></i>5. Atribuir Guincheiro (opcional)</div>
                    <div class="card-body">
                        <select class="form-select" name="guincho_id">
                            <option value="">— Deixar em aberto (aguarda aceite) —</option>
                            <?php foreach ($guinchos as $g): ?>
                            <option value="<?php echo (int)$g['id']; ?>">
                                <?php echo htmlspecialchars($g['nome'] . ' – ' . $g['placa_guincho']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="small mt-2" style="color:var(--theme-muted)">
                            <i class="fas fa-info-circle me-1"></i>
                            Deixando em aberto, o pedido vai para a fila e qualquer guincheiro disponível pode aceitar.
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mb-4" id="btnCriar" style="font-size:1.05rem" disabled>
                    <i class="fas fa-circle-exclamation me-2"></i>Criar Pedido de Socorro
                </button>
            </form>
        </div>

        <!-- Mapa -->
        <div class="col-lg-5">
            <div class="card" style="position:sticky;top:80px">
                <div class="card-header"><i class="fas fa-map me-2"></i>Mapa — Clique para definir pontos</div>
                <div class="card-body p-0">
                    <div id="map" style="height:460px;border-radius:0"></div>
                </div>
                <div class="card-body pt-2 pb-3">
                    <div class="d-flex gap-2 mb-2">
                        <button class="btn btn-sm flex-fill" id="btnOrigem"
                                style="background:rgba(47,179,74,.15);color:var(--primary);border:1px solid rgba(47,179,74,.3)"
                                onclick="setMapMode('origem')">
                            <i class="fas fa-map-pin me-1"></i>Definir Origem
                        </button>
                        <button class="btn btn-sm flex-fill" id="btnDestino"
                                style="background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.3)"
                                onclick="setMapMode('destino')">
                            <i class="fas fa-flag me-1"></i>Definir Destino
                        </button>
                    </div>
                    <div id="mapStatus" class="small text-center" style="color:var(--theme-muted)">
                        Clique em "Definir Origem" e depois clique no mapa
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const bp = '<?php echo $bp; ?>';
const tarifaKm = <?php echo (float)($cfg['tarifa_por_km'] ?? 5); ?>;
const taxaFixa = <?php echo (float)($cfg['taxa_fixa'] ?? 10); ?>;

// ── MAPA ──────────────────────────────────────────────────────────
const map = L.map('map').setView([-23.5505, -46.6333], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

let mapMode = null, markerO = null, markerD = null;

const iconO = L.divIcon({
    html: '<div style="background:#2fb34a;width:18px;height:18px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4)"></div>',
    iconAnchor: [9,9], className: ''
});
const iconD = L.divIcon({
    html: '<div style="background:#ef4444;width:18px;height:18px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4)"></div>',
    iconAnchor: [9,9], className: ''
});

function setMapMode(mode) {
    mapMode = mode;
    const status = mode === 'origem' ? '📍 Clique no mapa para definir a ORIGEM' : '🏁 Clique no mapa para definir o DESTINO';
    document.getElementById('mapStatus').textContent = status;
    document.getElementById('btnOrigem').style.fontWeight = mode === 'origem' ? '700' : '400';
    document.getElementById('btnDestino').style.fontWeight = mode === 'destino' ? '700' : '400';
    map.getContainer().style.cursor = 'crosshair';
}

map.on('click', function(e) {
    if (!mapMode) return;
    const {lat, lng} = e.latlng;
    setCoordinate(mapMode, lat, lng);
    // Reverse geocode
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=pt-BR`)
        .then(r => r.json()).then(d => {
            const addr = d.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            document.getElementById(mapMode === 'origem' ? 'endOrigem' : 'endDestino').value = addr;
        }).catch(() => {});
    mapMode = null;
    map.getContainer().style.cursor = '';
    document.getElementById('mapStatus').textContent = 'Ponto definido! Selecione outro se precisar ajustar.';
    recalcularCusto();
});

function setCoordinate(tipo, lat, lng) {
    if (tipo === 'origem') {
        document.getElementById('latOrigem').value = lat;
        document.getElementById('lngOrigem').value = lng;
        document.getElementById('coordOrigem').style.display = 'inline';
        if (markerO) map.removeLayer(markerO);
        markerO = L.marker([lat, lng], {icon: iconO}).addTo(map).bindPopup('Origem');
    } else {
        document.getElementById('latDestino').value = lat;
        document.getElementById('lngDestino').value = lng;
        document.getElementById('coordDestino').style.display = 'inline';
        if (markerD) map.removeLayer(markerD);
        markerD = L.marker([lat, lng], {icon: iconD}).addTo(map).bindPopup('Destino');
    }
}

// ── GEOCODING ─────────────────────────────────────────────────────
let geocodeTimer = null;
function geocodeDebounce(tipo, addr) {
    clearTimeout(geocodeTimer);
    geocodeTimer = setTimeout(() => geocodeAddr(tipo, addr), 900);
}

function geocodeAddr(tipo, addr) {
    if (addr.length < 6) return;
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr + ', Brasil')}&limit=1&accept-language=pt-BR`)
        .then(r => r.json()).then(data => {
            if (!data[0]) return;
            const lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
            setCoordinate(tipo, lat, lng);
            recalcularCusto();
            map.flyTo([lat, lng], 15, {duration: 1});
        }).catch(() => {});
}

// ── CUSTO ─────────────────────────────────────────────────────────
function recalcularCusto() {
    const latO = parseFloat(document.getElementById('latOrigem').value);
    const lngO = parseFloat(document.getElementById('lngOrigem').value);
    const latD = parseFloat(document.getElementById('latDestino').value);
    const lngD = parseFloat(document.getElementById('lngDestino').value);
    if (!latO || !latD || !lngO || !lngD) return;
    const R = 6371;
    const dLat = (latD - latO) * Math.PI / 180;
    const dLng = (lngD - lngO) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(latO*Math.PI/180) * Math.cos(latD*Math.PI/180) * Math.sin(dLng/2)**2;
    const dist = R * 2 * Math.asin(Math.sqrt(a));
    const custo = (tarifaKm * dist + taxaFixa);
    document.getElementById('distanciaKm').value = dist.toFixed(2);
    document.getElementById('distanciaDisplay').value = dist.toFixed(1);
    document.getElementById('custoDisplay').value = custo.toLocaleString('pt-BR', {minimumFractionDigits:2});
    if (markerO && markerD) map.fitBounds([[latO,lngO],[latD,lngD]], {padding:[40,40]});
    atualizarBotaoCriar();
}

// ── CLIENTE AJAX SEARCH ───────────────────────────────────────────
let selectedCliente = null;
let clienteTimer = null;

document.getElementById('clienteBusca').addEventListener('input', function() {
    clearTimeout(clienteTimer);
    const q = this.value.trim();
    if (q.length < 2) { fecharSugestoes(); return; }
    clienteTimer = setTimeout(() => buscarClientes(q), 350);
});

document.getElementById('clienteBusca').addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharSugestoes();
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('#clienteBusca') && !e.target.closest('#clienteSugestoes')) fecharSugestoes();
});

function buscarClientes(q) {
    fetch(`${bp}/admin/clientes/ajax?q=${encodeURIComponent(q)}`)
        .then(r => r.json()).then(data => {
            const box = document.getElementById('clienteSugestoes');
            if (!data.clientes || !data.clientes.length) {
                box.innerHTML = '<div style="padding:.75rem;color:var(--theme-muted);font-size:.85rem">Nenhum cliente encontrado</div>';
                box.style.display = 'block';
                return;
            }
            box.innerHTML = data.clientes.map(c =>
                `<div class="sugestao-item" style="padding:.65rem 1rem;cursor:pointer;border-bottom:1px solid var(--theme-border);font-size:.9rem"
                     onclick="selecionarCliente(${c.id}, '${c.nome.replace(/'/g,"\\'")} (${c.email.replace(/'/g,"\\'")})', ${c.id})">
                    <i class="fas fa-user me-2" style="color:var(--primary)"></i>
                    <strong>${c.nome}</strong>
                    <span style="color:var(--theme-muted);font-size:.8rem"> &nbsp;${c.email}</span>
                </div>`
            ).join('');
            box.style.display = 'block';
        }).catch(() => fecharSugestoes());
}

function selecionarCliente(id, label, clienteId) {
    selectedCliente = id;
    document.getElementById('clienteIdHidden').value = id;
    document.getElementById('clienteBusca').value = '';
    document.getElementById('clienteNomeDisplay').textContent = label;
    document.getElementById('clienteSelecionado').classList.remove('d-none');
    fecharSugestoes();
    carregarVeiculos(id);
    carregarOficinas(id);
    atualizarBotaoCriar();
}

function limparCliente() {
    selectedCliente = null;
    document.getElementById('clienteIdHidden').value = '';
    document.getElementById('clienteBusca').value = '';
    document.getElementById('clienteSelecionado').classList.add('d-none');
    document.getElementById('veiculoSelect').innerHTML = '<option value="">Selecione o cliente primeiro...</option>';
    document.getElementById('veiculoSelect').disabled = true;
    document.getElementById('oficinasCliente').style.display = 'none';
    atualizarBotaoCriar();
}

function fecharSugestoes() {
    document.getElementById('clienteSugestoes').style.display = 'none';
}

// Hover effect
document.getElementById('clienteSugestoes').addEventListener('mouseover', function(e) {
    const item = e.target.closest('.sugestao-item');
    if (item) item.style.background = 'var(--theme-surface2)';
});
document.getElementById('clienteSugestoes').addEventListener('mouseout', function(e) {
    const item = e.target.closest('.sugestao-item');
    if (item) item.style.background = '';
});

// ── VEÍCULOS AJAX ─────────────────────────────────────────────────
function carregarVeiculos(clienteId) {
    const sel = document.getElementById('veiculoSelect');
    sel.disabled = true;
    sel.innerHTML = '<option>Carregando...</option>';
    fetch(`${bp}/admin/veiculos/ajax?cliente_id=${clienteId}`)
        .then(r => r.json()).then(data => {
            if (data.veiculos && data.veiculos.length) {
                sel.innerHTML = '<option value="">Selecione o veículo...</option>' +
                    data.veiculos.map(v => `<option value="${v.id}">${v.marca} ${v.modelo} — ${v.placa}</option>`).join('');
                sel.disabled = false;
            } else {
                sel.innerHTML = '<option value="">Este cliente não tem veículos cadastrados</option>';
            }
        }).catch(() => { sel.innerHTML = '<option value="">Erro ao carregar veículos</option>'; });
}

// ── OFICINAS AJAX ─────────────────────────────────────────────────
function carregarOficinas(clienteId) {
    fetch(`${bp}/admin/oficinas/ajax?cliente_id=${clienteId}`)
        .then(r => r.json()).then(data => {
            const box = document.getElementById('oficinasCliente');
            const lista = document.getElementById('oficinasLista');
            if (data.oficinas && data.oficinas.length) {
                lista.innerHTML = data.oficinas.map(o =>
                    `<button type="button" class="btn btn-sm"
                             style="background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.3);font-size:.78rem"
                             onclick="usarOficina('${o.endereco.replace(/'/g,"\\'")}', ${o.lat||0}, ${o.lng||0})">
                        <i class="fas fa-tools me-1"></i>${o.nome}
                     </button>`
                ).join('');
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        }).catch(() => {});
}

function usarOficina(endereco, lat, lng) {
    document.getElementById('endDestino').value = endereco;
    if (lat && lng) {
        setCoordinate('destino', lat, lng);
        recalcularCusto();
    } else {
        geocodeAddr('destino', endereco);
    }
}

// ── HIGHLIGHT PROBLEMA ────────────────────────────────────────────
function highlightProblema() {
    document.querySelectorAll('[name="tipo_problema"]').forEach(r => {
        const lbl = r.closest('label');
        if (r.checked) {
            lbl.style.borderColor = 'var(--primary)';
            lbl.style.background = 'rgba(47,179,74,.1)';
        } else {
            lbl.style.borderColor = 'var(--theme-border)';
            lbl.style.background = '';
        }
    });
    atualizarBotaoCriar();
}

// ── VALIDAÇÃO BOTÃO CRIAR ─────────────────────────────────────────
function atualizarBotaoCriar() {
    const temCliente  = !!document.getElementById('clienteIdHidden').value;
    const temVeiculo  = !!document.getElementById('veiculoSelect').value;
    const temProblema = !!document.querySelector('[name="tipo_problema"]:checked');
    const temOrigem   = !!document.getElementById('latOrigem').value;
    const temDestino  = !!document.getElementById('latDestino').value;
    const ok = temCliente && temVeiculo && temProblema && temOrigem && temDestino;
    const btn = document.getElementById('btnCriar');
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.5';
}

document.getElementById('veiculoSelect').addEventListener('change', atualizarBotaoCriar);
</script>
