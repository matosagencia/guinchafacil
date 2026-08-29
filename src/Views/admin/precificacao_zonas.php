<?php
/**
 * Zonas de Precificação — reestruturada pro padrão shell-ops. A lista já
 * vem inteira do servidor, por isso o workspace é preenchido via JS a
 * partir de um JSON embutido (window.__zonasData), sem round-trip extra.
 *
 * @var array $zonas
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totalZonas = count($zonas ?? []);
$zonasAtivas = count(array_filter($zonas ?? [], static fn($z) => !empty($z['active'])));
$zonasSemPoligono = count(array_filter($zonas ?? [], static fn($z) => empty($z['polygon_geojson'])));

$cidadesAtivas = $cidadesAtivas ?? [];
$cidadesPorId = [];
foreach ($cidadesAtivas as $c) { $cidadesPorId[(int)$c['id']] = $c['nome'] . '/' . $c['uf']; }

$zonasPayload = [];
foreach (($zonas ?? []) as $z) {
    $zonasPayload[] = [
        'id' => (int)$z['id'],
        'code' => (string)$z['code'],
        'name' => (string)$z['name'],
        'tem_poligono' => !empty($z['polygon_geojson']),
        'polygon_geojson' => (string)($z['polygon_geojson'] ?? ''),
        'active' => !empty($z['active']),
        'cidade_id' => isset($z['cidade_id']) ? (int)$z['cidade_id'] : null,
        'cidade_nome' => isset($z['cidade_id']) && isset($cidadesPorId[(int)$z['cidade_id']]) ? $cidadesPorId[(int)$z['cidade_id']] : null,
        'ordem_expansao' => isset($z['ordem_expansao']) && $z['ordem_expansao'] !== null ? (int)$z['ordem_expansao'] : null,
        'status_expansao' => (string)($z['status_expansao'] ?? 'nao_ativada'),
        'bairros_referencia' => (string)($z['bairros_referencia'] ?? ''),
        'meta_guinchos_min' => isset($z['meta_guinchos_min']) && $z['meta_guinchos_min'] !== null ? (int)$z['meta_guinchos_min'] : null,
        'meta_especialistas_min' => isset($z['meta_especialistas_min']) && $z['meta_especialistas_min'] !== null ? (int)$z['meta_especialistas_min'] : null,
    ];
}
$statusExpansaoLabel = [
    'nao_ativada' => 'Não ativada',
    'pedra_morta' => 'Pedra morta',
    'pedra_viva' => 'Pedra viva',
];
$statusExpansaoBadge = [
    'nao_ativada' => 'ops-badge--audit',
    'pedra_morta' => 'ops-badge--critical',
    'pedra_viva' => 'ops-badge--service',
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="zonasSearchTop" placeholder="Buscar por código ou nome da zona" autocomplete="off" aria-label="Buscar zonas"></div>
    <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalZonas; ?> zonas</span></div>
</div>

<section class="ops-summary" aria-label="Resumo de zonas de precificação">
    <article class="ops-metric">
        <span class="ops-metric__label">Total</span>
        <strong class="ops-metric__value"><?php echo $totalZonas; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Ativas</span>
        <strong class="ops-metric__value"><?php echo $zonasAtivas; ?></strong>
    </article>
    <article class="ops-metric <?php echo $zonasSemPoligono > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Sem polígono — não afeta preço</span>
        <strong class="ops-metric__value"><?php echo $zonasSemPoligono; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Células vivas</span>
        <strong class="ops-metric__value"><?php echo count(array_filter($zonasPayload, static fn($z) => $z['status_expansao'] === 'pedra_viva')); ?></strong>
    </article>
</section>

<?php $flash = $flash ?? null; if (!empty($flash)): ?>
<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>" style="margin:0 24px 16px;">
    <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div style="padding:0 24px 16px;">
    <div class="row g-3 mb-3" aria-label="Escolha do motor de precificacao">
        <div class="col-md-6"><a class="card h-100 text-decoration-none border-success" href="<?php echo htmlspecialchars($bp); ?>/admin/precificacao/zonas">
            <div class="card-body"><div class="d-flex align-items-center gap-3"><i class="fas fa-truck fa-2x text-success"></i><div><h2 class="h5 mb-1 text-dark">Tarifas de guincho</h2><p class="mb-0 text-muted small">Zonas, distancia origem-destino, veiculo, pedagio e reboque.</p></div></div></div>
        </a></div>
        <div class="col-md-6"><a class="card h-100 text-decoration-none border-warning" href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tarifas">
            <div class="card-body"><div class="d-flex align-items-center gap-3"><i class="fas fa-screwdriver-wrench fa-2x text-warning"></i><div><h2 class="h5 mb-1 text-dark">Tarifas de especialistas</h2><p class="mb-0 text-muted small">Atendimento no local, adicionais, raio, horario e repasse.</p></div></div></div>
        </a></div>
    </div>
    <div class="alert alert-info small mb-3">
        <i class="fas fa-circle-info me-1"></i>
        Enquanto uma zona não tiver polígono desenhado + regra de preço ativa, ela não afeta nenhum cálculo — o sistema
        continua usando as tarifas globais (Reboque via TarifaService, demais serviços via
        <a href="<?php echo $bp; ?>/admin/catalogo-servicos/tarifas">Tarifas por tipo de serviço</a>).
    </div>
    <div class="card">
        <div class="card-header p-0"><button type="button" class="btn btn-link w-100 text-start text-decoration-none d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#novaZonaPanel" aria-expanded="true" aria-controls="novaZonaPanel"><span><i class="fas fa-circle-plus me-2"></i>Nova zona</span><i class="fas fa-chevron-up"></i></button></div><div id="novaZonaPanel" class="collapse show">
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/precificacao/zona/salvar" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="col-md-3">
                    <label class="form-label">Código</label>
                    <input type="text" class="form-control" name="code" placeholder="RIO_CENTRO" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="name" placeholder="Rio de Janeiro — Centro" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Polígono (GeoJSON Polygon, opcional)</label>
                    <div id="novaZonaMap" style="height:260px;width:100%;border-radius:6px;"></div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="novaZonaDrawStart"><i class="fas fa-draw-polygon me-1"></i>Desenhar polígono</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="novaZonaDrawClear">Limpar</button>
                    </div>
                    <input type="hidden" name="polygon_geojson" id="novaZonaPolygon">
                    <div class="form-text small">Clique nos vértices no mapa e dê duplo clique para finalizar.</div>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i></button>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cidade-alvo (opcional, só organizacional)</label>
                    <select class="form-select" name="cidade_id">
                        <option value="">— sem cidade —</option>
                        <?php foreach ($cidadesAtivas as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nome'] . '/' . $c['uf']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">Não afeta o point-in-polygon — é só pra organizar/auditar qual zona pertence a qual cidade-alvo.</div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="active" value="1" checked id="novaZonaAtiva">
                        <label class="form-check-label small text-muted" for="novaZonaAtiva">Ativa</label>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

    </div>

<div class="shell-ops" id="zonasShell">

    <aside class="shell-ops-sidebar" id="zonasSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Zonas cadastradas">
        <header class="ops-worklist-header">
            <span class="eyebrow">Catálogo estruturado</span>
            <h2>Zonas de precificação</h2>
            <p><span id="zonasWorklistCount"><?php echo count($zonasPayload); ?></span> zona(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="zonasWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="zonasWorklistResults">
            <?php if (empty($zonasPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-map-location-dot"></i>
                    Nenhuma zona cadastrada ainda.
                </div>
            <?php else: foreach ($zonasPayload as $i => $z):
                $busca = strtolower($z['code'] . ' ' . $z['name']);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo !$z['tem_poligono'] ? 'is-warning' : ''; ?>"
                    data-zona-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($z['name']); ?></strong>
                            <span class="ops-badge <?php echo $z['active'] ? 'ops-badge--service' : 'ops-badge--audit'; ?>"><?php echo $z['active'] ? 'Ativa' : 'Inativa'; ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><code><?php echo htmlspecialchars($z['code']); ?></code><?php echo $z['cidade_nome'] ? ' · ' . htmlspecialchars($z['cidade_nome']) : ''; ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo $z['tem_poligono'] ? 'Polígono desenhado' : 'Sem polígono — não afeta preço'; ?></span>
                            <?php if ($z['ordem_expansao'] !== null): ?>
                            <span class="ops-badge <?php echo $statusExpansaoBadge[$z['status_expansao']] ?? 'ops-badge--audit'; ?>">Fase <?php echo (int)$z['ordem_expansao']; ?> · <?php echo $statusExpansaoLabel[$z['status_expansao']] ?? $z['status_expansao']; ?></span>
                            <?php endif; ?>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if (!$z['tem_poligono']): ?>
                        <span class="ops-signal is-danger" title="Sem polígono"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="zonasWorkspace" aria-live="polite">
        <?php if (empty($zonasPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhuma zona pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__zonasData = <?php echo json_encode($zonasPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var ZONAS_BP = '<?php echo addslashes($bp); ?>';
var ZONAS_CSRF = <?php echo json_encode($csrfToken ?? ''); ?>;

(function () {
    var zonas = window.__zonasData || [];
    var shell = document.getElementById('zonasShell');
    var results = document.getElementById('zonasWorklistResults');
    var workspace = document.getElementById('zonasWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function renderZona(zonaId) {
        var z = zonas[zonaId];
        if (!z) return;



var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(z.name) + '</h1>' +
            '<p><code>' + escapeHtml(z.code) + '</code>' + (z.cidade_nome ? ' · ' + escapeHtml(z.cidade_nome) : ' · sem cidade-alvo vinculada') + '</p></div>' +
            '<span class="ops-badge ' + (z.active ? 'ops-badge--service' : 'ops-badge--audit') + '">' + (z.active ? 'Ativa' : 'Inativa') + '</span>' +
            '</header>';
        html += '<div class="card mb-3"><div class="card-header p-0"><button type="button" class="btn btn-link w-100 text-start text-decoration-none d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#zonaMapPanel" aria-expanded="true" aria-controls="zonaMapPanel"><span><i class="fas fa-map me-2"></i>Mapa das zonas</span><i class="fas fa-chevron-up"></i></button></div><div id="zonaMapPanel" class="collapse show"><div class="card-body p-0"><div id="zonaMap" style="height:360px;width:100%;"></div><div class="p-3 border-top"><button type="button" class="btn btn-outline-primary btn-sm" id="zonaDrawStart"><i class="fas fa-draw-polygon me-1"></i>Desenhar/editar polígono</button> <button type="button" class="btn btn-outline-secondary btn-sm" id="zonaDrawClear">Limpar desenho</button><span class="small text-muted ms-2" id="zonaDrawHint">Clique nos pontos e dê duplo clique para finalizar.</span><form method="post" action="' + ZONAS_BP + '/admin/precificacao/zona/salvar" id="zonaPolygonForm" class="mt-3"><input type="hidden" name="csrf_token" value="' + escapeHtml(ZONAS_CSRF) + '"><input type="hidden" name="id" value="' + z.id + '"><input type="hidden" name="name" value="' + escapeHtml(z.name) + '"><input type="hidden" name="active" value="' + (z.active ? '1' : '') + '"><input type="hidden" name="cidade_id" value="' + (z.cidade_id || '') + '"><input type="hidden" name="polygon_geojson" id="zonaPolygonGeojson" value="' + escapeHtml(z.polygon_geojson || '') + '"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Salvar polígono no banco</button></form></div></div></div></div>';

        html += '<div style="padding:0 24px 12px">' +
            '<a class="ops-btn" href="' + ZONAS_BP + '/admin/precificacao/zona/' + z.id + '"><i class="fas fa-tags"></i> Regras de preço da zona</a>' +
            '</div>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card"><div class="card-header"><i class="fas fa-map-location-dot me-2"></i>Polígono</div><div class="card-body">';
        if (z.tem_poligono) {
            html += '<span class="badge text-bg-success">Desenhado</span> <span class="text-muted small">Esta zona já afeta o cálculo de preço nas regras ativas.</span>';
        } else {
            html += '<span class="badge text-bg-secondary">Não desenhado</span> <span class="text-muted small">Sem polígono, esta zona não afeta nenhum cálculo — o sistema usa as tarifas globais.</span>';
        }
        html += '</div></div>';

        html += '<div class="card mt-3"><div class="card-header"><i class="fas fa-flag-checkered me-2"></i>Expansão territorial (célula)</div><div class="card-body">';
        html += '<p class="text-muted small">Fase de expansão, status (não ativada / pedra morta / pedra viva) e bairros de referência desta célula — governa a ordem de domínio territorial, não o preço.</p>';
        html += '<form method="post" action="' + ZONAS_BP + '/admin/precificacao/zona/expansao" class="row g-3">';
        html += '<input type="hidden" name="csrf_token" value="' + escapeHtml(ZONAS_CSRF) + '">';
        html += '<input type="hidden" name="id" value="' + z.id + '">';
        html += '<div class="col-md-3"><label class="form-label">Ordem (fase)</label><input type="number" min="1" class="form-control" name="ordem_expansao" value="' + (z.ordem_expansao !== null ? z.ordem_expansao : '') + '" placeholder="1"></div>';
        html += '<div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status_expansao">';
        ['nao_ativada', 'pedra_morta', 'pedra_viva'].forEach(function (st) {
            var labels = { nao_ativada: 'Não ativada', pedra_morta: 'Pedra morta', pedra_viva: 'Pedra viva' };
            html += '<option value="' + st + '"' + (z.status_expansao === st ? ' selected' : '') + '>' + labels[st] + '</option>';
        });
        html += '</select></div>';
        html += '<div class="col-md-5"><label class="form-label">Bairros de referência</label><input type="text" class="form-control" name="bairros_referencia" value="' + escapeHtml(z.bairros_referencia || '') + '" placeholder="Ex: Icaraí, Santa Rosa, Centro"></div>';
        html += '<div class="col-12"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar expansão</button></div>';
        html += '<div class="col-md-3"><label class="form-label">Meta de guinchos *</label><input type="number" min="0" class="form-control" name="meta_guinchos_min" value="' + (z.meta_guinchos_min !== null ? z.meta_guinchos_min : '') + '" required></div>';
        html += '<div class="col-md-3"><label class="form-label">Meta de especialistas *</label><input type="number" min="0" class="form-control" name="meta_especialistas_min" value="' + (z.meta_especialistas_min !== null ? z.meta_especialistas_min : '') + '" required></div>';
        html += '<div class="col-12"><div class="alert alert-info py-2 mb-0 small"><i class="fas fa-lock me-1"></i>Pedra viva só será sugerida quando as duas metas estiverem definidas e atingidas.</div></div>';
        html += '</form>';
        html += '</div></div>';

        html += '</div>';

        workspace.innerHTML = html;
        if (window.L) {
            var map = L.map('zonaMap').setView([-22.8832, -43.1034], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
            var bounds = null;
            zonas.forEach(function (item) {
                if (!item.polygon_geojson) return;
                try {
                    var layer = L.geoJSON(JSON.parse(item.polygon_geojson), {
                        style: { color: Number(item.id) === Number(z.id) ? '#0d6efd' : '#6c757d', weight: Number(item.id) === Number(z.id) ? 4 : 2, fillOpacity: Number(item.id) === Number(z.id) ? 0.28 : 0.10 }
                    }).bindTooltip(item.name);
                    layer.addTo(map);
                    bounds = bounds ? bounds.extend(layer.getBounds()) : layer.getBounds();
                } catch (e) { console.warn('GeoJSON inválido na zona ' + item.id, e); }
            });
            if (bounds && bounds.isValid()) map.fitBounds(bounds.pad(0.08));
            var polygonField = document.getElementById('zonaPolygonGeojson');
            var drawStart = document.getElementById('zonaDrawStart');
            var drawClear = document.getElementById('zonaDrawClear');
            var drawHint = document.getElementById('zonaDrawHint');
            var drawing = false, drawPoints = [], drawMarkers = [], drawnLayer = null;
            function clearDrawing() {
                drawMarkers.forEach(function (marker) { map.removeLayer(marker); });
                drawMarkers = []; drawPoints = [];
                if (drawnLayer) { map.removeLayer(drawnLayer); drawnLayer = null; }
                if (polygonField) polygonField.value = '';
            }
            function finishDrawing() {
                if (!drawnLayer || drawPoints.length < 3) return;
                drawing = false;
                if (drawHint) drawHint.textContent = 'Polígono pronto. Clique em “Salvar polígono no banco”.';
                if (polygonField) polygonField.value = JSON.stringify(drawnLayer.toGeoJSON().geometry);
            }
            if (drawStart) drawStart.addEventListener('click', function () {
                clearDrawing(); drawing = true;
                if (drawHint) drawHint.textContent = 'Desenho ativo: clique nos vértices e dê duplo clique para finalizar.';
                map.doubleClickZoom.disable();
            });
            if (drawClear) drawClear.addEventListener('click', function () {
                clearDrawing(); drawing = false;
                if (drawHint) drawHint.textContent = 'Desenho limpo. Clique em “Desenhar/editar polígono”.';
            });
            map.on('click', function (event) {
                if (!drawing) return;
                drawPoints.push([event.latlng.lat, event.latlng.lng]);
                drawMarkers.push(L.circleMarker(event.latlng, { radius: 4, color: '#dc3545', fillOpacity: 1 }).addTo(map));
                if (drawnLayer) map.removeLayer(drawnLayer);
                if (drawPoints.length >= 3) drawnLayer = L.polygon(drawPoints, { color: '#dc3545', weight: 3, fillOpacity: 0.18 }).addTo(map);
            });
            map.on('dblclick', function () { if (drawing && drawPoints.length >= 3) finishDrawing(); });            setTimeout(function () { map.invalidateSize(); }, 50);
        }
    }

    function selectZona(zonaId) {
        results.querySelectorAll('[data-zona-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.zonaId) === zonaId));
        });
        renderZona(zonaId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-zona-id]');
        if (!item) return;
        selectZona(Number(item.dataset.zonaId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-zona-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('zonasWorklistSearch');
    var topSearch = document.getElementById('zonasSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (window.L && document.getElementById('novaZonaMap')) {
        var newMap = L.map('novaZonaMap').setView([-22.8832, -43.1034], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(newMap);
        var newDrawing = false, newPoints = [], newMarkers = [], newLayer = null;
        var newField = document.getElementById('novaZonaPolygon');
        function clearNewDrawing() {
            newMarkers.forEach(function (marker) { newMap.removeLayer(marker); });
            newMarkers = []; newPoints = [];
            if (newLayer) { newMap.removeLayer(newLayer); newLayer = null; }
            if (newField) newField.value = '';
        }
        document.getElementById('novaZonaDrawStart').addEventListener('click', function () {
            clearNewDrawing(); newDrawing = true; newMap.doubleClickZoom.disable();
        });
        document.getElementById('novaZonaDrawClear').addEventListener('click', function () {
            clearNewDrawing(); newDrawing = false;
        });
        newMap.on('click', function (event) {
            if (!newDrawing) return;
            newPoints.push([event.latlng.lat, event.latlng.lng]);
            newMarkers.push(L.circleMarker(event.latlng, { radius: 4, color: '#dc3545', fillOpacity: 1 }).addTo(newMap));
            if (newLayer) newMap.removeLayer(newLayer);
            if (newPoints.length >= 3) newLayer = L.polygon(newPoints, { color: '#dc3545', weight: 3, fillOpacity: 0.18 }).addTo(newMap);
        });
        newMap.on('dblclick', function () {
            if (newDrawing && newLayer && newPoints.length >= 3) {
                newDrawing = false;
                if (newField) newField.value = JSON.stringify(newLayer.toGeoJSON().geometry);
            }
        });
        setTimeout(function () { newMap.invalidateSize(); }, 100);
    }
    if (zonas.length > 0) selectZona(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques, Tipos de Serviço...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
