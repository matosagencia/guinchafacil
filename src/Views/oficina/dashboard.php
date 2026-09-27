<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<script>document.body.classList.add('theme-oficina');</script>
<div class="main-wrapper" style="display:flex;min-height:100vh">
    <?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
    <main class="main-content" style="flex:1;padding:1.5rem">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <h1 style="font-size:1.4rem;font-weight:700;color:var(--ofi-primary-darker);margin:0">Painel da Oficina</h1>
                <p class="ofi-card-sub" style="margin:0">Ofertas, atendimentos e status em tempo real</p>
            </div>
            <div class="ofi-toggle">
                <input type="checkbox" id="toggleDisponivel" <?= !empty($oficina['disponivel']) ? 'checked' : '' ?>>
                <label class="ofi-toggle-track" for="toggleDisponivel"></label>
                <span id="labelDisponivel" style="font-size:.85rem;font-weight:600"><?= !empty($oficina['disponivel']) ? 'Online' : 'Offline' ?></span>
            </div>
        </div>
        <?php if (!empty($_SESSION['_flash'])): foreach ((array)$_SESSION['_flash'] as $f): unset($_SESSION['_flash']); ?>
            <div class="alert alert-<?= $f['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= htmlspecialchars($f['message']) ?></div>
        <?php endforeach; endif; ?>
        <div class="ofi-stats">
            <div class="ofi-stat"><div class="ofi-stat-value"><?= (int)$atendimentosHoje ?></div><div class="ofi-stat-label">Atendimentos hoje</div></div>
            <div class="ofi-stat"><div class="ofi-stat-value"><?= count($pedidos) ?></div><div class="ofi-stat-label">Pedidos proximos</div></div>
            <div class="ofi-stat"><div class="ofi-stat-value"><?= (int)($oficina['raio_atendimento_km'] ?? 0) ?> km</div><div class="ofi-stat-label">Raio</div></div>
        </div>
        <div class="ofi-card">
            <div class="ofi-card-title"><i class="fas fa-inbox me-1"></i> Pedidos disponiveis</div>
            <p class="ofi-card-sub mb-3">Ordenados por distancia</p>
            <?php if (empty($pedidos)): ?>
                <div class="text-center py-4" style="color:#7C6B5C"><i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>Nenhum pedido disponivel.</div>
            <?php else: foreach ($pedidos as $p): ?>
                <div class="ofi-pedido">
                    <div class="ofi-pedido-info" style="flex:1">
                        <strong>Pedido #<?= (int)$p['id'] ?></strong>
                        <div class="ofi-pedido-meta"><i class="fas fa-location-dot me-1"></i><?= htmlspecialchars((string)($p['endereco_origem'] ?? '')) ?></div>
                        <div class="ofi-pedido-meta"><i class="fas fa-route me-1"></i><?= number_format((float)$p['distancia_km'], 1, ',', '.') ?> km &middot; <?= htmlspecialchars((string)($p['tipo_problema'] ?? 'outro')) ?></div>
                    </div>
                    <div class="ofi-pedido-actions">
                        <a href="<?= $bp ?>/oficina/pedido/<?= (int)$p['id'] ?>/aceitar" class="btn-ofi-primary"><i class="fas fa-check me-1"></i> Aceitar</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </main>
</div>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function(){
    const BP = '<?= $bp ?>'; const CSRF = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    const toggle = document.getElementById('toggleDisponivel');
    const label  = document.getElementById('labelDisponivel');
    if (!toggle) return;
    toggle.addEventListener('change', async function(){
        const d = this.checked ? 1 : 0;
        this.disabled = true; label.textContent = 'Salvando...';
        try {
            const fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('disponivel', d ? '1' : '0');
            const r = await fetch(BP + '/oficina/disponibilidade', { method:'POST', body:fd, headers:{Accept:'application/json'} });
            const j = await r.json();
            if (j.ok) label.textContent = j.disponivel ? 'Online' : 'Offline';
            else { this.checked = !d; label.textContent = !d ? 'Online' : 'Offline'; }
        } catch(e) { this.checked = !d; label.textContent = !d ? 'Online' : 'Offline'; }
        finally { this.disabled = false; }
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>