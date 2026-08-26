<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-wrench me-2" style="color:var(--primary)"></i>Oficinas Favoritas
            </div>
            <div class="page-subtitle"><?php echo count($oficinas ?? []); ?> oficina(s) cadastrada(s)</div>
        </div>
        <a href="<?php echo $bp; ?>/cliente/oficina/nova" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Adicionar Oficina
        </a>
    </div>

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
            <div class="card h-100" style="border-top:3px solid var(--primary)">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="stat-icon" style="margin:0;flex-shrink:0;width:44px;height:44px;font-size:1rem">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="flex-fill min-w-0">
                            <div style="font-weight:700;color:var(--theme-text);margin-bottom:.25rem">
                                <?php echo htmlspecialchars($o['nome']); ?>
                            </div>
                            <div style="font-size:.79rem;color:var(--theme-muted)">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($o['endereco'] ?? '—'); ?>
                            </div>
                            <?php if (!empty($o['telefone'])): ?>
                            <div style="font-size:.79rem;color:var(--theme-muted);margin-top:.2rem">
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($o['telefone']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($o['lat']) && !empty($o['lng'])): ?>
                    <div id="minimap_<?php echo (int)$o['id']; ?>"
                         class="mb-3"
                         data-lat="<?php echo (float)$o['lat']; ?>"
                         data-lng="<?php echo (float)$o['lng']; ?>"
                         data-nome="<?php echo htmlspecialchars($o['nome']); ?>"
                         style="height:120px;border-radius:8px;overflow:hidden;border:1px solid var(--theme-border);background:var(--theme-surf2)">
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
                                    onclick="return confirm('Remover oficina &quot;<?php echo addslashes(htmlspecialchars($o['nome'])); ?>&quot;?')">
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
            <i class="fas fa-tools fa-3x d-block mb-3" style="opacity:.2;color:var(--theme-muted)"></i>
            <h5 style="color:var(--theme-text);font-weight:700">Nenhuma oficina cadastrada</h5>
            <p style="color:var(--theme-muted);font-size:.9rem;max-width:380px;margin:0 auto 1.5rem">
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
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[id^="minimap_"]').forEach(function(el) {
        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        const nome = el.dataset.nome || 'Oficina';
        if (!lat || !lng) return;
        const m = L.map(el.id, {zoomControl:false,dragging:false,scrollWheelZoom:false,touchZoom:false}).setView([lat,lng],15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:''}).addTo(m);
        const pinIcon = L.divIcon({
            html:'<div style="width:14px;height:14px;background:#2fb34a;border-radius:50%;border:2.5px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,.4)"></div>',
            iconAnchor:[7,7],iconSize:[14,14]
        });
        L.marker([lat,lng],{icon:pinIcon}).addTo(m).bindPopup(nome);
    });
});
</script>
