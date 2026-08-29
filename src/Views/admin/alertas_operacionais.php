<?php
/**
 * Alertas Operacionais — reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central, /admin/pedidos, /admin/ocorrencias...).
 * Os grupos de alerta já vêm inteiros do servidor (AdminAlertService), sem
 * paginação nem N+1 — por isso o workspace é preenchido via JS a partir de
 * um JSON embutido (window.__alertGroupsData), sem round-trip extra.
 *
 * @var array $alertas
 * @var array $alertasAgrupados
 * @var array $contagemPorNivel
 * @var string $nivelFiltro
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$nivelLabels = ['erro' => 'Critico', 'aviso' => 'Atencao', 'info' => 'Informativo'];
$nivelBadgeCss = ['erro' => 'critical', 'aviso' => 'new', 'info' => 'audit'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="alertasSearchTop" placeholder="Buscar por pedido, evento ou detalhe" autocomplete="off" aria-label="Buscar alertas"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo count($alertas); ?> ocorrências<?php echo $nivelFiltro !== '' ? ' · filtradas' : ''; ?></span>
        <?php if ($nivelFiltro !== ''): ?><a href="<?php echo htmlspecialchars($bp); ?>/admin/alertas" class="ops-dashboard-link"><i class="fas fa-xmark me-1"></i>Limpar filtro</a><?php endif; ?>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/central" class="ops-dashboard-link"><i class="fas fa-tower-broadcast me-1"></i>Central Operacional</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de alertas por nível">
    <?php foreach (['erro' => ['circle-exclamation', 'is-danger', 'Críticos'], 'aviso' => ['triangle-exclamation', 'is-warning', 'Atenção'], 'info' => ['circle-info', '', 'Informativos']] as $nivel => $meta): ?>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/alertas?nivel=<?php echo $nivel; ?>" style="text-decoration:none;">
        <article class="ops-metric <?php echo $nivelFiltro === $nivel ? $meta[1] : (($contagemPorNivel[$nivel] ?? 0) > 0 ? $meta[1] : ''); ?>">
            <span class="ops-metric__label"><i class="fas fa-<?php echo $meta[0]; ?> me-1"></i><?php echo $meta[2]; ?><?php echo $nivelFiltro === $nivel ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($contagemPorNivel[$nivel] ?? 0); ?></strong>
        </article>
    </a>
    <?php endforeach; ?>
</section>

<div class="shell-ops" id="alertasShell">

    <aside class="shell-ops-sidebar" id="alertasSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Grupos de alerta">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Alertas Operacionais</h2>
            <p><span id="alertasWorklistCount"><?php echo count($alertasAgrupados); ?></span> grupo(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="alertasWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="alertasWorklistResults">
            <?php if (empty($alertasAgrupados)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum alerta no momento. Operação normal.
                </div>
            <?php else: foreach ($alertasAgrupados as $i => $grupo):
                $grupoTexto = strtolower($grupo['titulo'] . ' ' . implode(' ', array_map(static fn($item) => ($item['label'] ?? '') . ' ' . ($item['info'] ?? ''), $grupo['itens'])));
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $grupo['nivel'] === 'erro' ? 'is-critical' : ($grupo['nivel'] === 'aviso' ? 'is-warning' : ''); ?>"
                    data-group-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($grupoTexto, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($grupo['titulo']); ?></strong>
                            <span class="ops-badge ops-badge--<?php echo $nivelBadgeCss[$grupo['nivel']] ?? 'audit'; ?>"><?php echo htmlspecialchars($nivelLabels[$grupo['nivel']] ?? ucfirst((string)$grupo['nivel'])); ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo count($grupo['itens']); ?> ocorrência(s)</span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars($grupo['quando']); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($grupo['nivel'] === 'erro'): ?>
                        <span class="ops-signal is-danger" title="Crítico"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="alertasWorkspace" aria-live="polite">
        <?php if (empty($alertasAgrupados)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum grupo pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__alertGroupsData = <?php echo json_encode(array_values($alertasAgrupados), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var ALERT_NIVEL_LABELS = <?php echo json_encode($nivelLabels, JSON_UNESCAPED_UNICODE); ?>;
var ALERT_NIVEL_CSS = <?php echo json_encode($nivelBadgeCss, JSON_UNESCAPED_UNICODE); ?>;
var ALERT_BP = '<?php echo addslashes($bp); ?>';

(function () {
    var groups = window.__alertGroupsData || [];
    var shell = document.getElementById('alertasShell');
    var results = document.getElementById('alertasWorklistResults');
    var workspace = document.getElementById('alertasWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function renderGroup(groupId) {
        var grupo = groups[groupId];
        if (!grupo) return;

        var badgeCss = ALERT_NIVEL_CSS[grupo.nivel] || 'audit';
        var badgeLabel = ALERT_NIVEL_LABELS[grupo.nivel] || grupo.nivel;

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(grupo.titulo) + '</h1>' +
            '<p>' + grupo.itens.length + ' ocorrência(s) · ' + escapeHtml(grupo.quando) + '</p></div>' +
            '<span class="ops-badge ops-badge--' + badgeCss + '">' + escapeHtml(badgeLabel) + '</span>' +
            '</header>';

        if (grupo.pedido_id) {
            html += '<div style="padding:0 24px 12px"><a class="ops-btn" href="' + ALERT_BP + '/admin/pedido/' + grupo.pedido_id + '"><i class="fas fa-eye"></i> Ver pedido #' + grupo.pedido_id + '</a></div>';
        }

        html += '<div style="padding:0 24px 32px">';
        if (!grupo.itens.length) {
            html += '<div class="ops-empty-state"><i class="fas fa-circle-check"></i>Sem ocorrências detalhadas.</div>';
        } else {
            html += '<ul class="ops-timeline">' + grupo.itens.map(function (item) {
                return '<li><time>' + escapeHtml(item.quando || '—') + '</time><strong>' + escapeHtml(item.label || 'Evento') + '</strong> ' + escapeHtml(item.info || '') + '</li>';
            }).join('') + '</ul>';
        }
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectGroup(groupId) {
        results.querySelectorAll('[data-group-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.groupId) === groupId));
        });
        renderGroup(groupId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-group-id]');
        if (!item) return;
        selectGroup(Number(item.dataset.groupId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-group-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('alertasWorklistSearch');
    var topSearch = document.getElementById('alertasSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (groups.length > 0) selectGroup(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Pedidos, Despacho, Ocorrências, Carteiras e Guinchos.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
