<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-gauge me-2 text-primary-custom"></i>Dashboard Admin</div>
            <div class="page-subtitle">Visão geral da plataforma</div>
        </div>
        <a href="<?php echo $bp; ?>/admin/configuracoes" class="btn btn-secondary btn-sm">
            <i class="fas fa-gear me-1"></i>Configurações
        </a>
    </div>

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-list-check"></i></div>
                <div class="stat-value"><?php echo $totalPedidosHoje ?? 50; ?></div>
                <div class="stat-label">Pedidos Hoje</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="stat-value">R$<?php echo number_format($receitaHoje ?? 2000, 0, ',', '.'); ?></div>
                <div class="stat-label">Receita Hoje</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
                <div class="stat-value"><?php echo $guinchoAtivos ?? 10; ?></div>
                <div class="stat-label">Guinchos Ativos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?php echo $totalUsuarios ?? 238; ?></div>
                <div class="stat-label">Usuários Total</div>
            </div>
        </div>
    </div>

    <!-- GRÁFICO -->
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Pedidos por Dia (última semana)</div>
        <div class="card-body">
            <canvas id="chartPedidos" height="100"></canvas>
        </div>
    </div>

    <!-- ATALHOS RÁPIDOS -->
    <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo $bp; ?>/admin/pedidos" class="card text-decoration-none" style="transition:.2s">
                <div class="card-body text-center py-4">
                    <i class="fas fa-list fa-2x mb-2" style="color:var(--primary)"></i>
                    <div style="font-weight:700;color:var(--theme-text)">Ver Pedidos</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo $bp; ?>/admin/pedido/novo" class="card text-decoration-none">
                <div class="card-body text-center py-4">
                    <i class="fas fa-plus-circle fa-2x mb-2" style="color:var(--primary)"></i>
                    <div style="font-weight:700;color:var(--theme-text)">Novo Pedido</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo $bp; ?>/admin/guincho/novo" class="card text-decoration-none">
                <div class="card-body text-center py-4">
                    <i class="fas fa-truck-medical fa-2x mb-2" style="color:#22c55e"></i>
                    <div style="font-weight:700;color:var(--theme-text)">Novo Guincheiro</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo $bp; ?>/admin/guinchos" class="card text-decoration-none">
                <div class="card-body text-center py-4">
                    <i class="fas fa-truck fa-2x mb-2" style="color:#f59e0b"></i>
                    <div style="font-weight:700;color:var(--theme-text)">Guinchos Pendentes</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo $bp; ?>/admin/financeiro" class="card text-decoration-none">
                <div class="card-body text-center py-4">
                    <i class="fas fa-chart-line fa-2x mb-2" style="color:#60a5fa"></i>
                    <div style="font-weight:700;color:var(--theme-text)">Financeiro</div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-3">
            <a href="<?php echo $bp; ?>/admin/logs" class="card text-decoration-none">
                <div class="card-body text-center py-4">
                    <i class="fas fa-terminal fa-2x mb-2" style="color:#a78bfa"></i>
                    <div style="font-weight:700;color:var(--theme-text)">Logs do Sistema</div>
                </div>
            </a>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartPedidos').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'],
        datasets: [{
            label: 'Pedidos',
            data: [12,19,9,15,22,18,7],
            backgroundColor: 'rgba(47,179,74,.5)',
            borderColor: '#2fb34a',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: getComputedStyle(document.body).getPropertyValue('--theme-text') || '#e8e8e8' } } },
        scales: {
            x: { ticks: { color: '#888' }, grid: { color: 'rgba(255,255,255,.07)' } },
            y: { beginAtZero: true, ticks: { color: '#888' }, grid: { color: 'rgba(255,255,255,.07)' } }
        }
    }
});
</script>
