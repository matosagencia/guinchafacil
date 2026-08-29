<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-pedidocriar.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-plus-circle me-2 pedidocriar-icon-accent"></i>Criar Pedido de Socorro</h1>
            <p>Abertura manual pelo administrador</p>
        </div>
        <a href="<?php echo $bp; ?>/admin/pedidos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </header>

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
                            <div id="clienteSugestoes" class="position-absolute w-100 shadow-lg rounded pedidocriar-sugestoes-box"></div>
                        </div>
                        <div id="clienteSelecionado" class="p-2 rounded d-none pedidocriar-cliente-selecionado">
                            <i class="fas fa-check-circle me-2 pedidocriar-icon-accent"></i>
                            <span id="clienteNomeDisplay"></span>
                            <button type="button" class="btn btn-sm btn-link float-end p-0" id="btnLimparCliente">
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
                                <label class="d-flex align-items-center gap-2 p-2 rounded border pedidocriar-problema-label"
                                       id="label_<?php echo $val; ?>">
                                    <input type="radio" name="tipo_problema" value="<?php echo $val; ?>"
                                           data-problema-radio class="d-none">
                                    <span><?php echo $emoji; ?></span>
                                    <span class="pedidocriar-problema-text"><?php echo $label; ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <textarea class="form-control" name="descricao" rows="2"
                                  placeholder="Descreva o problema em detalhes..."></textarea>
                    </div>
                </div>

                <!-- 3.1 Tipo de serviço (catálogo) + condições da ocorrência
                     — paridade com o painel do cliente (Etapa 2/14/15). -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-clipboard-list me-2"></i>3.1 Serviço e condições</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tipo de serviço (define o modo de atendimento e o matching)</label>
                            <select class="form-select" name="service_type_id" id="serviceTypeSelect">
                                <option value="">Reboque (padrão)</option>
                                <?php foreach (($tiposServico ?? []) as $ts): ?>
                                <option value="<?php echo (int)$ts['id']; ?>"
                                        data-mode="<?php echo htmlspecialchars($ts['attendance_mode']); ?>">
                                    <?php echo htmlspecialchars($ts['name']); ?>
                                    (<?php echo htmlspecialchars($ts['attendance_mode']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="small mt-1 pedidocriar-hint">
                                Deixando em "Reboque (padrão)", o pedido vai para a fila de reboque como sempre. Escolhendo um serviço no local (bateria, pneu, chaveiro etc.), o matching passa a exigir capacidade/compatibilidade do prestador.
                            </div>
                        </div>
                        <label class="form-label">Condições do atendimento (usadas para escolher o prestador certo):</label>
                        <div class="row g-2">
                            <div class="col-6 col-md-3 form-check ms-2">
                                <input type="checkbox" class="form-check-input" id="c_batido" name="veiculo_esta_batido" value="1">
                                <label class="form-check-label" for="c_batido">Veículo batido</label>
                            </div>
                            <div class="col-6 col-md-3 form-check ms-2">
                                <input type="checkbox" class="form-check-input" id="c_rodas" name="rodas_travadas" value="1">
                                <label class="form-check-label" for="c_rodas">Rodas travadas</label>
                            </div>
                            <div class="col-6 col-md-3 form-check ms-2">
                                <input type="checkbox" class="form-check-input" id="c_acesso" name="local_dificil_acesso" value="1">
                                <label class="form-check-label" for="c_acesso">Difícil acesso</label>
                            </div>
                            <div class="col-6 col-md-3 form-check ms-2">
                                <input type="checkbox" class="form-check-input" id="c_subsolo" name="em_garagem_subsolo" value="1">
                                <label class="form-check-label" for="c_subsolo">Garagem/subsolo</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Endereços -->
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-map-marker-alt me-2"></i>4. Origem e Destino</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Endereço de Origem *
                                <span id="coordOrigem" class="badge ms-2 pedidocriar-coord-badge">
                                    <i class="fas fa-check"></i> Coordenadas OK
                                </span>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="endereco_origem" id="endOrigem"
                                       placeholder="Rua, número, cidade..." required
                                       >
                                <button type="button" class="btn btn-outline-secondary" id="btnMapOrigemInput" title="Clique no mapa">
                                    <i class="fas fa-map-pin"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Endereço de Destino *
                                <span id="coordDestino" class="badge ms-2 pedidocriar-coord-badge">
                                    <i class="fas fa-check"></i> Coordenadas OK
                                </span>
                            </label>
                            <!-- Oficinas do cliente como destino rápido -->
                            <div id="oficinasCliente" class="mb-2" style="display:none">
                                <div class="pedidocriar-oficinas-label">
                                    <i class="fas fa-star me-1 pedidocriar-oficinas-icon"></i>Oficinas favoritas do cliente:
                                </div>
                                <div id="oficinasLista" class="d-flex gap-2 flex-wrap"></div>
                            </div>
                            <div class="input-group">
                                <input type="text" class="form-control" name="endereco_destino" id="endDestino"
                                       placeholder="Oficina ou destino (obrigatório para reboque)..."
                                       >
                                <button type="button" class="btn btn-outline-secondary" id="btnMapDestinoInput" title="Clique no mapa">
                                    <i class="fas fa-flag"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Custo -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Distância Estimada</label>
                                <div class="input-group">
                                    <input type="text" class="form-control pedidocriar-readonly-bg" id="distanciaDisplay" value="—" readonly>
                                    <span class="input-group-text">km</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Custo Estimado</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" class="form-control pedidocriar-readonly-bg" id="custoDisplay" value="—" readonly>
                                </div>
                                <div class="small mt-1 pedidocriar-hint" id="custoDetalhe">
                                    A estimativa será calculada pela mesma regra oficial usada na criação do pedido.
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
                        <div class="small mt-2 pedidocriar-hint">
                            <i class="fas fa-info-circle me-1"></i>
                            Deixando em aberto, o pedido vai para a fila e qualquer guincheiro disponível pode aceitar.
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 mb-4 pedidocriar-btn-criar" id="btnCriar" disabled>
                    <i class="fas fa-circle-exclamation me-2"></i>Criar Pedido de Socorro
                </button>
            </form>
        </div>

        <!-- Mapa -->
        <div class="col-lg-5">
            <div class="card pedidocriar-map-card">
                <div class="card-header"><i class="fas fa-map me-2"></i>Mapa — Clique para definir pontos</div>
                <div class="card-body p-0">
                    <div id="map" class="pedidocriar-map"></div>
                </div>
                <div class="card-body pt-2 pb-3">
                    <div class="d-flex gap-2 mb-2">
                        <button class="btn btn-sm flex-fill pedidocriar-btn-origem" id="btnOrigem"
                                type="button">
                            <i class="fas fa-map-pin me-1"></i>Definir Origem
                        </button>
                        <button class="btn btn-sm flex-fill pedidocriar-btn-destino" id="btnDestino"
                                type="button">
                            <i class="fas fa-flag me-1"></i>Definir Destino
                        </button>
                    </div>
                    <div id="mapStatus" class="small text-center pedidocriar-hint">
                        Clique em "Definir Origem" e depois clique no mapa
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
const bp = '<?php echo $bp; ?>';

// ── MAPA ──────────────────────────────────────────────────────────
const map = L.map('map').setView([-23.5505, -46.6333], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

let mapMode = null, markerO = null, markerD = null;

const iconO = L.divIcon({
    html: '<div class="pedidocriar-pin-origem"></div>',
    iconAnchor: [9,9], className: ''
});
const iconD = L.divIcon({
    html: '<div class="pedidocriar-pin-destino"></div>',
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
    fetch(`${bp}/geocode/reverse?lat=${lat}&lng=${lng}`)
        .then(r => r.json()).then(d => {
            const addr = (d.result && (d.result.display_name || d.result.street)) || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
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
    fetch(`${bp}/geocode?q=${encodeURIComponent(addr)}`)
        .then(r => r.json()).then(data => {
            if (!data.ok || !data.result) return;
            const lat = parseFloat(data.result.lat), lng = parseFloat(data.result.lng);
            setCoordinate(tipo, lat, lng);
            recalcularCusto();
            map.flyTo([lat, lng], 15, {duration: 1});
        }).catch(() => {});
}

// ── CUSTO ─────────────────────────────────────────────────────────
function recalcularCusto() {
    const latO = parseFloat(document.getElementById('latOrigem').value);
    const lngO = parseFloat(document.getElementById('lngOrigem').value);
    let latD = parseFloat(document.getElementById('latDestino').value);
    let lngD = parseFloat(document.getElementById('lngDestino').value);
    const modo = document.querySelector('#serviceTypeSelect option:checked')?.dataset.mode || 'TOWING';
    if (modo !== 'TOWING' && latO && lngO && (!latD || !lngD)) { latD = latO; lngD = lngO; }
    if (!latO || !latD || !lngO || !lngD) return;
    const R = 6371;
    const dLat = (latD - latO) * Math.PI / 180;
    const dLng = (lngD - lngO) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 + Math.cos(latO*Math.PI/180) * Math.cos(latD*Math.PI/180) * Math.sin(dLng/2)**2;
    const dist = R * 2 * Math.asin(Math.sqrt(a));
    document.getElementById('distanciaKm').value = dist.toFixed(2);
    document.getElementById('distanciaDisplay').value = dist.toFixed(1);
    if (markerO && markerD) map.fitBounds([[latO,lngO],[latD,lngD]], {padding:[40,40]});
    atualizarEstimativaCusto(dist);
    atualizarBotaoCriar();
}

async function atualizarEstimativaCusto(distanciaKm) {
    const veiculoId = document.getElementById('veiculoSelect').value;
    const custoDisplay = document.getElementById('custoDisplay');
    const custoDetalhe = document.getElementById('custoDetalhe');
    if (!veiculoId) {
        custoDisplay.value = '—';
        custoDetalhe.textContent = 'Selecione o veículo do cliente para carregar a tarifa oficial.';
        return;
    }

    try {
        const url = new URL(bp + '/admin/pedido/custo', window.location.origin);
        url.searchParams.set('distancia_km', distanciaKm.toFixed(2));
        url.searchParams.set('veiculo_id', veiculoId);
        const serviceType = document.getElementById('serviceTypeSelect').value;
        if (serviceType) url.searchParams.set('service_type_id', serviceType);
        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.erro || 'Falha ao carregar a tarifa');
        }

        const tarifa = payload.tarifa || {};
        custoDisplay.value = Number(payload.custo || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        if (payload.origem === 'especialista_catalogo') {
            const d = tarifa.detalhe || {};
            custoDetalhe.textContent = `Catálogo especialista • base R$ ${Number(d.base || 0).toLocaleString('pt-BR',{minimumFractionDigits:2})} • deslocamento R$ ${Number(d.distancia || 0).toLocaleString('pt-BR',{minimumFractionDigits:2})}${Number(d.noturno || 0) ? ' • adicional noturno' : ''}`;
            return;
        }
        const detalhes = [
            `${Number(tarifa.tarifa_km_aplicada || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}/km`,
            `taxa fixa R$ ${Number(tarifa.taxa_fixa_aplicada || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
            `categoria ${String(tarifa.categoria || 'popular').replace('_', ' ')}`,
        ];
        if (tarifa.is_noturno) {
            detalhes.push('adicional noturno');
        }
        custoDetalhe.textContent = detalhes.join(' • ');
    } catch (error) {
        custoDisplay.value = 'erro';
        custoDetalhe.textContent = error instanceof Error ? error.message : 'Não foi possível obter a tarifa oficial.';
    }
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
                box.innerHTML = '<div class="pedidocriar-sugestao-vazia">Nenhum cliente encontrado</div>';
                box.style.display = 'block';
                return;
            }
            box.innerHTML = data.clientes.map(c =>
                `<div class="sugestao-item"
                     data-cliente-id="${c.id}"
                     data-cliente-label="${(c.nome + ' (' + c.email + ')').replace(/"/g, '&quot;')}">
                    <i class="fas fa-user me-2 pedidocriar-icon-accent"></i>
                    <strong>${c.nome}</strong>
                    <span class="pedidocriar-sugestao-email"> &nbsp;${c.email}</span>
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
                if (document.getElementById('distanciaKm').value && Number(document.getElementById('distanciaKm').value) > 0) {
                    document.getElementById('custoDetalhe').textContent = 'Selecione o veículo para recalcular a tarifa oficial.';
                }
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
                    `<button type="button" class="btn btn-sm pedidocriar-oficina-btn"
                             data-oficina-endereco="${String(o.endereco || '').replace(/"/g, '&quot;')}"
                             data-oficina-lat="${o.lat || 0}"
                             data-oficina-lng="${o.lng || 0}">
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
    const modo = document.querySelector('#serviceTypeSelect option:checked')?.dataset.mode || 'TOWING';
    const temDestino  = modo !== 'TOWING' || !!document.getElementById('latDestino').value;
    const ok = temCliente && temVeiculo && temProblema && temOrigem && temDestino;
    const btn = document.getElementById('btnCriar');
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.5';
}

document.getElementById('veiculoSelect').addEventListener('change', atualizarBotaoCriar);
document.getElementById('serviceTypeSelect').addEventListener('change', function () {
    const towing = (this.options[this.selectedIndex]?.dataset.mode || 'TOWING') === 'TOWING';
    document.getElementById('endDestino').required = towing;
    atualizarBotaoCriar();
    if (Number(document.getElementById('distanciaKm').value || 0) > 0) atualizarEstimativaCusto(Number(document.getElementById('distanciaKm').value));
});
document.getElementById('veiculoSelect').addEventListener('change', function () {
    if (Number(document.getElementById('distanciaKm').value || 0) > 0) {
        atualizarEstimativaCusto(Number(document.getElementById('distanciaKm').value));
    }
});
document.getElementById('btnLimparCliente').addEventListener('click', limparCliente);
document.getElementById('btnMapOrigemInput').addEventListener('click', function () { setMapMode('origem'); });
document.getElementById('btnMapDestinoInput').addEventListener('click', function () { setMapMode('destino'); });
document.getElementById('btnOrigem').addEventListener('click', function () { setMapMode('origem'); });
document.getElementById('btnDestino').addEventListener('click', function () { setMapMode('destino'); });
document.getElementById('endOrigem').addEventListener('input', function () { geocodeDebounce('origem', this.value); });
document.getElementById('endDestino').addEventListener('input', function () { geocodeDebounce('destino', this.value); });
document.querySelectorAll('[data-problema-radio]').forEach(function (input) {
    input.addEventListener('change', highlightProblema);
});
document.getElementById('clienteSugestoes').addEventListener('click', function (e) {
    const item = e.target.closest('[data-cliente-id]');
    if (!item) return;
    selecionarCliente(Number(item.dataset.clienteId), item.dataset.clienteLabel || '', Number(item.dataset.clienteId));
});
document.getElementById('oficinasLista').addEventListener('click', function (e) {
    const btn = e.target.closest('[data-oficina-endereco]');
    if (!btn) return;
    usarOficina(btn.dataset.oficinaEndereco || '', Number(btn.dataset.oficinaLat || 0), Number(btn.dataset.oficinaLng || 0));
});
</script>
