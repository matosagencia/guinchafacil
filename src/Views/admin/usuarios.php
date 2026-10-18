<?php

/**

 * Usuários — reestruturada pro padrão shell-ops (mesma arquitetura de

 * /admin/central, /admin/pedidos, /admin/guinchos...). A lista paginada

 * server-side vira a worklist clicável; o detalhe de cada usuário é

 * buscado via fetch() ao endpoint /admin/usuario-fragmento/{id}, que

 * devolve {ok, html} com o mesmo partial usado antes em usuariodetalhe.php.

 *

 * @var array $usuarios

 * @var int $total

 * @var array $resumoUsuarios

 * @var string $csrfToken

 */

$bp = defined('BASE_PATH') ? BASE_PATH : '';

include __DIR__ . '/../layouts/header.php';

$buscaAtual  = $_GET['busca'] ?? '';

$tipoAtual   = $_GET['tipo']  ?? '';

$ativoAtual  = $_GET['ativo'] ?? '';

$suspensosAtivo = $ativoAtual === '0';

$paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));

$totalPaginas = (int)ceil(($total ?? 0) / 20);
if (!function_exists('gfF18f19Count')) {
    function gfF18f19Count(int $n): string {
        if ($n < 1000) return (string)$n;
        if ($n < 1000000) {
            $v = $n / 1000;
            return str_replace(',0', '', number_format($v, $v < 10 ? 1 : 0, ',', '.')) . ' mil';
        }
        $v = $n / 1000000;
        return str_replace(',0', '', number_format($v, $v < 10 ? 1 : 0, ',', '.')) . ' mi';
    }
}


?>


<link rel="stylesheet" href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/public/assets/css/pages/gf-f18f19-shell-unificado.css?v=20261018-2">
<script>document.body.classList.add("gf-f18f19");</script>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">
<!-- GF-F18F15-REPAIRED -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/css/pages/gf-f18f14b-acabamento.css?v=20261018-f15">

<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-usuarios.css">
<!-- GF-F18F12-ASSET -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/css/pages/gf-f18f12-pessoas-estrutura.css?v=20261018-1">
<!-- GF-F18F14-ASSET -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/css/pages/gf-f18f14-acabamento.css?v=20261018-1">
<!-- /GF-F18F14-ASSET -->
<!-- /GF-F18F12-ASSET -->
<!-- GF-F18F12-SCOPE -->
<script>document.body.classList.add("gf-f18f12");</script>
<!-- /GF-F18F12-SCOPE -->




<!-- GF-F18F13: busca global e indicadores redundantes removidos -->




<?php if (!empty($_GET['criado'])): ?>

<div class="alert alert-success" style="margin:16px 24px 0;"><i class="fas fa-check-circle me-2"></i>Usuário criado com sucesso!</div>

<?php endif; ?>

<?php if (!empty($_GET['msg'])): ?>

<div class="alert alert-info" style="margin:16px 24px 0;">

    <?php

    $msgs = ['ativado'=>'Usuário reativado.','suspenso'=>'Usuário suspenso.'];

    echo htmlspecialchars($msgs[$_GET['msg']] ?? $_GET['msg']);

    ?>

</div>

<?php endif; ?>




<link rel="stylesheet" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/css/pages/gf-f18f18-sidebar-cadastros.css?v=20261018-1">
<script>document.body.classList.add("gf-f18f18");</script>
<!-- GF-F18F1-NAV -->
<!-- /GF-F18F1-NAV -->
<div class="shell-ops" id="usuariosShell">



    <aside class="shell-ops-sidebar gf-f18f18-sidebar" id="usuariosSidebar">

        <div class="gf-f18f18-brand"><strong>Guincha<span>Fácil.</span></strong><small>Central GuinchaFácil</small></div>
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>

    </aside>



    <section class="shell-ops-worklist" aria-label="Usuários">

        
<?php $gfF18f1Tab = $tipoAtual === 'oficina' ? 'oficinas' : ($tipoAtual === 'guincho' ? 'guinchos' : 'clientes'); include __DIR__ . '/../components/gf_f18f1_pessoas_tabs.php'; ?>
<header class="ops-worklist-header">
<div class="gf-f18f13-worklist-actions"></div>

            <span class="eyebrow">Pessoas</span>

            <h2>Usuários</h2>

            <p><span id="usuariosWorklistCount"><?php echo count($usuarios ?? []); ?></span> nesta página</p>

        </header>



        <div class="d-flex gap-1 flex-wrap" style="padding:0 16px 10px;">

            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios" class="btn btn-sm <?php echo ($tipoAtual === '' && !$suspensosAtivo) ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todos</a>

            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios?tipo=cliente" class="btn btn-sm <?php echo $tipoAtual === 'cliente' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Clientes <span class="gf-f18f19-count" title="<?php echo (int)($resumoUsuarios['clientes'] ?? 0); ?>">(<?php echo htmlspecialchars(gfF18f19Count((int)($resumoUsuarios['clientes'] ?? 0))); ?>)</span></a>

            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios?tipo=guincho" class="btn btn-sm <?php echo $tipoAtual === 'guincho' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Guinchos <span class="gf-f18f19-count" title="<?php echo (int)($resumoUsuarios['guinchos'] ?? 0); ?>">(<?php echo htmlspecialchars(gfF18f19Count((int)($resumoUsuarios['guinchos'] ?? 0))); ?>)</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios?tipo=oficina" class="btn btn-sm <?php echo $tipoAtual === 'oficina' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Oficinas <span class="gf-f18f19-count" title="<?php echo (int)($resumoUsuarios['oficinas'] ?? 0); ?>">(<?php echo htmlspecialchars(gfF18f19Count((int)($resumoUsuarios['oficinas'] ?? 0))); ?>)</span></a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios?tipo=admin" class="btn btn-sm <?php echo $tipoAtual === 'admin' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Administradores</a>

            <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios?ativo=0" class="btn btn-sm <?php echo $suspensosAtivo ? 'btn-primary' : 'btn-outline-secondary'; ?>">Suspensos</a>

        </div>



        <div class="ops-worklist-search">

            <i class="fas fa-magnifying-glass"></i>

            <input type="search" id="usuariosWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">

        </div>



        <div class="ops-worklist-results" id="usuariosWorklistResults">

            <?php if (empty($usuarios)): ?>

                <div class="ops-empty-state">

                    <i class="fas fa-users-slash"></i>

                    Nenhum usuário encontrado.

                </div>

            <?php else: foreach ($usuarios as $u):

                $busca = strtolower(($u['nome'] ?? '') . ' ' . ($u['email'] ?? '') . ' ' . ($u['telefone'] ?? ''));

                $suspenso = empty($u['ativo']);

            ?>

                <button type="button"

                    class="ops-worklist-item <?php echo $suspenso ? 'is-warning' : ''; ?>"

                    data-usuario-id="<?php echo (int)$u['id']; ?>"

                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"

                    aria-selected="false"

                >

                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>

                    <span class="ops-worklist-item__content">

                        <span class="ops-worklist-item__top">

                            <strong><?php echo htmlspecialchars($u['nome']); ?></strong>

                            <span class="ops-badge ops-badge--<?php echo $suspenso ? 'critical' : 'audit'; ?>"><?php echo $suspenso ? 'Suspenso' : ucfirst($u['tipo']); ?></span>

                        </span>

                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($u['email']); ?></span>

                        <span class="ops-worklist-item__footer">

                            <span>#<?php echo (int)$u['id']; ?></span>

                            <span><?php echo htmlspecialchars($u['telefone'] ?? '—'); ?></span>

                        </span>

                    </span>

                    <span class="ops-worklist-item__signals">

                        <?php if ($suspenso): ?>

                        <span class="ops-signal is-danger" title="Suspenso"><i class="fas fa-ban"></i></span>

                        <?php endif; ?>

                    </span>

                </button>

            <?php endforeach; endif; ?>

        </div>



        <?php if ($totalPaginas > 1): ?>

        <nav style="padding:10px 16px;">

            <ul class="pagination pagination-sm justify-content-center mb-0">

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>

                <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">

                    <a class="page-link"

                       href="<?php echo $bp; ?>/admin/usuarios?pagina=<?php echo $i; ?>&tipo=<?php echo urlencode($tipoAtual); ?>&busca=<?php echo urlencode($buscaAtual); ?><?php echo $suspensosAtivo ? '&ativo=0' : ''; ?>">

                        <?php echo $i; ?>

                    </a>

                </li>

                <?php endfor; ?>

            </ul>

        </nav>

        <?php endif; ?>

    </section>



    <section class="shell-ops-workspace" id="usuariosWorkspace" aria-live="polite">


        <?php if (empty($usuarios)): ?>

        <div class="ops-empty-state" style="padding:80px 20px">

            <i class="fas fa-inbox"></i>

            Nenhum usuário pra exibir.

        </div>

        <?php else: ?>

        <div class="ops-empty-state" style="padding:80px 20px">

            <i class="fas fa-circle-notch fa-spin"></i>

            Carregando…

        </div>

        <?php endif; ?>

    </section>



</div>



<script<?php echo csp_script_nonce_attr(); ?>>

(function () {

    var shell = document.getElementById('usuariosShell');

    var results = document.getElementById('usuariosWorklistResults');

    var workspace = document.getElementById('usuariosWorkspace');

    if (!shell || !results || !workspace) return;



    var buttons = Array.prototype.slice.call(results.querySelectorAll('[data-usuario-id]'));

    var cache = {};

    var loadToken = 0;

    var BP = '<?php echo addslashes($bp); ?>';



    function escapeHtml(value) {

        var el = document.createElement('div');

        el.textContent = String(value == null ? '' : value);

        return el.innerHTML;

    }



    function renderSkeleton() {

        workspace.innerHTML = '<div class="ops-empty-state" style="padding:60px 20px"><i class="fas fa-circle-notch fa-spin"></i>Carregando…</div>';

    }



    function renderError(message) {

        workspace.innerHTML = '<div class="ops-empty-state" style="padding:60px 20px"><i class="fas fa-triangle-exclamation"></i>' + escapeHtml(message) + '</div>';

    }



    function wireBackLink() {

        var back = workspace.querySelector('[data-action="us-clear-selection"]');

        if (back) back.addEventListener('click', function () { selectUsuario(null); });

    }



    async function loadDetail(usuarioId) {

        var myToken = ++loadToken;

        renderSkeleton();

        try {

            if (!cache[usuarioId]) {

                var res = await fetch(BP + '/admin/usuario-fragmento/' + usuarioId, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

                var body = await res.json();

                if (!res.ok || !body.ok) throw new Error(body.erro || ('HTTP ' + res.status));

                cache[usuarioId] = body.html;

            }

            if (myToken !== loadToken) return;

            workspace.innerHTML = cache[usuarioId];

            wireBackLink();

        } catch (err) {

            if (myToken === loadToken) renderError('Falha ao carregar detalhe: ' + err.message);

        }

    }



    function selectUsuario(usuarioId) {

        buttons.forEach(function (btn) {

            btn.setAttribute('aria-selected', String(Number(btn.dataset.usuarioId) === usuarioId));

        });

        if (!usuarioId) {

            workspace.innerHTML = '<div class="ops-empty-state" style="padding:80px 20px"><i class="fas fa-hand-pointer"></i>Selecione um usuário na lista ao lado.</div>';

        } else {

            loadDetail(usuarioId);

        }

        if (window.matchMedia('(max-width: 767px)').matches) {

            shell.classList.toggle('has-selection', !!usuarioId);

        }

    }



    results.addEventListener('click', function (event) {

        var item = event.target.closest('[data-usuario-id]');

        if (!item) return;

        selectUsuario(Number(item.dataset.usuarioId));

    });



    function applyFilter(term) {

        var t = term.trim().toLowerCase();

        buttons.forEach(function (btn) {

            var blob = btn.dataset.searchBlob || '';

            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';

        });

    }



    var worklistSearch = document.getElementById('usuariosWorklistSearch');

    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });



    // Reabre no mesmo usuário vindo do link antigo /admin/usuario/{id}

    // (agora um redirect ?usuario_id=X), do retorno_usuario_id pós-ação,

    // ou seleciona o primeiro da lista.

    var paramUsuario = Number(new URLSearchParams(window.location.search).get('usuario_id'));

    if (paramUsuario && buttons.some(function (b) { return Number(b.dataset.usuarioId) === paramUsuario; })) {

        selectUsuario(paramUsuario);

    } else if (buttons.length > 0) {

        selectUsuario(Number(buttons[0].dataset.usuarioId));

    }

})();

</script>



<?php

// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),

// igual à Central Operacional, Pedidos, Despacho, Alertas, Guinchos...

?>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>

