<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
$csrfToken = $csrfToken ?? AuthService::gerarCsrfToken();

?>
<div class="bo-toolbar-meta"><span class="bo-toolbar-status"><span class="bo-toolbar-status-dot"></span><?= count($especialistas ?? []) ?> especialistas</span><a href="<?= htmlspecialchars($bp) ?>/admin/especialistas/cadastrar" class="ops-dashboard-link">Cadastrar especialista</a></div>
</div>
<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success">Atualização do especialista realizada.</div><?php endif; ?>
<div class="bo-operation-grid">
  <section class="shell-ops-worklist">
    <header class="bo-panel-heading"><span class="eyebrow">Rede de atendimento</span><h2>Especialistas</h2><p>Homologação, disponibilidade e capacidades técnicas</p></header>
    <div class="bo-search"><input type="search" id="especialistasWorklistSearch" placeholder="Filtrar nesta lista"></div>
    <div class="bo-orders__list" id="especialistasResults">
    <?php if (empty($especialistas)): ?><div class="bo-empty">Nenhum especialista cadastrado.</div>
    <?php else: foreach ($especialistas as $e): $aprovado=(int)($e['aprovado']??0); $blob=strtolower(($e['nome']??'').' '.($e['especialidade']??'').' '.($e['endereco_base']??'')); ?>
      <article class="bo-order especialista-card" data-especialista-id="<?= (int)($e['especialista_id'] ?? 0) ?>" data-search-blob="<?= htmlspecialchars($blob,ENT_QUOTES,'UTF-8') ?>">
        <span class="bo-order__priority"></span><span class="bo-order__content"><span class="bo-order__top"><strong><?= htmlspecialchars($e['nome']??'—') ?></strong><span class="bo-status <?= $aprovado?'bo-status--service':'bo-status--new' ?>"><?= $aprovado?'Aprovado':'Pendente' ?></span></span><span class="bo-order__customer"><?= htmlspecialchars($e['especialidade']??'Capacidades não informadas') ?></span><span class="bo-order__customer small text-muted"><?= htmlspecialchars($e['endereco_base']??'Endereco nao informado') ?></span><span class="bo-order__footer"><span><?= (int)($e['disponivel']??0)?'Online':'Offline' ?></span><span>#<?= (int)$e['id'] ?></span></span></span>
        <span class="d-flex gap-1 align-items-center"><form method="post" action="<?= htmlspecialchars($bp) ?>/admin/especialista/<?= $aprovado?'suspender':'aprovar' ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)($e['especialista_id'] ?? 0) ?>"><button class="btn btn-sm <?= $aprovado?'btn-outline-warning':'btn-success' ?>" onclick="event.stopPropagation()" type="submit"><?= $aprovado?'Suspender':'Aprovar' ?></button></form></span>
      </article>
    <?php endforeach; endif; ?>
    </div>
  </section><aside id="especialistaDetail" class="shell-ops-detail" aria-live="polite"><div class="bo-empty">Selecione um especialista para ver os detalhes.</div></aside>
</div>
<script<?= csp_script_nonce_attr() ?> src="<?= htmlspecialchars($bp) ?>/public/assets/js/admin-especialistas.js"></script>
<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Especialistas'; $breadcrumb=['Pessoas e parceiros','Especialistas']; $assets_css=['/public/assets/css/pages/bo-admin-operacao.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
