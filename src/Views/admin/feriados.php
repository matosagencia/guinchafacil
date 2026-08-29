<?php
/**
 * Feriados — reestruturada pro padrão shell-ops. A lista já vem inteira do
 * servidor, por isso o workspace é preenchido via JS a partir de um JSON
 * embutido (window.__feriadosData), sem round-trip extra.
 *
 * @var array $feriados
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totalFeriados = count($feriados ?? []);
$feriadosAtivos = count(array_filter($feriados ?? [], static fn($f) => !empty($f['ativo'])));
$hoje = date('Y-m-d');
$proximo = null;
foreach (($feriados ?? []) as $f) {
    if (empty($f['ativo'])) continue;
    $dataComparar = !empty($f['recorrente_anual']) ? date('Y') . substr((string)$f['data'], 4) : (string)$f['data'];
    if ($dataComparar >= $hoje && ($proximo === null || $dataComparar < $proximo['data_comparar'])) {
        $proximo = ['nome' => $f['nome'], 'data_comparar' => $dataComparar];
    }
}

$feriadosPayload = [];
foreach (($feriados ?? []) as $f) {
    $feriadosPayload[] = [
        'id' => (int)$f['id'],
        'data' => (string)$f['data'],
        'data_label' => date('d/m/Y', strtotime((string)$f['data'])),
        'nome' => (string)$f['nome'],
        'recorrente_anual' => !empty($f['recorrente_anual']),
        'ativo' => !empty($f['ativo']),
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="feriadosSearchTop" placeholder="Buscar por nome do feriado" autocomplete="off" aria-label="Buscar feriados"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalFeriados; ?> cadastrados</span>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de feriados">
    <article class="ops-metric">
        <span class="ops-metric__label">Total</span>
        <strong class="ops-metric__value"><?php echo $totalFeriados; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Ativos</span>
        <strong class="ops-metric__value"><?php echo $feriadosAtivos; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Próximo</span>
        <strong class="ops-metric__value" style="font-size:1.1rem;"><?php echo $proximo ? htmlspecialchars((string)$proximo['nome']) : '—'; ?></strong>
        <?php if ($proximo): ?><span class="ops-metric__trend"><?php echo htmlspecialchars(date('d/m/Y', strtotime($proximo['data_comparar']))); ?></span><?php endif; ?>
    </article>
</section>

<?php if (!empty($_GET['salvo'])): ?>
<div class="alert alert-success" style="margin:0 24px 16px;"><i class="fas fa-check-circle me-2"></i>Feriado salvo com sucesso.</div>
<?php endif; ?>
<?php if (!empty($_GET['removido'])): ?>
<div class="alert alert-info" style="margin:0 24px 16px;"><i class="fas fa-check-circle me-2"></i>Feriado removido.</div>
<?php endif; ?>
<?php if (!empty($_GET['erro'])): ?>
<div class="alert alert-danger" style="margin:0 24px 16px;"><i class="fas fa-exclamation-circle me-2"></i>Informe data (AAAA-MM-DD) e nome.</div>
<?php endif; ?>

<div style="padding:0 24px 16px;">
    <div class="card">
        <div class="card-header"><i class="fas fa-circle-plus me-2"></i>Novo feriado</div>
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/feriado/salvar" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <div class="col-md-3">
                    <label class="form-label">Data</label>
                    <input type="date" class="form-control" name="data" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="nome" maxlength="120" required placeholder="Ex.: Natal">
                </div>
                <div class="col-md-3 form-check ms-2">
                    <input type="checkbox" class="form-check-input" id="recorrente_anual" name="recorrente_anual" value="1" checked>
                    <label class="form-check-label" for="recorrente_anual">Recorrente todo ano</label>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="shell-ops" id="feriadosShell">

    <aside class="shell-ops-sidebar" id="feriadosSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Feriados cadastrados">
        <header class="ops-worklist-header">
            <span class="eyebrow">Gestão</span>
            <h2>Feriados</h2>
            <p><span id="feriadosWorklistCount"><?php echo count($feriadosPayload); ?></span> feriado(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="feriadosWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="feriadosWorklistResults">
            <?php if (empty($feriadosPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-calendar-day"></i>
                    Nenhum feriado cadastrado ainda.
                </div>
            <?php else: foreach ($feriadosPayload as $i => $f):
                $busca = strtolower($f['nome']);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo !$f['ativo'] ? 'is-warning' : ''; ?>"
                    data-feriado-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($f['nome']); ?></strong>
                            <span class="ops-badge <?php echo $f['ativo'] ? 'ops-badge--service' : 'ops-badge--audit'; ?>"><?php echo $f['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($f['data_label']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo $f['recorrente_anual'] ? 'Todo ano' : 'Só ' . htmlspecialchars(date('Y', strtotime($f['data']))); ?></span>
                        </span>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="feriadosWorkspace" aria-live="polite">
        <?php if (empty($feriadosPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum feriado pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__feriadosData = <?php echo json_encode($feriadosPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var FER_BP = '<?php echo addslashes($bp); ?>';
var FER_CSRF = <?php echo json_encode($csrfToken ?? ''); ?>;

(function () {
    var feriados = window.__feriadosData || [];
    var shell = document.getElementById('feriadosShell');
    var results = document.getElementById('feriadosWorklistResults');
    var workspace = document.getElementById('feriadosWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function renderFeriado(feriadoId) {
        var f = feriados[feriadoId];
        if (!f) return;

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(f.nome) + '</h1>' +
            '<p>' + escapeHtml(f.data_label) + '</p></div>' +
            '<span class="ops-badge ' + (f.ativo ? 'ops-badge--service' : 'ops-badge--audit') + '">' + (f.ativo ? 'Ativo' : 'Inativo') + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-calendar-day me-2"></i>Detalhes</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--35">Data</td><td>' + escapeHtml(f.data_label) + '</td></tr>';
        html += '<tr><td class="ghd-label">Recorrência</td><td>' + (f.recorrente_anual ? 'Todo ano' : 'Só ' + f.data.slice(0, 4)) + '</td></tr>';
        html += '</table></div></div>';

        html += '<div class="d-flex gap-2">';
        html += '<form method="post" action="' + FER_BP + '/admin/feriado/alternar" class="d-inline">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(FER_CSRF) + '">' +
            '<input type="hidden" name="id" value="' + f.id + '">' +
            '<button type="submit" class="btn btn-outline-warning"><i class="fas fa-toggle-on me-1"></i>Ativar/Desativar</button></form>';
        html += '<form method="post" action="' + FER_BP + '/admin/feriado/remover" class="d-inline" onsubmit="return confirm(\'Remover este feriado?\');">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(FER_CSRF) + '">' +
            '<input type="hidden" name="id" value="' + f.id + '">' +
            '<button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>Remover</button></form>';
        html += '</div>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectFeriado(feriadoId) {
        results.querySelectorAll('[data-feriado-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.feriadoId) === feriadoId));
        });
        renderFeriado(feriadoId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-feriado-id]');
        if (!item) return;
        selectFeriado(Number(item.dataset.feriadoId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-feriado-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('feriadosWorklistSearch');
    var topSearch = document.getElementById('feriadosSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (feriados.length > 0) selectFeriado(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques, Tipos de Serviço...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
