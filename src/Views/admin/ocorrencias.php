<?php

/**

 * Ocorrências — reestruturada pro padrão shell-ops (mesma arquitetura de

 * /admin/central, Capacidades e Carteiras, pedido explícito do usuário).

 * Resolve um problema real da versão em tabela: a coluna de descrição

 * ficava truncada em max-width:320px — no workspace ela tem espaço de

 * sobra pra ler o incidente inteiro antes de decidir o status.

 *

 * @var array $ocorrencias

 * @var array $contagemPorStatus

 * @var string $statusFiltro

 * @var string $csrfToken

 */

$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();





$statusLabels = ['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'resolvida' => 'Resolvida', 'arquivada' => 'Arquivada'];

$statusBadgeOps = ['aberta' => 'bo-status--critical', 'em_analise' => 'bo-status--route', 'resolvida' => 'bo-status--service', 'arquivada' => 'bo-status--new'];

$severidadeBadgeOps = ['critica' => 'bo-status--critical', 'alta' => 'bo-status--route', 'media' => 'bo-status--audit', 'baixa' => 'bo-status--new'];

$tipoLabels = ['avaria' => 'Avaria', 'atraso' => 'Atraso', 'conduta' => 'Conduta', 'veiculo' => 'Veículo', 'seguranca' => 'Segurança', 'outro' => 'Outro'];

?>








<section class="bo-metrics" aria-label="Resumo de ocorrências">

    <?php foreach (['aberta', 'em_analise', 'resolvida', 'arquivada'] as $st): ?>

    <a href="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencias?status=<?php echo $st; ?>">

        <article class="bo-metric <?php echo $st === 'aberta' && ($contagemPorStatus[$st] ?? 0) > 0 ? 'is-danger' : ($statusFiltro === $st ? 'is-warning' : ''); ?>">

            <span class="bo-metric__label"><?php echo $statusLabels[$st]; ?><?php echo $statusFiltro === $st ? ' · filtrando' : ''; ?></span>

            <strong class="bo-metric__value"><?php echo (int)($contagemPorStatus[$st] ?? 0); ?></strong>

        </article>

    </a>

    <?php endforeach; ?>

</section>



<?php if ($statusFiltro !== ''): ?>

<div>

    <a href="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencias" class="small">

        Limpar filtro de status

    </a>

</div>

<?php endif; ?>



<?php /* Fase 18E: navegação por abas sobre rotas existentes */ ?>

<div class="bo-operation-grid" id="ocShell">



    



    <section class="shell-ops-worklist" aria-label="Ocorrências registradas">

        <header class="bo-panel-heading">

            <span class="eyebrow">Operação</span>

            <h2>Ocorrências</h2>

            <p><span id="ocWorklistCount"><?php echo count($ocorrencias); ?></span> ocorrência(s) <?php echo $statusFiltro !== '' ? 'filtrada(s)' : 'no total'; ?></p>

        </header>



        <div class="bo-search">

            

            <input type="search" id="ocWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">

        </div>



        <div class="bo-orders__list" id="ocWorklistResults">

            <?php if (empty($ocorrencias)): ?>

                <div class="bo-empty">

                    

                    Nenhuma ocorrência <?php echo $statusFiltro !== '' ? 'neste status' : 'registrada'; ?>.

                </div>

            <?php else: foreach ($ocorrencias as $o):

                $busca = strtolower((string)$o['pedido_id'] . ' ' . ($o['cliente_nome'] ?? '') . ' ' . $o['descricao'] . ' ' . ($tipoLabels[$o['tipo']] ?? $o['tipo']));

                $critico = in_array($o['severidade'], ['critica', 'alta'], true) && in_array($o['status'], ['aberta', 'em_analise'], true);

            ?>

                <button type="button"

                    class="bo-order <?php echo $critico ? 'is-critical' : ($o['status'] === 'aberta' ? 'is-warning' : ''); ?>"

                    data-ocorrencia-id="<?php echo (int)$o['id']; ?>"

                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"

                    aria-selected="false"

                >

                    <span class="bo-order__priority" aria-hidden="true"></span>

                    <span class="bo-order__content">

                        <span class="bo-order__top">

                            <strong>Pedido #<?php echo (int)$o['pedido_id']; ?></strong>

                            <span class="bo-status <?php echo $statusBadgeOps[$o['status']] ?? 'bo-status--audit'; ?>"><?php echo htmlspecialchars($statusLabels[$o['status']] ?? $o['status']); ?></span>

                        </span>

                        <span class="bo-order__customer"><?php echo htmlspecialchars($tipoLabels[$o['tipo']] ?? $o['tipo']); ?> · <?php echo htmlspecialchars(ucfirst((string)$o['severidade'])); ?></span>

                        <span class="bo-order__vehicle"><?php echo htmlspecialchars(mb_strimwidth((string)$o['descricao'], 0, 70, '…')); ?></span>

                        <span class="bo-order__footer">

                            <span><?php echo htmlspecialchars((string)($o['cliente_nome'] ?? '')); ?></span>

                            <span><?php echo htmlspecialchars((string)($o['criado_em'] ?? '—')); ?></span>

                        </span>

                    </span>

                    <span class="bo-order__signals">

                        <?php if ($critico): ?>

                        <span class="ops-signal is-danger" title="Severidade alta/crítica em aberto"></span>

                        <?php endif; ?>

                    </span>

                </button>

            <?php endforeach; endif; ?>

        </div>

    </section>



    <section class="shell-ops-workspace" id="ocWorkspace" aria-live="polite">

        <?php if (empty($ocorrencias)): ?>

        <div class="bo-empty">

            

            Nenhuma ocorrência pra exibir.

        </div>

        <?php else: ?>

        <div class="bo-empty" id="ocWorkspaceEmpty">

            

            Selecione uma ocorrência na lista ao lado para ver os detalhes.

        </div>

        <?php foreach ($ocorrencias as $o): ?>

        <div class="oc-detail-panel" id="oc-panel-<?php echo (int)$o['id']; ?>" data-ocorrencia-id="<?php echo (int)$o['id']; ?>">

            <header class="bo-panel-heading">

                <div>

                    <button type="button" class="ops-back-link" data-action="oc-clear-selection">

                         Todas as ocorrências

                    </button>

                    <h1>Pedido #<?php echo (int)$o['pedido_id']; ?> · <?php echo htmlspecialchars($tipoLabels[$o['tipo']] ?? $o['tipo']); ?></h1>

                    <p><?php echo htmlspecialchars((string)($o['cliente_nome'] ?? '')); ?> · registrada em <?php echo htmlspecialchars((string)($o['criado_em'] ?? '—')); ?></p>

                </div>

                <span class="bo-status <?php echo $statusBadgeOps[$o['status']] ?? 'bo-status--audit'; ?>"><?php echo htmlspecialchars($statusLabels[$o['status']] ?? $o['status']); ?></span>

            </header>



            <div class="bo-grid">

                <div class="bo-panel"><span class="bo-panel__label">Severidade</span><span class="bo-panel__value"><?php echo htmlspecialchars(ucfirst((string)$o['severidade'])); ?></span></div>

                <div class="bo-panel"><span class="bo-panel__label">Tipo</span><span class="bo-panel__value"><?php echo htmlspecialchars($tipoLabels[$o['tipo']] ?? $o['tipo']); ?></span></div>

                <div class="bo-panel"><span class="bo-panel__label">Pedido</span><span class="bo-panel__value"><a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/<?php echo (int)$o['pedido_id']; ?>">Ver pedido #<?php echo (int)$o['pedido_id']; ?></a></span></div>

                <div class="bo-panel"><span class="bo-panel__label">Relator</span><span class="bo-panel__value"><?php echo htmlspecialchars(ucfirst((string)($o['relator_tipo'] ?? '—'))); ?></span></div>

            </div>



            <div>

                <span class="eyebrow">Descrição completa</span>

                <p><?php echo htmlspecialchars((string)$o['descricao']); ?></p>

                <?php if (!empty($o['resolucao'])): ?>

                <span class="eyebrow">Resolução</span>

                <p><?php echo htmlspecialchars((string)$o['resolucao']); ?></p>

                <?php endif; ?>

            </div>



            <?php if (in_array($o['status'], ['aberta', 'em_analise'], true)): ?>

            <div>

                <span class="eyebrow">Atualizar status</span>

                <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencia/resolver">

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>">

                    <input type="hidden" name="retorno_status_filtro" value="<?php echo htmlspecialchars($statusFiltro); ?>">

                    <select name="status" class="form-select form-select-sm mb-2">

                        <?php if ($o['status'] === 'aberta'): ?>

                        <option value="em_analise">Em análise</option>

                        <?php endif; ?>

                        <option value="resolvida">Resolvida</option>

                        <option value="arquivada">Arquivada</option>

                    </select>

                    <textarea name="resolucao" class="form-control form-control-sm mb-2" rows="3" placeholder="Resolução (opcional)"></textarea>

                    <button type="submit" class="btn btn-sm btn-primary">Salvar</button>

                </form>

            </div>

            <?php endif; ?>

        </div>

        <?php endforeach; ?>

        <?php endif; ?>

    </section>



</div>



<script<?php echo csp_script_nonce_attr(); ?>>

(function () {

    var shell = document.getElementById('ocShell');

    var results = document.getElementById('ocWorklistResults');

    var workspace = document.getElementById('ocWorkspace');

    var emptyState = document.getElementById('ocWorkspaceEmpty');

    if (!shell || !results || !workspace) return;



    var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-ocorrencia-id]'));

    var panels = Array.prototype.slice.call(workspace.querySelectorAll('.oc-detail-panel'));



    function selectOcorrencia(id) {

        buttons.forEach(function (btn) {

            btn.setAttribute('aria-selected', String(Number(btn.dataset.ocorrenciaId) === id));

        });

        panels.forEach(function (panel) {

            panel.style.display = (Number(panel.dataset.ocorrenciaId) === id) ? 'block' : 'none';

        });

        if (emptyState) emptyState.style.display = id ? 'none' : '';

        if (window.matchMedia('(max-width: 767px)').matches) {

            shell.classList.toggle('has-selection', !!id);

        }

    }



    results.addEventListener('click', function (event) {

        var item = event.target.closest('[data-ocorrencia-id]');

        if (!item) return;

        selectOcorrencia(Number(item.dataset.ocorrenciaId));

    });



    workspace.querySelectorAll('[data-action="oc-clear-selection"]').forEach(function (backLink) {

        backLink.addEventListener('click', function () { selectOcorrencia(null); });

    });



    function applyFilter(term) {

        var t = term.trim().toLowerCase();

        buttons.forEach(function (btn) {

            var blob = btn.dataset.searchBlob || '';

            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';

        });

    }



    var worklistSearch = document.getElementById('ocWorklistSearch');

    var topSearch = document.getElementById('ocWorklistSearchTop');

    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });

    if (topSearch) topSearch.addEventListener('input', function (e) {

        if (worklistSearch) worklistSearch.value = e.target.value;

        applyFilter(e.target.value);

    });



    // Reabre na mesma ocorrência após redirect pós-ação (?ocorrencia_id=X),

    // senão seleciona a primeira crítica/aberta, senão a primeira da lista.

    var paramId = Number(new URLSearchParams(window.location.search).get('ocorrencia_id'));

    if (paramId && buttons.some(function (b) { return Number(b.dataset.ocorrenciaId) === paramId; })) {

        selectOcorrencia(paramId);

    } else if (buttons.length > 0) {

        var critica = buttons.find(function (b) { return b.classList.contains('is-critical'); });

        selectOcorrencia(Number((critica || buttons[0]).dataset.ocorrenciaId));

    }

})();

</script>



<!-- Modal: registrar nova ocorrência (independente da seleção lista/workspace) -->

<div class="modal fade" id="modalNovaOcorrencia" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencia/criar">

                <div class="modal-header">

                    <h5 class="modal-title">Registrar ocorrência</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="mb-3">

                        <label class="form-label">Pedido (ID)</label>

                        <input type="number" name="pedido_id" class="form-control" required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Tipo</label>

                        <select name="tipo" class="form-select">

                            <?php foreach ($tipoLabels as $val => $label): ?>

                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Severidade</label>

                        <select name="severidade" class="form-select">

                            <option value="baixa">Baixa</option>

                            <option value="media" selected>Média</option>

                            <option value="alta">Alta</option>

                            <option value="critica">Crítica</option>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">Descrição</label>

                        <textarea name="descricao" class="form-control" rows="4" required></textarea>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>

                    <button type="submit" class="btn btn-primary">Registrar</button>

                </div>

            </form>

        </div>

    </div>

</div>



<?php

// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),

// igual à Central Operacional, Capacidades e Carteiras — fechamento mínimo

// equivalente (mantém o bundle do Bootstrap pro modal acima funcionar).

?>


<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Ocorrências'; $breadcrumb=['Operação','Ocorrências']; $assets_css=['/public/assets/css/pages/bo-admin-operacao.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
