<?php
/**
 * Despacho manual (Pacote L2.3) — reestruturada pro padrão shell-ops (mesma
 * arquitetura de /admin/central, /admin/pedidos, /admin/ocorrencias...).
 * Reaproveita 100% a action existente de atribuição
 * (AdminController::pedidoAtribuir / PedidoTransitionService::assignByAdmin)
 * e o cadastro real de guinchos aprovados/disponíveis (Guincho::listarAprovados).
 * Seleção de pedido continua via ?pedido_id= (reload de página, não AJAX) —
 * o workspace depende de cálculo de distância/elegibilidade feito no
 * servidor por pedido, então recarregar é mais simples e não duplica essa
 * lógica em JS.
 *
 * @var array $filas
 * @var array|null $pedidoSelecionado
 * @var array $prestadores
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$flashMessages = $_SESSION['_flash'] ?? [];
if (isset($flashMessages['message'])) $flashMessages = [$flashMessages];
unset($_SESSION['_flash']);
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="despachoBusca" placeholder="Buscar na fila por cliente ou endereço" autocomplete="off" aria-label="Buscar na fila de despacho"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo count($filas); ?> na fila</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/central" class="ops-dashboard-link"><i class="fas fa-tower-broadcast me-1"></i>Central Operacional</a>
    </div>
</div>

<?php foreach ($flashMessages as $flash): ?>
<div class="alert alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?>" style="margin:16px 24px 0;"><i class="fas fa-<?php echo ($flash['type'] ?? '') === 'success' ? 'check-circle' : 'triangle-exclamation'; ?> me-2"></i><?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?></div>
<?php endforeach; ?>

<section class="ops-summary" aria-label="Resumo de despacho">
    <article class="ops-metric <?php echo count($filas) > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Aguardando prestador</span>
        <strong class="ops-metric__value"><?php echo count($filas); ?></strong>
    </article>
    <article class="ops-metric <?php echo ($pedidoSelecionado && count($prestadores) === 0) ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Prestadores elegíveis (pedido selecionado)</span>
        <strong class="ops-metric__value"><?php echo $pedidoSelecionado ? count($prestadores) : '—'; ?></strong>
        <?php if ($pedidoSelecionado && count($prestadores) === 0): ?><span class="ops-metric__trend">Sem opção — requer ação</span><?php endif; ?>
    </article>
</section>

<div class="shell-ops" id="despachoShell">

    <aside class="shell-ops-sidebar" id="despachoSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Fila aguardando prestador">
        <header class="ops-worklist-header">
            <span class="eyebrow">Operação</span>
            <h2>Despacho</h2>
            <p><span id="despachoWorklistCount"><?php echo count($filas); ?></span> pedido(s) aguardando prestador</p>
        </header>

        <div class="ops-worklist-results" id="despachoFilaLista">
            <?php if (empty($filas)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum pedido aguardando prestador no momento.
                </div>
            <?php endif; ?>
            <?php foreach ($filas as $f): ?>
                <?php $ativo = $pedidoSelecionado && (int)$pedidoSelecionado['id'] === (int)$f['id']; ?>
                <?php $textoBusca = strtolower(($f['cliente_nome'] ?? '') . ' ' . ($f['endereco_origem'] ?? '')); ?>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/despacho?pedido_id=<?php echo (int)$f['id']; ?>"
                   class="ops-worklist-item"
                   aria-selected="<?php echo $ativo ? 'true' : 'false'; ?>"
                   data-search="<?php echo htmlspecialchars($textoBusca, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong>#<?php echo (int)$f['id']; ?> — <?php echo htmlspecialchars((string)($f['cliente_nome'] ?? '')); ?></strong>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars((string)($f['endereco_origem'] ?? '—')); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars((string)($f['criado_em'] ?? '')); ?></span>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="despachoWorkspace" aria-live="polite">
        <?php if (!$pedidoSelecionado): ?>
            <div class="ops-empty-state" style="padding:80px 20px">
                <i class="fas fa-hand-pointer"></i>
                Selecione um pedido na fila para ver os prestadores disponíveis.
            </div>
        <?php else: ?>
            <header class="ops-order-header">
                <div>
                    <h1>Pedido #<?php echo (int)$pedidoSelecionado['id']; ?></h1>
                    <p><?php echo htmlspecialchars((string)($pedidoSelecionado['cliente_nome'] ?? '—')); ?></p>
                </div>
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/<?php echo (int)$pedidoSelecionado['id']; ?>" class="ops-btn">
                    <i class="fas fa-eye"></i> Ver pedido completo
                </a>
            </header>

            <div class="ops-order-facts">
                <div class="ops-order-fact"><span class="ops-order-fact__label">Cliente</span><span class="ops-order-fact__value"><?php echo htmlspecialchars((string)($pedidoSelecionado['cliente_nome'] ?? '—')); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Veículo</span><span class="ops-order-fact__value"><?php echo htmlspecialchars(trim(($pedidoSelecionado['marca'] ?? '') . ' ' . ($pedidoSelecionado['modelo'] ?? '') . ' · ' . ($pedidoSelecionado['placa'] ?? ''))); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Origem</span><span class="ops-order-fact__value"><?php echo htmlspecialchars((string)($pedidoSelecionado['endereco_origem'] ?? '—')); ?></span></div>
                <div class="ops-order-fact"><span class="ops-order-fact__label">Destino</span><span class="ops-order-fact__value"><?php echo htmlspecialchars((string)($pedidoSelecionado['endereco_destino'] ?? '—')); ?></span></div>
            </div>

            <div style="padding:0 24px 32px">
                <div class="card">
                    <div class="card-header"><strong>Prestadores disponíveis (por distância)</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <div class="despacho-assign-toolbar">
                                <label for="despachoGuinchoSelect" class="form-label mb-0">Localizar guincho para atribuir</label>
                                <div class="despacho-assign-toolbar__controls">
                                    <select id="despachoGuinchoSelect" class="form-select form-select-sm">
                                        <option value="">Todos os guinchos disponiveis</option>
                                        <?php foreach ($prestadores as $pr): ?>
                                        <option value="<?php echo (int)$pr['id']; ?>"><?php echo htmlspecialchars((string)($pr['nome_operador'] ?? 'Guincho')); ?> · #<?php echo (int)$pr['id']; ?><?php echo !empty($pr['placa_guincho']) ? ' · ' . htmlspecialchars((string)$pr['placa_guincho']) : ''; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="despachoAtribuirSelecionado" class="btn btn-sm btn-primary" disabled><i class="fas fa-truck-fast me-1"></i>Atribuir selecionado</button>
                                </div>
                                <small class="text-muted">Digite no seletor para localizar por nome, ID ou placa.</small>
                            </div>
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Prestador</th>
                                        <th>Placa</th>
                                        <th>Reputação</th>
                                        <th>Distância</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($prestadores)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">Nenhum prestador aprovado/disponível no momento.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($prestadores as $pr): ?>
                                        <tr data-despacho-guincho="<?php echo (int)$pr['id']; ?>">
                                            <td><?php echo htmlspecialchars((string)($pr['nome_operador'] ?? '—')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($pr['placa_guincho'] ?? '—')); ?></td>
                                            <td><?php echo number_format((float)($pr['reputacao'] ?? 0), 1); ?></td>
                                            <td>
                                                <?php if ($pr['distancia_km'] !== null): ?>
                                                    <?php echo number_format((float)$pr['distancia_km'], 1); ?> km
                                                <?php else: ?>
                                                    <span class="text-muted">sem GPS</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/pedido/atribuir" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="pedido_id" value="<?php echo (int)$pedidoSelecionado['id']; ?>">
                                                    <input type="hidden" name="guincho_id" value="<?php echo (int)$pr['id']; ?>">
                                                    <input type="hidden" name="retorno" value="despacho">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-truck-fast me-1"></i>Atribuir
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var select = document.getElementById('despachoGuinchoSelect');
    var action = document.getElementById('despachoAtribuirSelecionado');
    if (!select || !action) return;
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-despacho-guincho]'));
    function sync() {
        var id = select.value;
        rows.forEach(function (row) {
            var match = !id || row.getAttribute('data-despacho-guincho') === id;
            row.hidden = !match;
            row.classList.toggle('table-primary', Boolean(id && match));
        });
        action.disabled = !id;
    }
    select.addEventListener('change', sync);
    action.addEventListener('click', function () {
        if (!select.value) return;
        var row = document.querySelector('[data-despacho-guincho="' + select.value + '"]');
        var form = row ? row.querySelector('form') : null;
        if (form && typeof form.requestSubmit === 'function') form.requestSubmit();
        else if (form) form.submit();
    });
})();
</script>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    var busca = document.getElementById('despachoBusca');
    var itens = Array.prototype.slice.call(document.querySelectorAll('#despachoFilaLista [data-search]'));
    if (!busca) return;
    busca.addEventListener('input', function () {
        var query = busca.value.trim().toLowerCase();
        itens.forEach(function (item) {
            item.hidden = Boolean(query) && (item.getAttribute('data-search') || '').indexOf(query) < 0;
        });
    });
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Pedidos, Ocorrências, Carteiras e Guinchos.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
