<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$faturas   = $faturas   ?? [];
$resumo    = $resumo    ?? [];
$filtros   = $filtros   ?? [];
$csrfToken = $csrfToken ?? '';

$money = static fn($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');

$statusLabels = [
    'aberta'    => ['label' => 'Aberta',    'badge' => 'warning text-dark'],
    'paga'      => ['label' => 'Paga',      'badge' => 'success'],
    'vencida'   => ['label' => 'Vencida',   'badge' => 'danger'],
    'bloqueada' => ['label' => 'Bloqueada', 'badge' => 'dark'],
    'cancelada' => ['label' => 'Cancelada', 'badge' => 'secondary'],
];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-financeiro.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Financeiro</span>
            <h1><i class="fas fa-file-invoice-dollar me-2"></i>Faturas dos parceiros</h1>
            <p>Ciclos semanais, repasses, PIX e bloqueios por vencimento.</p>
        </div>
    </header>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Abertas</div><div class="stat-value"><?= (int)($resumo['aberta'] ?? 0) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Pagas</div><div class="stat-value text-success"><?= (int)($resumo['paga'] ?? 0) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Vencidas / Bloqueadas</div><div class="stat-value text-danger"><?= (int)($resumo['vencida'] ?? 0) + (int)($resumo['bloqueada'] ?? 0) ?></div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Saldo acumulado</div><div class="stat-value"><?= $money($resumo['saldo_total'] ?? 0) ?></div></div></div>
    </div>
    <section class="fin-card p-4 mb-4">
        <form method="GET" action="<?= $bp ?>/admin/faturas" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($statusLabels as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filtros['status'] ?? '') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-2"><label class="form-label small">Tipo</label>
                <select name="parceiro_tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="guincho"      <?= ($filtros['parceiro_tipo'] ?? '') === 'guincho'      ? 'selected' : '' ?>>Guincho</option>
                    <option value="oficina"      <?= ($filtros['parceiro_tipo'] ?? '') === 'oficina'      ? 'selected' : '' ?>>Oficina</option>
                    <option value="especialista" <?= ($filtros['parceiro_tipo'] ?? '') === 'especialista' ? 'selected' : '' ?>>Especialista</option>
                </select></div>
            <div class="col-md-2"><label class="form-label small">Ciclo de</label>
                <input type="date" name="ciclo_de" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtros['ciclo_de'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label small">Ciclo ate</label>
                <input type="date" name="ciclo_ate" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($filtros['ciclo_ate'] ?? '')) ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Filtrar</button></div>
            <div class="col-md-2"><a href="<?= $bp ?>/admin/faturas" class="btn btn-outline-secondary btn-sm w-100">Limpar</a></div>
        </form>
    </section>
    <section class="fin-card p-4">
        <?php if (!empty($faturas)): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>#</th><th>Parceiro</th><th>Ciclo</th>
                    <th class="text-end">Total faturado</th>
                    <th class="text-end">Total repasse</th>
                    <th class="text-end">Saldo</th>
                    <th>Status</th><th>Vencimento</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($faturas as $f): ?>
                <?php
                    $st = (string)($f['status'] ?? 'aberta');
                    $badge = $statusLabels[$st]['badge'] ?? 'secondary';
                    $label = $statusLabels[$st]['label'] ?? ucfirst($st);
                    $saldo = (float)($f['saldo'] ?? 0);
                    $saldoClass = $saldo > 0 ? 'text-success' : ($saldo < 0 ? 'text-danger' : 'text-muted');
                ?>
                <tr>
                    <td>#<?= (int)($f['id'] ?? 0) ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string)($f['parceiro_nome'] ?? 'â€”')) ?></strong>
                        <div class="small text-muted"><?= htmlspecialchars((string)($f['parceiro_tipo'] ?? '')) ?> #<?= (int)($f['parceiro_id'] ?? 0) ?></div>
                    </td>
                    <td>
                        <div class="small"><?= date('d/m/Y', strtotime((string)($f['ciclo_inicio'] ?? 'now'))) ?></div>
                        <div class="small text-muted">ate <?= date('d/m/Y', strtotime((string)($f['ciclo_fim'] ?? 'now'))) ?></div>
                    </td>
                    <td class="text-end"><?= $money($f['total_faturado'] ?? 0) ?></td>
                    <td class="text-end"><?= $money($f['total_repasse'] ?? 0) ?></td>
                    <td class="text-end <?= $saldoClass ?>"><strong><?= $money($saldo) ?></strong></td>
                    <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($label) ?></span></td>
                    <td><?= date('d/m/Y', strtotime((string)($f['vencimento_em'] ?? 'now'))) ?></td>
                    <td><a href="<?= $bp ?>/admin/fatura/<?= (int)($f['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Ver</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-file-invoice fa-2x mb-2 d-block"></i>
            Nenhuma fatura encontrada para os filtros selecionados.
        </div>
        <?php endif; ?>
    </section>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php';