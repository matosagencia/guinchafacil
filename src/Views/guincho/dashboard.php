<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-truck-pickup me-2 text-primary-custom"></i>Painel do Guincho</div>
            <div class="page-subtitle">Bem-vindo, <?php echo htmlspecialchars($_SESSION['user']['nome'] ?? 'Operador'); ?>!</div>
        </div>
        <div class="toggle-disponivel d-flex align-items-center gap-2">
            <span id="labelDisponivel" style="color:var(--theme-muted);font-size:.88rem">
                <?php echo $disponivel ? 'Online' : 'Offline'; ?>
            </span>
            <label class="toggle-switch mb-0">
                <input type="checkbox" id="toggleDisponivel" <?php echo $disponivel ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <?php foreach ($_SESSION['_flash'] ?? [] as $flash): unset($_SESSION['_flash']); ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>

    <?php if ($pedidoAtivo): ?>
    <!-- Corrida ativa em andamento -->
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <i class="fas fa-truck-fast fa-2x"></i>
        <div class="flex-grow-1">
            <strong>Corrida em andamento!</strong>
            Status: <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($pedidoAtivo['status']); ?></span>
            — <?php echo htmlspecialchars($pedidoAtivo['cliente_nome'] ?? ''); ?>
        </div>
        <a href="<?php echo $bp; ?>/guincho/atendimento/<?php echo (int)$pedidoAtivo['id']; ?>"
           class="btn btn-warning btn-sm">Continuar atendimento</a>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo (int)$atendimentosHoje; ?></div>
                <div class="stat-label">Atendimentos Hoje</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-value">R$<?php echo number_format((float)$ganhoHoje, 0, ',', '.'); ?></div>
                <div class="stat-label">Ganho Hoje</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo $notaMedia > 0 ? number_format((float)$notaMedia, 1) : '—'; ?></div>
                <div class="stat-label">Nota Média</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-road"></i></div>
                <div class="stat-value"><?php echo number_format((float)$kmPercorridos, 0, ',', '.'); ?></div>
                <div class="stat-label">Km Hoje</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="fas fa-map-location-dot me-2"></i>Mapa em Tempo Real</div>
                <div class="card-body p-0">
                    <div id="map" class="map-container" style="height:420px;border-radius:0 0 12px 12px;min-height:300px"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-bell me-2"></i>
                    <?php echo $pedidoPendente ? 'Novo Pedido Disponível!' : 'Pedidos'; ?>
                </div>
                <div class="card-body">
                    <?php if ($pedidoPendente): ?>
                    <div class="card border-success mb-3">
                        <div class="card-body">
                            <p class="mb-1"><strong>Problema:</strong> <?php echo htmlspecialchars(ucfirst($pedidoPendente['tipo_problema'] ?? '-')); ?></p>
                            <p class="mb-1"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($pedidoPendente['endereco_origem'] ?? '-'); ?></p>
                            <p class="mb-1"><i class="fas fa-car me-1"></i><?php echo htmlspecialchars(($pedidoPendente['marca'] ?? '') . ' ' . ($pedidoPendente['modelo'] ?? '')); ?></p>
                            <p class="mb-2 fw-bold text-success fs-6">R$ <?php echo number_format((float)($pedidoPendente['custo_estimado'] ?? 0), 2, ',', '.'); ?></p>
                            <a href="<?php echo $bp; ?>/guincho/aceitar/<?php echo (int)$pedidoPendente['id']; ?>"
                               class="btn btn-success w-100">
                                <i class="fas fa-eye me-1"></i>Ver pedido
                            </a>
                        </div>
                    </div>
                    <?php elseif (!$disponivel): ?>
                    <div class="text-center py-4" style="color:var(--theme-muted)">
                        <i class="fas fa-toggle-off fa-2x mb-2 d-block"></i>
                        <p class="mb-0">Você está <strong>offline</strong>.<br>Ative o toggle para receber pedidos.</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4" style="color:var(--theme-muted)">
                        <i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>
                        <p class="mb-0">Aguardando novos pedidos...</p>
                    </div>
                    <?php endif; ?>
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
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken ?? ''); ?>';
const BP = '<?php echo $bp; ?>';

document.addEventListener('DOMContentLoaded', () => {
    if (typeof MapManager !== 'undefined') MapManager.init('map');

    const toggle = document.getElementById('toggleDisponivel');
    const label  = document.getElementById('labelDisponivel');
    if (!toggle) return;

    toggle.addEventListener('change', async function () {
        const desejado = this.checked ? 1 : 0;
        toggle.disabled = true;
        label.textContent = 'Salvando...';

        try {
            // Envia como FormData (PHP lê $_POST corretamente)
            const fd = new FormData();
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('disponivel', desejado ? '1' : '0');

            const resp = await fetch(BP + '/guincho/disponibilidade', {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();

            if (data.ok) {
                label.textContent = data.disponivel ? 'Online' : 'Offline';
                toggle.checked = !!data.disponivel;
            } else {
                // Reverte o toggle em caso de erro
                toggle.checked = !desejado;
                label.textContent = !desejado ? 'Online' : 'Offline';
                // Mostra toast de erro em vez de alert bloqueante
                mostrarToast(data.erro || 'Tente novamente.', 'danger');
            }
        } catch (e) {
            toggle.checked = !desejado;
            label.textContent = !desejado ? 'Online' : 'Offline';
        } finally {
            toggle.disabled = false;
        }
    });
});

function mostrarToast(msg, tipo = 'danger') {
    const container = document.getElementById('toastContainer') || (() => {
        const el = document.createElement('div');
        el.id = 'toastContainer';
        el.style.cssText = 'position:fixed;top:70px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px';
        document.body.appendChild(el);
        return el;
    })();
    const toast = document.createElement('div');
    toast.className = `alert alert-${tipo} alert-dismissible shadow mb-0`;
    toast.style.cssText = 'min-width:260px;max-width:380px;animation:fadeIn .2s';
    toast.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${msg}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>
