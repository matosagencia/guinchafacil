<?php
/**
 * §PRESTADORES-HUB-01: módulo único "Prestadores", fundindo Guinchos +
 * Especialistas + Guinchos pendentes + Documentos (Pacote de reorganização
 * de navegação, bloco Claude — ver instrucoes_codex_reorganizacao_admin.md
 * pro bloco espelho do Codex em Financeiro/Qualidade/Sistema/Catálogo).
 *
 * Duas abas dentro do MESMO shell-ops (uma sidebar só):
 *  - "Prestadores": worklist unificada de aprovados (com filtro Todos/
 *    Reboque/Especialistas) + pendentes (filtro "Pendentes"), detalhe via
 *    fetch no MESMO endpoint /admin/guincho-fragmento/{id} de sempre — o
 *    partial já sabia renderizar o card de Aprovar/Rejeitar quando
 *    !aprovado, então nenhuma lógica nova de aprovação foi necessária.
 *  - "Documentos": mesmo padrão JSON embutido (window.__documentosData) de
 *    /admin/documentos, copiado aqui pra não obrigar um fetch/round-trip.
 *
 * As rotas antigas (/admin/guinchos, /admin/guinchos?tipo=, /admin/
 * guinchospendentes, /admin/documentos) continuam de pé sem nenhuma
 * alteração — só pararam de aparecer como links próprios na sidebar.
 *
 * @var array $worklistPrestadores
 * @var array $pendentes
 * @var array $documentos
 * @var string $tipoFiltro
 * @var string $statusFiltro
 * @var array $resumoPrestadores
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

function guinchoStatusBadge(array $g): string {
    if (!empty($g['_pendente'])) return '<span class="ops-badge ops-badge--new">Pendente</span>';
    if (!(int)($g['ativo'] ?? 1)) return '<span class="ops-badge ops-badge--critical">Suspenso</span>';
    if ((int)($g['disponivel'] ?? 0)) return '<span class="ops-badge ops-badge--service">Online</span>';
    return '<span class="ops-badge ops-badge--audit">Offline</span>';
}

$docLabels = ['vencida' => 'Vencida', 'vencendo' => 'Vence em até 30 dias', 'ok' => 'Válida', 'ausente' => 'Sem validade'];
$docClasses = ['vencida' => 'ops-badge--critical', 'vencendo' => 'ops-badge--new', 'ok' => 'ops-badge--service', 'ausente' => 'ops-badge--audit'];
$totalDocs = count($documentos ?? []);
$docsVencidos = count(array_filter($documentos ?? [], static fn($d) => ($d['cnh_status'] ?? '') === 'vencida'));
$docsVencendo = count(array_filter($documentos ?? [], static fn($d) => ($d['cnh_status'] ?? '') === 'vencendo'));
$docsAusentes = count(array_filter($documentos ?? [], static fn($d) => ($d['cnh_status'] ?? '') === 'ausente'));
$abaInicial = ($_GET['aba'] ?? '') === 'documentos' ? 'documentos' : 'prestadores';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-guinchos.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" id="prestSearchTop" placeholder="Buscar por nome, e-mail, telefone ou placa" autocomplete="off">
    </div>
    <div class="ops-topbar__meta">
        <div class="ops-tabs" role="tablist" style="margin:0;">
            <button type="button" class="ops-tab <?php echo $abaInicial === 'prestadores' ? 'is-active' : ''; ?>" data-hub-tab="prestadores">Guinchos</button>
            <button type="button" class="ops-tab <?php echo $abaInicial === 'documentos' ? 'is-active' : ''; ?>" data-hub-tab="documentos">Documentos</button>
        </div>
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

<section class="ops-summary" aria-label="Resumo de guinchos" data-hub-panel="prestadores" style="<?php echo $abaInicial === 'documentos' ? 'display:none' : ''; ?>">
    <article class="ops-metric <?php echo $tipoFiltro === '' ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Aprovados</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoPrestadores['aprovados']; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Online agora</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoPrestadores['online']; ?></strong>
    </article>
    <article class="ops-metric <?php echo $resumoPrestadores['suspensos'] > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Suspensos</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoPrestadores['suspensos']; ?></strong>
    </article>
    <article class="ops-metric <?php echo $resumoPrestadores['pendentes'] > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Aguardando aprovação</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoPrestadores['pendentes']; ?></strong>
    </article>
</section>

<section class="ops-summary" aria-label="Resumo de documentos" data-hub-panel="documentos" style="<?php echo $abaInicial === 'prestadores' ? 'display:none' : ''; ?>">
    <article class="ops-metric <?php echo $docsVencidos > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Vencidas</span>
        <strong class="ops-metric__value"><?php echo $docsVencidos; ?></strong>
    </article>
    <article class="ops-metric <?php echo $docsVencendo > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Vencendo em 30 dias</span>
        <strong class="ops-metric__value"><?php echo $docsVencendo; ?></strong>
    </article>
    <article class="ops-metric <?php echo $docsAusentes > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Sem validade cadastrada</span>
        <strong class="ops-metric__value"><?php echo $docsAusentes; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Válidas</span>
        <strong class="ops-metric__value"><?php echo (int)($totalDocs - $docsVencidos - $docsVencendo - $docsAusentes); ?></strong>
    </article>
</section>

<div class="shell-ops" id="prestShell">

    <aside class="shell-ops-sidebar" id="prestSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Lista de prestadores e documentos">

        <div data-hub-panel="prestadores" style="<?php echo $abaInicial === 'documentos' ? 'display:none' : ''; ?>">
            <header class="ops-worklist-header">
                <span class="eyebrow">Cadastros</span>
                <h2>Guinchos</h2>
                <p><span id="prestWorklistCount"><?php echo count($worklistPrestadores ?? []); ?></span> guincho(s)</p>
            </header>

            <div class="d-flex gap-1 flex-wrap" style="padding:0 16px 10px;">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/prestadores" class="btn btn-sm <?php echo $tipoFiltro === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todos</a>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/prestadores?tipo=reboque" class="btn btn-sm <?php echo $tipoFiltro === 'reboque' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Reboque</a>
                <button type="button" class="btn btn-sm btn-outline-warning" data-prest-filtro="pendente">Pendentes<?php echo !empty($pendentes) ? ' (' . count($pendentes) . ')' : ''; ?></button>
            </div>

            <div class="ops-worklist-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="prestWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
            </div>

            <div class="ops-worklist-results" id="prestWorklistResults">
                <?php if (empty($worklistPrestadores)): ?>
                    <div class="ops-empty-state">
                        <i class="fas fa-truck"></i>
                        Nenhum guincho cadastrado ainda.
                    </div>
                <?php else: foreach ($worklistPrestadores as $g):
                    $busca = strtolower(($g['nome_operador'] ?? '') . ' ' . ($g['email'] ?? '') . ' ' . ($g['telefone'] ?? '') . ' ' . ($g['placa_guincho'] ?? ''));
                    $suspenso = empty($g['ativo'] ?? 1) && empty($g['_pendente']);
                    $rep = (float)($g['reputacao'] ?? 0);
                ?>
                    <button type="button"
                        class="ops-worklist-item <?php echo $suspenso ? 'is-warning' : ''; ?> <?php echo !empty($g['_pendente']) ? 'is-warning' : ''; ?>"
                        data-guincho-id="<?php echo (int)$g['id']; ?>"
                        data-pendente="<?php echo !empty($g['_pendente']) ? '1' : '0'; ?>"
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
                            <?php if (!empty($g['_pendente'])): ?>
                            <span class="ops-signal is-warning" title="Aguardando aprovação"><i class="fas fa-user-clock"></i></span>
                            <?php elseif ($suspenso): ?>
                            <span class="ops-signal is-danger" title="Suspenso"><i class="fas fa-ban"></i></span>
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div data-hub-panel="documentos" style="<?php echo $abaInicial === 'prestadores' ? 'display:none' : ''; ?>">
            <header class="ops-worklist-header">
                <span class="eyebrow">Pessoas e Frota</span>
                <h2>Documentos</h2>
                <p><span id="docWorklistCount"><?php echo $totalDocs; ?></span> guincheiro(s)</p>
            </header>

            <div class="d-flex gap-1 flex-wrap" style="padding:0 16px 10px;">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/prestadores?aba=documentos" class="btn btn-sm <?php echo $statusFiltro === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todas</a>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/prestadores?aba=documentos&status=vencida" class="btn btn-sm <?php echo $statusFiltro === 'vencida' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Vencidas</a>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/prestadores?aba=documentos&status=vencendo" class="btn btn-sm <?php echo $statusFiltro === 'vencendo' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Vencendo</a>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/prestadores?aba=documentos&status=ausente" class="btn btn-sm <?php echo $statusFiltro === 'ausente' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Ausentes</a>
            </div>

            <div class="ops-worklist-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" id="docWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
            </div>

            <div class="ops-worklist-results" id="docWorklistResults">
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
                        aria-selected="false"
                    >
                        <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                        <span class="ops-worklist-item__content">
                            <span class="ops-worklist-item__top">
                                <strong><?php echo htmlspecialchars((string)$d['operador_nome']); ?></strong>
                                <span class="ops-badge <?php echo $docClasses[$status] ?? 'ops-badge--audit'; ?>"><?php echo $docLabels[$status] ?? 'Ausente'; ?></span>
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
        </div>

    </section>

    <section class="shell-ops-workspace" id="prestWorkspace" aria-live="polite">
        <div data-hub-panel="prestadores" style="<?php echo $abaInicial === 'documentos' ? 'display:none' : ''; ?>" id="prestWorkspacePrestadores">
            <?php if (empty($worklistPrestadores)): ?>
            <div class="ops-empty-state" style="padding:80px 20px"><i class="fas fa-inbox"></i>Nenhum prestador pra exibir.</div>
            <?php else: ?>
            <div class="ops-empty-state" style="padding:80px 20px"><i class="fas fa-circle-notch fa-spin"></i>Carregando…</div>
            <?php endif; ?>
        </div>
        <div data-hub-panel="documentos" style="<?php echo $abaInicial === 'prestadores' ? 'display:none' : ''; ?>" id="prestWorkspaceDocumentos">
            <?php if (empty($documentos)): ?>
            <div class="ops-empty-state" style="padding:80px 20px"><i class="fas fa-inbox"></i>Nenhum guincheiro pra exibir.</div>
            <?php endif; ?>
        </div>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__documentosData = <?php echo json_encode(array_values($documentos ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var DOC_STATUS_LABELS = <?php echo json_encode($docLabels, JSON_UNESCAPED_UNICODE); ?>;
var DOC_STATUS_CSS = <?php echo json_encode($docClasses, JSON_UNESCAPED_UNICODE); ?>;

(function () {
    var BP = '<?php echo addslashes($bp); ?>';
    var shell = document.getElementById('prestShell');

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    // ---- Aba "Prestadores" (fetch no fragmento existente) ----
    (function () {
        var results = document.getElementById('prestWorklistResults');
        var workspace = document.getElementById('prestWorkspacePrestadores');
        if (!shell || !results || !workspace) return;

        var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-guincho-id]'));
        var cache = {};
        var loadToken = 0;

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
                    var raw = await res.text();
                    var body;
                    try {
                        body = JSON.parse(raw);
                    } catch (parseError) {
                        if (res.redirected || (res.url && res.url.indexOf('/login') !== -1)) {
                            throw new Error('Sua sessão expirou. Faça login novamente.');
                        }
                        throw new Error('O servidor não retornou JSON (HTTP ' + res.status + ').');
                    }
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
                workspace.innerHTML = '<div class="ops-empty-state" style="padding:80px 20px"><i class="fas fa-hand-pointer"></i>Selecione um prestador na lista ao lado.</div>';
            } else {
                loadDetail(guinchoId);
            }
        }

        results.addEventListener('click', function (event) {
            var item = event.target.closest('[data-guincho-id]');
            if (!item) return;
            selectGuincho(Number(item.dataset.guinchoId));
        });

        function applyFilter(term, pendenteOnly) {
            var t = term.trim().toLowerCase();
            buttons.forEach(function (btn) {
                var blob = btn.dataset.searchBlob || '';
                var matchesText = t === '' || blob.indexOf(t) !== -1;
                var matchesPendente = !pendenteOnly || btn.dataset.pendente === '1';
                btn.style.display = (matchesText && matchesPendente) ? '' : 'none';
            });
        }

        var pendenteAtivo = false;
        var pendenteBtn = document.querySelector('[data-prest-filtro="pendente"]');
        if (pendenteBtn) {
            pendenteBtn.addEventListener('click', function () {
                pendenteAtivo = !pendenteAtivo;
                pendenteBtn.classList.toggle('btn-warning', pendenteAtivo);
                pendenteBtn.classList.toggle('btn-outline-warning', !pendenteAtivo);
                applyFilter((document.getElementById('prestWorklistSearch') || {}).value || '', pendenteAtivo);
            });
        }

        var worklistSearch = document.getElementById('prestWorklistSearch');
        if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value, pendenteAtivo); });

        window.__prestFilterActive = function (term) { applyFilter(term, pendenteAtivo); };

        var paramGuincho = Number(new URLSearchParams(window.location.search).get('guincho_id'));
        if (paramGuincho && buttons.some(function (b) { return Number(b.dataset.guinchoId) === paramGuincho; })) {
            selectGuincho(paramGuincho);
        } else if (buttons.length > 0) {
            selectGuincho(Number(buttons[0].dataset.guinchoId));
        }
    })();

    // ---- Aba "Documentos" (JSON embutido, igual /admin/documentos) ----
    (function () {
        var docs = window.__documentosData || [];
        var results = document.getElementById('docWorklistResults');
        var workspace = document.getElementById('prestWorkspaceDocumentos');
        if (!shell || !results || !workspace) return;

        function fileRow(url, label) {
            if (url) return '<a target="_blank" class="ops-btn" href="' + escapeHtml(url) + '"><i class="fas fa-file me-1"></i>' + escapeHtml(label) + '</a>';
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
            html += '</div></div></div>';
            workspace.innerHTML = html;
        }

        function selectDoc(docId) {
            results.querySelectorAll('[data-doc-id]').forEach(function (btn) {
                btn.setAttribute('aria-selected', String(Number(btn.dataset.docId) === docId));
            });
            renderDoc(docId);
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

        var worklistSearch = document.getElementById('docWorklistSearch');
        if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });

        window.__docFilterActive = function (term) { applyFilter(term); };

        if (docs.length > 0) selectDoc(0);
    })();

    // ---- Alternância de abas (Prestadores <-> Documentos) ----
    var tabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-hub-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-hub-panel]'));
    var topSearch = document.getElementById('prestSearchTop');

    function activateTab(tab) {
        tabButtons.forEach(function (b) { b.classList.toggle('is-active', b.dataset.hubTab === tab); });
        panels.forEach(function (p) { p.style.display = (p.dataset.hubPanel === tab) ? '' : 'none'; });
        if (topSearch) topSearch.value = '';
        try { localStorage.setItem('gf_prestadores_aba', tab); } catch (e) {}
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () { activateTab(btn.dataset.hubTab); });
    });

    if (topSearch) topSearch.addEventListener('input', function (e) {
        var activeTab = (document.querySelector('[data-hub-tab].is-active') || {}).dataset || {};
        if (activeTab.hubTab === 'documentos' && window.__docFilterActive) window.__docFilterActive(e.target.value);
        else if (window.__prestFilterActive) window.__prestFilterActive(e.target.value);
    });
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Guinchos, Documentos...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
