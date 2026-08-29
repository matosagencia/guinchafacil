<?php
/**
 * Central de Comunicados — reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central, /admin/documentos, /admin/saques...). A
 * lista já vem carregada do servidor (paginada em 20), por isso o
 * workspace é preenchido via JS a partir de um JSON embutido
 * (window.__comunicadosData), sem round-trip extra.
 *
 * @var array $items
 * @var array $stats
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../../layouts/header.php';

$statusLabels = ['publicado' => 'Publicado', 'rascunho' => 'Rascunho', 'pausado' => 'Pausado', 'arquivado' => 'Arquivado'];
$statusCss = ['publicado' => 'ops-badge--service', 'rascunho' => 'ops-badge--audit', 'pausado' => 'ops-badge--new', 'arquivado' => 'ops-badge--critical'];

$comunicadosPayload = [];
foreach (($items ?? []) as $item) {
    $comunicadosPayload[] = [
        'id' => (int)$item['id'],
        'titulo' => (string)$item['titulo'],
        'subtitulo' => (string)($item['subtitulo'] ?? ''),
        'publico' => (string)$item['publico'],
        'placement' => (string)$item['placement'],
        'status' => (string)$item['status'],
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-comunicados.css?v=20260812-1">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="comunicadosSearchTop" placeholder="Buscar por título" autocomplete="off" aria-label="Buscar comunicados"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo count($comunicadosPayload); ?> comunicado(s)</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/comunicado/novo" class="ops-dashboard-link"><i class="fas fa-circle-plus me-1"></i>Novo comunicado</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de comunicados">
    <article class="ops-metric">
        <span class="ops-metric__label">Publicados</span>
        <strong class="ops-metric__value"><?php echo (int)($stats['publicados'] ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Rascunhos</span>
        <strong class="ops-metric__value"><?php echo (int)($stats['rascunhos'] ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Pausados</span>
        <strong class="ops-metric__value"><?php echo (int)($stats['pausados'] ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Arquivados</span>
        <strong class="ops-metric__value"><?php echo (int)($stats['arquivados'] ?? 0); ?></strong>
    </article>
</section>

<div class="shell-ops" id="comunicadosShell">

    <aside class="shell-ops-sidebar" id="comunicadosSidebar">
        <?php include __DIR__ . '/../../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Comunicados cadastrados">
        <header class="ops-worklist-header">
            <span class="eyebrow">Gestão</span>
            <h2>Central de Comunicados</h2>
            <p><span id="comunicadosWorklistCount"><?php echo count($comunicadosPayload); ?></span> nesta página</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="comunicadosWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="comunicadosWorklistResults">
            <?php if (empty($comunicadosPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-bullhorn"></i>
                    Nenhum comunicado cadastrado.
                </div>
            <?php else: foreach ($comunicadosPayload as $i => $c):
                $busca = strtolower($c['titulo'] . ' ' . $c['publico'] . ' ' . $c['placement']);
            ?>
                <button type="button"
                    class="ops-worklist-item"
                    data-comunicado-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($c['titulo']); ?></strong>
                            <span class="ops-badge <?php echo $statusCss[$c['status']] ?? 'ops-badge--audit'; ?>"><?php echo htmlspecialchars($statusLabels[$c['status']] ?? ucfirst($c['status'])); ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($c['publico']); ?> · <?php echo htmlspecialchars($c['placement']); ?></span>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="comunicadosWorkspace" aria-live="polite">
        <?php if (empty($comunicadosPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum comunicado pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__comunicadosData = <?php echo json_encode($comunicadosPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var COM_STATUS_LABELS = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE); ?>;
var COM_STATUS_CSS = <?php echo json_encode($statusCss, JSON_UNESCAPED_UNICODE); ?>;
var COM_BP = '<?php echo addslashes($bp); ?>';
var COM_CSRF = <?php echo json_encode($csrfToken ?? ''); ?>;

(function () {
    var items = window.__comunicadosData || [];
    var shell = document.getElementById('comunicadosShell');
    var results = document.getElementById('comunicadosWorklistResults');
    var workspace = document.getElementById('comunicadosWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function actionForm(action, label, btnClass) {
        return '<form method="post" action="' + COM_BP + '/admin/comunicado/' + action + '" class="d-inline">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(COM_CSRF) + '">' +
            '<button type="submit" class="btn ' + btnClass + '">' + label + '</button></form>';
    }

    function renderComunicado(comunicadoId) {
        var c = items[comunicadoId];
        if (!c) return;

        var badgeCss = COM_STATUS_CSS[c.status] || 'ops-badge--audit';
        var badgeLabel = COM_STATUS_LABELS[c.status] || c.status;

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(c.titulo) + '</h1>' +
            '<p>' + (c.subtitulo ? escapeHtml(c.subtitulo) : escapeHtml(c.publico) + ' · ' + escapeHtml(c.placement)) + '</p></div>' +
            '<span class="ops-badge ' + badgeCss + '">' + escapeHtml(badgeLabel) + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 12px" class="d-flex gap-2 flex-wrap">';
        html += '<a class="ops-btn" href="' + COM_BP + '/admin/comunicado/' + c.id + '"><i class="fas fa-pen"></i> Editar</a>';
        html += '<a class="ops-btn" href="' + COM_BP + '/admin/comunicado/preview/' + c.id + '"><i class="fas fa-eye"></i> Preview</a>';
        html += '</div>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-bullhorn me-2"></i>Detalhes</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--35">Público</td><td>' + escapeHtml(c.publico) + '</td></tr>';
        html += '<tr><td class="ghd-label">Placement</td><td>' + escapeHtml(c.placement) + '</td></tr>';
        html += '</table></div></div>';

        html += '<div class="d-flex gap-2 flex-wrap">';
        if (c.status !== 'publicado') {
            html += actionForm('publicar/' + c.id, 'Publicar', 'btn-success');
        }
        if (c.status === 'publicado') {
            html += actionForm('pausar/' + c.id, 'Pausar', 'btn-warning');
        }
        html += actionForm('arquivar/' + c.id, 'Arquivar', 'btn-outline-danger');
        html += '</div>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectComunicado(comunicadoId) {
        results.querySelectorAll('[data-comunicado-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.comunicadoId) === comunicadoId));
        });
        renderComunicado(comunicadoId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-comunicado-id]');
        if (!item) return;
        selectComunicado(Number(item.dataset.comunicadoId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-comunicado-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('comunicadosWorklistSearch');
    var topSearch = document.getElementById('comunicadosSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (items.length > 0) selectComunicado(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
