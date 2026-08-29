<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $pendentes = $pendentes ?? []; include __DIR__ . '/../layouts/header.php'; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_gerente.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gerência</span>
            <h1><i class="fas fa-list-check me-2 text-primary-custom"></i>Demandas pendentes</h1>
            <p>Ordenadas da mais antiga para a mais nova.</p>
        </div>
    </header>

    <section class="fin-card p-4 p-lg-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>#</th><th>Tipo</th><th>Solicitante</th><th>Pedido</th><th>Valor</th><th>Status</th><th>Criada em</th><th class="text-center">Ação</th></tr></thead>
                <tbody>
                <?php foreach ($pendentes as $d): ?>
                    <tr>
                        <td>#<?php echo (int)$d['id']; ?></td>
                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$d['tipo']))); ?>
                            <?php if (!empty($d['requer_dupla_aprovacao'])): ?>
                            <span class="badge bg-dark ms-1" title="Exige aprovação de dois gerentes diferentes">2 aprovações</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)($d['solicitante_nome'] ?? '—')); ?></td>
                        <td><?php echo $d['pedido_id'] ? '#' . (int)$d['pedido_id'] : '—'; ?></td>
                        <td><?php echo $d['valor_envolvido'] !== null ? 'R$ ' . number_format((float)$d['valor_envolvido'], 2, ',', '.') : '—'; ?></td>
                        <td><span class="badge bg-<?php echo $d['status'] === 'aprovada_parcial' ? 'info' : 'secondary'; ?>"><?php echo htmlspecialchars((string)$d['status']); ?></span></td>
                        <td><?php echo date('d/m/Y H:i', strtotime((string)$d['criado_em'])); ?></td>
                        <td class="text-center">
                            <a class="btn btn-outline-primary btn-sm" href="<?php echo $bp; ?>/gerente/demanda/<?php echo (int)$d['id']; ?>">Revisar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pendentes)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">Nenhuma demanda pendente. 🎉</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
