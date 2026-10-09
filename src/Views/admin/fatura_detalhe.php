<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$fatura     = $fatura     ?? [];
$itens      = $itens      ?? [];
$pagamentos = $pagamentos ?? [];
$csrfToken  = $csrfToken  ?? '';

$money = static fn($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');

$status = (string)($fatura['status'] ?? 'aberta');
$badges = [
    'aberta'    => 'warning text-dark',
    'paga'      => 'success',
    'vencida'   => 'danger',
    'bloqueada' => 'dark',
    'cancelada' => 'secondary',
];
$badge = $badges[$status] ?? 'secondary';
$saldo = (float)($fatura['saldo'] ?? 0);
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-financeiro.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Financeiro</span>
            <h1>Fatura #<?= (int)($fatura['id'] ?? 0) ?></h1>
            <p>
                <?= htmlspecialchars((string)($fatura['parceiro_nome'] ?? 'â€”')) ?>
                (<?= htmlspecialchars((string)($fatura['parceiro_tipo'] ?? '')) ?> #<?= (int)($fatura['parceiro_id'] ?? 0) ?>)
                <span class="badge bg-<?= $badge ?> ms-2"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </p>
        </div>
        <a href="<?= $bp ?>/admin/faturas" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </header>
    <?php $flash = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); ?>
    <?php foreach ($flash as $fmsg): ?>
    <div class="alert alert-<?= ($fmsg['type'] ?? 'info') === 'error' ? 'danger' : htmlspecialchars((string)($fmsg['type'] ?? 'info')) ?> mb-3">
        <?= htmlspecialchars((string)($fmsg['message'] ?? '')) ?>
    </div>
    <?php endforeach; ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Ciclo</div><div class="stat-value small"><?= date('d/m/Y', strtotime((string)($fatura['ciclo_inicio'] ?? 'now'))) ?> a <?= date('d/m/Y', strtotime((string)($fatura['ciclo_fim'] ?? 'now'))) ?></div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Total faturado</div><div class="stat-value"><?= $money($fatura['total_faturado'] ?? 0) ?></div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Total repasse</div><div class="stat-value"><?= $money($fatura['total_repasse'] ?? 0) ?></div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-label">Saldo</div><div class="stat-value <?= $saldo > 0 ? 'text-success' : ($saldo < 0 ? 'text-danger' : '') ?>"><?= $money($saldo) ?></div></div></div>
    </div>
    <section class="fin-card p-4 mb-4">
        <h3 class="fin-subtitle mb-3"><i class="fas fa-list me-2"></i>Itens (<?= count($itens) ?>)</h3>
        <?php if (!empty($itens)): ?>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th>Pedido</th><th>Data</th><th>Cliente</th>
                <th class="text-end">Total</th><th class="text-end">Comissao</th>
                <th class="text-end">Liquido parceiro</th><th>Tipo</th></tr></thead>
            <tbody>
            <?php foreach ($itens as $i): ?>
            <tr>
                <td><a href="<?= $bp ?>/admin/pedido/<?= (int)($i['pedido_id'] ?? 0) ?>">#<?= (int)($i['pedido_id'] ?? 0) ?></a></td>
                <td><?= date('d/m/Y H:i', strtotime((string)($i['pedido_em'] ?? 'now'))) ?></td>
                <td><?= htmlspecialchars((string)($i['cliente_nome'] ?? 'â€”')) ?><div class="small text-muted"><?= htmlspecialchars((string)($i['tipo_problema'] ?? '')) ?></div></td>
                <td class="text-end"><?= $money($i['valor_total'] ?? 0) ?></td>
                <td class="text-end text-danger">-<?= $money($i['comissao_valor'] ?? 0) ?></td>
                <td class="text-end"><strong><?= $money($i['valor_liquido_parceiro'] ?? 0) ?></strong></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars((string)($i['comissao_tipo'] ?? '')) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php else: ?>
        <div class="text-muted small">Nenhum item nesta fatura.</div>
        <?php endif; ?>
    </section>
    <?php if (!empty($fatura['pix_copia_cola']) || !empty($fatura['pix_qrcode'])): ?>
    <section class="fin-card p-4 mb-4">
        <h3 class="fin-subtitle mb-3"><i class="fas fa-qrcode me-2"></i>PIX</h3>
        <?php if (!empty($fatura['pix_copia_cola'])): ?>
        <label class="form-label small">Copia e cola</label>
        <textarea class="form-control" rows="3" readonly><?= htmlspecialchars((string)$fatura['pix_copia_cola']) ?></textarea>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="fin-card p-4 mb-4">
        <h3 class="fin-subtitle mb-3"><i class="fas fa-history me-2"></i>Pagamentos (<?= count($pagamentos) ?>)</h3>
        <?php if (!empty($pagamentos)): ?>
        <table class="table table-sm">
            <thead><tr><th>Data</th><th>Transacao</th><th>Metodo</th><th class="text-end">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($pagamentos as $p): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime((string)($p['created_at'] ?? 'now'))) ?></td>
                <td><code><?= htmlspecialchars((string)($p['transacao_id'] ?? '')) ?></code></td>
                <td><?= htmlspecialchars((string)($p['metodo'] ?? '')) ?></td>
                <td class="text-end"><?= $money($p['valor'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-muted small">Nenhum pagamento registrado.</div>
        <?php endif; ?>
    </section>
    <section class="fin-card p-4">
        <h3 class="fin-subtitle mb-3"><i class="fas fa-cogs me-2"></i>Acoes</h3>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($status !== 'paga'): ?>
            <form method="POST" action="<?= $bp ?>/admin/fatura/<?= (int)($fatura['id'] ?? 0) ?>/marcar-paga" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="text" name="transacao_id" class="form-control form-control-sm" placeholder="transacao_id (opcional)" style="width:260px">
                <button class="btn btn-success btn-sm" onclick="return confirm('Confirmar baixa manual?')">Marcar como paga</button>
            </form>
            <?php endif; ?>
            <?php if ($status === 'bloqueada'): ?>
            <form method="POST" action="<?= $bp ?>/admin/fatura/<?= (int)($fatura['id'] ?? 0) ?>/desbloquear">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button class="btn btn-warning btn-sm" onclick="return confirm('Desbloquear manualmente? O cron vai re-bloquear se continuar vencida.')">Desbloquear</button>
            </form>
            <?php endif; ?>
        </div>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php';