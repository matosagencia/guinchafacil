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
$statusAtual  = $_GET['status'] ?? '';
$buscaAtual   = $_GET['busca']  ?? '';
$dataAtual    = $_GET['data']   ?? '';
$paginaAtual  = max(1, (int)($_GET['pagina'] ?? 1));
$totalPaginas = $totalPaginas ?? 1;
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-list-check me-2 text-primary-custom"></i>Gerenciar Pedidos</div>
            <div class="page-subtitle">Total: <strong><?php echo (int)($total ?? 0); ?></strong> pedidos encontrados</div>
        </div>
        <a href="<?php echo $bp; ?>/admin/pedido/novo" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Novo Pedido
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" action="<?php echo $bp; ?>/admin/pedidos" class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Buscar (cliente, guincho, endereço)</label>
                    <input type="text" class="form-control form-control-sm" name="busca"
                           value="<?php echo htmlspecialchars($buscaAtual); ?>"
                           placeholder="Nome, e-mail, placa...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Todos os status</option>
                        <?php foreach ($statusLabels as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $statusAtual === $val ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Data</label>
                    <input type="date" class="form-control form-control-sm" name="data"
                           value="<?php echo htmlspecialchars($dataAtual); ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                    <a href="<?php echo $bp; ?>/admin/pedidos" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Tabela -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Veículo</th>
                            <th>Guincho</th>
                            <th>Problema</th>
                            <th>Status</th>
                            <th>Valor</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pedidos)): foreach ($pedidos as $p): ?>
                        <tr>
                            <td><strong>#<?php echo (int)$p['id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($p['cliente_nome'] ?? '—'); ?></div>
                                <?php if (!empty($p['cliente_telefone'])): ?>
                                <small style="color:var(--theme-muted)"><?php echo htmlspecialchars($p['cliente_telefone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['placa'])): ?>
                                <span class="badge badge-aguardando_pagamento"><?php echo htmlspecialchars($p['placa']); ?></span>
                                <br><small style="color:var(--theme-muted)"><?php echo htmlspecialchars($p['modelo'] ?? ''); ?></small>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['guincho_operador'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$p['tipo_problema'] ?? '—'))); ?></td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($p['status'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($statusLabels[$p['status'] ?? ''] ?? ucfirst($p['status'] ?? '')); ?>
                                </span>
                            </td>
                            <td>R$ <?php echo number_format($p['custo_estimado'] ?? 0, 2, ',', '.'); ?></td>
                            <td>
                                <div><?php echo date('d/m/Y', strtotime($p['criado_em'] ?? 'now')); ?></div>
                                <small style="color:var(--theme-muted)"><?php echo date('H:i', strtotime($p['criado_em'] ?? 'now')); ?></small>
                            </td>
                            <td>
                                <a href="<?php echo $bp; ?>/admin/pedido/<?php echo (int)$p['id']; ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5" style="color:var(--theme-muted)">
                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                Nenhum pedido encontrado com os filtros selecionados.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                <a class="page-link"
                   href="<?php echo $bp; ?>/admin/pedidos?pagina=<?php echo $i; ?>&status=<?php echo urlencode($statusAtual); ?>&busca=<?php echo urlencode($buscaAtual); ?>&data=<?php echo urlencode($dataAtual); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
