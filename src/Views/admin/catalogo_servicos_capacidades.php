<?php
/**
 * Capacidades dos prestadores — reestruturada pra usar a MESMA arquitetura
 * visual da Central Operacional (pedido explícito do usuário: "essa
 * arquitetura de página é muito melhor para a gestão"): faixa de métricas +
 * shell de 3 colunas (nav / lista de guinchos / workspace com o detalhe do
 * guincho selecionado). Diferente da Central, aqui não existe API/tempo
 * real — os dados já vêm todos prontos do servidor (volume pequeno,
 * ProviderCapability::listar* é uma query só), então a seleção troca o
 * workspace via JS puro (sem fetch), e cada painel de detalhe já nasce
 * renderizado no HTML (só um fica visível por vez).
 *
 * @var array $capacidadesPorGuincho
 * @var array $capacidades
 * @var array $resumoCapacidades
 * @var string $csrfToken
 * @var array|null $flash
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
$statusBadge = ['PENDING' => 'bo-status--new', 'APPROVED' => 'bo-status--service', 'SUSPENDED' => 'bo-status--route', 'REJECTED' => 'bo-status--critical'];
$statusLabel = ['PENDING' => 'Pendente', 'APPROVED' => 'Aprovada', 'SUSPENDED' => 'Suspensa', 'REJECTED' => 'Rejeitada'];
?>

<div class="bo-panel">
    <div class="bo-panel__search">
        
        <input type="search" id="capWorklistSearchTop" placeholder="Buscar por nome do guincho, serviço ou status" autocomplete="off">
    </div>
    <div class="bo-panel__meta">
        <span id="capResultCount"><?php echo count($capacidadesPorGuincho); ?> guinchos · <?php echo count($capacidades); ?> solicitações</span>
    </div>
</div>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-0">
    <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<section class="bo-metrics" aria-label="Resumo das capacidades">
    <article class="bo-metric">
        <span class="bo-metric__label">Guinchos com solicitação</span>
        <strong class="bo-metric__value"><?php echo (int)$resumoCapacidades['guinchos']; ?></strong>
    </article>
    <article class="bo-metric <?php echo $resumoCapacidades['pendentes'] > 0 ? 'is-warning' : ''; ?>">
        <span class="bo-metric__label">Pendentes</span>
        <strong class="bo-metric__value"><?php echo (int)$resumoCapacidades['pendentes']; ?></strong>
        <span class="bo-metric__trend">Requer ação</span>
    </article>
    <article class="bo-metric">
        <span class="bo-metric__label">Aprovadas</span>
        <strong class="bo-metric__value"><?php echo (int)$resumoCapacidades['aprovadas']; ?></strong>
    </article>
    <article class="bo-metric <?php echo $resumoCapacidades['rejeitadas'] > 0 ? 'is-danger' : ''; ?>">
        <span class="bo-metric__label">Rejeitadas</span>
        <strong class="bo-metric__value"><?php echo (int)$resumoCapacidades['rejeitadas']; ?></strong>
    </article>
    <article class="bo-metric">
        <span class="bo-metric__label">Total de solicitações</span>
        <strong class="bo-metric__value"><?php echo (int)$resumoCapacidades['total']; ?></strong>
    </article>
</section>

<div class="bo-operation-grid cap-content-shell" id="capShell">
<section class="bo-orders" aria-label="Guinchos com solicitação de capacidade">
        <header class="bo-orders-header">
            <span class="eyebrow">Homologação</span>
            <h2>Capacidades dos prestadores</h2>
            <p><span id="capWorklistCount"><?php echo count($capacidadesPorGuincho); ?></span> guincho(s) na fila</p>
        </header>

        <div class="bo-orders-search">
            
            <input type="search" id="capWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="bo-orders-results" id="capWorklistResults">
            <?php if (empty($capacidadesPorGuincho)): ?>
                <div class="bo-empty">
                    
                    Nenhuma capacidade declarada ainda.
                </div>
            <?php else: foreach ($capacidadesPorGuincho as $i => $grupo):
                $itens = $grupo['itens'];
                $pendentes = count(array_filter($itens, static fn($c) => $c['approval_status'] === 'PENDING'));
                $aprovadas = count(array_filter($itens, static fn($c) => $c['approval_status'] === 'APPROVED'));
                $ultima = (string)($itens[0]['updated_at'] ?? '—');
                $busca = strtolower($grupo['prestador_nome'] . ' ' . implode(' ', array_map(static fn($c) => ($c['service_name'] ?? '') . ' ' . ($c['service_code'] ?? '') . ' ' . ($c['approval_status'] ?? ''), $itens)));
                $prioridade = $pendentes > 0 ? 'is-warning' : '';
            ?>
                <button type="button"
                    class="bo-orders-item <?php echo $prioridade; ?>"
                    data-guincho-id="<?php echo (int)$grupo['guincho_id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                >
                    <span class="bo-orders-item__priority" aria-hidden="true"></span>
                    <span class="bo-orders-item__content">
                        <span class="bo-orders-item__top">
                            <strong><?php echo htmlspecialchars($grupo['prestador_nome']); ?></strong>
                            <span class="bo-status <?php echo $pendentes > 0 ? 'bo-status--new' : 'bo-status--service'; ?>">
                                <?php echo $pendentes > 0 ? $pendentes . ' pendente(s)' : 'em dia'; ?>
                            </span>
                        </span>
                        <span class="bo-orders-item__customer">#<?php echo (int)$grupo['guincho_id']; ?> · <?php echo count($itens); ?> solicitação(ões) · <?php echo $aprovadas; ?> aprovada(s)</span>
                        <span class="bo-orders-item__footer">
                            <span>Atualizado</span>
                            <span><?php echo htmlspecialchars($ultima); ?></span>
                        </span>
                    </span>
                    <span class="bo-orders-item__signals">
                        <?php if ($pendentes > 0): ?>
                        <span class="bo-status is-danger" title="Tem solicitação pendente">
                            
                        </span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="bo-panel" id="capWorkspace" aria-live="polite">
        <?php if (empty($capacidadesPorGuincho)): ?>
        <div class="bo-empty">
            
            Nenhuma capacidade declarada ainda.
        </div>
        <?php else: ?>
        <div class="bo-empty" id="capWorkspaceEmpty">
            
            Selecione um guincho na lista ao lado para revisar as capacidades.
        </div>
        <?php foreach ($capacidadesPorGuincho as $i => $grupo):
            $itens = $grupo['itens'];
            $pendentes = count(array_filter($itens, static fn($c) => $c['approval_status'] === 'PENDING'));
            $aprovadas = count(array_filter($itens, static fn($c) => $c['approval_status'] === 'APPROVED'));
            $ultima = (string)($itens[0]['updated_at'] ?? '—');
        ?>
        <div class="bo-panel bo-cap-detail" id="cap-panel-<?php echo (int)$grupo['guincho_id']; ?>" data-guincho-id="<?php echo (int)$grupo['guincho_id']; ?>">
            <header class="bo-panel__header">
                <div>
                    <button type="button" class="bo-btn bo-btn--ghost" data-action="cap-clear-selection">
                         Todos os guinchos
                    </button>
                    <h1><?php echo htmlspecialchars($grupo['prestador_nome']); ?></h1>
                    <p>Guincho #<?php echo (int)$grupo['guincho_id']; ?> · atualizado em <?php echo htmlspecialchars($ultima); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/guincho/<?php echo (int)$grupo['guincho_id']; ?>" class="bo-btn">
                     Ver cadastro do prestador
                </a>
            </header>

            <div class="bo-grid">
                <div class="bo-panel"><span class="bo-panel__label">Solicitações</span><span class="bo-panel__value"><?php echo count($itens); ?></span></div>
                <div class="bo-panel"><span class="bo-panel__label">Pendentes</span><span class="bo-panel__value"><?php echo $pendentes; ?></span></div>
                <div class="bo-panel"><span class="bo-panel__label">Aprovadas</span><span class="bo-panel__value"><?php echo $aprovadas; ?></span></div>
                <div class="bo-panel"><span class="bo-panel__label">Última atualização</span><span class="bo-panel__value"><?php echo htmlspecialchars($ultima); ?></span></div>
            </div>

            <div>
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Serviço</th><th>Preço base</th><th>Raio</th><th>Status</th><th>Atualizado</th><th class="text-end">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($itens as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['service_name']); ?> <code class="small"><?php echo htmlspecialchars($c['service_code']); ?></code></td>
                        <td><?php echo $c['base_price'] !== null ? 'R$ ' . number_format((float)$c['base_price'], 2, ',', '.') : '—'; ?></td>
                        <td><?php echo $c['coverage_radius_km'] !== null ? (int)$c['coverage_radius_km'] . ' km' : '—'; ?></td>
                        <td><span class="bo-status <?php echo $statusBadge[$c['approval_status']] ?? 'bo-status--audit'; ?>"><?php echo $statusLabel[$c['approval_status']] ?? htmlspecialchars($c['approval_status']); ?></span></td>
                        <td class="small text-muted"><?php echo htmlspecialchars((string)$c['updated_at']); ?></td>
                        <td class="text-end">
                            <?php if ($c['approval_status'] !== 'APPROVED'): ?>
                            <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/capacidade/decidir" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="capability_id" value="<?php echo (int)$c['id']; ?>">
                                <input type="hidden" name="acao" value="aprovar">
                                <input type="hidden" name="retorno_guincho_id" value="<?php echo (int)$grupo['guincho_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Aprovar"></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($c['approval_status'] === 'APPROVED'): ?>
                            <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/capacidade/decidir" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="capability_id" value="<?php echo (int)$c['id']; ?>">
                                <input type="hidden" name="acao" value="suspender">
                                <input type="hidden" name="retorno_guincho_id" value="<?php echo (int)$grupo['guincho_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Suspender"></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($c['approval_status'] !== 'REJECTED'): ?>
                            <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/capacidade/decidir" class="d-inline" onsubmit="return confirm('Rejeitar esta capacidade?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                <input type="hidden" name="capability_id" value="<?php echo (int)$c['id']; ?>">
                                <input type="hidden" name="acao" value="rejeitar">
                                <input type="hidden" name="retorno_guincho_id" value="<?php echo (int)$grupo['guincho_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Rejeitar"></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var shell = document.getElementById('capShell');
    var results = document.getElementById('capWorklistResults');
    var workspace = document.getElementById('capWorkspace');
    var emptyState = document.getElementById('capWorkspaceEmpty');
    if (!shell || !results || !workspace) return;

    var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-guincho-id]'));
    var panels = Array.prototype.slice.call(workspace.querySelectorAll('.bo-panel bo-cap-detail'));

    function selectGuincho(guinchoId) {
        buttons.forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.guinchoId) === guinchoId));
        });
        panels.forEach(function (panel) {
            panel.style.display = (Number(panel.dataset.guinchoId) === guinchoId) ? 'block' : 'none';
        });
        if (emptyState) emptyState.style.display = guinchoId ? 'none' : '';
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', !!guinchoId);
        }
        if (guinchoId) {
            var url = new URL(window.location.href);
            url.searchParams.set('guincho_id', guinchoId);
            window.history.replaceState({}, '', url);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-guincho-id]');
        if (!item) return;
        selectGuincho(Number(item.dataset.guinchoId));
    });

    workspace.querySelectorAll('[data-action="cap-clear-selection"]').forEach(function (backLink) {
        backLink.addEventListener('click', function () { selectGuincho(null); });
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        buttons.forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('capWorklistSearch');
    var topSearch = document.getElementById('capWorklistSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    // Reabre no mesmo guincho após um redirect pós-ação (?guincho_id=X),
    // ou seleciona o primeiro da fila (priorizando quem tem pendente) —
    // mesmo comportamento de auto-seleção da Central Operacional.
    var paramGuincho = Number(new URLSearchParams(window.location.search).get('guincho_id'));
    if (paramGuincho && buttons.some(function (b) { return Number(b.dataset.guinchoId) === paramGuincho; })) {
        selectGuincho(paramGuincho);
    } else if (buttons.length > 0) {
        var comPendente = buttons.find(function (b) { return b.classList.contains('is-warning'); });
        selectGuincho(Number((comPendente || buttons[0]).dataset.guinchoId));
    }
})();
</script>

<?php
$conteudo_pagina = ob_get_clean();
$perfil = 'admin';
$titulo_pagina = 'Capacidades dos prestadores';
$breadcrumb = ['Backoffice', 'Pessoas e parceiros', 'Capacidades'];
$assets_css = ['/public/assets/css/pages/bo-admin-capacidades.css'];
include __DIR__ . '/../layouts/backoffice_shell.php';
