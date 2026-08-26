<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-clock-rotate-left me-2 text-primary-custom"></i>Histórico de Atendimentos</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Data</th><th>Cliente</th><th>Serviço</th><th>Valor Bruto</th><th>Valor Líquido</th><th>Avaliação</th></tr></thead>
                    <tbody>
                        <?php if (!empty($historico)): foreach ($historico as $h): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($h['data']); ?></td>
                            <td><?php echo htmlspecialchars($h['cliente']); ?></td>
                            <td><?php echo htmlspecialchars($h['servico']); ?></td>
                            <td>R$ <?php echo number_format($h['valor_bruto'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($h['valor_liquido'], 2, ',', '.'); ?></td>
                            <td><?php echo str_repeat('★', $h['avaliacao'] ?? 5); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td>01/02/2025</td><td>João Silva</td><td>Pneu Furado</td><td>R$ 150,00</td><td>R$ 135,00</td><td>★★★★★</td></tr>
                        <tr><td>15/02/2025</td><td>Maria Costa</td><td>Bateria</td><td>R$ 80,00</td><td>R$ 72,00</td><td>★★★★☆</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
