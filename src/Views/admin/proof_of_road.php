<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
$qualityLabels = ['poor'=>'Ruim','degraded'=>'Degradada','good'=>'Boa','excellent'=>'Excelente','unknown'=>'Desconhecida'];
?>
<div class="bo-page">
<nav class="bo-tabs" aria-label="Navegação de qualidade"><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/avaliacoes" class="<?= $gfQualityTab === 'avaliacoes' ? 'is-active' : '' ?>">Avaliações</a><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/proof-of-road" class="<?= $gfQualityTab === 'proof' ? 'is-active' : '' ?>">Proof-of-Road</a><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/checklists-incompletos" class="<?= $gfQualityTab === 'checklists' ? 'is-active' : '' ?>">Checklists incompletos</a><a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/admin/revisoes-manuais">Revisões manuais</a></nav>
<header class="bo-page-title"><div><span class="eyebrow">Qualidade e Segurança</span><h1><i class=""></i>Proof-of-Road</h1><p>Auditoria cross-pedidos da qualidade de rastreamento.</p></div></header>
<div class="bo-metrics"><div class="bo-metric"><span class="bo-metric__label">Pedidos com trilha</span><strong class="bo-metric__value"><?php echo (int)$resumo['total']; ?></strong></div><div class="bo-metric is-danger"><span class="bo-metric__label">Qualidade ruim ou degradada</span><strong class="bo-metric__value"><?php echo (int)$resumo['ruins']; ?></strong></div></div>
<div class="bo-panel"><div class="bo-panel-body"><form class="bo-grid"><div class="bo-field-group"><label class="bo-label">Qualidade</label><select name="tracking_quality" class="bo-field"><option value="">Todas</option><?php foreach($qualityLabels as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo ($_GET['tracking_quality']??'')===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div><div class="bo-field-group"><label class="bo-label">Últimos dias</label><input class="bo-field" type="number" min="0" name="dias" value="<?php echo (int)($_GET['dias']??0); ?>"></div><div class="bo-field-group"><label class="bo-label">Pedido</label><input class="bo-field" type="number" name="pedido_id" value="<?php echo (int)($_GET['pedido_id']??0); ?>"></div><div class="bo-field-group"><button class="bo-btn bo-btn--primary">Filtrar</button> <a class="bo-btn" href="<?php echo $bp; ?>/admin/proof-of-road">Limpar</a></div></form></div></div>
<div class="bo-panel"><div class="bo-table-scroll"><table class="bo-ledger"><thead><tr><th>Pedido</th><th>Cliente</th><th>Guincheiro</th><th>Qualidade</th><th>Rejeitados</th><th>Maior gap</th><th>Data</th></tr></thead><tbody>
<?php if (!$trilhas): ?><tr><td colspan="7" class="bo-empty">Nenhuma trilha encontrada.</td></tr><?php else: foreach($trilhas as $t): ?><tr data-search="<?php echo htmlspecialchars(strtolower((string)$t['cliente_nome'] . ' ' . (string)($t['guincho_nome'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
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
</div>
<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Proof-of-Road'; $breadcrumb=['Qualidade','Proof-of-Road']; $assets_css=['/public/assets/css/pages/bo-admin-avaliacoes.css']; require __DIR__ . '/../layouts/backoffice_shell.php';