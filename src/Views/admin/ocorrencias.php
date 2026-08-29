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
include __DIR__ . '/../layouts/header.php';

$statusLabels = ['aberta' => 'Aberta', 'em_analise' => 'Em análise', 'resolvida' => 'Resolvida', 'arquivada' => 'Arquivada'];
$statusBadgeOps = ['aberta' => 'ops-badge--critical', 'em_analise' => 'ops-badge--route', 'resolvida' => 'ops-badge--service', 'arquivada' => 'ops-badge--new'];
$severidadeBadgeOps = ['critica' => 'ops-badge--critical', 'alta' => 'ops-badge--route', 'media' => 'ops-badge--audit', 'baixa' => 'ops-badge--new'];
$tipoLabels = ['avaria' => 'Avaria', 'atraso' => 'Atraso', 'conduta' => 'Conduta', 'veiculo' => 'Veículo', 'seguranca' => 'Segurança', 'outro' => 'Outro'];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" id="ocWorklistSearchTop" placeholder="Buscar por pedido, cliente ou descrição" autocomplete="off">
    </div>
    <div class="ops-topbar__meta">
        <button type="button" class="ops-btn" data-bs-toggle="modal" data-bs-target="#modalNovaOcorrencia" style="height:32px">
            <i class="fas fa-plus"></i> Registrar
        </button>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/central" style="color:var(--brand);text-decoration:none;">
            <i class="fas fa-tower-broadcast me-1"></i>Central Operacional
        </a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo de ocorrências">
    <?php foreach (['aberta', 'em_analise', 'resolvida', 'arquivada'] as $st): ?>
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencias?status=<?php echo $st; ?>" style="text-decoration:none;">
        <article class="ops-metric <?php echo $st === 'aberta' && ($contagemPorStatus[$st] ?? 0) > 0 ? 'is-danger' : ($statusFiltro === $st ? 'is-warning' : ''); ?>">
            <span class="ops-metric__label"><?php echo $statusLabels[$st]; ?><?php echo $statusFiltro === $st ? ' · filtrando' : ''; ?></span>
            <strong class="ops-metric__value"><?php echo (int)($contagemPorStatus[$st] ?? 0); ?></strong>
        </article>
    </a>
    <?php endforeach; ?>
</section>

<?php if ($statusFiltro !== ''): ?>
<div style="margin:0 24px;">
    <a href="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencias" class="small" style="color:var(--theme-muted);">
        <i class="fas fa-xmark me-1"></i>Limpar filtro de status
    </a>
</div>
<?php endif; ?>

<div class="shell-ops" id="ocShell">

    <aside class="shell-ops-sidebar" id="ocSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Ocorrências registradas">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Ocorrências</h2>
            <p><span id="ocWorklistCount"><?php echo count($ocorrencias); ?></span> ocorrência(s) <?php echo $statusFiltro !== '' ? 'filtrada(s)' : 'no total'; ?></p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="ocWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="ocWorklistResults">
            <?php if (empty($ocorrencias)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhuma ocorrência <?php echo $statusFiltro !== '' ? 'neste status' : 'registrada'; ?>.
                </div>
            <?php else: foreach ($ocorrencias as $o):
                $busca = strtolower((string)$o['pedido_id'] . ' ' . ($o['cliente_nome'] ?? '') . ' ' . $o['descricao'] . ' ' . ($tipoLabels[$o['tipo']] ?? $o['tipo']));
                $critico = in_array($o['severidade'], ['critica', 'alta'], true) && in_array($o['status'], ['aberta', 'em_analise'], true);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $critico ? 'is-critical' : ($o['status'] === 'aberta' ? 'is-warning' : ''); ?>"
                    data-ocorrencia-id="<?php echo (int)$o['id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong>Pedido #<?php echo (int)$o['pedido_id']; ?></strong>
                            <span class="ops-badge <?php echo $statusBadgeOps[$o['status']] ?? 'ops-badge--audit'; ?>"><?php echo htmlspecialchars($statusLabels[$o['status']] ?? $o['status']); ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($tipoLabels[$o['tipo']] ?? $o['tipo']); ?> · <?php echo htmlspecialchars(ucfirst((string)$o['severidade'])); ?></span>
                        <span class="ops-worklist-item__vehicle"><?php echo htmlspecialchars(mb_strimwidth((string)$o['descricao'], 0, 70, '…')); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars((string)($o['cliente_nome'] ?? '')); ?></span>
                            <span><?php echo htmlspecialchars((string)($o['criado_em'] ?? '—')); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($critico): ?>
                        <span class="ops-signal is-danger" title="Severidade alta/crítica em aberto"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="ocWorkspace" aria-live="polite">
        <?php if (empty($ocorrencias)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhuma ocorrência pra exibir.
        </div>
        <?php else: ?>
        <div class="ops-empty-state" id="ocWorkspaceEmpty" style="padding:80px 20px">
            <i class="fas fa-hand-pointer"></i>
            Selecione uma ocorrência na lista ao lado para ver os detalhes.
        </div>
        <?php foreach ($ocorrencias as $o): ?>
        <div class="oc-detail-panel" id="oc-panel-<?php echo (int)$o['id']; ?>" data-ocorrencia-id="<?php echo (int)$o['id']; ?>" style="display:none">
            <header class="ops-order-header">
                <div>
                    <button type="button" class="ops-back-link" data-action="oc-clear-selection">
                        <i class="fas fa-arrow-left"></i> Todas as ocorrências
                    </button>
                    <h1>Pedido #<?php echo (int)$o['pedido_id']; ?> · <?php echo htmlspecialchars($tipoLabels[$o['tipo']] ?? $o['tipo']); ?></h1>
                    <p><?php echo htmlspecialchars((string)($o['cliente_nome'] ?? '')); ?> · registrada em <?php echo htmlspecialchars((string)($o['criado_em'] ?? '—')); ?></p>
                </div>
                <span class="ops-badge <?php echo $statusBadgeOps[$o['status']] ?? 'ops-badge--audit'; ?>"><?php echo htmlspecialchars($statusLabels[$o['status']] ?? $o['status']); ?></span>
            </header>

            <div class="ops-order-facts">
                <div class="ops-order-fact"><span class="ops-order-fact__label">Severidade</span><span class="ops-order-fact__value"><?php echo htmlspecialchars(ucfirst((string)$o['severidade'])); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Tipo</span><span class="ops-order-fact__value"><?php echo htmlspecialchars($tipoLabels[$o['tipo']] ?? $o['tipo']); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Pedido</span><span class="ops-order-fact__value"><a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/<?php echo (int)$o['pedido_id']; ?>">Ver pedido #<?php echo (int)$o['pedido_id']; ?></a></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Relator</span><span class="ops-order-fact__value"><?php echo htmlspecialchars(ucfirst((string)($o['relator_tipo'] ?? '—'))); ?></span></div>
            </div>

            <div style="padding:18px 24px 0">
                <span class="eyebrow">Descrição completa</span>
                <p style="margin-top:8px; color:var(--theme-text); white-space:pre-wrap;"><?php echo htmlspecialchars((string)$o['descricao']); ?></p>
                <?php if (!empty($o['resolucao'])): ?>
                <span class="eyebrow">Resolução</span>
                <p style="margin-top:8px; color:var(--theme-text); white-space:pre-wrap;"><?php echo htmlspecialchars((string)$o['resolucao']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (in_array($o['status'], ['aberta', 'em_analise'], true)): ?>
            <div style="padding:20px 24px 32px">
                <span class="eyebrow">Atualizar status</span>
                <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/ocorrencia/resolver" style="margin-top:10px; max-width:480px;">
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
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
