<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $demandas = $demandas ?? []; include __DIR__ . '/../layouts/header.php'; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_funcionario.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Atendimento</span>
            <h1><i class="fas fa-paper-plane me-2 text-primary-custom"></i>Minhas demandas</h1>
            <p>Acompanhe o status de tudo que você solicitou — só um gerente pode aprovar, rejeitar ou executar.</p>
        </div>
    </header>

    <section class="fin-card p-4 p-lg-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>#</th><th>Tipo</th><th>Pedido</th><th>Status</th><th>Gerente</th><th>Nota do gerente</th><th>Criada em</th></tr></thead>
                <tbody>
                <?php foreach ($demandas as $d):
                    $badgeMap = [
                        'pendente' => 'secondary', 'aprovada_parcial' => 'info', 'aprovada' => 'primary',
                        'executada' => 'success', 'rejeitada' => 'danger', 'falhou' => 'dark',
                    ];
                ?>
                    <tr>
                        <td>#<?php echo (int)$d['id']; ?></td>
                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$d['tipo']))); ?></td>
                        <td><?php echo $d['pedido_id'] ? '#' . (int)$d['pedido_id'] : '—'; ?></td>
                        <td><span class="badge bg-<?php echo $badgeMap[$d['status']] ?? 'secondary'; ?>"><?php echo htmlspecialchars((string)$d['status']); ?></span></td>
                        <td><?php echo htmlspecialchars((string)($d['gerente_nome'] ?? '—')); ?></td>
                        <td class="small text-muted"><?php echo htmlspecialchars((string)($d['nota_gerente'] ?? ($d['erro_execucao'] ?? '—'))); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime((string)$d['criado_em'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($demandas)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Nenhuma demanda criada ainda.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
