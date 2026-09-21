<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$csrfToken = $csrfToken ?? AuthService::gerarCsrfToken();
include __DIR__ . '/../layouts/header.php';
?>
<style>
  .mobile-admin{padding:28px;max-width:1500px;margin:auto}.mobile-hero{padding:28px;border-radius:20px;background:linear-gradient(135deg,#111827,#164e63);color:#fff;margin-bottom:22px}.mobile-hero h1{margin:0 0 8px;font-size:clamp(1.6rem,3vw,2.4rem)}.mobile-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:18px}.mobile-card{border:1px solid #dbe3ea;border-radius:18px;background:#fff;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.07)}.mobile-card__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.mobile-card h2{font-size:1.1rem;margin:0}.mobile-muted{color:#64748b;font-size:.9rem}.mobile-badge{border-radius:999px;padding:5px 10px;font-size:.75rem;font-weight:700;background:#e2e8f0;color:#334155}.mobile-badge.ok{background:#dcfce7;color:#166534}.mobile-badge.pending{background:#fef3c7;color:#92400e}.mobile-rule{margin-top:18px;padding-top:16px;border-top:1px solid #e2e8f0}.mobile-rule h3{font-size:.98rem;margin:0 0 12px}.mobile-rule form{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.mobile-rule label{font-size:.78rem;color:#475569}.mobile-rule input{width:100%;border:1px solid #cbd5e1;border-radius:9px;padding:9px;margin-top:4px}.mobile-rule button{grid-column:1/-1;border:0;border-radius:10px;padding:10px;background:#0f766e;color:#fff;font-weight:700}.mobile-actions{display:flex;gap:8px;margin-top:16px}.mobile-actions form{display:inline}.mobile-actions button{border:1px solid #cbd5e1;background:#fff;border-radius:9px;padding:8px 12px}.mobile-actions .approve{background:#166534;color:#fff;border-color:#166534}
</style>
<main class="mobile-admin">
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
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
