<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$qualityLabels = ['poor'=>'Ruim','degraded'=>'Degradada','good'=>'Boa','excellent'=>'Excelente','unknown'=>'Desconhecida'];
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">
<div class="ops-topbar">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="porBusca" placeholder="Buscar por cliente ou guincheiro" autocomplete="off" aria-label="Buscar trilhas"></div>
    <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo (int)$resumo['total']; ?> trilhas</span></div>
</div>
<header class="page-head mb-3"><div><span class="eyebrow">Qualidade e Segurança</span><h1><i class="fas fa-route me-2 text-primary-custom"></i>Proof-of-Road</h1><p>Auditoria cross-pedidos da qualidade de rastreamento.</p></div></header>
<div class="stat-grid mb-4"><div class="stat-grid__item"><span class="stat-grid__label">Pedidos com trilha</span><strong class="stat-grid__value"><?php echo (int)$resumo['total']; ?></strong></div><div class="stat-grid__item is-danger"><span class="stat-grid__label">Qualidade ruim ou degradada</span><strong class="stat-grid__value"><?php echo (int)$resumo['ruins']; ?></strong></div></div>
<div class="card mb-4"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Qualidade</label><select name="tracking_quality" class="form-select"><option value="">Todas</option><?php foreach($qualityLabels as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo ($_GET['tracking_quality']??'')===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label">Últimos dias</label><input class="form-control" type="number" min="0" name="dias" value="<?php echo (int)($_GET['dias']??0); ?>"></div><div class="col-md-2"><label class="form-label">Pedido</label><input class="form-control" type="number" name="pedido_id" value="<?php echo (int)($_GET['pedido_id']??0); ?>"></div><div class="col-md-3"><button class="btn btn-primary">Filtrar</button> <a class="btn btn-outline-secondary" href="<?php echo $bp; ?>/admin/proof-of-road">Limpar</a></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Pedido</th><th>Cliente</th><th>Guincheiro</th><th>Qualidade</th><th>Rejeitados</th><th>Maior gap</th><th>Data</th></tr></thead><tbody>
<?php if (!$trilhas): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhuma trilha encontrada.</td></tr><?php else: foreach($trilhas as $t): ?><tr data-search="<?php echo htmlspecialchars(strtolower((string)$t['cliente_nome'] . ' ' . (string)($t['guincho_nome'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
 <td><a href="<?php echo $bp; ?>/admin/pedido/trilha/<?php echo (int)$t['pedido_id']; ?>">#<?php echo (int)$t['pedido_id']; ?></a></td><td><?php echo htmlspecialchars((string)$t['cliente_nome']); ?></td><td><?php echo htmlspecialchars((string)($t['guincho_nome'] ?: 'Não atribuído')); ?></td>
 <td><?php echo htmlspecialchars($qualityLabels[$t['tracking_quality']] ?? (string)$t['tracking_quality']); ?></td><td><?php echo number_format((float)$t['rejected_percent'], 1, ',', '.'); ?>% (<?php echo (int)$t['rejected_points']; ?>)</td><td><?php echo (int)$t['max_gap_seconds']; ?> s</td><td><?php echo htmlspecialchars((string)($t['pedido_criado_em'] ?? '')); ?></td>
</tr><?php endforeach; endif; ?></tbody></table></div></div>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var busca = document.getElementById('porBusca');
    var linhas = Array.prototype.slice.call(document.querySelectorAll('tr[data-search]'));
    if (!busca) return;
    busca.addEventListener('input', function () {
        var query = busca.value.trim().toLowerCase();
        linhas.forEach(function (linha) {
            linha.hidden = Boolean(query) && (linha.getAttribute('data-search') || '').indexOf(query) < 0;
        });
    });
})();
</script>
</main><?php include __DIR__ . '/../layouts/footer.php'; ?>
