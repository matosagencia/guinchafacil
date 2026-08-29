<?php
/**
 * Guinchos Pendentes — reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central e /admin/catalogo-servicos/capacidades,
 * pedido explícito do usuário). Cada card já trazia toda a info necessária
 * pra decidir (documentos, CNH, placa, capacidade) — aqui isso vira o
 * painel de workspace, e a lista à esquerda é só o essencial pra escolher
 * quem revisar.
 *
 * @var array $pendentes
 * @var array $resumoPendentes
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" id="gpWorklistSearchTop" placeholder="Buscar por nome, e-mail, telefone ou placa" autocomplete="off">
    </div>
    <div class="ops-topbar__meta">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos" class="ops-dashboard-link" style="color:var(--brand);text-decoration:none;">
            <i class="fas fa-arrow-left me-1"></i>Ver aprovados
        </a>
    </div>
</div>

<?php if (!empty($_GET['msg'])):
    $msgMap = ['aprovado' => ['success', 'check-circle', 'Guincheiro aprovado! Ele já pode receber pedidos.'],
               'rejeitado' => ['warning', 'times-circle', 'Cadastro rejeitado.']];
    $m = $msgMap[$_GET['msg']] ?? ['info', 'info-circle', htmlspecialchars($_GET['msg'])];
?>
<div class="alert alert-<?php echo $m[0]; ?>" style="margin:16px 24px 0;">
    <i class="fas fa-<?php echo $m[1]; ?> me-2"></i><?php echo $m[2]; ?>
</div>
<?php endif; ?>

<section class="ops-summary" aria-label="Resumo de cadastros pendentes">
    <article class="ops-metric <?php echo $resumoPendentes['total'] > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Aguardando aprovação</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoPendentes['total']; ?></strong>
        <span class="ops-metric__trend">Requer ação</span>
    </article>
    <article class="ops-metric <?php echo $resumoPendentes['sem_documentos'] > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Sem nenhum documento enviado</span>
        <strong class="ops-metric__value"><?php echo (int)$resumoPendentes['sem_documentos']; ?></strong>
    </article>
</section>

<div class="shell-ops" id="gpShell">

    <aside class="shell-ops-sidebar" id="gpSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Guinchos aguardando aprovação">
        <header class="ops-worklist-header">
            <span class="eyebrow">Administração</span>
            <h2>Guinchos Pendentes</h2>
            <p><span id="gpWorklistCount"><?php echo count($pendentes ?? []); ?></span> operador(es) na fila</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="gpWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="gpWorklistResults">
            <?php if (empty($pendentes)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum cadastro pendente. Todos os guincheiros foram revisados.
                </div>
            <?php else: foreach ($pendentes as $g):
                $temDocs = !empty($g['doc_cnh_frente']) || !empty($g['foto_veiculo']);
                $busca = strtolower(($g['nome_operador'] ?? '') . ' ' . ($g['email'] ?? '') . ' ' . ($g['telefone'] ?? '') . ' ' . ($g['placa_guincho'] ?? ''));
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $temDocs ? '' : 'is-warning'; ?>"
                    data-guincho-id="<?php echo (int)$g['id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="false"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($g['nome_operador'] ?? '—'); ?></strong>
                            <span class="ops-badge <?php echo $temDocs ? 'ops-badge--service' : 'ops-badge--critical'; ?>">
                                <?php echo $temDocs ? 'com docs' : 'sem docs'; ?>
                            </span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($g['placa_guincho'] ?? '—'); ?> · <?php echo htmlspecialchars($g['email'] ?? ''); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span>Cadastrado</span>
                            <span><?php echo isset($g['criado_em']) ? date('d/m/Y', strtotime($g['criado_em'])) : '—'; ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if (!$temDocs): ?>
                        <span class="ops-signal is-danger" title="Sem documentos"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="gpWorkspace" aria-live="polite">
        <?php if (empty($pendentes)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-check-circle"></i>
            Nenhum cadastro pendente no momento.
        </div>
        <?php else: ?>
        <div class="ops-empty-state" id="gpWorkspaceEmpty" style="padding:80px 20px">
            <i class="fas fa-hand-pointer"></i>
            Selecione um operador na lista ao lado para revisar o cadastro.
        </div>
        <?php foreach ($pendentes as $g): ?>
        <div class="gp-detail-panel" id="gp-panel-<?php echo (int)$g['id']; ?>" data-guincho-id="<?php echo (int)$g['id']; ?>" style="display:none">
            <header class="ops-order-header">
                <div>
                    <button type="button" class="ops-back-link" data-action="gp-clear-selection">
                        <i class="fas fa-arrow-left"></i> Todos os pendentes
                    </button>
                    <h1><?php echo htmlspecialchars($g['nome_operador'] ?? '—'); ?></h1>
                    <p><?php echo htmlspecialchars($g['email'] ?? ''); ?><?php echo !empty($g['telefone']) ? ' · ' . htmlspecialchars($g['telefone']) : ''; ?> · cadastrado em <?php echo isset($g['criado_em']) ? date('d/m/Y', strtotime($g['criado_em'])) : '—'; ?></p>
                </div>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/guincho/<?php echo (int)$g['id']; ?>" class="ops-btn">
                    <i class="fas fa-eye"></i> Ver perfil completo
                </a>
            </header>

            <div class="ops-order-facts">
                <div class="ops-order-fact"><span class="ops-order-fact__label">Placa</span><span class="ops-order-fact__value"><?php echo htmlspecialchars($g['placa_guincho'] ?? '—'); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">CNH</span><span class="ops-order-fact__value"><?php echo htmlspecialchars($g['cnh_numero'] ?? '—'); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Capacidade</span><span class="ops-order-fact__value"><?php echo number_format($g['capacidade_ton'] ?? 0, 1); ?> ton</span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Raio</span><span class="ops-order-fact__value"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</span></div>
                <?php if (!empty($g['cpf'])): ?>
                <div class="ops-order-fact"><span class="ops-order-fact__label">CPF</span><span class="ops-order-fact__value"><?php echo htmlspecialchars($g['cpf']); ?></span></div>
                <?php endif; ?>
            </div>

            <div style="padding:18px 24px 0">
                <span class="eyebrow">Documentos</span>
                <?php if (!empty($g['doc_cnh_frente']) || !empty($g['foto_veiculo'])): ?>
                <div class="d-flex gap-2 flex-wrap mt-2">
                    <?php if (!empty($g['doc_cnh_frente'])): ?>
                    <a href="<?php echo htmlspecialchars($bp); ?>/arquivo/<?php echo (int)$g['id']; ?>?tipo=doc_cnh_frente" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-id-card me-1"></i>CNH Frente
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($g['doc_cnh_verso'])): ?>
                    <a href="<?php echo htmlspecialchars($bp); ?>/arquivo/<?php echo (int)$g['id']; ?>?tipo=doc_cnh_verso" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-id-card me-1"></i>CNH Verso
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($g['foto_veiculo'])): ?>
                    <a href="<?php echo htmlspecialchars($bp); ?>/arquivo/<?php echo (int)$g['id']; ?>?tipo=foto_veiculo" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-truck me-1"></i>Foto Veículo
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning mt-2 mb-0"><i class="fas fa-exclamation-triangle me-1"></i>Nenhum documento enviado</div>
                <?php endif; ?>
            </div>

            <div style="padding:20px 24px 32px; display:flex; gap:10px;">
                <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/admin/guincho/aprovar" class="flex-fill">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                    <button class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Aprovar</button>
                </form>
                <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/admin/guincho/rejeitar" class="flex-fill">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                    <button class="btn btn-danger w-100" data-confirm-message="Rejeitar cadastro de <?php echo htmlspecialchars((string)($g['nome_operador'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>?">
                        <i class="fas fa-times me-1"></i>Rejeitar
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var shell = document.getElementById('gpShell');
    var results = document.getElementById('gpWorklistResults');
    var workspace = document.getElementById('gpWorkspace');
    var emptyState = document.getElementById('gpWorkspaceEmpty');
    if (!shell || !results || !workspace) return;

    var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-guincho-id]'));
    var panels = Array.prototype.slice.call(workspace.querySelectorAll('.gp-detail-panel'));

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
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-guincho-id]');
        if (!item) return;
        selectGuincho(Number(item.dataset.guinchoId));
    });

    workspace.querySelectorAll('[data-action="gp-clear-selection"]').forEach(function (backLink) {
        backLink.addEventListener('click', function () { selectGuincho(null); });
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        buttons.forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('gpWorklistSearch');
    var topSearch = document.getElementById('gpWorklistSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (buttons.length > 0) {
        var semDocs = buttons.find(function (b) { return b.classList.contains('is-warning'); });
        selectGuincho(Number((semDocs || buttons[0]).dataset.guinchoId));
    }
})();
</script>

<?php
// Não usa layouts/footer.php: essa página usa .shell-ops (grid próprio),
// igual à Central Operacional e Capacidades — fechamento mínimo equivalente.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
