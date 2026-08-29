<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $jobs = $jobs ?? []; $pagamentos = $pagamentos ?? []; include __DIR__ . '/../layouts/header.php'; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_funcionario.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Financeiro</span>
            <h1><i class="fas fa-coins me-2 text-primary-custom"></i>Financeiro</h1>
            <p>Solicite reprocessamento de repasse travado ou reembolso ao cliente — ambos exigem aprovação de gerente, e valores acima do limiar configurado exigem dois gerentes.</p>
        </div>
    </header>

    <section class="fin-card p-4 p-lg-5 mb-4">
        <h3 class="mb-3 fin-subtitle"><i class="fas fa-truck-ramp-box me-2 text-primary-custom"></i>Repasses travados</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Job</th><th>Pedido</th><th>Guincho</th><th>Status</th><th>Último erro</th><th class="text-center">Ação</th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $j): ?>
                    <tr>
                        <td>#<?php echo (int)$j['id']; ?></td>
                        <td>#<?php echo (int)$j['pedido_id']; ?></td>
                        <td><?php echo htmlspecialchars((string)($j['guincho_nome'] ?? '—')); ?></td>
                        <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars((string)$j['status']); ?></span></td>
                        <td class="small text-muted"><?php echo htmlspecialchars((string)($j['last_error'] ?? '—')); ?></td>
                        <td class="text-center">
                            <a class="btn btn-outline-primary btn-sm" href="<?php echo $bp; ?>/funcionario/demandas/nova?tipo=pagamento&payment_job_id=<?php echo (int)$j['id']; ?>">Solicitar reprocessamento</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($jobs)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum repasse travado no momento.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="fin-card p-4 p-lg-5">
        <h3 class="mb-3 fin-subtitle"><i class="fas fa-rotate-left me-2 text-primary-custom"></i>Pagamentos aprovados (reembolso)</h3>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Pagamento</th><th>Pedido</th><th>Cliente</th><th class="text-end">Valor</th><th class="text-center">Ação</th></tr></thead>
                <tbody>
                <?php foreach ($pagamentos as $p): ?>
                    <tr>
                        <td>#<?php echo (int)$p['pagamento_id']; ?></td>
                        <td>#<?php echo (int)$p['pedido_id']; ?></td>
                        <td><?php echo htmlspecialchars((string)($p['cliente_nome'] ?? '—')); ?></td>
                        <td class="text-end">R$ <?php echo number_format((float)$p['valor_total'], 2, ',', '.'); ?></td>
                        <td class="text-center">
                            <a class="btn btn-outline-danger btn-sm" href="<?php echo $bp; ?>/funcionario/demandas/nova?tipo=reembolso&pedido_id=<?php echo (int)$p['pedido_id']; ?>">Solicitar reembolso</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pagamentos)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum pagamento aprovado encontrado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
