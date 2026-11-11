<?php
/**
 * "Saques" (Pacote L2.3) — como não existe solicitação manual de saque hoje
 * (repasse Pix já é automático), esta é a fila operacional real: repasses
 * pendentes/processando/com falha. Reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central, /admin/alertas, /admin/documentos...) — a
 * lista já vem inteira do servidor (sem paginação nem N+1), por isso o
 * workspace é preenchido via JS a partir de um JSON embutido
 * (window.__saquesData), sem round-trip extra. Reprocessamento reaproveita
 * 100% PixService::reprocessar() via AdminController::pixReprocessar() já
 * existente.
 *
 * @var array $repasses ['ok'=>bool,'erro'=>?string,'linhas'=>array]
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();


$statusLabel = ['pendente' => 'Pendente', 'processando' => 'Processando', 'falha' => 'Falha', 'falha_permanente' => 'Falha permanente'];
$statusCss = ['pendente' => 'bo-status--audit', 'processando' => 'bo-status--service', 'falha' => 'bo-status--critical', 'falha_permanente' => 'bo-status--critical'];

if (!empty($_GET['msg'])) {
    $msgMap = ['pix_reprocessado' => ['success', 'Repasse reprocessado com sucesso.'], 'pix_falha' => ['danger', 'Reprocessamento falhou — veja os logs.']];
}

$resumoSaques = ['pendente' => 0, 'processando' => 0, 'falha' => 0, 'falha_permanente' => 0];
foreach (($repasses['linhas'] ?? []) as $r) {
    $st = (string)($r['status_pix'] ?? '');
    if (isset($resumoSaques[$st])) $resumoSaques[$st]++;
}
$totalFalhas = $resumoSaques['falha'] + $resumoSaques['falha_permanente'];

$repassesPayload = [];
foreach (($repasses['linhas'] ?? []) as $r) {
    $repassesPayload[] = [
        'pedido_id' => (int)$r['pedido_id'],
        'guincho_operador' => (string)($r['guincho_operador'] ?? '—'),
        'valor_guincho' => (float)($r['valor_guincho'] ?? 0),
        'status_pix' => (string)($r['status_pix'] ?? ''),
        'aguardando_desde' => (string)($r['pagamento_criado_em'] ?? '—'),
    ];
}
?>
<!-- GF-FIN2-R4-SHELL -->

<div class="bo-toolbar-meta">
        <span class="bo-toolbar-status"><span class="bo-toolbar-status-dot"></span><?php echo count($repassesPayload); ?> repasse(s)</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/carteiras" class="ops-dashboard-link">Ver carteiras</a>
    </div>
</div>

<section class="bo-metrics" aria-label="Resumo de repasses">
    <article class="bo-metric <?php echo $resumoSaques['pendente'] > 0 ? 'is-warning' : ''; ?>">
        <span class="bo-metric__label">Pendentes</span>
        <strong class="bo-metric__value"><?php echo $resumoSaques['pendente']; ?></strong>
    </article>
    <article class="bo-metric">
        <span class="bo-metric__label">Processando</span>
        <strong class="bo-metric__value"><?php echo $resumoSaques['processando']; ?></strong>
    </article>
    <article class="bo-metric <?php echo $totalFalhas > 0 ? 'is-danger' : ''; ?>">
        <span class="bo-metric__label">Com falha</span>
        <strong class="bo-metric__value"><?php echo $totalFalhas; ?></strong>
        <?php if ($totalFalhas > 0): ?><span class="bo-metric__trend">Requer reprocessamento</span><?php endif; ?>
    </article>
</section>

<?php $gfMsgKey = (string)($_GET['msg'] ?? ''); if ($gfMsgKey !== '' && isset($msgMap[$gfMsgKey])): ?>
<div class="alert alert-<?php echo $msgMap[$gfMsgKey][0]; ?>"><?php echo $msgMap[$gfMsgKey][1]; ?></div>
<?php endif; ?>

<?php if (!$repasses['ok']): ?>
<div class="alert alert-danger">
    
    <strong>Falha ao carregar repasses pendentes.</strong> Detalhe técnico:
    <code><?php echo htmlspecialchars((string)($repasses['erro'] ?? '')); ?></code>
</div>
<?php endif; ?>

<div class="alert alert-info small">
    
    O repasse ao guincheiro é automático (Pix enviado ao concluir o pedido). Esta tela lista só os que ainda
    não foram confirmados — pra você acompanhar e reprocessar quando necessário, não pra "liberar" nada manualmente.
</div>

<div class="bo-operation-grid" id="saquesShell">

    

    <section class="shell-ops-worklist" aria-label="Repasses">
        <header class="bo-panel-heading">
            <span class="eyebrow">Financeiro</span>
            <h2>Saques (repasses Pix)</h2>
            <p><span id="saquesWorklistCount"><?php echo count($repassesPayload); ?></span> repasse(s)</p>
        </header>

        <div class="bo-search">
            
            <input type="search" id="saquesWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="bo-orders__list" id="saquesWorklistResults">
            <?php if (empty($repassesPayload)): ?>
                <div class="bo-empty">
                    
                    <?php echo $repasses['ok'] ? 'Nenhum repasse pendente ou com falha no momento.' : 'Não foi possível carregar (ver alerta acima).'; ?>
                </div>
            <?php else: foreach ($repassesPayload as $i => $r):
                $busca = strtolower($r['pedido_id'] . ' ' . $r['guincho_operador']);
                $critico = in_array($r['status_pix'], ['falha', 'falha_permanente'], true);
            ?>
                <button type="button"
                    class="bo-order <?php echo $critico ? 'is-critical' : ($r['status_pix'] === 'pendente' ? 'is-warning' : ''); ?>"
                    data-repasse-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="bo-order__priority" aria-hidden="true"></span>
                    <span class="bo-order__content">
                        <span class="bo-order__top">
                            <strong>Pedido #<?php echo (int)$r['pedido_id']; ?></strong>
                            <span class="bo-status <?php echo $statusCss[$r['status_pix']] ?? 'bo-status--audit'; ?>"><?php echo htmlspecialchars($statusLabel[$r['status_pix']] ?? $r['status_pix']); ?></span>
                        </span>
                        <span class="bo-order__customer"><?php echo htmlspecialchars($r['guincho_operador']); ?> · R$ <?php echo number_format($r['valor_guincho'], 2, ',', '.'); ?></span>
                        <span class="bo-order__footer">
                            <span>Aguardando desde <?php echo htmlspecialchars($r['aguardando_desde']); ?></span>
                        </span>
                    </span>
                    <span class="bo-order__signals">
                        <?php if ($critico): ?>
                        <span class="ops-signal is-danger" title="Falha no repasse"></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="saquesWorkspace" aria-live="polite">
        <?php if (empty($repassesPayload)): ?>
        <div class="bo-empty">
            
            Nenhum repasse pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__saquesData = <?php echo json_encode($repassesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var SAQ_STATUS_LABELS = <?php echo json_encode($statusLabel, JSON_UNESCAPED_UNICODE); ?>;
var SAQ_STATUS_CSS = <?php echo json_encode($statusCss, JSON_UNESCAPED_UNICODE); ?>;
var SAQ_BP = '<?php echo addslashes($bp); ?>';
var SAQ_CSRF = <?php echo json_encode($csrfToken ?? ''); ?>;

(function () {
    var repasses = window.__saquesData || [];
    var shell = document.getElementById('saquesShell');
    var results = document.getElementById('saquesWorklistResults');
    var workspace = document.getElementById('saquesWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function money(v) {
        return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderRepasse(repasseId) {
        var r = repasses[repasseId];
        if (!r) return;

        var badgeCss = SAQ_STATUS_CSS[r.status_pix] || 'bo-status--audit';
        var badgeLabel = SAQ_STATUS_LABELS[r.status_pix] || r.status_pix;

        var html = '<header class="bo-panel-heading">' +
            '<div><h1>Pedido #' + r.pedido_id + '</h1>' +
            '<p>' + escapeHtml(r.guincho_operador) + '</p></div>' +
            '<span class="bo-status ' + badgeCss + '">' + escapeHtml(badgeLabel) + '</span>' +
            '</header>';

        html += '<div><a class="bo-btn" href="' + SAQ_BP + '/admin/pedido/' + r.pedido_id + '"> Ver pedido #' + r.pedido_id + '</a></div>';

        html += '<div>';
        html += '<div class="card mb-3"><div class="card-header">Repasse</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--40">Guincheiro</td><td>' + escapeHtml(r.guincho_operador) + '</td></tr>';
        html += '<tr><td class="ghd-label">Valor</td><td>' + money(r.valor_guincho) + '</td></tr>';
        html += '<tr><td class="ghd-label">Aguardando desde</td><td>' + escapeHtml(r.aguardando_desde) + '</td></tr>';
        html += '</table></div></div>';

        html += '<form method="post" action="' + SAQ_BP + '/admin/pix/reprocessar/' + r.pedido_id + '" onsubmit="return confirm(\'Reprocessar este repasse Pix agora?\');">' +
            '<input type="hidden" name="csrf_token" value="' + escapeHtml(SAQ_CSRF) + '">' +
            '<input type="hidden" name="retorno" value="saques">' +
            '<button type="submit" class="btn btn-primary">Reprocessar repasse</button></form>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectRepasse(repasseId) {
        results.querySelectorAll('[data-repasse-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.repasseId) === repasseId));
        });
        renderRepasse(repasseId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-repasse-id]');
        if (!item) return;
        selectRepasse(Number(item.dataset.repasseId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-repasse-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('saquesWorklistSearch');
    var topSearch = document.getElementById('saquesSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (repasses.length > 0) selectRepasse(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Alertas, Documentos, Financeiro...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Saques e repasses'; $breadcrumb=['Financeiro','Saques']; $assets_css=['/public/assets/css/pages/bo-admin-financeiro.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
