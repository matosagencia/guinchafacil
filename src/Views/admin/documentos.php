<?php
/**
 * Documentos — reestruturada pro padrão shell-ops (mesma arquitetura de
 * /admin/central, /admin/alertas...). Todos os guincheiros aprovados já
 * vêm inteiros do servidor (sem paginação nem N+1) — por isso o workspace
 * é preenchido via JS a partir de um JSON embutido (window.__documentosData),
 * sem round-trip extra, igual ao padrão usado em Alertas Operacionais.
 *
 * @var array $documentos
 * @var string $statusFiltro
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$labels = ['vencida' => 'Vencida', 'vencendo' => 'Vence em até 30 dias', 'ok' => 'Válida', 'ausente' => 'Sem validade'];
$classes = ['vencida' => 'ops-badge--critical', 'vencendo' => 'ops-badge--new', 'ok' => 'ops-badge--service', 'ausente' => 'ops-badge--audit'];
include __DIR__ . '/../layouts/header.php';

$totalDocs = count($documentos ?? []);
$docsVencidos = count(array_filter($documentos ?? [], static fn($d) => ($d['cnh_status'] ?? '') === 'vencida'));
$docsVencendo = count(array_filter($documentos ?? [], static fn($d) => ($d['cnh_status'] ?? '') === 'vencendo'));
$docsAusentes = count(array_filter($documentos ?? [], static fn($d) => ($d['cnh_status'] ?? '') === 'ausente'));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="documentosSearchTop" placeholder="Buscar por nome ou e-mail do guincheiro" autocomplete="off" aria-label="Buscar documentos"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalDocs; ?> guincheiros<?php echo ($statusFiltro ?? '') !== '' ? ' · filtrado' : ''; ?></span>
        <?php if (($statusFiltro ?? '') !== ''): ?><a href="<?php echo htmlspecialchars($bp); ?>/admin/documentos" class="ops-dashboard-link"><i class="fas fa-xmark me-1"></i>Limpar filtro</a><?php endif; ?>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos" class="ops-dashboard-link"><i class="fas fa-truck me-1"></i>Guinchos</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de documentos">
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/documentos?status=vencida" style="text-decoration:none;">
        <article class="ops-metric <?php echo $docsVencidos > 0 ? 'is-danger' : ''; ?>">
            <span class="ops-metric__label">Vencidas<?php echo ($statusFiltro ?? '') === 'vencida' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo $docsVencidos; ?></strong>
        </article>
    </a>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/documentos?status=vencendo" style="text-decoration:none;">
        <article class="ops-metric <?php echo $docsVencendo > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Vencendo em 30 dias<?php echo ($statusFiltro ?? '') === 'vencendo' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo $docsVencendo; ?></strong>
        </article>
    </a>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/documentos?status=ausente" style="text-decoration:none;">
        <article class="ops-metric <?php echo $docsAusentes > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Sem validade cadastrada<?php echo ($statusFiltro ?? '') === 'ausente' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo $docsAusentes; ?></strong>
        </article>
    </a>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/documentos?status=ok" style="text-decoration:none;">
        <article class="ops-metric <?php echo ($statusFiltro ?? '') === 'ok' ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Válidas<?php echo ($statusFiltro ?? '') === 'ok' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($totalDocs - $docsVencidos - $docsVencendo - $docsAusentes); ?></strong>
        </article>
    </a>
</section>

<div class="shell-ops" id="documentosShell">

    <aside class="shell-ops-sidebar" id="documentosSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Guincheiros">
        <header class="ops-worklist-header">
            <span class="eyebrow">Pessoas e Frota</span>
            <h2>Documentos</h2>
            <p><span id="documentosWorklistCount"><?php echo $totalDocs; ?></span> guincheiro(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="documentosWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="documentosWorklistResults">
            <?php if (empty($documentos)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-folder-open"></i>
                    Nenhum guincheiro encontrado.
                </div>
            <?php else: foreach ($documentos as $i => $d):
                $busca = strtolower(($d['operador_nome'] ?? '') . ' ' . ($d['operador_email'] ?? ''));
                $status = $d['cnh_status'] ?? 'ausente';
                $critico = $status === 'vencida';
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $critico ? 'is-critical' : ($status === 'vencendo' ? 'is-warning' : ''); ?>"
                    data-doc-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars((string)$d['operador_nome']); ?></strong>
                            <span class="ops-badge <?php echo $classes[$status] ?? 'ops-badge--audit'; ?>"><?php echo $labels[$status] ?? 'Ausente'; ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars((string)$d['operador_email']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span>CNH <?php echo htmlspecialchars((string)($d['cnh_numero'] ?: 'ausente')); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($critico): ?>
                        <span class="ops-signal is-danger" title="CNH vencida"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="documentosWorkspace" aria-live="polite">
        <?php if (empty($documentos)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum guincheiro pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__documentosData = <?php echo json_encode(array_values($documentos ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var DOC_STATUS_LABELS = <?php echo json_encode($labels, JSON_UNESCAPED_UNICODE); ?>;
var DOC_STATUS_CSS = <?php echo json_encode($classes, JSON_UNESCAPED_UNICODE); ?>;
var DOC_BP = '<?php echo addslashes($bp); ?>';

(function () {
    var docs = window.__documentosData || [];
    var shell = document.getElementById('documentosShell');
    var results = document.getElementById('documentosWorklistResults');
    var workspace = document.getElementById('documentosWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function fileRow(url, label) {
        if (url) {
            return '<a target="_blank" class="ops-btn" href="' + escapeHtml(url) + '"><i class="fas fa-file me-1"></i>' + escapeHtml(label) + '</a>';
        }
        return '<span class="text-muted small">' + escapeHtml(label) + ' ausente</span>';
    }

    function renderDoc(docId) {
        var d = docs[docId];
        if (!d) return;

        var status = d.cnh_status || 'ausente';
        var badgeCss = DOC_STATUS_CSS[status] || 'ops-badge--audit';
        var badgeLabel = DOC_STATUS_LABELS[status] || 'Ausente';

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(d.operador_nome) + '</h1>' +
            '<p>' + escapeHtml(d.operador_email) + '</p></div>' +
            '<span class="ops-badge ' + badgeCss + '">' + escapeHtml(badgeLabel) + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-id-card me-2"></i>Dados da CNH</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--35">Número</td><td>' + escapeHtml(d.cnh_numero || 'Ausente') + '</td></tr>';
        html += '<tr><td class="ghd-label">Validade</td><td>' + escapeHtml(d.cnh_validade || 'Ausente') + '</td></tr>';
        html += '</table></div></div>';

        html += '<div class="card"><div class="card-header"><i class="fas fa-folder-open me-2"></i>Arquivos</div><div class="card-body d-flex gap-2 flex-wrap">';
        html += fileRow(d.cnh_frente_url, 'CNH frente');
        html += fileRow(d.cnh_verso_url, 'CNH verso');
        html += fileRow(d.foto_veiculo_url, 'Foto veículo');
        html += '</div></div>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectDoc(docId) {
        results.querySelectorAll('[data-doc-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.docId) === docId));
        });
        renderDoc(docId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-doc-id]');
        if (!item) return;
        selectDoc(Number(item.dataset.docId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-doc-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('documentosWorklistSearch');
    var topSearch = document.getElementById('documentosSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (docs.length > 0) selectDoc(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Alertas, Guinchos, Usuários...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
