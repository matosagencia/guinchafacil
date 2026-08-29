<?php
/**
 * Cidades-alvo — reestruturada pro padrão shell-ops. Unidade de expansão
 * territorial: todo guincheiro é vinculado a uma cidade cadastrada aqui no
 * momento do cadastro (AuthController::registroGuincho); cliente não tem
 * esse vínculo. Também é a base do seletor de cidade em
 * /admin/planejamento. A lista já vem inteira do servidor, por isso o
 * workspace é preenchido via JS a partir de um JSON embutido
 * (window.__cidadesData), sem round-trip extra.
 *
 * @var array $cidades
 * @var array $guinchosPorCidade
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totalCidades = count($cidades ?? []);
$cidadesAtivas = count(array_filter($cidades ?? [], static fn($c) => !empty($c['ativo'])));
$totalGuinchosVinculados = array_sum($guinchosPorCidade ?? []);

$cidadesPayload = [];
foreach (($cidades ?? []) as $c) {
    $cidadesPayload[] = [
        'id' => (int)$c['id'],
        'nome' => (string)$c['nome'],
        'uf' => (string)$c['uf'],
        'slug' => (string)$c['slug'],
        'ativo' => !empty($c['ativo']),
        'guinchos' => (int)($guinchosPorCidade[(int)$c['id']] ?? 0),
        'lat_centro' => $c['lat_centro'] !== null ? (float)$c['lat_centro'] : null,
        'lng_centro' => $c['lng_centro'] !== null ? (float)$c['lng_centro'] : null,
        'raio_km' => $c['raio_km'] !== null ? (int)$c['raio_km'] : null,
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="cidadesSearchTop" placeholder="Buscar por nome ou UF" autocomplete="off" aria-label="Buscar cidades"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalCidades; ?> cadastradas</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/planejamento" class="ops-dashboard-link"><i class="fas fa-calculator me-1"></i>Planejamento</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de cidades-alvo">
    <article class="ops-metric">
        <span class="ops-metric__label">Total</span>
        <strong class="ops-metric__value"><?php echo $totalCidades; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Ativas</span>
        <strong class="ops-metric__value"><?php echo $cidadesAtivas; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Guincheiros vinculados</span>
        <strong class="ops-metric__value"><?php echo (int)$totalGuinchosVinculados; ?></strong>
    </article>
</section>

<?php if (!empty($_GET['salvo'])): ?>
<div class="alert alert-success" style="margin:0 24px 16px;"><i class="fas fa-check-circle me-2"></i>Cidade salva com sucesso.</div>
<?php endif; ?>
<?php if (!empty($_GET['geo_salva'])): ?>
<div class="alert alert-success" style="margin:0 24px 16px;"><i class="fas fa-check-circle me-2"></i>Geolocalização da cidade salva com sucesso.</div>
<?php endif; ?>
<?php if (!empty($_GET['erro'])): ?>
<div class="alert alert-danger" style="margin:0 24px 16px;"><i class="fas fa-exclamation-circle me-2"></i>Informe nome e UF (2 letras) válidos.</div>
<?php endif; ?>

<div style="padding:0 24px 16px;">
    <div class="alert alert-info small mb-3">
        <i class="fas fa-circle-info me-1"></i>
        Toda cidade cadastrada aqui vira uma opção obrigatória no cadastro de guincheiro (o prestador escolhe sua
        cidade de atuação) e um cenário próprio em <a href="<?php echo $bp; ?>/admin/planejamento">Planejamento de lançamento</a>.
        O cliente nunca é vinculado a uma cidade — pode solicitar de qualquer lugar.
    </div>
    <div class="card">
        <div class="card-header fw-bold"><i class="fas fa-circle-plus me-2"></i>Nova cidade-alvo</div>
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/cidade/salvar" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <div class="col-md-5">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="nome" maxlength="120" required placeholder="Ex.: Niterói">
                </div>
                <div class="col-md-2">
                    <label class="form-label">UF</label>
                    <input type="text" class="form-control" name="uf" maxlength="2" required placeholder="RJ" style="text-transform:uppercase">
                </div>
                <div class="col-md-3 form-check ms-2">
                    <input type="checkbox" class="form-check-input" id="cidadeAtiva" name="ativo" value="1" checked>
                    <label class="form-check-label" for="cidadeAtiva">Ativa</label>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="shell-ops" id="cidadesShell">

    <aside class="shell-ops-sidebar" id="cidadesSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Cidades cadastradas">
        <header class="ops-worklist-header">
            <span class="eyebrow">Território</span>
            <h2>Cidades-alvo</h2>
            <p><span id="cidadesWorklistCount"><?php echo count($cidadesPayload); ?></span> cidade(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="cidadesWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="cidadesWorklistResults">
            <?php if (empty($cidadesPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-city"></i>
                    Nenhuma cidade-alvo cadastrada ainda.
                </div>
            <?php else: foreach ($cidadesPayload as $i => $c):
                $busca = strtolower($c['nome'] . ' ' . $c['uf']);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo !$c['ativo'] ? 'is-warning' : ''; ?>"
                    data-cidade-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($c['nome']); ?></strong>
                            <span class="ops-badge <?php echo $c['ativo'] ? 'ops-badge--service' : 'ops-badge--audit'; ?>"><?php echo $c['ativo'] ? 'Ativa' : 'Inativa'; ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($c['uf']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo $c['guinchos']; ?> guincheiro(s) vinculado(s)</span>
                        </span>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="cidadesWorkspace" aria-live="polite">
        <?php if (empty($cidadesPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhuma cidade pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__cidadesData = <?php echo json_encode($cidadesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var CID_BP = '<?php echo addslashes($bp); ?>';
var CID_CSRF = <?php echo json_encode($csrfToken ?? ''); ?>;

(function () {
    var cidades = window.__cidadesData || [];
    var shell = document.getElementById('cidadesShell');
    var results = document.getElementById('cidadesWorklistResults');
    var workspace = document.getElementById('cidadesWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function renderCidade(cidadeId) {
        var c = cidades[cidadeId];
        if (!c) return;

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(c.nome) + '</h1>' +
            '<p>' + escapeHtml(c.uf) + ' · <code>' + escapeHtml(c.slug) + '</code></p></div>' +
            '<span class="ops-badge ' + (c.ativo ? 'ops-badge--service' : 'ops-badge--audit') + '">' + (c.ativo ? 'Ativa' : 'Inativa') + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 12px">' +
            '<a class="ops-btn" href="' + CID_BP + '/admin/planejamento?cidade_id=' + c.id + '"><i class="fas fa-calculator"></i> Ver planejamento desta cidade</a>' +
            '</div>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-truck me-2"></i>Guincheiros vinculados</div><div class="card-body">';
        html += '<strong style="font-size:1.4rem;">' + c.guinchos + '</strong> <span class="text-muted small">prestador(es) de atuação nesta cidade</span>';
        html += '</div></div>';

        var temGeo = c.lat_centro !== null && c.lng_centro !== null;
        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-location-crosshairs me-2"></i>Geolocalização (preço por cidade)</div><div class="card-body">';
        html += '<p class="text-muted small mb-3">Define o centro geográfico e o raio de abrangência desta cidade. ' +
            'É isso que permite ao sistema resolver automaticamente "este pedido pertence a qual cidade-alvo?" e aplicar ' +
            'tarifas específicas (ver <a href="' + CID_BP + '/admin/configuracoes">Configurações</a> e ' +
            '<a href="' + CID_BP + '/admin/catalogo-servicos/tarifas">Tarifas por tipo de serviço</a>). Sem isso preenchido, ' +
            'esta cidade nunca é resolvida por coordenada e o preço cai no padrão global de sempre.</p>';
        html += '<form method="post" action="' + CID_BP + '/admin/cidade/geo/salvar" class="row g-2">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(CID_CSRF) + '">' +
            '<input type="hidden" name="id" value="' + c.id + '">' +
            '<div class="col-md-4"><label class="form-label small">Latitude do centro</label>' +
            '<input type="text" class="form-control form-control-sm" name="lat_centro" value="' + (c.lat_centro !== null ? c.lat_centro : '') + '" placeholder="-22.8833"></div>' +
            '<div class="col-md-4"><label class="form-label small">Longitude do centro</label>' +
            '<input type="text" class="form-control form-control-sm" name="lng_centro" value="' + (c.lng_centro !== null ? c.lng_centro : '') + '" placeholder="-43.1036"></div>' +
            '<div class="col-md-2"><label class="form-label small">Raio (km)</label>' +
            '<input type="number" class="form-control form-control-sm" name="raio_km" value="' + (c.raio_km !== null ? c.raio_km : 30) + '" min="1"></div>' +
            '<div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-sm btn-primary w-100">Salvar</button></div>' +
            '</form>';
        html += '<p class="small mt-2 mb-0">' + (temGeo
            ? '<span class="badge text-bg-success">Configurada</span> esta cidade já é resolvida automaticamente por coordenada.'
            : '<span class="badge text-bg-secondary">Não configurada</span> preencha os campos acima pra ativar a segmentação de preço por cidade.') + '</p>';
        html += '</div></div>';

        html += '<form method="post" action="' + CID_BP + '/admin/cidade/alternar" class="d-inline">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(CID_CSRF) + '">' +
            '<input type="hidden" name="id" value="' + c.id + '">' +
            '<button type="submit" class="btn btn-outline-warning"><i class="fas fa-toggle-on me-1"></i>Ativar/Desativar</button></form>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectCidade(cidadeId) {
        results.querySelectorAll('[data-cidade-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.cidadeId) === cidadeId));
        });
        renderCidade(cidadeId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-cidade-id]');
        if (!item) return;
        selectCidade(Number(item.dataset.cidadeId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-cidade-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('cidadesWorklistSearch');
    var topSearch = document.getElementById('cidadesSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (cidades.length > 0) selectCidade(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques, Feriados...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
