<?php
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();
/**
 * Pedidos — reestruturada pro padrão shell-ops (mesma arquitetura de
 * /admin/central, /admin/ocorrencias, /admin/carteiras, /admin/guinchos).
 * A fila vem paginada/filtrada no servidor (status/busca/data — igual a
 * antes), e o workspace de detalhe (mapa/timeline/chat) reaproveita o MESMO
 * módulo JS e a MESMA API real de /admin/central (AdminOrderWorkspace +
 * /api/admin/orders/{id}) — não duplica lógica, só muda a fonte da fila
 * (aqui cobre todos os status, não só ativos).
 *
 * @var array $pedidos
 * @var array $worklist
 * @var int $total
 * @var int $totalPaginas
 * @var string $csrfToken
 */
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
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <form method="GET" action="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" name="busca" value="<?php echo htmlspecialchars($buscaAtual); ?>" placeholder="Buscar por nº do pedido, cliente, placa ou endereço" autocomplete="off">
        <?php if ($statusAtual !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($statusAtual); ?>"><?php endif; ?>
        <?php if ($dataAtual !== ''): ?><input type="hidden" name="data" value="<?php echo htmlspecialchars($dataAtual); ?>"><?php endif; ?>
    </form>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo (int)($total ?? 0); ?> pedidos<?php echo ($statusAtual !== '' || $buscaAtual !== '' || $dataAtual !== '') ? ' · filtrado' : ''; ?></span>
        <?php if ($statusAtual !== '' || $buscaAtual !== '' || $dataAtual !== ''): ?><a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="ops-dashboard-link"><i class="fas fa-xmark me-1"></i>Limpar filtro</a><?php endif; ?>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/novo" class="ops-dashboard-link"><i class="fas fa-plus me-1"></i>Novo Pedido</a>
    </div>
</div>

<?php
$resumoPedidos = $resumoPedidos ?? [];
?>
<section class="ops-summary" aria-label="Resumo de pedidos">
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=aguardando_guincho" style="text-decoration:none;">
        <article class="ops-metric <?php echo (int)($resumoPedidos['aguardando_guincho'] ?? 0) > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Aguard. guincho<?php echo $statusAtual === 'aguardando_guincho' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['aguardando_guincho'] ?? 0); ?></strong>
            <span class="ops-metric__trend">Requer ação</span>
        </article>
    </a>
    <article class="ops-metric">
        <span class="ops-metric__label">Em atendimento</span>
        <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['em_atendimento'] ?? 0); ?></strong>
        <span class="ops-metric__trend">A caminho / no local / reboque</span>
    </article>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=concluido&data=<?php echo date('Y-m-d'); ?>" style="text-decoration:none;">
        <article class="ops-metric">
            <span class="ops-metric__label">Concluídos hoje</span>
            <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['concluido_hoje'] ?? 0); ?></strong>
        </article>
    </a>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=cancelado" style="text-decoration:none;">
        <article class="ops-metric <?php echo $statusAtual === 'cancelado' ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Cancelados<?php echo $statusAtual === 'cancelado' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['cancelado'] ?? 0); ?></strong>
        </article>
    </a>
</section>

<div class="shell-ops" id="pedShell">

    <aside class="shell-ops-sidebar" id="pedSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Pedidos">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Pedidos</h2>
            <p><span id="pedWorklistCount"><?php echo count($worklist ?? []); ?></span> nesta página · <?php echo (int)($total ?? 0); ?> no total</p>
        </header>

        <div class="d-flex gap-1 flex-wrap" style="padding:0 16px 10px;">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="btn btn-sm <?php echo $statusAtual === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todos</a>
            <?php foreach ($statusLabels as $val => $label): ?>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=<?php echo $val; ?>" class="btn btn-sm <?php echo $statusAtual === $val ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="pedWorklistSearch" placeholder="Filtrar nesta página" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="pedWorklistResults">
            <?php if (empty($worklist)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum pedido encontrado com este filtro.
                </div>
            <?php else: foreach ($worklist as $i => $w): ?>
                <button
                    type="button"
                    class="ops-worklist-item <?php echo $w['prioridade'] === 'critical' ? 'is-critical' : ($w['prioridade'] === 'warning' ? 'is-warning' : ''); ?>"
                    data-order-id="<?php echo (int)$w['id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars(mb_strtolower($w['codigo'] . ' ' . $w['cliente_nome'] . ' ' . $w['veiculo_resumo']), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($w['codigo']); ?></strong>
                            <span class="ops-badge ops-badge--<?php echo htmlspecialchars($w['status_css']); ?>">
                                <?php echo htmlspecialchars($w['status_label']); ?>
                            </span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($w['cliente_nome']); ?></span>
                        <span class="ops-worklist-item__vehicle"><?php echo htmlspecialchars($w['veiculo_resumo']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars($w['guincho_operador'] ? 'Prestador: ' . $w['guincho_operador'] : 'Sem prestador atribuído'); ?></span>
                            <span>Há <?php echo (int)$w['minutos_decorridos']; ?> min</span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($w['prioridade'] === 'warning'): ?>
                            <span class="ops-signal is-warning" title="Aguardando há mais de 15 min">
                                <i class="fas fa-clock"></i>
                            </span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="d-flex flex-wrap gap-1 justify-content-center" style="padding:10px 16px;">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a class="btn btn-sm <?php echo $i === $paginaAtual ? 'btn-primary' : 'btn-outline-secondary'; ?>"
               href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?pagina=<?php echo $i; ?>&status=<?php echo urlencode($statusAtual); ?>&busca=<?php echo urlencode($buscaAtual); ?>&data=<?php echo urlencode($dataAtual); ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="shell-ops-workspace" id="pedWorkspace" aria-live="polite">
        <?php if (empty($worklist)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum pedido pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-order-workspace.js?v=20260815-1"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
AdminOrderWorkspace.init({
    shellId: 'pedShell',
    resultsId: 'pedWorklistResults',
    workspaceId: 'pedWorkspace',
    worklistSearchId: 'pedWorklistSearch',
    apiBase: '<?php echo addslashes($bp); ?>/api/admin/orders',
    csrfToken: <?php echo json_encode($csrfToken); ?>,
    worklistData: <?php echo json_encode($worklist ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    osrmBaseUrl: <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>,
    emptyLabel: 'Nenhum pedido selecionado.'
});
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Ocorrências, Carteiras e Guinchos.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();
/**
 * Pedidos — reestruturada pro padrão shell-ops (mesma arquitetura de
 * /admin/central, /admin/ocorrencias, /admin/carteiras, /admin/guinchos).
 * A fila vem paginada/filtrada no servidor (status/busca/data — igual a
 * antes), e o workspace de detalhe (mapa/timeline/chat) reaproveita o MESMO
 * módulo JS e a MESMA API real de /admin/central (AdminOrderWorkspace +
 * /api/admin/orders/{id}) — não duplica lógica, só muda a fonte da fila
 * (aqui cobre todos os status, não só ativos).
 *
 * @var array $pedidos
 * @var array $worklist
 * @var int $total
 * @var int $totalPaginas
 * @var string $csrfToken
 */
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
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <form method="GET" action="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="text" name="busca" value="<?php echo htmlspecialchars($buscaAtual); ?>" placeholder="Buscar por nº do pedido, cliente, placa ou endereço" autocomplete="off">
        <?php if ($statusAtual !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($statusAtual); ?>"><?php endif; ?>
        <?php if ($dataAtual !== ''): ?><input type="hidden" name="data" value="<?php echo htmlspecialchars($dataAtual); ?>"><?php endif; ?>
    </form>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo (int)($total ?? 0); ?> pedidos<?php echo ($statusAtual !== '' || $buscaAtual !== '' || $dataAtual !== '') ? ' · filtrado' : ''; ?></span>
        <?php if ($statusAtual !== '' || $buscaAtual !== '' || $dataAtual !== ''): ?><a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="ops-dashboard-link"><i class="fas fa-xmark me-1"></i>Limpar filtro</a><?php endif; ?>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/novo" class="ops-dashboard-link"><i class="fas fa-plus me-1"></i>Novo Pedido</a>
    </div>
</div>

<?php
$resumoPedidos = $resumoPedidos ?? [];
?>
<section class="ops-summary" aria-label="Resumo de pedidos">
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=aguardando_guincho" style="text-decoration:none;">
        <article class="ops-metric <?php echo (int)($resumoPedidos['aguardando_guincho'] ?? 0) > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Aguard. guincho<?php echo $statusAtual === 'aguardando_guincho' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['aguardando_guincho'] ?? 0); ?></strong>
            <span class="ops-metric__trend">Requer ação</span>
        </article>
    </a>
    <article class="ops-metric">
        <span class="ops-metric__label">Em atendimento</span>
        <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['em_atendimento'] ?? 0); ?></strong>
        <span class="ops-metric__trend">A caminho / no local / reboque</span>
    </article>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=concluido&data=<?php echo date('Y-m-d'); ?>" style="text-decoration:none;">
        <article class="ops-metric">
            <span class="ops-metric__label">Concluídos hoje</span>
            <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['concluido_hoje'] ?? 0); ?></strong>
        </article>
    </a>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=cancelado" style="text-decoration:none;">
        <article class="ops-metric <?php echo $statusAtual === 'cancelado' ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Cancelados<?php echo $statusAtual === 'cancelado' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($resumoPedidos['cancelado'] ?? 0); ?></strong>
        </article>
    </a>
</section>

<div class="shell-ops" id="pedShell">

    <aside class="shell-ops-sidebar" id="pedSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Pedidos">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Pedidos</h2>
            <p><span id="pedWorklistCount"><?php echo count($worklist ?? []); ?></span> nesta página · <?php echo (int)($total ?? 0); ?> no total</p>
        </header>

        <div class="d-flex gap-1 flex-wrap" style="padding:0 16px 10px;">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos" class="btn btn-sm <?php echo $statusAtual === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todos</a>
            <?php foreach ($statusLabels as $val => $label): ?>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?status=<?php echo $val; ?>" class="btn btn-sm <?php echo $statusAtual === $val ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="pedWorklistSearch" placeholder="Filtrar nesta página" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="pedWorklistResults">
            <?php if (empty($worklist)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum pedido encontrado com este filtro.
                </div>
            <?php else: foreach ($worklist as $i => $w): ?>
                <button
                    type="button"
                    class="ops-worklist-item <?php echo $w['prioridade'] === 'critical' ? 'is-critical' : ($w['prioridade'] === 'warning' ? 'is-warning' : ''); ?>"
                    data-order-id="<?php echo (int)$w['id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars(mb_strtolower($w['codigo'] . ' ' . $w['cliente_nome'] . ' ' . $w['veiculo_resumo']), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($w['codigo']); ?></strong>
                            <span class="ops-badge ops-badge--<?php echo htmlspecialchars($w['status_css']); ?>">
                                <?php echo htmlspecialchars($w['status_label']); ?>
                            </span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($w['cliente_nome']); ?></span>
                        <span class="ops-worklist-item__vehicle"><?php echo htmlspecialchars($w['veiculo_resumo']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars($w['guincho_operador'] ? 'Prestador: ' . $w['guincho_operador'] : 'Sem prestador atribuído'); ?></span>
                            <span>Há <?php echo (int)$w['minutos_decorridos']; ?> min</span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($w['prioridade'] === 'warning'): ?>
                            <span class="ops-signal is-warning" title="Aguardando há mais de 15 min">
                                <i class="fas fa-clock"></i>
                            </span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="d-flex flex-wrap gap-1 justify-content-center" style="padding:10px 16px;">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a class="btn btn-sm <?php echo $i === $paginaAtual ? 'btn-primary' : 'btn-outline-secondary'; ?>"
               href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos?pagina=<?php echo $i; ?>&status=<?php echo urlencode($statusAtual); ?>&busca=<?php echo urlencode($buscaAtual); ?>&data=<?php echo urlencode($dataAtual); ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="shell-ops-workspace" id="pedWorkspace" aria-live="polite">
        <?php if (empty($worklist)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum pedido pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-order-workspace.js?v=20260802-1"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
AdminOrderWorkspace.init({
    shellId: 'pedShell',
    resultsId: 'pedWorklistResults',
    workspaceId: 'pedWorkspace',
    worklistSearchId: 'pedWorklistSearch',
    apiBase: '<?php echo addslashes($bp); ?>/api/admin/orders',
    csrfToken: <?php echo json_encode($csrfToken); ?>,
    worklistData: <?php echo json_encode($worklist ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    osrmBaseUrl: <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>,
    emptyLabel: 'Nenhum pedido selecionado.'
});
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Ocorrências, Carteiras e Guinchos.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
