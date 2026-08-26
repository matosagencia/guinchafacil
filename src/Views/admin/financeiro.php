<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-chart-line me-2 text-primary-custom"></i>Relatório Financeiro</div>
            <div class="page-subtitle">Receitas, comissões e repasses</div>
        </div>
        <select class="form-select form-select-sm" style="width:160px">
            <option>Fevereiro 2025</option><option>Janeiro 2025</option>
        </select>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-value">R$<?php echo number_format($receitaBruta ?? 10000, 0, ',', '.'); ?></div>
                <div class="stat-label">Receita Bruta</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                <div class="stat-value">R$<?php echo number_format($comissao ?? 1000, 0, ',', '.'); ?></div>
                <div class="stat-label">Comissão Plataforma</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                <div class="stat-value">R$<?php echo number_format($repasse ?? 9000, 0, ',', '.'); ?></div>
                <div class="stat-label">Repasse Guinchos</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <div class="stat-value"><?php echo $totalTransacoes ?? 87; ?></div>
                <div class="stat-label">Transações</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fas fa-table me-2"></i>Detalhamento por Período</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Período</th><th>Receita Bruta</th><th>Comissão</th><th>Repasse</th></tr></thead>
                    <tbody>
                        <tr><td>Fevereiro 2025</td><td>R$ 10.000,00</td><td>R$ 1.000,00</td><td>R$ 9.000,00</td></tr>
                        <tr><td>Janeiro 2025</td><td>R$ 8.500,00</td><td>R$ 850,00</td><td>R$ 7.650,00</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
