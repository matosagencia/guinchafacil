<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$csrfToken = $csrfToken ?? AuthService::gerarCsrfToken();
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($bp) ?>/public/assets/css/pages/admin-central-operacional.css">
<link rel="stylesheet" href="<?= htmlspecialchars($bp) ?>/public/assets/css/pages/admin-usuarios.css">
<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
  <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="especialistasSearch" placeholder="Buscar especialista" autocomplete="off"></div>
  <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?= count($especialistas ?? []) ?> especialistas</span><a href="<?= htmlspecialchars($bp) ?>/admin/especialistas/cadastrar" class="ops-dashboard-link"><i class="fas fa-user-plus me-1"></i>Cadastrar especialista</a></div>
</div>
<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success" style="margin:16px 24px 0">Atualização do especialista realizada.</div><?php endif; ?>
<div class="shell-ops" id="especialistasShell">
  <aside class="shell-ops-sidebar" id="especialistasSidebar"><?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?></aside>
  <section class="shell-ops-worklist">
    <header class="ops-worklist-header"><span class="eyebrow">Rede de atendimento</span><h2>Especialistas</h2><p>Homologação, disponibilidade e capacidades técnicas</p></header>
    <div class="ops-worklist-search"><i class="fas fa-magnifying-glass"></i><input type="search" id="especialistasWorklistSearch" placeholder="Filtrar nesta lista"></div>
    <div class="ops-worklist-results" id="especialistasResults">
    <?php if (empty($especialistas)): ?><div class="ops-empty-state"><i class="fas fa-user-gear"></i>Nenhum especialista cadastrado.</div>
    <?php else: foreach ($especialistas as $e): $aprovado=(int)($e['aprovado']??0); $blob=strtolower(($e['nome']??'').' '.($e['especialidade']??'')); ?>
      <article class="ops-worklist-item especialista-card" data-especialista-id="<?= (int)($e['especialista_id'] ?? 0) ?>" data-search-blob="<?= htmlspecialchars($blob,ENT_QUOTES,'UTF-8') ?>" style="cursor:pointer">
        <span class="ops-worklist-item__priority"></span><span class="ops-worklist-item__content"><span class="ops-worklist-item__top"><strong><?= htmlspecialchars($e['nome']??'—') ?></strong><span class="ops-badge <?= $aprovado?'ops-badge--service':'ops-badge--new' ?>"><?= $aprovado?'Aprovado':'Pendente' ?></span></span><span class="ops-worklist-item__customer"><?= htmlspecialchars($e['especialidade']??'Capacidades não informadas') ?></span><span class="ops-worklist-item__footer"><span><?= (int)($e['disponivel']??0)?'Online':'Offline' ?></span><span>#<?= (int)$e['id'] ?></span></span></span>
        <span class="d-flex gap-1 align-items-center"><form method="post" action="<?= htmlspecialchars($bp) ?>/admin/especialista/<?= $aprovado?'suspender':'aprovar' ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)($e['especialista_id'] ?? 0) ?>"><button class="btn btn-sm <?= $aprovado?'btn-outline-warning':'btn-success' ?>" onclick="event.stopPropagation()" type="submit"><?= $aprovado?'Suspender':'Aprovar' ?></button></form></span>
      </article>
    <?php endforeach; endif; ?>
    </div>
  </section><aside id="especialistaDetail" class="shell-ops-detail" aria-live="polite"><div class="ops-empty-state"><i class="fas fa-id-card"></i>Selecione um especialista para ver os detalhes.</div></aside>
</div>
<script<?= csp_script_nonce_attr() ?> src="<?= htmlspecialchars($bp) ?>/public/assets/js/admin-especialistas.js"></script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
