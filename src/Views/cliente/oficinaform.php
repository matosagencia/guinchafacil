<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$editando = !empty($oficina['id']);
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-wrench me-2" style="color:var(--primary)"></i>
                <?php echo $editando ? 'Editar Oficina' : 'Cadastrar Oficina Favorita'; ?>
            </div>
        </div>
        <a href="<?php echo $bp; ?>/cliente/oficinas" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>Preencha o nome e o endereço da oficina.
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Mapa -->
        <div class="col-lg-6 order-lg-2">
            <div class="card" style="position:sticky;top:80px">
                <div class="card-header">
                    <i class="fas fa-map-pin me-2"></i>Localização — clique no mapa ou preencha o endereço
                </div>
                <div class="card-body p-0">
                    <div id="mapaOficina" style="height:340px"></div>
                </div>
                <div class="card-body pt-2 pb-2">
                    <div id="mapaStatus" class="small text-center" style="color:var(--theme-muted)">
                        <i class="fas fa-mouse-pointer me-1"></i>Clique no mapa para marcar ou busque pelo endereço
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulário -->
        <div class="col-lg-6 order-lg-1">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-store me-2"></i>
                    <?php echo $editando ? 'Dados da Oficina' : 'Informações da Oficina'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/cliente/oficina/salvar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        <?php if ($editando): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$oficina['id']; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="lat" id="latInput" value="<?php echo htmlspecialchars($oficina['lat'] ?? ''); ?>">
                        <input type="hidden" name="lng" id="lngInput" value="<?php echo htmlspecialchars($oficina['lng'] ?? ''); ?>">

                        <div class="mb-3">
                            <label class="form-label">Nome da Oficina *</label>
                            <input type="text" class="form-control" name="nome"
                                   value="<?php echo htmlspecialchars($oficina['nome'] ?? ''); ?>"
                                   placeholder="Ex: Oficina do João" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Endereço Completo *</label>
                            <input type="text" class="form-control" name="endereco" id="enderecoInput"
                                   value="<?php echo htmlspecialchars($oficina['endereco'] ?? ''); ?>"
                                   placeholder="Rua, número, bairro, cidade" required>
                            <div style="font-size:.77rem;color:var(--theme-muted);margin-top:.3rem">
                                <i class="fas fa-search me-1"></i>O mapa busca a localização automaticamente
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Telefone da Oficina (opcional)</label>
                            <input type="text" class="form-control" name="telefone" id="telInput"
                                   value="<?php echo htmlspecialchars($oficina['telefone'] ?? ''); ?>"
                                   placeholder="(11) 99999-9999">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-save me-2"></i>
                            <?php echo $editando ? 'Salvar Alterações' : 'Cadastrar Oficina'; ?>
                        </button>
                        <?php if ($editando): ?>
                        <a href="<?php echo $bp; ?>/cliente/oficinas" class="btn btn-secondary w-100 mt-2">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    const initLat = <?php echo !empty($oficina['lat']) ? (float)$oficina['lat'] : -23.5505; ?>;
    const initLng = <?php echo !empty($oficina['lng']) ? (float)$oficina['lng'] : -46.6333; ?>;
    const initZoom = <?php echo !empty($oficina['lat']) ? 15 : 12; ?>;

    const map = L.map('mapaOficina').setView([initLat, initLng], initZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '© OSM'}).addTo(map);

    const pinIcon = L.divIcon({
        html: '<div style="width:18px;height:18px;background:#2fb34a;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.5)"></div>',
        iconAnchor: [9,9], iconSize: [18,18]
    });

    let marker = null;
    <?php if (!empty($oficina['lat']) && !empty($oficina['lng'])): ?>
    marker = L.marker([initLat, initLng], {icon: pinIcon, draggable: true}).addTo(map);
    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        document.getElementById('latInput').value = pos.lat.toFixed(6);
        document.getElementById('lngInput').value = pos.lng.toFixed(6);
    });
    <?php endif; ?>

    function setMarker(lat, lng) {
        document.getElementById('latInput').value = lat.toFixed(6);
        document.getElementById('lngInput').value = lng.toFixed(6);
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lng], {icon: pinIcon, draggable: true}).addTo(map);
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('latInput').value = pos.lat.toFixed(6);
            document.getElementById('lngInput').value = pos.lng.toFixed(6);
        });
        map.setView([lat, lng], 16);
        document.getElementById('mapaStatus').innerHTML =
            '<i class="fas fa-check-circle" style="color:#2fb34a"></i> Localização marcada! Você pode arrastar o pino para ajustar.';
    }

    map.on('click', function(e) {
        setMarker(e.latlng.lat, e.latlng.lng);
        fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+e.latlng.lat+'&lon='+e.latlng.lng+'&accept-language=pt-BR')
            .then(r => r.json()).then(d => {
                if (d.display_name) {
                    const inp = document.getElementById('enderecoInput');
                    if (!inp.value) inp.value = d.display_name;
                }
            }).catch(() => {});
    });

    let geocodeTimer = null;
    document.getElementById('enderecoInput').addEventListener('input', function() {
        clearTimeout(geocodeTimer);
        const q = this.value.trim();
        if (q.length < 8) return;
        geocodeTimer = setTimeout(function() {
            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=1&countrycodes=br&accept-language=pt-BR')
                .then(r => r.json()).then(data => {
                    if (data[0]) setMarker(parseFloat(data[0].lat), parseFloat(data[0].lon));
                }).catch(() => {});
        }, 900);
    });

    // Máscara telefone
    document.getElementById('telInput').addEventListener('input', function() {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 6) v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
        else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        this.value = v;
    });
})();
</script>
