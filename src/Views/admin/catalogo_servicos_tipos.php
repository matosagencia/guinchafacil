<?php
/**
 * Tipos de Serviço — reestruturada pro padrão shell-ops (mesma arquitetura
 * de /admin/central, /admin/documentos, /admin/saques...). O catálogo já
 * vem inteiro do servidor (sem paginação nem N+1), por isso o workspace é
 * preenchido via JS a partir de um JSON embutido (window.__tiposData), sem
 * round-trip extra.
 *
 * @var array $categorias
 * @var array $tipos
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$modoLabels = ['TOWING' => 'Reboque', 'ON_SITE' => 'No local', 'HYBRID' => 'Híbrido'];

$totalTipos = count($tipos ?? []);
$tiposAtivos = count(array_filter($tipos ?? [], static fn($t) => !empty($t['active'])));
$tiposHibridos = count(array_filter($tipos ?? [], static fn($t) => ($t['attendance_mode'] ?? '') !== 'TOWING'));

$tiposPayload = [];
foreach (($tipos ?? []) as $t) {
    $tiposPayload[] = [
        'id' => (int)$t['id'],
        'code' => (string)$t['code'],
        'name' => (string)$t['name'],
        'category_name' => (string)($t['category_name'] ?? ''),
        'attendance_mode' => (string)$t['attendance_mode'],
        'attendance_mode_label' => $modoLabels[$t['attendance_mode']] ?? $t['attendance_mode'],
        'requires_destination' => !empty($t['requires_destination']),
        'allows_conversion_to_towing' => !empty($t['allows_conversion_to_towing']),
        'requires_diagnostic' => !empty($t['requires_diagnostic']),
        'active' => !empty($t['active']),
        'is_system' => !empty($t['is_system']),
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="tiposSearchTop" placeholder="Buscar por código, nome ou categoria" autocomplete="off" aria-label="Buscar tipos de serviço"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalTipos; ?> tipos cadastrados</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tipo/novo" class="ops-dashboard-link"><i class="fas fa-circle-plus me-1"></i>Novo tipo de serviço</a>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/capacidades" class="ops-dashboard-link"><i class="fas fa-user-check me-1"></i>Aprovar serviços</a>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tarifas" class="ops-dashboard-link"><i class="fas fa-tags me-1"></i>Tarifas</a>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/compatibilidade" class="ops-dashboard-link"><i class="fas fa-truck me-1"></i>Compatibilidade</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de tipos de serviço">
    <article class="ops-metric">
        <span class="ops-metric__label">Total</span>
        <strong class="ops-metric__value"><?php echo $totalTipos; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Ativos</span>
        <strong class="ops-metric__value"><?php echo $tiposAtivos; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">No local / híbridos</span>
        <strong class="ops-metric__value"><?php echo $tiposHibridos; ?></strong>
    </article>
</section>

<?php $flash = $flash ?? null; if (!empty($flash)): ?>
<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>" style="margin:0 24px 16px;">
    <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div style="padding:0 24px 16px;">
    <div class="card">
        <div class="card-header fw-bold"><i class="fas fa-layer-group me-2"></i>Categorias</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Código</th><th>Nome</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach (($categorias ?? []) as $c): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                        <td><span class="badge <?php echo $c['active'] ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?php echo $c['active'] ? 'Ativa' : 'Inativa'; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="shell-ops" id="tiposShell">

    <aside class="shell-ops-sidebar" id="tiposSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Tipos de serviço">
        <header class="ops-worklist-header">
            <span class="eyebrow">Catálogo estruturado</span>
            <h2>Tipos de serviço</h2>
            <p><span id="tiposWorklistCount"><?php echo count($tiposPayload); ?></span> tipo(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="tiposWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="tiposWorklistResults">
            <?php if (empty($tiposPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-toolbox"></i>
                    Nenhum tipo de serviço cadastrado ainda.
                </div>
            <?php else: foreach ($tiposPayload as $i => $t):
                $busca = strtolower($t['code'] . ' ' . $t['name'] . ' ' . $t['category_name']);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo !$t['active'] ? 'is-warning' : ''; ?>"
                    data-tipo-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                            <span class="ops-badge <?php echo $t['active'] ? 'ops-badge--service' : 'ops-badge--audit'; ?>"><?php echo $t['active'] ? 'Ativo' : 'Inativo'; ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><code><?php echo htmlspecialchars($t['code']); ?></code> · <?php echo htmlspecialchars($t['category_name']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars($t['attendance_mode_label']); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($t['is_system']): ?>
                        <span class="ops-signal" title="Serviço estrutural do sistema"><i class="fas fa-lock"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="tiposWorkspace" aria-live="polite">
        <?php if (empty($tiposPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum tipo pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__tiposData = <?php echo json_encode($tiposPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var TIPOS_BP = '<?php echo addslashes($bp); ?>';

(function () {
    var tipos = window.__tiposData || [];
    var shell = document.getElementById('tiposShell');
    var results = document.getElementById('tiposWorklistResults');
    var workspace = document.getElementById('tiposWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function checkIcon(val) {
        return val ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-minus text-muted"></i>';
    }

    function renderTipo(tipoId) {
        var t = tipos[tipoId];
        if (!t) return;

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(t.name) + '</h1>' +
            '<p><code>' + escapeHtml(t.code) + '</code> · ' + escapeHtml(t.category_name) + '</p></div>' +
            '<span class="ops-badge ' + (t.active ? 'ops-badge--service' : 'ops-badge--audit') + '">' + (t.active ? 'Ativo' : 'Inativo') + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 12px" class="d-flex gap-2 flex-wrap">' +
            '<a class="ops-btn" href="' + TIPOS_BP + '/admin/catalogo-servicos/tipo/novo?id=' + t.id + '"><i class="fas fa-pen"></i> Editar</a>' +
            '<a class="ops-btn" href="' + TIPOS_BP + '/admin/catalogo-servicos/compatibilidade?service_type_id=' + t.id + '"><i class="fas fa-truck"></i> Compatibilidade veicular</a>' +
            '</div>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card"><div class="card-header"><i class="fas fa-circle-info me-2"></i>Regras do tipo</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--40">Modo de atendimento</td><td>' + escapeHtml(t.attendance_mode_label) + '</td></tr>';
        html += '<tr><td class="ghd-label">Requer destino</td><td>' + checkIcon(t.requires_destination) + '</td></tr>';
        html += '<tr><td class="ghd-label">Permite conversão p/ reboque</td><td>' + checkIcon(t.allows_conversion_to_towing) + '</td></tr>';
        html += '<tr><td class="ghd-label">Requer diagnóstico</td><td>' + checkIcon(t.requires_diagnostic) + '</td></tr>';
        html += '<tr><td class="ghd-label">Serviço de sistema</td><td>' + (t.is_system ? '<span class="badge text-bg-dark"><i class="fas fa-lock me-1"></i>Sim, não pode ser removido</span>' : 'Não') + '</td></tr>';
        html += '</table></div></div>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectTipo(tipoId) {
        results.querySelectorAll('[data-tipo-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.tipoId) === tipoId));
        });
        renderTipo(tipoId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-tipo-id]');
        if (!item) return;
        selectTipo(Number(item.dataset.tipoId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-tipo-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('tiposWorklistSearch');
    var topSearch = document.getElementById('tiposSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (tipos.length > 0) selectTipo(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
