<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
$csrfToken = $csrfToken ?? AuthService::gerarCsrfToken();

?>

<div class="bo-page">
  <section class="mobile-hero">
    <div class="mobile-muted" style="color:#a7f3d0">REDE DE ATENDIMENTO</div>
    <h1>Prestadores móveis</h1>
    <p style="margin:0;color:#d1fae5">Aprove, suspenda e configure as faixas de orçamento para oficinas e profissionais que atendem no local.</p>
  </section>
  <?php if (!empty($_GET['msg'])): ?><div class="alert alert-success">Regra de preço atualizada com sucesso.</div><?php endif; ?>
  <?php if (!empty($_GET['erro'])): ?><div class="alert alert-danger"><?= htmlspecialchars((string)$_GET['erro']) ?></div><?php endif; ?>
  <div class="mobile-grid">
    <?php foreach (($prestadoresMoveis ?? []) as $p):
      $nome = $p['trade_name'] ?: ($p['legal_name'] ?: ($p['owner_name'] ?: 'Prestador sem nome'));
      $aprovado = ($p['approval_status'] ?? '') === 'APPROVED';
      $ativo = (int)($p['active'] ?? 0) === 1;
    ?>
      <article class="mobile-card">
        <div class="mobile-card__head">
          <div><h2><?= htmlspecialchars($nome) ?></h2><div class="mobile-muted"><?= htmlspecialchars((string)($p['owner_email'] ?? '')) ?></div></div>
          <span class="mobile-badge <?= $aprovado && $ativo ? 'ok' : ($aprovado ? '' : 'pending') ?>"><?= $aprovado ? ($ativo ? 'Ativo' : 'Suspenso') : 'Pendente' ?></span>
        </div>
        <p class="mobile-muted" style="margin:14px 0 0">Tipo: <?= htmlspecialchars((string)($p['provider_type'] ?? '')) ?> · ID #<?= (int)$p['provider_id'] ?></p>
        <p class="mobile-muted">Atende no local: <?= (int)($p['faz_resgate_direto'] ?? 0) ? 'Sim' : 'Não' ?> · Recebe no pátio: <?= (int)($p['recebe_veiculo_patio'] ?? 0) ? 'Sim' : 'Não' ?></p>
        <div class="mobile-rule">
          <h3>Regra de orçamento prévio</h3>
          <form method="post" action="<?= htmlspecialchars($bp) ?>/admin/prestador-movel/regra-orcamento">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="provider_id" value="<?= (int)$p['provider_id'] ?>"><input type="hidden" name="service_code" value="DEFAULT">
            <label>Mínimo (R$)<input type="number" name="estimativa_minima" min="0" step="0.01" value="<?= htmlspecialchars((string)($p['estimativa_minima'] ?? '0.00')) ?>" required></label>
            <label>Teto (R$)<input type="number" name="estimativa_maxima" min="0" step="0.01" value="<?= htmlspecialchars((string)($p['estimativa_maxima'] ?? '5000.00')) ?>" required></label>
            <label>Diagnóstico (R$)<input type="number" name="taxa_diagnostico_local" min="0" step="0.01" value="<?= htmlspecialchars((string)($p['taxa_diagnostico_local'] ?? '0.00')) ?>" required></label>
            <button type="submit">Salvar regra de preço</button>
          </form>
        </div>
        <div class="mobile-actions">
          <?php if (!$aprovado): ?><form method="post" action="<?= htmlspecialchars($bp) ?>/admin/oficina/aprovar"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="provider_id" value="<?= (int)$p['provider_id'] ?>"><button class="approve" type="submit">Aprovar</button></form>
          <?php elseif ($ativo): ?><form method="post" action="<?= htmlspecialchars($bp) ?>/admin/oficina/suspender"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="provider_id" value="<?= (int)$p['provider_id'] ?>"><input type="hidden" name="motivo" value="Revisão administrativa"><button type="submit">Suspender</button></form>
          <?php else: ?><form method="post" action="<?= htmlspecialchars($bp) ?>/admin/oficina/reativar"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="provider_id" value="<?= (int)$p['provider_id'] ?>"><button class="approve" type="submit">Reativar</button></form><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (empty($prestadoresMoveis)): ?><div class="mobile-card" style="margin-top:18px;text-align:center">Nenhum prestador móvel ou oficina cadastrada.</div><?php endif; ?>
</div>

<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Prestadores móveis'; $breadcrumb=['Pessoas e parceiros','Prestadores móveis']; $assets_css=['/public/assets/css/pages/bo-admin-cadastros.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
