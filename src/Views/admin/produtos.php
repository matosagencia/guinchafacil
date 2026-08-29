<?php
/**
 * Produtos — reestruturada pro padrão shell-ops. O catálogo já vem inteiro
 * do servidor, por isso o workspace é preenchido via JS a partir de um
 * JSON embutido (window.__produtosData), sem round-trip extra.
 *
 * @var array $produtos
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totalProdutos = count($produtos ?? []);
$produtosAtivos = count(array_filter($produtos ?? [], static fn($p) => !empty($p['active'])));
$produtosSemPreco = count(array_filter($produtos ?? [], static fn($p) => $p['preco_referencia'] === null));

$produtosPayload = [];
foreach (($produtos ?? []) as $p) {
    $produtosPayload[] = [
        'id' => (int)$p['id'],
        'sku' => (string)$p['sku'],
        'nome' => (string)$p['nome'],
        'categoria' => (string)$p['categoria'],
        'especificacao' => (string)($p['especificacao'] ?? ''),
        'preco_referencia' => $p['preco_referencia'] !== null ? (float)$p['preco_referencia'] : null,
        'active' => !empty($p['active']),
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="produtosSearchTop" placeholder="Buscar por SKU, nome ou categoria" autocomplete="off" aria-label="Buscar produtos"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo $totalProdutos; ?> produtos</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/produto/novo" class="ops-dashboard-link"><i class="fas fa-circle-plus me-1"></i>Novo produto</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo do catálogo de produtos">
    <article class="ops-metric">
        <span class="ops-metric__label">Total</span>
        <strong class="ops-metric__value"><?php echo $totalProdutos; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Ativos</span>
        <strong class="ops-metric__value"><?php echo $produtosAtivos; ?></strong>
    </article>
    <article class="ops-metric <?php echo $produtosSemPreco > 0 ? 'is-warning' : ''; ?>">
        <span class="ops-metric__label">Sem preço de referência</span>
        <strong class="ops-metric__value"><?php echo $produtosSemPreco; ?></strong>
    </article>
</section>

<?php $flash = $flash ?? null; if (!empty($flash)): ?>
<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>" style="margin:0 24px 16px;">
    <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div class="shell-ops" id="produtosShell">

    <aside class="shell-ops-sidebar" id="produtosSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Catálogo de produtos">
        <header class="ops-worklist-header">
            <span class="eyebrow">Estoque</span>
            <h2>Produtos</h2>
            <p><span id="produtosWorklistCount"><?php echo count($produtosPayload); ?></span> produto(s)</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="produtosWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="produtosWorklistResults">
            <?php if (empty($produtosPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-box"></i>
                    Nenhum produto cadastrado.
                </div>
            <?php else: foreach ($produtosPayload as $i => $p):
                $busca = strtolower($p['sku'] . ' ' . $p['nome'] . ' ' . $p['categoria']);
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo !$p['active'] ? 'is-warning' : ''; ?>"
                    data-produto-id="<?php echo (int)$i; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong><?php echo htmlspecialchars($p['nome']); ?></strong>
                            <span class="ops-badge <?php echo $p['active'] ? 'ops-badge--service' : 'ops-badge--audit'; ?>"><?php echo $p['active'] ? 'Ativo' : 'Inativo'; ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><code><?php echo htmlspecialchars($p['sku']); ?></code> · <?php echo htmlspecialchars($p['categoria']); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo $p['preco_referencia'] !== null ? 'R$ ' . number_format($p['preco_referencia'], 2, ',', '.') : 'sem preço ref.'; ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($p['preco_referencia'] === null): ?>
                        <span class="ops-signal is-warning" title="Sem preço de referência"><i class="fas fa-tag"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>
    </section>

    <section class="shell-ops-workspace" id="produtosWorkspace" aria-live="polite">
        <?php if (empty($produtosPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum produto pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__produtosData = <?php echo json_encode($produtosPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var PROD_BP = '<?php echo addslashes($bp); ?>';

(function () {
    var produtos = window.__produtosData || [];
    var shell = document.getElementById('produtosShell');
    var results = document.getElementById('produtosWorklistResults');
    var workspace = document.getElementById('produtosWorkspace');
    if (!shell || !results || !workspace) return;

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function money(v) {
        return v === null ? '—' : 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderProduto(produtoId) {
        var p = produtos[produtoId];
        if (!p) return;

        var html = '<header class="ops-order-header">' +
            '<div><h1>' + escapeHtml(p.nome) + '</h1>' +
            '<p><code>' + escapeHtml(p.sku) + '</code></p></div>' +
            '<span class="ops-badge ' + (p.active ? 'ops-badge--service' : 'ops-badge--audit') + '">' + (p.active ? 'Ativo' : 'Inativo') + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 12px">' +
            '<a class="ops-btn" href="' + PROD_BP + '/admin/produto/novo?id=' + p.id + '"><i class="fas fa-pen"></i> Editar</a>' +
            '</div>';

        html += '<div style="padding:0 24px 32px">';
        html += '<div class="card"><div class="card-header"><i class="fas fa-box me-2"></i>Detalhes</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--35">Categoria</td><td>' + escapeHtml(p.categoria) + '</td></tr>';
        html += '<tr><td class="ghd-label">Especificação</td><td>' + (p.especificacao ? escapeHtml(p.especificacao) : '—') + '</td></tr>';
        html += '<tr><td class="ghd-label">Preço de referência</td><td>' + money(p.preco_referencia) + '</td></tr>';
        html += '</table></div></div>';
        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectProduto(produtoId) {
        results.querySelectorAll('[data-produto-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.produtoId) === produtoId));
        });
        renderProduto(produtoId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-produto-id]');
        if (!item) return;
        selectProduto(Number(item.dataset.produtoId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-produto-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('produtosWorklistSearch');
    var topSearch = document.getElementById('produtosSearchTop');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });
    if (topSearch) topSearch.addEventListener('input', function (e) {
        if (worklistSearch) worklistSearch.value = e.target.value;
        applyFilter(e.target.value);
    });

    if (produtos.length > 0) selectProduto(0);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Documentos, Saques, Tipos de Serviço...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
