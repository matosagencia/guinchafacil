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
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-historico.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <div class="history-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Histórico</span>
                <h1><i class="fas fa-clock-rotate-left me-2 history-icon-accent"></i>Histórico de Pedidos</h1>
                <p><?php echo (int)($total ?? count($pedidos ?? [])); ?> pedido(s) no total</p>
            </div>
            <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-primary">
                <i class="fas fa-circle-plus me-2"></i>Novo Pedido
            </a>
        </header>

        <?php if (!empty($_GET['avaliado'])): ?>
        <div class="alert alert-success mb-3">
            <i class="fas fa-star me-2"></i>Avaliação enviada! Obrigado pelo feedback.
        </div>
        <?php elseif (!empty($_GET['ja_avaliou'])): ?>
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle me-2"></i>Você já avaliou este atendimento.
        </div>
        <?php endif; ?>

        <section class="history-hero p-4 p-lg-5 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="history-chip"><i class="fas fa-route"></i>Pedidos recentes</span>
                        <span class="history-chip"><i class="fas fa-camera"></i>Evidências e fotos</span>
                        <span class="history-chip"><i class="fas fa-star"></i>Avaliações pós-serviço</span>
                    </div>
                    <h2 class="mb-2 history-title">Seu histórico completo de atendimento.</h2>
                    <p class="mb-0 text-muted">A mesma lógica de dados foi mantida. A mudança aqui é de apresentação, priorizando leitura rápida e ações claras por pedido.</p>
                </div>
                <div class="col-lg-4">
                    <div class="history-item">
                        <div class="small text-muted mb-1">Acesso rápido</div>
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Novo chamado de socorro</strong>
                            <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-sm btn-primary">Abrir</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="history-card p-4 p-lg-5">
            <div class="history-list">
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
                $temFotos = !empty($p['foto_plataforma']) || !empty($p['foto_destino']);
                ?>
                <article class="history-item">
                    <div class="history-item-head">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <strong class="history-pedido-id">Pedido #<?php echo (int)$p['id']; ?></strong>
                                <span class="badge badge-<?php echo htmlspecialchars($status); ?>">
                                    <?php echo $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                            </div>
                            <div class="small text-muted">
                                <?php echo date('d/m/Y', strtotime($p['criado_em'])); ?> às <?php echo date('H:i', strtotime($p['criado_em'])); ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Valor</div>
                            <strong>R$ <?php echo number_format((float)($p['custo_estimado'] ?? 0), 2, ',', '.'); ?></strong>
                        </div>
                    </div>

                    <div class="row g-3 align-items-center">
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Veículo</div>
                            <strong><?php echo !empty($p['placa']) ? htmlspecialchars(trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? ''))) : '—'; ?></strong>
                            <?php if (!empty($p['placa'])): ?>
                            <div><span class="badge badge-aguardando_pagamento mt-2"><?php echo htmlspecialchars($p['placa']); ?></span></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Problema</div>
                            <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $p['tipo_problema'] ?? '—'))); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Guincheiro</div>
                            <strong><?php echo htmlspecialchars($p['guincho_operador'] ?? '—'); ?></strong>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Fotos</div>
                            <?php if ($temFotos): ?>
                            <a href="<?php echo $bp; ?>/cliente/pedido/<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-info text-white">
                                <i class="fas fa-camera me-1"></i>Ver evidências
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a href="<?php echo $bp; ?>/cliente/pedido/<?php echo (int)$p['id']; ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>Ver pedido
                        </a>

                        <?php if ($status === 'concluido' && $guinchoId && !$jaAvaliou): ?>
                        <a href="<?php echo $bp; ?>/cliente/avaliar/<?php echo (int)$p['id']; ?>"
                           class="btn btn-sm btn-warning history-avaliar-btn">
                            <i class="fas fa-star me-1"></i>Avaliar atendimento
                        </a>
                        <?php elseif ($jaAvaliou): ?>
                        <span class="btn btn-sm btn-outline-secondary disabled">
                            <i class="fas fa-check me-1"></i>Avaliado
                        </span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center py-5 history-empty">
                    <i class="fas fa-inbox fa-3x d-block mb-3 history-empty-icon"></i>
                    <div class="history-empty-title">
                        Nenhum pedido ainda
                    </div>
                    <div class="history-empty-subtitle">
                        Solicite seu primeiro atendimento de guincho agora.
                    </div>
                    <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-primary">
                        <i class="fas fa-circle-plus me-2"></i>Pedir Socorro
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </section>

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
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
