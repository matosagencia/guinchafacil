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
ob_start();





$nivelLabels = ['erro' => 'Critico', 'aviso' => 'Atencao', 'info' => 'Informativo'];

$nivelBadgeCss = ['erro' => 'critical', 'aviso' => 'new', 'info' => 'audit'];

?>




<section class="bo-metrics" aria-label="Resumo de alertas por nível">

    <?php foreach (['erro' => ['circle-exclamation', 'is-danger', 'Críticos'], 'aviso' => ['triangle-exclamation', 'is-warning', 'Atenção'], 'info' => ['circle-info', '', 'Informativos']] as $nivel => $meta): ?>

    <a href="<?php echo htmlspecialchars($bp); ?>/admin/alertas?nivel=<?php echo $nivel; ?>">

        <article class="bo-metric <?php echo $nivelFiltro === $nivel ? $meta[1] : (($contagemPorNivel[$nivel] ?? 0) > 0 ? $meta[1] : ''); ?>">

            <span class="bo-metric__label"><?php echo $meta[2]; ?><?php echo $nivelFiltro === $nivel ? ' · filtrando' : ''; ?></span>

            <strong class="bo-metric__value"><?php echo (int)($contagemPorNivel[$nivel] ?? 0); ?></strong>

        </article>

    </a>

    <?php endforeach; ?>

</section>



<?php /* Fase 18E: navegação por abas sobre rotas existentes */ ?>

<div class="bo-operation-grid" id="alertasShell">



    <aside class="shell-ops-sidebar" id="alertasSidebar">

        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>

    </aside>



    <section class="shell-ops-worklist" aria-label="Grupos de alerta">

        <header class="bo-panel-heading">

            <span class="eyebrow">Operação</span>

            <h2>Alertas Operacionais</h2>

            <p><span id="alertasWorklistCount"><?php echo count($alertasAgrupados); ?></span> grupo(s)</p>

        </header>



        <div class="bo-search">

            

            <input type="search" id="alertasWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">

        </div>



        <div class="bo-orders__list" id="alertasWorklistResults">

            <?php if (empty($alertasAgrupados)): ?>

                <div class="bo-empty">

                    

                    Nenhum alerta no momento. Operação normal.

                </div>

            <?php else: foreach ($alertasAgrupados as $i => $grupo):

                $grupoTexto = strtolower($grupo['titulo'] . ' ' . implode(' ', array_map(static fn($item) => ($item['label'] ?? '') . ' ' . ($item['info'] ?? ''), $grupo['itens'])));

            ?>

                <button type="button"

                    class="bo-order <?php echo $grupo['nivel'] === 'erro' ? 'is-critical' : ($grupo['nivel'] === 'aviso' ? 'is-warning' : ''); ?>"

                    data-group-id="<?php echo (int)$i; ?>"

                    data-search-blob="<?php echo htmlspecialchars($grupoTexto, ENT_QUOTES, 'UTF-8'); ?>"

                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"

                >

                    <span class="bo-order__priority" aria-hidden="true"></span>

                    <span class="bo-order__content">

                        <span class="bo-order__top">

                            <strong><?php echo htmlspecialchars($grupo['titulo']); ?></strong>

                            <span class="bo-status bo-status--<?php echo $nivelBadgeCss[$grupo['nivel']] ?? 'audit'; ?>"><?php echo htmlspecialchars($nivelLabels[$grupo['nivel']] ?? ucfirst((string)$grupo['nivel'])); ?></span>

                        </span>

                        <span class="bo-order__customer"><?php echo count($grupo['itens']); ?> ocorrência(s)</span>

                        <span class="bo-order__footer">

                            <span><?php echo htmlspecialchars($grupo['quando']); ?></span>

                        </span>

                    </span>

                    <span class="bo-order__signals">

                        <?php if ($grupo['nivel'] === 'erro'): ?>

                        <span class="ops-signal is-danger" title="Crítico"></span>

                        <?php endif; ?>

                    </span>

                </button>

            <?php endforeach; endif; ?>

        </div>

    </section>



    <section class="shell-ops-workspace" id="alertasWorkspace" aria-live="polite">

        <?php if (empty($alertasAgrupados)): ?>

        <div class="bo-empty">

            

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



        var html = '<header class="bo-panel-heading">' +

            '<div><h1>' + escapeHtml(grupo.titulo) + '</h1>' +

            '<p>' + grupo.itens.length + ' ocorrência(s) · ' + escapeHtml(grupo.quando) + '</p></div>' +

            '<span class="bo-status bo-status--' + badgeCss + '">' + escapeHtml(badgeLabel) + '</span>' +

            '</header>';



        if (grupo.pedido_id) {

            html += '<div><a class="bo-btn" href="' + ALERT_BP + '/admin/pedido/' + grupo.pedido_id + '"> Ver pedido #' + grupo.pedido_id + '</a></div>';

        }



        html += '<div>';

        if (!grupo.itens.length) {

            html += '<div class="bo-empty">Sem ocorrências detalhadas.</div>';

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


<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Alertas operacionais'; $breadcrumb=['Operação','Alertas']; $assets_css=['/public/assets/css/pages/bo-admin-operacao.css']; require __DIR__ . '/../layouts/backoffice_shell.php';