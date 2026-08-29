<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-oficinas.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Conta</span>
            <h1><i class="fas fa-wrench me-2 oficina-icon-accent"></i>Oficinas Favoritas</h1>
            <p><?php echo count($oficinas ?? []); ?> oficina(s) cadastrada(s)</p>
        </div>
        <a href="<?php echo $bp; ?>/cliente/oficina/nova" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Adicionar Oficina
        </a>
    </header>

    <?php if (!empty($_GET['salvo'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Oficina salva com sucesso!</div>
    <?php endif; ?>
    <?php if (!empty($_GET['deletado'])): ?>
    <div class="alert alert-info mb-3"><i class="fas fa-trash me-2"></i>Oficina removida.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Erro: preencha nome e endereço.</div>
    <?php endif; ?>

    <?php if (!empty($oficinas)): ?>
    <div class="row g-3">
        <?php foreach ($oficinas as $o): ?>
        <div class="col-sm-6 col-lg-4">
            <div class="card h-100 oficina-card">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="stat-icon oficina-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="flex-fill min-w-0">
                            <div class="oficina-nome">
                                <?php echo htmlspecialchars($o['nome']); ?>
                            </div>
                            <div class="oficina-meta">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($o['endereco'] ?? '—'); ?>
                            </div>
                            <?php if (!empty($o['telefone'])): ?>
                            <div class="oficina-meta-mt">
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($o['telefone']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($o['lat']) && !empty($o['lng'])): ?>
                    <div id="minimap_<?php echo (int)$o['id']; ?>"
                         class="mb-3 oficina-minimap"
                         data-lat="<?php echo (float)$o['lat']; ?>"
                         data-lng="<?php echo (float)$o['lng']; ?>"
                         data-nome="<?php echo htmlspecialchars($o['nome']); ?>">
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <a href="<?php echo $bp; ?>/cliente/oficina/editar/<?php echo (int)$o['id']; ?>"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fas fa-edit me-1"></i>Editar
                        </a>
                        <form method="POST" action="<?php echo $bp; ?>/cliente/oficina/deletar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger w-100"
                                    data-confirm-message="Remover oficina &quot;<?php echo htmlspecialchars($o['nome'], ENT_QUOTES, 'UTF-8'); ?>&quot;?">
                                <i class="fas fa-trash me-1"></i>Remover
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-tools fa-3x d-block mb-3 oficina-empty-icon"></i>
            <h5 class="oficina-empty-title">Nenhuma oficina cadastrada</h5>
            <p class="oficina-empty-subtitle">
                Salve oficinas de confiança para agilizar seus pedidos de reboque.
            </p>
            <a href="<?php echo $bp; ?>/cliente/oficina/nova" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Adicionar Primeira Oficina
            </a>
        </div>
    </div>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="minimap_"]').forEach(function(el) {
        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        const nome = el.dataset.nome || 'Oficina';
        if (!lat || !lng) return;
        const m = L.map(el.id, {zoomControl:false,dragging:false,scrollWheelZoom:false,touchZoom:false}).setView([lat,lng],15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:''}).addTo(m);
        const pinIcon = L.divIcon({
            html:'<div class="oficina-minimap-pin"></div>',
            iconAnchor:[7,7],iconSize:[14,14]
        });
        L.marker([lat,lng],{icon:pinIcon}).addTo(m).bindPopup(nome);
    });
});
</script>
