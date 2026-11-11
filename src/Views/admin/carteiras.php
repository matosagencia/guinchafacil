<?php

/**

 * Carteiras - reestruturada pro padrão shell-ops (mesma arquitetura de

 * /admin/central e /admin/catalogo-servicos/capacidades, pedido explícito

 * do usuário). Fundiu esta lista com a antiga /admin/carteira/{id}

 * (extrato individual): agora é uma página só, lista + workspace, e o

 * detalhe de cada guincheiro · buscado via fetch() ao endpoint JSON

 * /admin/carteira-json/{id} - evita reintroduzir N+1 (CarteiraService

 * já documenta explicitamente esse cuidado) buscando só o detalhe do

 * guincheiro selecionado, não de todos de uma vez.

 *

 * Painel de VISIBILIDADE sobre o repasse Pix já automático - não · saldo

 * retido nem saque manual (ver CarteiraService). Toda falha de query

 * aparece como alerta explícito, nunca como "R$ 0,00" silencioso.

 *

 * @var array $resumo  ['ok'=>bool,'erro'=>?string,'linhas'=>array]

 * @var array $reconciliacao

 */

$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();




$totalCompensacao = 0.0; $totalPago = 0.0; $totalEstornado = 0.0; $totalReservaGateway = 0.0; $totalFalhas = 0;

foreach ($resumo['linhas'] ?? [] as $l) {

    $totalCompensacao += (float)$l['saldo_em_compensacao'];

    $totalPago += (float)$l['saldo_pago'];

    $totalEstornado += (float)$l['saldo_estornado'];
    $totalReservaGateway += (float)($l['reserva_gateway'] ?? 0);

    $totalFalhas += (int)$l['repasses_com_falha'];

}

?>

<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/css/pages/gf-fin2-r4-shell-unificado.css?v=20261019-r4">
<link rel="stylesheet" href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/public/assets/css/pages/gf-fin2-r5-layout.css?v=20261019-r5">



<?php if (!$resumo['ok']): ?>

<div class="alert alert-danger">

    

    <strong>Falha ao calcular o resumo de carteiras.</strong> Isto NÃO significa saldo zero - a consulta falhou.

    Detalhe técnico: <code><?php echo htmlspecialchars((string)($resumo['erro'] ?? '')); ?></code>.

    Ver <a href="<?php echo htmlspecialchars($bp); ?>/admin/logs">Logs</a> para o rastro completo.

</div>

<?php endif; ?>



<?php if ($reconciliacao['ok'] && !$reconciliacao['consistente']): ?>

<div class="alert alert-warning">

    

    <strong>Divergência de reconciliação detectada.</strong>

    Ledger contábil: R$ <?php echo number_format($reconciliacao['credito_guincho_ledger'], 2, ',', '.'); ?> ·

    Soma em pagamentos: R$ <?php echo number_format($reconciliacao['credito_guincho_pagamentos'], 2, ',', '.'); ?> ·

    Diferença: R$ <?php echo number_format($reconciliacao['diferenca'], 2, ',', '.'); ?>.

    Isso indica um bug real em algum dos dois caminhos - não ignore.

</div>

<?php elseif (!$reconciliacao['ok']): ?>

<div class="alert alert-danger">

    Falha ao checar reconciliação: <?php echo htmlspecialchars((string)($reconciliacao['erro'] ?? '')); ?>

</div>

<?php endif; ?>



<section class="ops-summary" aria-label="Resumo financeiro">

    <article class="ops-metric">

        <span class="ops-metric__label">Em compensação</span>

        <strong class="ops-metric__value">R$ <?php echo number_format($totalCompensacao, 2, ',', '.'); ?></strong>

        <span class="ops-metric__trend">Aprovado, ainda não repassado</span>

    </article>

    <article class="ops-metric">

        <span class="ops-metric__label">Pago</span>

        <strong class="ops-metric__value">R$ <?php echo number_format($totalPago, 2, ',', '.'); ?></strong>

        <span class="ops-metric__trend">Repasse Pix confirmado</span>

    </article>

    <article class="ops-metric">

        <span class="ops-metric__label">Reserva de gateway</span>

        <strong class="ops-metric__value">R$ <?php echo number_format($totalReservaGateway, 2, ',', '.'); ?></strong>

        <span class="ops-metric__trend">Retenão registrada no ledger</span>

    </article>

    <article class="ops-metric">

        <span class="ops-metric__label">Estornado</span>

        <strong class="ops-metric__value">R$ <?php echo number_format($totalEstornado, 2, ',', '.'); ?></strong>

    </article>

    <article class="ops-metric <?php echo $totalFalhas > 0 ? 'is-danger' : ''; ?>">

        <span class="ops-metric__label">Repasses com falha</span>

        <strong class="ops-metric__value"><?php echo $totalFalhas; ?></strong>

    </article>

</section>



<div class="bo-operation-grid" id="cartShell">



    <section class="shell-ops-worklist" aria-label="Guincheiros com movimentação financeira">

        <header class="bo-panel-heading">

            <span class="eyebrow">Financeiro</span>

            <h2>Carteiras</h2>

            <p><span id="cartWorklistCount"><?php echo count($resumo['linhas'] ?? []); ?></span> guincheiro(s)</p>

        </header>



        <div class="bo-search">

            

            <input type="search" id="cartWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">

        </div>



        <div class="bo-orders__list" id="cartWorklistResults">

            <?php if (empty($resumo['linhas'])): ?>

                <div class="bo-empty">

                    

                    <?php echo $resumo['ok'] ? 'Nenhum guincheiro aprovado com movimentação.' : 'Não foi possível carregar (ver alerta acima).'; ?>

                </div>

            <?php else: foreach ($resumo['linhas'] as $l):

                $busca = strtolower(($l['nome_operador'] ?? '') . ' ' . ($l['placa_guincho'] ?? ''));

                $temFalha = (int)$l['repasses_com_falha'] > 0;

            ?>

                <button type="button"

                    class="bo-order <?php echo $temFalha ? 'is-warning' : ''; ?>"

                    data-guincho-id="<?php echo (int)$l['guincho_id']; ?>"

                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"

                    aria-selected="false"

                >

                    <span class="bo-order__priority" aria-hidden="true"></span>

                    <span class="bo-order__content">

                        <span class="bo-order__top">

                            <strong><?php echo htmlspecialchars($l['nome_operador']); ?></strong>

                            <span class="bo-status bo-status--service">R$ <?php echo number_format((float)$l['saldo_pago'], 2, ',', '.'); ?></span>

                        </span>

                        <span class="bo-order__customer"><?php echo htmlspecialchars($l['placa_guincho'] ?? '-'); ?> · em compensação: R$ <?php echo number_format((float)$l['saldo_em_compensacao'], 2, ',', '.'); ?></span>

                        <span class="bo-order__footer">

                            <span>#<?php echo (int)$l['guincho_id']; ?></span>

                            <span><?php echo $temFalha ? (int)$l['repasses_com_falha'] . ' falha(s)' : 'sem falhas'; ?></span>

                        </span>

                    </span>

                    <span class="bo-order__signals">

                        <?php if ($temFalha): ?>

                        <span class="ops-signal is-danger" title="Repasse com falha"></span>

                        <?php endif; ?>

                    </span>

                </button>

            <?php endforeach; endif; ?>

        </div>

    </section>



    <section class="shell-ops-workspace" id="cartWorkspace" aria-live="polite">

        <?php if (empty($resumo['linhas'])): ?>

        <div class="bo-empty">

            

            Nenhum guincheiro pra exibir.

        </div>

        <?php else: ?>

        <div class="bo-empty">

            

            Selecione um guincheiro na lista ao lado pra ver o extrato completo.

        </div>

        <?php endif; ?>

    </section>



</div>



<script<?php echo csp_script_nonce_attr(); ?>>

(function () {

    var shell = document.getElementById('cartShell');

    var results = document.getElementById('cartWorklistResults');

    var workspace = document.getElementById('cartWorkspace');

    if (!shell || !results || !workspace) return;



    var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-guincho-id]'));

    var cache = {};

    var loadToken = 0;

    var BP = '<?php echo addslashes($bp); ?>';



    var statusPixLabel = { pendente: 'Pendente', processando: 'Processando', concluido: 'Concluído', falha: 'Falha', falha_permanente: 'Falha permanente' };

    var statusPixBadge = { pendente: 'bo-status--new', processando: 'bo-status--route', concluido: 'bo-status--service', falha: 'bo-status--critical', falha_permanente: 'bo-status--critical' };



    function escapeHtml(value) {

        var el = document.createElement('div');

        el.textContent = String(value == null ? '' : value);

        return el.innerHTML;

    }



    function renderSkeleton() {

        workspace.innerHTML = '<div class="bo-empty">Carregando extrato.</div>';

    }



    function renderError(message) {

        workspace.innerHTML = '<div class="bo-empty">' + escapeHtml(message) + '</div>';

    }



    function renderDetail(data) {

        var g = data.guincho, saldo = data.saldo, extrato = data.extrato;



        var html = '<header class="bo-panel-heading">' +

            '<div><button type="button" class="ops-back-link" data-action="cart-clear-selection"> Todos os guincheiros</button>' +

            '<h1>' + escapeHtml(g.nome_operador) + '</h1>' +

            '<p>' + escapeHtml(g.placa_guincho || '—') + (g.telefone ? ' · ' + escapeHtml(g.telefone) : '') + '</p></div>' +

            '</header>';



        if (!saldo.ok) {

            html += '<div class="alert alert-danger">Falha ao calcular o saldo: ' + escapeHtml(saldo.erro || '') + '</div>';

        } else {

            html += '<div class="bo-grid">' +

                '<div class="bo-panel"><span class="bo-panel__label">Em compensação</span><span class="bo-panel__value">R$ ' + Number(saldo.saldo_em_compensacao).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span></div>' +

                '<div class="bo-panel"><span class="bo-panel__label">Pago</span><span class="bo-panel__value">R$ ' + Number(saldo.saldo_pago).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span></div>' +

                '<div class="bo-panel"><span class="bo-panel__label">Estornado</span><span class="bo-panel__value">R$ ' + Number(saldo.saldo_estornado).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span></div>' +

                '<div class="bo-panel"><span class="bo-panel__label">Último repasse</span><span class="bo-panel__value">' + escapeHtml(saldo.ultimo_repasse_em || '-') + '</span></div>' +

                '</div>';

        }



        html += '<div><table class="table table-sm mb-0 align-middle">' +

            '<thead><tr><th>Pedido</th><th>Status pedido</th><th>Valor total</th><th>Valor guincho</th><th>Status Pix</th><th>Transação Pix</th><th>Pago em</th></tr></thead><tbody>';



        if (!extrato.ok) {

            html += '<tr><td colspan="7" class="text-center text-muted py-4">Falha ao carregar extrato: ' + escapeHtml(extrato.erro || '') + '</td></tr>';

        } else if (!extrato.linhas.length) {

            html += '<tr><td colspan="7" class="text-center text-muted py-4">Nenhum pedido encontrado para este guincheiro.</td></tr>';

        } else {

            extrato.linhas.forEach(function (r) {

                var badge = r.status_pix ? '<span class="bo-status ' + (statusPixBadge[r.status_pix] || 'bo-status--audit') + '">' + escapeHtml(statusPixLabel[r.status_pix] || r.status_pix) + '</span>' : '-';

                html += '<tr>' +

                    '<td><a href="' + BP + '/admin/pedido/' + r.pedido_id + '">#' + r.pedido_id + '</a></td>' +

                    '<td class="small text-muted">' + escapeHtml(r.pedido_status) + '</td>' +

                    '<td>' + (r.valor_total !== null ? 'R$ ' + Number(r.valor_total).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '-') + '</td>' +

                    '<td>' + (r.valor_guincho !== null ? 'R$ ' + Number(r.valor_guincho).toLocaleString('pt-BR', {minimumFractionDigits:2}) : '-') + '</td>' +

                    '<td>' + badge + '</td>' +

                    '<td class="small text-muted">' + escapeHtml(r.id_transacao_pix || '-') + '</td>' +

                    '<td class="small text-muted">' + escapeHtml(r.data_pagamento_guincho || '-') + '</td>' +

                    '</tr>';

            });

        }

        html += '</tbody></table></div>';



        workspace.innerHTML = html;

        var back = workspace.querySelector('[data-action="cart-clear-selection"]');

        if (back) back.addEventListener('click', function () { selectGuincho(null); });

    }



    async function loadDetail(guinchoId) {

        var myToken = ++loadToken;

        renderSkeleton();

        try {

            if (!cache[guinchoId]) {

                var res = await fetch(BP + '/admin/carteira-json/' + guinchoId, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

                var body = await res.json();

                if (!res.ok || !body.ok) throw new Error(body.erro || ('HTTP ' + res.status));

                cache[guinchoId] = body;

            }

            if (myToken !== loadToken) return;

            renderDetail(cache[guinchoId]);

        } catch (err) {

            if (myToken === loadToken) renderError('Falha ao carregar extrato: ' + err.message);

        }

    }



    function selectGuincho(guinchoId) {

        buttons.forEach(function (btn) {

            btn.setAttribute('aria-selected', String(Number(btn.dataset.guinchoId) === guinchoId));

        });

        if (!guinchoId) {

            workspace.innerHTML = '<div class="bo-empty">Selecione um guincheiro na lista ao lado pra ver o extrato completo.</div>';

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



    var worklistSearch = document.getElementById('cartWorklistSearch');

    var topSearch = document.getElementById('cartWorklistSearchTop');

    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });

    if (topSearch) topSearch.addEventListener('input', function (e) {

        if (worklistSearch) worklistSearch.value = e.target.value;

        applyFilter(e.target.value);

    });



    // Reabre no mesmo guincheiro vindo do link antigo /admin/carteira/{id}

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

// igual à Central Operacional e Capacidades - fechamento m·nimo equivalente.

?>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Carteiras'; $breadcrumb=['Financeiro','Carteiras']; $assets_css=['/public/assets/css/pages/bo-admin-carteiras.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
