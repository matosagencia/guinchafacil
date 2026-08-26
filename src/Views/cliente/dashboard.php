<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-gauge me-2 text-primary-custom"></i>Meu Painel</div>
            <div class="page-subtitle">Olá, <?php echo htmlspecialchars($_SESSION['user']['nome'] ?? 'Cliente'); ?>!</div>
        </div>
        <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn-socorro">
            <i class="fas fa-circle-exclamation me-2"></i>Pedir Socorro
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                <div class="stat-value"><?php echo $pedidosAtivos ?? 1; ?></div>
                <div class="stat-label">Pedidos Ativos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $pedidosConcluidos ?? 15; ?></div>
                <div class="stat-label">Concluídos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-car"></i></div>
                <div class="stat-value"><?php echo $totalVeiculos ?? 2; ?></div>
                <div class="stat-label">Meus Veículos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo number_format($mediaAvaliacoes ?? 4.5, 1); ?></div>
                <div class="stat-label">Avaliação Média</div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-map-location-dot me-2"></i>Sua Localização</div>
        <div class="card-body p-0">
            <div id="map" class="map-container" style="border-radius:0 0 12px 12px;min-height:280px"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-clock-rotate-left me-2"></i>Últimos Pedidos</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php if (!empty($ultimosPedidos)): foreach ($ultimosPedidos as $p): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center" style="background:var(--theme-surf);color:var(--theme-text);border-color:var(--theme-border)">
                    Pedido #<?php echo $p['id']; ?>
                    <span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucfirst(str_replace('_',' ',$p['status'])); ?></span>
                </li>
                <?php endforeach; else: ?>
                <li class="list-group-item" style="background:var(--theme-surf);color:var(--theme-text);border-color:var(--theme-border)">Pedido #123 <span class="float-end badge badge-concluido">Concluído</span></li>
                <li class="list-group-item" style="background:var(--theme-surf);color:var(--theme-text);border-color:var(--theme-border)">Pedido #122 <span class="float-end badge badge-a_caminho">A Caminho</span></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card-footer text-end">
            <a href="<?php echo $bp; ?>/cliente/historico" class="btn btn-outline-primary btn-sm">Ver todo histórico</a>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?php echo $bp; ?>/public/assets/js/app.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => { if (typeof MapManager !== 'undefined') MapManager.init('map'); });</script>
