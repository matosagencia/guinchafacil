<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <?php
    $totalRegistros = count($registros ?? []);
    $totalRemovidos = count(array_filter($registros ?? [], static fn($r) => ($r['acao'] ?? '') === 'removido'));
    ?>
    <div class="ops-topbar">
        <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="envAuditoriaBusca" placeholder="Buscar por chave ou admin" autocomplete="off" aria-label="Buscar auditoria do ambiente"></div>
        <div class="ops-topbar__meta">
            <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalRegistros; ?> alterações</span>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/env" class="ops-dashboard-link"><i class="fas fa-shield-halved me-1"></i>Editar .env</a>
        </div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-clock-rotate-left me-2"></i>Auditoria do Ambiente</h1>
            <p>§15.4 — Histórico de alterações nas variáveis de ambiente</p>
        </div>
    </header>

    <section class="ops-summary mb-4" aria-label="Resumo de auditoria do ambiente">
        <article class="ops-metric">
            <span class="ops-metric__label">Total de alterações</span>
            <strong class="ops-metric__value"><?php echo $totalRegistros; ?></strong>
        </article>
        <article class="ops-metric <?php echo $totalRemovidos > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Chaves removidas</span>
            <strong class="ops-metric__value"><?php echo $totalRemovidos; ?></strong>
        </article>
    </section>

    <div class="card">
        <div class="card-header"><i class="fas fa-list me-2"></i>Registro de Alterações</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Admin</th>
                        <th>Chave</th>
                        <th>Ação</th>
                        <th>Valor (mascarado)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($registros)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhuma alteração registrada ainda.</td></tr>
                <?php else: ?>
                <?php foreach ($registros as $r): ?>
                <tr data-search="<?php echo htmlspecialchars(strtolower($r['chave'] . ' ' . ($r['admin_nome'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                    <td class="font-monospace small"><?php echo htmlspecialchars($r['criado_em']); ?></td>
                    <td><?php echo htmlspecialchars($r['admin_nome'] ?? 'Admin #'.$r['admin_id']); ?></td>
                    <td><code><?php echo htmlspecialchars($r['chave']); ?></code></td>
                    <td>
                        <span class="badge bg-<?php echo match($r['acao']) {
                            'alterado' => 'warning text-dark',
                            'criado'   => 'success',
                            'removido' => 'danger',
                            default    => 'secondary'
                        }; ?>">
                            <?php echo htmlspecialchars($r['acao']); ?>
                        </span>
                    </td>
                    <td class="font-monospace text-muted small"><?php echo htmlspecialchars($r['valor_mascarado']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('envAuditoriaBusca');
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
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
