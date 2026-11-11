<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
?>

<div class="bo-page">
<?php $gfQualityTab = 'avaliacoes'; ?>
<nav class="bo-tabs" aria-label="Navegação de qualidade"><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/avaliacoes" class="<?= $gfQualityTab === 'avaliacoes' ? 'is-active' : '' ?>">Avaliações</a><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/proof-of-road" class="<?= $gfQualityTab === 'proof' ? 'is-active' : '' ?>">Proof-of-Road</a><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/checklists-incompletos" class="<?= $gfQualityTab === 'checklists' ? 'is-active' : '' ?>">Checklists incompletos</a><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/revisoes-manuais">Revisões manuais</a></nav>
<header class="bo-page-title"><div><span class="eyebrow">Qualidade e Segurança</span><h1><i class=""></i>Avaliações</h1><p>Feedback dos clientes sobre os atendimentos.</p></div></header>
<div class="bo-metrics">
 <article class="bo-metric"><span class="bo-metric__label">Nota média</span><strong class="bo-metric__value"><?php echo number_format((float)$resumo['media'], 1, ',', '.'); ?> <span class="bo-status bo-status--warning" aria-hidden="true">★</span></strong></article>
 <article class="bo-metric"><span class="bo-metric__label">Total de avaliações</span><strong class="bo-metric__value"><?php echo (int)$resumo['total']; ?></strong></article>
</div><div class="bo-panel"><div class="bo-panel__body"><form class="bo-grid" method="get">
 <div class="bo-field-group"><label class="bo-label">Guincho ID</label><input class="bo-field" name="guincho_id" value="<?php echo (int)$filtros['guincho_id']; ?>" placeholder="Opcional"></div>
 <div class="bo-field-group"><label class="bo-label">Nota</label><select class="bo-field" name="nota"><option value="0">Todas</option><?php for($n=1;$n<=5;$n++): ?><option value="<?php echo $n; ?>" <?php echo (int)$filtros['nota']===$n?'selected':''; ?>><?php echo $n; ?> estrela(s)</option><?php endfor; ?></select></div>
 <div class="bo-field-group"><button class="bo-btn bo-btn--primary">Filtrar</button> <a class="bo-btn" href="<?php echo $bp; ?>/admin/avaliacoes">Limpar</a></div>
</form></div></div>
<div class="bo-panel"><div class="bo-table-scroll"><table class="bo-ledger"><thead><tr><th>Nota</th><th>Comentário</th><th>Pedido</th><th>Cliente</th><th>Guincheiro</th><th>Data</th></tr></thead><tbody>
<?php if (!$avaliacoes): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhuma avaliação encontrada.</td></tr><?php else: foreach($avaliacoes as $a): ?><tr data-search="<?php echo htmlspecialchars(strtolower((string)$a['cliente_nome'] . ' ' . (string)$a['guincho_nome'] . ' ' . (string)($a['comentario'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
 <td class="bo-status bo-status--warning" aria-label="<?php echo (int)$a['estrelas']; ?> estrelas"><?php echo str_repeat('★', (int)$a['estrelas']); ?><span class="bo-muted"><?php echo str_repeat('★', 5-(int)$a['estrelas']); ?></span></td>
 <td><?php echo htmlspecialchars((string)($a['comentario'] ?: '-')); ?></td><td><a href="<?php echo $bp; ?>/admin/pedido/<?php echo (int)$a['pedido_id']; ?>">#<?php echo (int)$a['pedido_id']; ?></a></td>
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
</div>
<?php
$conteudo_pagina = ob_get_clean();
$perfil = 'admin';
$titulo_pagina = 'Avaliações';
$breadcrumb = ['Qualidade', 'Avaliações'];
$assets_css = ['/public/assets/css/pages/bo-admin-avaliacoes.css'];
require __DIR__ . '/../layouts/backoffice_shell.php';
