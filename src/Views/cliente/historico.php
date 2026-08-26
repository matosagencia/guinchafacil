<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$statusLabels = [
    'aguardando_pagamento' => 'Aguard. Pagamento',
    'aguardando_guincho'   => 'Aguard. Guincho',
    'a_caminho'            => 'A Caminho',
    'no_local'             => 'No Local',
    'em_reboque'           => 'Em Reboque',
    'concluido'            => 'Concluído',
    'cancelado'            => 'Cancelado',
];
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-clock-rotate-left me-2" style="color:var(--primary)"></i>Histórico de Pedidos
            </div>
            <div class="page-subtitle">
                <?php echo (int)($total ?? count($pedidos ?? [])); ?> pedido(s) no total
            </div>
        </div>
        <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-primary">
            <i class="fas fa-circle-plus me-2"></i>Novo Pedido
        </a>
    </div>

    <?php if (!empty($_GET['avaliado'])): ?>
    <div class="alert alert-success mb-3">
        <i class="fas fa-star me-2"></i>Avaliação enviada! Obrigado pelo feedback.
    </div>
    <?php elseif (!empty($_GET['ja_avaliou'])): ?>
    <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle me-2"></i>Você já avaliou este atendimento.
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Veículo</th>
                            <th>Problema</th>
                            <th>Guincheiro</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pedidos)): ?>
                        <?php foreach ($pedidos as $p): ?>
                        <?php
                            $status     = $p['status'] ?? '';
                            $guinchoId  = (int)($p['guincho_id'] ?? 0);
                            $jaAvaliou  = false;
                            if ($status === 'concluido' && $guinchoId) {
                                try { $jaAvaliou = Avaliacao::jaAvaliou((int)$p['id'], (int)$p['cliente_id']); }
                                catch (Throwable $e) { $jaAvaliou = false; }
                            }
                        ?>
                        <tr>
                            <td style="font-weight:600">#<?php echo (int)$p['id']; ?></td>
                            <td>
                                <?php if (!empty($p['placa'])): ?>
                                <div style="font-size:.85rem;font-weight:600">
                                    <?php echo htmlspecialchars(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '')); ?>
                                </div>
                                <span class="badge badge-aguardando_pagamento" style="font-size:.7rem">
                                    <?php echo htmlspecialchars($p['placa']); ?>
                                </span>
                                <?php else: ?>
                                <span style="color:var(--theme-muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.85rem">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $p['tipo_problema'] ?? '—'))); ?>
                            </td>
                            <td style="font-size:.84rem">
                                <?php echo htmlspecialchars($p['guincho_operador'] ?? '—'); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($status); ?>">
                                    <?php echo $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                            </td>
                            <td style="font-weight:600">
                                R$ <?php echo number_format($p['custo_estimado'] ?? 0, 2, ',', '.'); ?>
                            </td>
                            <td>
                                <div style="font-size:.84rem"><?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></div>
                                <small style="color:var(--theme-muted)"><?php echo date('H:i', strtotime($p['criado_em'])); ?></small>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="<?php echo $bp; ?>/cliente/pedido/<?php echo (int)$p['id']; ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </a>
                                    <?php if ($status === 'concluido' && $guinchoId && !$jaAvaliou): ?>
                                    <a href="<?php echo $bp; ?>/cliente/avaliar/<?php echo (int)$p['id']; ?>"
                                       class="btn btn-sm btn-warning" style="color:#000">
                                        <i class="fas fa-star me-1"></i>Avaliar
                                    </a>
                                    <?php elseif ($jaAvaliou): ?>
                                    <span class="btn btn-sm btn-outline-secondary disabled" style="font-size:.75rem">
                                        <i class="fas fa-check me-1"></i>Avaliado
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5" style="color:var(--theme-muted)">
                                <i class="fas fa-inbox fa-3x d-block mb-3" style="opacity:.25"></i>
                                <div style="font-size:1rem;font-weight:600;color:var(--theme-text);margin-bottom:.5rem">
                                    Nenhum pedido ainda
                                </div>
                                <div style="font-size:.85rem;margin-bottom:1rem">
                                    Solicite seu primeiro atendimento de guincho agora!
                                </div>
                                <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-primary">
                                    <i class="fas fa-circle-plus me-2"></i>Pedir Socorro
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if (!empty($totalPaginas) && $totalPaginas > 1): ?>
    <?php $paginaAtual = max(1, (int)($_GET['pagina'] ?? 1)); ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($paginaAtual > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $bp; ?>/cliente/historico?pagina=<?php echo $paginaAtual - 1; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo $bp; ?>/cliente/historico?pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($paginaAtual < $totalPaginas): ?>
            <li class="page-item">
                <a class="page-link" href="<?php echo $bp; ?>/cliente/historico?pagina=<?php echo $paginaAtual + 1; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
