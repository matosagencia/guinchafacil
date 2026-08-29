<?php
/**
 * Checklists incompletos — reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central, /admin/documentos, /admin/saques...). A
 * lista já vem inteira do servidor (sem paginação nem N+1), por isso o
 * workspace é preenchido via JS a partir de um JSON embutido
 * (window.__checklistsData), sem round-trip extra.
 *
 * @var array $execucoes
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totalIncompletos = count($execucoes ?? []);
$semDiagnostico = count(array_filter($execucoes ?? [], static fn($e) => !empty($e['requires_diagnostic']) && empty($e['has_diagnostic'])));
$semEvidencia = count(array_filter($execucoes ?? [], static fn($e) => (!empty($e['requires_before_evidence']) && empty($e['has_before_evidence'])) || (!empty($e['requires_after_evidence']) && empty($e['has_after_evidence']))));

$statusItem = static function (array $e, string $req, string $has): array {
    if (!empty($e[$req]) && empty($e[$has])) return ['falta', 'Falta'];
    if (!empty($e[$req])) return ['ok', 'OK'];
    return ['na', 'n/a'];
};

$checklistsPayload = [];
foreach (($execucoes ?? []) as $e) {
    [$diagStatus, $diagLabel] = $statusItem($e, 'requires_diagnostic', 'has_diagnostic');
    [$antesStatus, $antesLabel] = $statusItem($e, 'requires_before_evidence', 'has_before_evidence');
    [$depoisStatus, $depoisLabel] = $statusItem($e, 'requires_after_evidence', 'has_after_evidence');
    $checklistsPayload[] = [
        'pedido_id' => (int)$e['pedido_id'],
        'pedido_status' => (string)($e['pedido_status'] ?? ''),
        'cliente_nome' => (string)($e['cliente_nome'] ?? '—'),
        'prestador_nome' => (string)($e['prestador_nome'] ?? '—'),
        'phase_code' => (string)($e['phase_code'] ?? ''),
        'diagnostico' => ['status' => $diagStatus, 'label' => $diagLabel],
        'foto_antes' => ['status' => $antesStatus, 'label' => $antesLabel],
        'foto_depois' => ['status' => $depoisStatus, 'label' => $depoisLabel],
        'avaliado_em' => (string)($e['avaliado_em'] ?? ''),
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="checklistSearchTop" placeholder="Buscar por nº do pedido, cliente ou prestador" autocomplete="off" aria-label="Buscar checklists incompletos"></div>
    <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalIncompletos; ?> pendências</span></div>
</div>

<section class="ops-summary" aria-label="Resumo de checklists incompletos">
    <article class="ops-metric <?php echo $totalIncompletos > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Total pendente</span>
        <strong class="ops-metric__value"><?php echo $totalIncompletos; ?></strong>
    </article>
    <article class="ops-metric <?php echo $semDiagnostico > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Sem diagnóstico</span>
        <strong class="ops-metric__value"><?php echo $semDiagnostico; ?></strong>
    </article>
    <article class="ops-metric <?php echo $semEvidencia > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Sem foto antes/depois</span>
        <strong class="ops-metric__value"><?php echo $semEvidencia; ?></strong>
    </article>
</section>

<?php $flash = $flash ?? null; if (!empty($flash)): ?>
<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>" style="margin:0 24px 16px;">
    <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div class="shell-ops" id="checklistShell">

    <aside class="shell-ops-sidebar" id="checklistSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Pendências de prova de serviço">
        <header class="ops-worklist-header">
            <span class="eyebrow">Qualidade</span>
            <h2>Checklists incompletos</h2>
            <p><span id="checklistWorklistCount"><?php echo count($checklistsPayload); ?></span> pendência(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="checklistWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="checklistWorklistResults">
            <?php if (empty($checklistsPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum checklist incompleto. Tudo em dia.
                </div>
            <?php else: foreach ($checklistsPayload as $i => $e):
                $busca = strtolower($e['pedido_id'] . ' ' . $e['cliente_nome'] . ' ' . $e['prestador_nome']);
                $critico = $e['diagnostico']['status'] === 'falta' || $e['foto_antes']['status'] === 'falta' || $e['foto_depois']['status'] === 'falta';
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $critico ? 'is-critical' : ''; ?>"
                    data-checklist-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong>Pedido #<?php echo (int)$e['pedido_id']; ?></strong>
                            <span class="ops-badge ops-badge--audit"><?php echo htmlspecialchars($e['phase_code']); ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($e['cliente_nome']); ?> · <?php echo htmlspecialchars($e['prestador_nome']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars($e['pedido_status']); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($critico): ?>
                        <span class="ops-signal is-danger" title="Faltam itens do checklist"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="checklistWorkspace" aria-live="polite">
        <?php if (empty($checklistsPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhuma pendência pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__checklistsData = <?php echo json_encode($checklistsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var CHK_BP = '<?php echo addslashes($bp); ?>';

(function () {
    var checklists = window.__checklistsData || [];
    var shell = document.getElementById('checklistShell');
    var results = document.getElementById('checklistWorklistResults');
    var workspace = document.getElementById('checklistWorkspace');
    if (!shell || !results || !workspace) return;

    var statusBadge = { falta: '<span class="badge text-bg-danger">Falta</span>', ok: '<span class="badge text-bg-success">OK</span>', na: '<span class="text-muted small">n/a</span>' };

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function renderChecklist(checklistId) {
        var e = checklists[checklistId];
        if (!e) return;

        var critico = e.diagnostico.status === 'falta' || e.foto_antes.status === 'falta' || e.foto_depois.status === 'falta';

        var html = '<header class="ops-order-header">' +
            '<div><h1>Pedido #' + e.pedido_id + '</h1>' +
            '<p>' + escapeHtml(e.cliente_nome) + ' · ' + escapeHtml(e.prestador_nome) + '</p></div>' +
            '<span class="ops-badge ' + (critico ? 'ops-badge--critical' : 'ops-badge--service') + '">' + escapeHtml(e.phase_code) + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 12px"><a class="ops-btn" href="' + CHK_BP + '/admin/pedido/' + e.pedido_id + '"><i class="fas fa-eye"></i> Ver pedido #' + e.pedido_id + '</a></div>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card"><div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Proof-of-Service</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--40">Status do pedido</td><td>' + escapeHtml(e.pedido_status) + '</td></tr>';
        html += '<tr><td class="ghd-label">Diagnóstico</td><td>' + (statusBadge[e.diagnostico.status] || escapeHtml(e.diagnostico.label)) + '</td></tr>';
        html += '<tr><td class="ghd-label">Foto antes</td><td>' + (statusBadge[e.foto_antes.status] || escapeHtml(e.foto_antes.label)) + '</td></tr>';
        html += '<tr><td class="ghd-label">Foto depois</td><td>' + (statusBadge[e.foto_depois.status] || escapeHtml(e.foto_depois.label)) + '</td></tr>';
        html += '<tr><td class="ghd-label">Avaliado em</td><td>' + (e.avaliado_em ? escapeHtml(e.avaliado_em) : '—') + '</td></tr>';
        html += '</table></div></div>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectChecklist(checklistId) {
        results.querySelectorAll('[data-checklist-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.checklistId) === checklistId));
        });
        renderChecklist(checklistId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-checklist-id]');
        if (!item) return;
        selectChecklist(Number(item.dataset.checklistId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-checklist-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('checklistWorklistSearch');
    var topSearch = document.getElementById('checklistSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (checklists.length > 0) selectChecklist(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
