<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">
<div class="ops-topbar">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="avaliacoesBusca" placeholder="Buscar por cliente, guincheiro ou comentário" autocomplete="off" aria-label="Buscar avaliações"></div>
    <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo (int)$resumo['total']; ?> avaliações</span></div>
</div>
<header class="page-head mb-3"><div><span class="eyebrow">Qualidade e Segurança</span><h1><i class="fas fa-star-half-stroke me-2 text-primary-custom"></i>Avaliações</h1><p>Feedback dos clientes sobre os atendimentos.</p></div></header>
<div class="stat-grid mb-4">
 <div class="stat-grid__item"><span class="stat-grid__label">Nota média</span><strong class="stat-grid__value"><?php echo number_format((float)$resumo['media'], 1, ',', '.'); ?> <span class="text-warning">★</span></strong></div>
 <div class="stat-grid__item"><span class="stat-grid__label">Total de avaliações</span><strong class="stat-grid__value"><?php echo (int)$resumo['total']; ?></strong></div>
</div>
<div class="card mb-4"><div class="card-body"><form class="row g-2 align-items-end" method="get">
 <div class="col-md-4"><label class="form-label">Guincho ID</label><input class="form-control" name="guincho_id" value="<?php echo (int)$filtros['guincho_id']; ?>" placeholder="Opcional"></div>
 <div class="col-md-3"><label class="form-label">Nota</label><select class="form-select" name="nota"><option value="0">Todas</option><?php for($n=1;$n<=5;$n++): ?><option value="<?php echo $n; ?>" <?php echo (int)$filtros['nota']===$n?'selected':''; ?>><?php echo $n; ?> estrela(s)</option><?php endfor; ?></select></div>
 <div class="col-md-3"><button class="btn btn-primary">Filtrar</button> <a class="btn btn-outline-secondary" href="<?php echo $bp; ?>/admin/avaliacoes">Limpar</a></div>
</form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nota</th><th>Comentário</th><th>Pedido</th><th>Cliente</th><th>Guincheiro</th><th>Data</th></tr></thead><tbody>
<?php if (!$avaliacoes): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhuma avaliação encontrada.</td></tr><?php else: foreach($avaliacoes as $a): ?><tr data-search="<?php echo htmlspecialchars(strtolower((string)$a['cliente_nome'] . ' ' . (string)$a['guincho_nome'] . ' ' . (string)($a['comentario'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
 <td class="text-warning" aria-label="<?php echo (int)$a['estrelas']; ?> estrelas"><?php echo str_repeat('★', (int)$a['estrelas']); ?><span class="text-muted"><?php echo str_repeat('★', 5-(int)$a['estrelas']); ?></span></td>
 <td><?php echo htmlspecialchars((string)($a['comentario'] ?: '—')); ?></td><td><a href="<?php echo $bp; ?>/admin/pedido/<?php echo (int)$a['pedido_id']; ?>">#<?php echo (int)$a['pedido_id']; ?></a></td>
 <td><?php echo htmlspecialchars((string)$a['cliente_nome']); ?></td><td><?php echo htmlspecialchars((string)$a['guincho_nome']); ?></td><td><?php echo htmlspecialchars((string)$a['criado_em']); ?></td>
</tr><?php endforeach; endif; ?></tbody></table></div></div>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var busca = document.getElementById('avaliacoesBusca');
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
