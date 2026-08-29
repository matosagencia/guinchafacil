<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $pedidos = $pedidos ?? []; include __DIR__ . '/../layouts/header.php'; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_funcionario.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Atendimento</span>
            <h1><i class="fas fa-list me-2 text-primary-custom"></i>Pedidos</h1>
            <p>Solicite cancelamento ou conclusão manual assistida — a ação só acontece depois que um gerente aprovar.</p>
        </div>
    </header>

    <section class="fin-card p-4 p-lg-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Cliente</th><th>Guincho</th><th>Status</th><th>Criado em</th><th class="text-end">Valor</th><th class="text-center">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <tr>
                        <td>#<?php echo (int)$p['id']; ?></td>
                        <td><?php echo htmlspecialchars((string)($p['cliente_nome'] ?? '—')); ?></td>
                        <td><?php echo htmlspecialchars((string)($p['guincho_nome'] ?? '—')); ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$p['status']))); ?></span></td>
                        <td><?php echo date('d/m/Y H:i', strtotime((string)$p['criado_em'])); ?></td>
                        <td class="text-end">R$ <?php echo number_format((float)($p['custo_final'] ?? $p['custo_estimado'] ?? 0), 2, ',', '.'); ?></td>
                        <td class="text-center">
                            <?php if (!in_array($p['status'], ['concluido', 'cancelado'], true)): ?>
                            <a class="btn btn-outline-danger btn-sm" href="<?php echo $bp; ?>/funcionario/demandas/nova?tipo=cancelamento&pedido_id=<?php echo (int)$p['id']; ?>">Solicitar cancelamento</a>
                            <?php endif; ?>
                            <?php if (in_array($p['status'], ['a_caminho', 'no_local', 'em_reboque'], true)): ?>
                            <a class="btn btn-outline-dark btn-sm" href="<?php echo $bp; ?>/funcionario/demandas/nova?tipo=conclusao_manual&pedido_id=<?php echo (int)$p['id']; ?>">Conclusão manual</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pedidos)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Nenhum pedido encontrado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
