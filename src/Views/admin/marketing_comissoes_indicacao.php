<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$row = $rows[0] ?? null;
ob_start();
?>
<div class="container-fluid py-4">
    <div class="mb-4"><span class="eyebrow">Regra financeira</span><h1>Comissões do Programa de Indicação</h1><p class="text-muted">Existe uma única configuração para o perfil parceiro.</p></div>
    <?php if (!empty($_GET['ok'])): ?><div class="alert alert-success">Configuração atualizada.</div><?php endif; ?>
    <?php if (!empty($_GET['erro'])): ?><div class="alert alert-warning">Não foi possível salvar a configuração.</div><?php endif; ?>
    <div class="bo-operation-grid" id="comissoes-admin-grid">
        <div class="col-12 col-md-5 shell-ops-worklist"><section class="card shadow-sm h-100"><div class="card-header"><strong><?= $row ? 'Editar configuração' : 'Nova configuração' ?></strong></div><div class="card-body">
            <form method="post" action="<?= $e($bp) ?>/admin/marketing/comissoes-indicacao/salvar">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="partner_profile" value="distribuicao">
                <label class="form-label" for="comissao-perfil">Perfil</label><input id="comissao-perfil" class="form-control mb-3" value="Parceiro / Programa de indicação" readonly>
                <label class="form-label" for="comissao-percentual">Participação sobre a comissão GF (%)</label><input id="comissao-percentual" class="form-control mb-3" name="referral_share_percent" type="number" min="0" max="100" step="0.01" value="<?= $e($row['commission_percent'] ?? 0) ?>" required>
                <label class="form-label" for="comissao-desconto">Desconto do cliente (%)</label><input id="comissao-desconto" class="form-control mb-3" name="discount_percent" type="number" min="0" max="100" step="0.01" value="<?= $e($row['discount_percent'] ?? 0) ?>" required>
                <button class="btn btn-primary" type="submit"><?= $row ? 'Atualizar configuração' : 'Ativar configuração' ?></button>
            </form>
        </div></section></div>
        <div class="col-12 col-md-7"><section class="card shadow-sm"><div class="card-header"><strong>Configuração vigente</strong></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Perfil</th><th>Participação</th><th>Desconto</th><th>Status</th><th>Vigência</th><th class="text-end">Ações</th></tr></thead><tbody>
        <?php if ($row): ?><tr><td>Parceiro</td><td><?= $e($row['commission_percent']) ?>%</td><td><?= $e($row['discount_percent']) ?>%</td><td><?= $e($row['status']) ?></td><td><?= $e($row['effective_from']) ?></td><td><div class="d-flex justify-content-end flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $row['id'] ?>">Editar</a><form method="post" action="<?= $e($bp) ?>/admin/marketing/comissoes-indicacao/status"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="btn btn-sm <?= $row['status'] === 'ativo' ? 'btn-outline-warning' : 'btn-outline-success' ?>" type="submit"><?= $row['status'] === 'ativo' ? 'Pausar' : 'Ativar' ?></button></form></div></td></tr>
        <?php else: ?><tr><td colspan="6" class="text-muted">Nenhuma configuração cadastrada.</td></tr><?php endif; ?></tbody></table></div></section></div>
    </div>
</div>
<?php $conteudo_pagina = ob_get_clean(); $perfil = 'admin'; $titulo_pagina = 'Comissões de indicação'; $breadcrumb = ['Marketing', 'Comissões']; $assets_css = ['/public/assets/css/pages/bo-admin-financeiro.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
