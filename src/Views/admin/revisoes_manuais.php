<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$esc = static function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
$url = static function (array $overrides = []) use ($bp, $status, $pedidoId): string {
    $args = array_merge(['status' => $status, 'pedido_id' => $pedidoId ?: null], $overrides);
    return $bp . '/admin/revisoes-manuais?' . http_build_query(array_filter($args, static fn($v) => $v !== null && $v !== ''));
};
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $esc($bp) ?>/public/assets/css/pages/gf-f18f19-shell-unificado.css?v=20261018-2">
<link rel="stylesheet" href="<?= $esc($bp) ?>/public/assets/css/pages/gf-f18f110-qualidade.css?v=20261018-112">
<link rel="stylesheet" href="<?= $esc($bp) ?>/public/assets/css/pages/gf-f18f113-revisoes.css?v=2">
<script<?= csp_script_nonce_attr() ?>>document.body.classList.add('gf-f18f19','gf-f18f110-quality');</script>
<div class="main-wrapper shell admin-shell gf-manual-reviews">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">
<nav class="gf-manual-tabs" aria-label="Qualidade">
  <a href="<?= $esc($bp) ?>/admin/avaliacoes">Avaliações</a>
  <a href="<?= $esc($bp) ?>/admin/proof-of-road">Proof-of-Road</a>
  <a href="<?= $esc($bp) ?>/admin/checklists-incompletos">Checklists incompletos</a>
  <a class="active" aria-current="page" href="<?= $esc($bp) ?>/admin/revisoes-manuais">Revisões manuais</a>
</nav>
<section class="gf-manual-head"><div><small>QUALIDADE E SEGURANÇA</small><h1>Revisões manuais</h1>
<p>Auditoria das conclusões realizadas sem confirmação operacional automática. A decisão não desfaz o atendimento ou pagamentos.</p></div>
<strong><?= number_format($total, 0, ',', '.') ?> registro(s)</strong></section>
<form class="gf-manual-filter" method="get" action="<?= $esc($bp) ?>/admin/revisoes-manuais">
<label>Status <select name="status">
<?php foreach (['pendente'=>'Pendentes','confirmada'=>'Confirmadas','rejeitada'=>'Rejeitadas','todos'=>'Todos'] as $value=>$label): ?>
<option value="<?= $esc($value) ?>" <?= $status===$value?'selected':'' ?>><?= $esc($label) ?></option>
<?php endforeach; ?></select></label>
<label>Pedido ID <input type="number" min="1" name="pedido_id" value="<?= $pedidoId ? (int)$pedidoId : '' ?>" placeholder="ID do pedido"></label>
<button type="submit">Filtrar</button> <a href="<?= $esc($bp) ?>/admin/revisoes-manuais">Limpar</a>
</form>
<section class="gf-manual-table-wrap"><table class="gf-manual-table"><thead><tr><th>Pedido</th><th>Conclusão</th><th>Solicitante</th><th>Situação</th><th>Revisão</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($pedidos as $p): $situacao=(string)($p['revisao_manual_status']??''); ?>
<tr><td><strong>#<?= (int)$p['id'] ?></strong></td><td><?= $esc($p['concluido_manual_em']??'—') ?></td>
<td>Admin #<?= (int)($p['concluido_manual_admin_id']??0) ?></td>
<td><span class="gf-manual-state state-<?= $esc($situacao) ?>"><?= $esc($situacao ?: 'sem status') ?></span></td>
<td><?= $esc($p['revisao_manual_em']??'—') ?></td>
<td><a href="<?= $esc($bp) ?>/admin/pedido/<?= (int)$p['id'] ?>">Ver pedido e evidências</a></td></tr>
<?php endforeach; ?>
<?php if (!$pedidos): ?><tr><td colspan="6" class="gf-manual-empty">Nenhuma revisão encontrada para os filtros.</td></tr><?php endif; ?>
</tbody></table></section>
<?php if ($totalPaginas > 1): ?><nav class="gf-manual-pages" aria-label="Paginação">
<?php if ($pagina > 1): ?><a href="<?= $esc($url(['pagina'=>$pagina-1])) ?>">Anterior</a><?php endif; ?>
<span>Página <?= (int)$pagina ?> de <?= (int)$totalPaginas ?></span>
<?php if ($pagina < $totalPaginas): ?><a href="<?= $esc($url(['pagina'=>$pagina+1])) ?>">Próxima</a><?php endif; ?>
</nav><?php endif; ?>
<p class="gf-manual-help">Para confirmar ou rejeitar, abra o pedido e utilize a ação administrativa existente. A revisão exige autenticação e token CSRF.</p>
</main></div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
