<?php
/**
 * Guinchos / Especialistas — reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central, /admin/ocorrencias, /admin/carteiras e
 * /admin/guinchospendentes). Lista de aprovados vira a worklist clicável;
 * o detalhe de cada guincheiro é buscado via fetch() ao endpoint
 * /admin/guincho-fragmento/{id}, que já existia (retorna {ok, html} com o
 * mesmo partial usado antes em guinhodetalhe.php) — evita pré-renderizar
 * o detalhe completo (documentos, pedidos, capacidades) de todos os
 * guincheiros de uma vez.
 *
 * @var array $pendentes
 * @var array $aprovados
 * @var string $tipoFiltro
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

function guinchoStatusBadge(array $g): string {
    if (!(int)($g['ativo'] ?? 1)) return '<span class="ops-badge ops-badge--critical">Suspenso</span>';
    if ((int)($g['disponivel'] ?? 0)) return '<span class="ops-badge ops-badge--service">Online</span>';
    return '<span class="ops-badge ops-badge--audit">Offline</span>';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-guinchos.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" id="guinchosSearchTop" placeholder="Buscar por nome, e-mail, telefone ou placa" autocomplete="off">
    </div>
    <div class="ops-topbar__meta">
        <?php if (!empty($pendentes)): ?>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchospendentes" class="ops-dashboard-link" style="color:var(--warning,#f2b53e);">
            <i class="fas fa-user-clock me-1"></i><?php echo count($pendentes); ?> aguardando aprovação
        </a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/guincho/novo" class="ops-dashboard-link"><i class="fas fa-plus me-1"></i>Cadastrar Guincheiro</a>
    </div>
</div>

<?php if (!empty($_GET['msg'])): ?>
<?php $msgMap = ['aprovado'=>['success','check-circle','Guincho aprovado com sucesso!'],
                 'rejeitado'=>['warning','times-circle','Guincho rejeitado.'],
                 'criado'=>['success','check-circle','Guincheiro cadastrado com sucesso!']];
      $m = $msgMap[$_GET['msg']] ?? ['info','info-circle',htmlspecialchars($_GET['msg'])]; ?>
<div class="alert alert-<?php echo $m[0]; ?>" style="margin:16px 24px 0;">
    <i class="fas fa-<?php echo $m[1]; ?> me-2"></i><?php echo $m[2]; ?>
</div>
<?php endif; ?>

<?php
$totalAtivos = count(array_filter($aprovados ?? [], static fn($g) => !empty($g['ativo'] ?? 1)));
$totalOnline = count(array_filter($aprovados ?? [], static fn($g) => !empty($g['disponivel'])));
$totalSuspensos = count(array_filter($aprovados ?? [], static fn($g) => empty($g['ativo'] ?? 1)));
?>
<section class="ops-summary" aria-label="Resumo de guinchos">
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos" style="text-decoration:none;">
        <article class="ops-metric <?php echo $tipoFiltro === '' ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Aprovados<?php echo $tipoFiltro === '' ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo count($aprovados ?? []); ?></strong>
        </article>
    </a>
    <article class="ops-metric">
        <span class="ops-metric__label">Online agora</span>
        <strong class="ops-metric__value"><?php echo $totalOnline; ?></strong>
    </article>
    <article class="ops-metric <?php echo $totalSuspensos > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Suspensos</span>
        <strong class="ops-metric__value"><?php echo $totalSuspensos; ?></strong>
    </article>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchospendentes" style="text-decoration:none;">
        <article class="ops-metric <?php echo !empty($pendentes) ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Aguardando aprovação</span>
            <strong class="ops-metric__value"><?php echo count($pendentes ?? []); ?></strong>
        </article>
    </a>
</section>

<div class="shell-ops" id="guinchosShell">

    <aside class="shell-ops-sidebar" id="guinchosSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Guinchos aprovados">
        <header class="ops-worklist-header">
            <span class="eyebrow">Cadastros</span>
            <h2><?php echo $tipoFiltro === 'especialista' ? 'Especialistas' : 'Guinchos'; ?></h2>
            <p><span id="guinchosWorklistCount"><?php echo count($aprovados ?? []); ?></span> prestador(es)</p>
        </header>

        <div class="d-flex gap-1 flex-wrap" style="padding:0 16px 10px;">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos" class="btn btn-sm <?php echo $tipoFiltro === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todos</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos?tipo=reboque" class="btn btn-sm <?php echo $tipoFiltro === 'reboque' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Reboque</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos?tipo=especialista" class="btn btn-sm <?php echo $tipoFiltro === 'especialista' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Especialistas</a>
        </div>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="guinchosWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="guinchosWorklistResults">
            <?php if (empty($aprovados)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-truck"></i>
                    Nenhum guincheiro aprovado ainda.
                </div>
            <?php else: foreach ($aprovados as $g):
                $busca = strtolower(($g['nome_operador'] ?? '') . ' ' . ($g['email'] ?? '') . ' ' . ($g['telefone'] ?? '') . ' ' . ($g['placa_guincho'] ?? ''));
                $suspenso = empty($g['ativo'] ?? 1);
                $rep = (float)($g['reputacao'] ?? 0);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $suspenso ? 'is-warning' : ''; ?>"
                    data-guincho-id="<?php echo (int)$g['id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($g['nome_operador'] ?? '—'); ?></strong>
                            <?php echo guinchoStatusBadge($g); ?>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($g['placa_guincho'] ?? '—'); ?> <?php echo $rep > 0 ? '· ★ ' . number_format($rep, 1) : ''; ?></span>
                        <span class="ops-worklist-item__footer">
                            <span>#<?php echo (int)$g['id']; ?></span>
                            <span><?php echo htmlspecialchars($g['telefone'] ?? '—'); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($suspenso): ?>
                        <span class="ops-signal is-danger" title="Suspenso"><i class="fas fa-ban"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="guinchosWorkspace" aria-live="polite">
        <?php if (empty($aprovados)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum guincheiro pra exibir.
        </div>
        <?php else: ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-circle-notch fa-spin"></i>
            Carregando…
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var shell = document.getElementById('guinchosShell');
    var results = document.getElementById('guinchosWorklistResults');
    var workspace = document.getElementById('guinchosWorkspace');
    if (!shell || !results || !workspace) return;

    var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-guincho-id]'));
    var cache = {};
    var loadToken = 0;
    var BP = '<?php echo addslashes($bp); ?>';

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function renderSkeleton() {
        workspace.innerHTML = '<div class="ops-empty-state" style="padding:60px 20px"><i class="fas fa-circle-notch fa-spin"></i>Carregando…</div>';
    }

    function renderError(message) {
        workspace.innerHTML = '<div class="ops-empty-state" style="padding:60px 20px"><i class="fas fa-triangle-exclamation"></i>' + escapeHtml(message) + '</div>';
    }

    function wireBackLink() {
        var back = workspace.querySelector('[data-action="gu-clear-selection"]');
        if (back) back.addEventListener('click', function () { selectGuincho(null); });
    }

    async function loadDetail(guinchoId) {
        var myToken = ++loadToken;
        renderSkeleton();
        try {
            if (!cache[guinchoId]) {
                var res = await fetch(BP + '/admin/guincho-fragmento/' + guinchoId, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                var body = await res.json();
                if (!res.ok || !body.ok) throw new Error(body.erro || ('HTTP ' + res.status));
                cache[guinchoId] = body.html;
            }
            if (myToken !== loadToken) return;
            workspace.innerHTML = cache[guinchoId];
            wireBackLink();
        } catch (err) {
            if (myToken === loadToken) renderError('Falha ao carregar detalhe: ' + err.message);
        }
    }

    function selectGuincho(guinchoId) {
        buttons.forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.guinchoId) === guinchoId));
        });
        if (!guinchoId) {
            workspace.innerHTML = '<div class="ops-empty-state" style="padding:80px 20px"><i class="fas fa-hand-pointer"></i>Selecione um guincheiro na lista ao lado.</div>';
        } else {
            loadDetail(guinchoId);
        }
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', !!guinchoId);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-guincho-id]');
        if (!item) return;
        selectGuincho(Number(item.dataset.guinchoId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        buttons.forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('guinchosWorklistSearch');
    var topSearch = document.getElementById('guinchosSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    // Reabre no mesmo guincheiro vindo do link antigo /admin/guincho/{id}
    // (agora um redirect ?guincho_id=X), ou seleciona o primeiro da lista.
    var paramGuincho = Number(new URLSearchParams(window.location.search).get('guincho_id'));
    if (paramGuincho && buttons.some(function (b) { return Number(b.dataset.guinchoId) === paramGuincho; })) {
        selectGuincho(paramGuincho);
    } else if (buttons.length > 0) {
        selectGuincho(Number(buttons[0].dataset.guinchoId));
    }
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Ocorrências, Carteiras e Guinchos Pendentes.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
