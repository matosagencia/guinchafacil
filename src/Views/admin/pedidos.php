<?php

require_once __DIR__ . '/../../Services/POR/PorThresholds.php';

$osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();

/**
 * Pedidos — padrão shell-ops.
 *
 * A fila vem paginada/filtrada no servidor (status/busca/data).
 * O workspace de detalhe reaproveita o mesmo módulo JS e a mesma API
 * de /admin/central:
 *
 *   AdminOrderWorkspace
 *   /api/admin/orders/{id}
 *
 * Esta view não duplica a lógica operacional.
 *
 * @var array $pedidos
 * @var array $worklist
 * @var int $total
 * @var int $totalPaginas
 * @var string $csrfToken
 */

$bp = defined('BASE_PATH') ? BASE_PATH : '';

include __DIR__ . '/../layouts/header.php';

$statusLabels = [
    'aguardando_pagamento' => 'Aguard. Pagamento',
    'aguardando_guincho'   => 'Aguard. Guincho',
    'a_caminho'            => 'A Caminho',
    'no_local'              => 'No Local',
    'em_reboque'            => 'Em Reboque',
    'concluido'             => 'Concluído',
    'cancelado'             => 'Cancelado',
];

$statusAtual = $_GET['status'] ?? '';
$buscaAtual  = $_GET['busca'] ?? '';
$dataAtual   = $_GET['data'] ?? '';

$paginaAtual = max(
    1,
    (int)($_GET['pagina'] ?? 1)
);

$totalPaginas = $totalPaginas ?? 1;

$resumoPedidos = $resumoPedidos ?? [];

/**
 * Normaliza a origem do funil para a apresentação.
 *
 * Idempotência:
 * - não modifica $worklist;
 * - sempre parte do mesmo campo contratual: origem_funil;
 * - valores desconhecidos permanecem neutros;
 * - evita lógica duplicada dentro do HTML.
 */
$getOrigemFunilBadge = static function ($origem): string {
    if ($origem === 'oficina') {
        return '<span class="badge bg-primary">oficina</span>';
    }

    if ($origem === 'especialista') {
        return '<span class="badge bg-secondary">especialista</span>';
    }

    return '<span class="text-muted">—</span>';
};

?>

<link
    rel="stylesheet"
    href="<?php echo htmlspecialchars(
        $bp,
        ENT_QUOTES,
        'UTF-8'
    ); ?>/public/assets/vendor/leaflet/leaflet.css"
>

<link
    rel="stylesheet"
    href="<?php echo htmlspecialchars(
        $bp,
        ENT_QUOTES,
        'UTF-8'
    ); ?>/public/assets/css/pages/admin-central-operacional.css"
>

<div
    class="ops-topbar"
    style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)"
>
    <form
        method="GET"
        action="<?php echo htmlspecialchars(
            $bp,
            ENT_QUOTES,
            'UTF-8'
        ); ?>/admin/pedidos"
        class="ops-topbar__search"
    >
        <i class="fas fa-magnifying-glass"></i>

        <input
            type="text"
            name="busca"
            value="<?php echo htmlspecialchars(
                $buscaAtual,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            placeholder="Buscar por nº do pedido, cliente, placa ou endereço"
            autocomplete="off"
        >

        <?php if ($statusAtual !== ''): ?>
            <input
                type="hidden"
                name="status"
                value="<?php echo htmlspecialchars(
                    $statusAtual,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        <?php endif; ?>

        <?php if ($dataAtual !== ''): ?>
            <input
                type="hidden"
                name="data"
                value="<?php echo htmlspecialchars(
                    $dataAtual,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >
        <?php endif; ?>
    </form>

    <div class="ops-topbar__meta">
        <span class="ops-topbar__status">
            <span class="ops-topbar__status-dot"></span>

            <?php echo (int)($total ?? 0); ?> pedidos

            <?php
            if (
                $statusAtual !== ''
                || $buscaAtual !== ''
                || $dataAtual !== ''
            ):
            ?>
                · filtrado
            <?php endif; ?>
        </span>

        <?php if (
            $statusAtual !== ''
            || $buscaAtual !== ''
            || $dataAtual !== ''
        ): ?>
            <a
                href="<?php echo htmlspecialchars(
                    $bp,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>/admin/pedidos"
                class="ops-dashboard-link"
            >
                <i class="fas fa-xmark me-1"></i>
                Limpar filtro
            </a>
        <?php endif; ?>

        <a
            href="<?php echo htmlspecialchars(
                $bp,
                ENT_QUOTES,
                'UTF-8'
            ); ?>/admin/pedido/novo/v2"
            class="ops-dashboard-link"
        >
            <i class="fas fa-plus me-1"></i>
            Novo Pedido
        </a>
    </div>
</div>

<section
    class="ops-summary"
    aria-label="Resumo de pedidos"
>
    <a
        href="<?php echo htmlspecialchars(
            $bp,
            ENT_QUOTES,
            'UTF-8'
        ); ?>/admin/pedidos?status=aguardando_guincho"
        style="text-decoration:none;"
    >
        <article
            class="ops-metric <?php
                echo (int)($resumoPedidos['aguardando_guincho'] ?? 0) > 0
                    ? 'is-warning'
                    : '';
            ?>"
        >
            <span class="ops-metric__label">
                Aguard. guincho<?php
                echo $statusAtual === 'aguardando_guincho'
                    ? ' · filtrando'
                    : '';
                ?>
            </span>

            <strong class="ops-metric__value">
                <?php echo (int)($resumoPedidos['aguardando_guincho'] ?? 0); ?>
            </strong>

            <span class="ops-metric__trend">
                Requer ação
            </span>
        </article>
    </a>

    <article class="ops-metric">
        <span class="ops-metric__label">
            Em atendimento
        </span>

        <strong class="ops-metric__value">
            <?php echo (int)($resumoPedidos['em_atendimento'] ?? 0); ?>
        </strong>

        <span class="ops-metric__trend">
            A caminho / no local / reboque
        </span>
    </article>

    <a
        href="<?php echo htmlspecialchars(
            $bp,
            ENT_QUOTES,
            'UTF-8'
        ); ?>/admin/pedidos?status=concluido&data=<?php echo date('Y-m-d'); ?>"
        style="text-decoration:none;"
    >
        <article class="ops-metric">
            <span class="ops-metric__label">
                Concluídos hoje
            </span>

            <strong class="ops-metric__value">
                <?php echo (int)($resumoPedidos['concluido_hoje'] ?? 0); ?>
            </strong>
        </article>
    </a>

    <a
        href="<?php echo htmlspecialchars(
            $bp,
            ENT_QUOTES,
            'UTF-8'
        ); ?>/admin/pedidos?status=cancelado"
        style="text-decoration:none;"
    >
        <article
            class="ops-metric <?php
                echo $statusAtual === 'cancelado'
                    ? 'is-warning'
                    : '';
            ?>"
        >
            <span class="ops-metric__label">
                Cancelados<?php
                echo $statusAtual === 'cancelado'
                    ? ' · filtrando'
                    : '';
                ?>
            </span>

            <strong class="ops-metric__value">
                <?php echo (int)($resumoPedidos['cancelado'] ?? 0); ?>
            </strong>
        </article>
    </a>
</section>

<div
    class="shell-ops"
    id="pedShell"
>
    <aside
        class="shell-ops-sidebar"
        id="pedSidebar"
    >
        <?php
        include __DIR__ . '/../components/admin_nav_operacional.php';
        ?>
    </aside>

    <section
        class="shell-ops-worklist"
        aria-label="Pedidos"
    >
        <header class="ops-worklist-header">
            <span class="eyebrow">
                Operação
            </span>

            <h2>
                Pedidos
            </h2>

            <p>
                <span id="pedWorklistCount">
                    <?php echo count($worklist ?? []); ?>
                </span>

                nesta página ·

                <?php echo (int)($total ?? 0); ?>

                no total
            </p>
        </header>

        <div
            class="d-flex gap-1 flex-wrap"
            style="padding:0 16px 10px;"
        >
            <a
                href="<?php echo htmlspecialchars(
                    $bp,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>/admin/pedidos"
                class="btn btn-sm <?php
                    echo $statusAtual === ''
                        ? 'btn-primary'
                        : 'btn-outline-secondary';
                ?>"
            >
                Todos
            </a>

            <?php foreach ($statusLabels as $val => $label): ?>
                <a
                    href="<?php echo htmlspecialchars(
                        $bp,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>/admin/pedidos?status=<?php echo urlencode($val); ?>"
                    class="btn btn-sm <?php
                        echo $statusAtual === $val
                            ? 'btn-primary'
                            : 'btn-outline-secondary';
                    ?>"
                >
                    <?php echo htmlspecialchars(
                        $label,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>

            <input
                type="search"
                id="pedWorklistSearch"
                placeholder="Filtrar nesta página"
                autocomplete="off"
            >
        </div>

        <div
            class="ops-worklist-results"
            id="pedWorklistResults"
        >
            <?php if (empty($worklist)): ?>

                <div class="ops-empty-state">
                    <i class="fas fa-circle-check"></i>
                    Nenhum pedido encontrado com este filtro.
                </div>

            <?php else: ?>

                <?php foreach ($worklist as $i => $w): ?>

                    <?php
                    /*
                     * Campo contratual único da origem:
                     *
                     *     origem_funil
                     *
                     * Não criamos fallback para outros campos.
                     *
                     * A normalização é somente de apresentação.
                     */
                    $origemFunil = $w['origem_funil'] ?? null;

                    $origemFunilBadge = $getOrigemFunilBadge(
                        $origemFunil
                    );
                    ?>

                    <button
                        type="button"
                        class="ops-worklist-item <?php
                            echo ($w['prioridade'] ?? '') === 'critical'
                                ? 'is-critical'
                                : (
                                    ($w['prioridade'] ?? '') === 'warning'
                                        ? 'is-warning'
                                        : ''
                                );
                        ?>"
                        data-order-id="<?php echo (int)($w['id'] ?? 0); ?>"
                        data-search-blob="<?php
                            echo htmlspecialchars(
                                mb_strtolower(
                                    ($w['codigo'] ?? '')
                                    . ' '
                                    . ($w['cliente_nome'] ?? '')
                                    . ' '
                                    . ($w['veiculo_resumo'] ?? ''),
                                    'UTF-8'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                    >
                        <span
                            class="ops-worklist-item__priority"
                            aria-hidden="true"
                        ></span>

                        <span class="ops-worklist-item__content">

                            <span class="ops-worklist-item__top">

                                <strong>
                                    <?php echo htmlspecialchars(
                                        $w['codigo'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </strong>

                                <?php
                                /*
                                 * Badge da origem do funil.
                                 *
                                 * A regra é centralizada em
                                 * $getOrigemFunilBadge() para impedir
                                 * divergência entre linhas.
                                 */
                                echo $origemFunilBadge;
                                ?>

                                <span
                                    class="ops-badge ops-badge--<?php
                                        echo htmlspecialchars(
                                            $w['status_css'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    ?>"
                                >
                                    <?php echo htmlspecialchars(
                                        $w['status_label'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </span>

                            <span class="ops-worklist-item__customer">
                                <?php echo htmlspecialchars(
                                    $w['cliente_nome'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </span>

                            <span class="ops-worklist-item__vehicle">
                                <?php echo htmlspecialchars(
                                    $w['veiculo_resumo'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </span>

                            <span class="ops-worklist-item__footer">

                                <span>
                                    <?php
                                    $guinchoOperador =
                                        $w['guincho_operador'] ?? null;

                                    echo htmlspecialchars(
                                        $guinchoOperador
                                            ? 'Prestador: ' . $guinchoOperador
                                            : 'Sem prestador atribuído',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </span>

                                <span>
                                    Há
                                    <?php echo (int)($w['minutos_decorridos'] ?? 0); ?>
                                    min
                                </span>

                            </span>

                        </span>

                        <span class="ops-worklist-item__signals">

                            <?php if (($w['prioridade'] ?? '') === 'warning'): ?>

                                <span
                                    class="ops-signal is-warning"
                                    title="Aguardando há mais de 15 min"
                                >
                                    <i class="fas fa-clock"></i>
                                </span>

                            <?php endif; ?>

                        </span>
                    </button>

                <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>

            <div
                class="d-flex flex-wrap gap-1 justify-content-center"
                style="padding:10px 16px;"
            >
                <?php for (
                    $i = 1;
                    $i <= $totalPaginas;
                    $i++
                ): ?>

                    <a
                        class="btn btn-sm <?php
                            echo $i === $paginaAtual
                                ? 'btn-primary'
                                : 'btn-outline-secondary';
                        ?>"
                        href="<?php echo htmlspecialchars(
                            $bp,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>/admin/pedidos?pagina=<?php echo $i; ?>&status=<?php echo urlencode($statusAtual); ?>&busca=<?php echo urlencode($buscaAtual); ?>&data=<?php echo urlencode($dataAtual); ?>"
                    >
                        <?php echo $i; ?>
                    </a>

                <?php endfor; ?>
            </div>

        <?php endif; ?>

    </section>

    <section
        class="shell-ops-workspace"
        id="pedWorkspace"
        aria-live="polite"
    >
        <?php if (empty($worklist)): ?>

            <div
                class="ops-empty-state"
                style="padding:80px 20px"
            >
                <i class="fas fa-inbox"></i>
                Nenhum pedido pra exibir.
            </div>

        <?php endif; ?>
    </section>
</div>

<script<?php echo csp_script_nonce_attr(); ?>
    src="<?php echo htmlspecialchars(
        $bp,
        ENT_QUOTES,
        'UTF-8'
    ); ?>/public/assets/vendor/leaflet/leaflet.js"
></script>

<script<?php echo csp_script_nonce_attr(); ?>
    src="<?php echo htmlspecialchars(
        $bp,
        ENT_QUOTES,
        'UTF-8'
    ); ?>/public/assets/js/admin-order-workspace.js?v=20260815-1"
></script>

<script<?php echo csp_script_nonce_attr(); ?>>
AdminOrderWorkspace.init({
    shellId: 'pedShell',
    resultsId: 'pedWorklistResults',
    workspaceId: 'pedWorkspace',
    worklistSearchId: 'pedWorklistSearch',

    apiBase: '<?php echo addslashes($bp); ?>/api/admin/orders',

    csrfToken: <?php
        echo json_encode($csrfToken);
    ?>,

    worklistData: <?php
        echo json_encode(
            $worklist ?? [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    ?>,

    osrmBaseUrl: <?php
        echo json_encode(
            $osrmBaseUrl,
            JSON_UNESCAPED_SLASHES
        );
    ?>,

    emptyLabel: 'Nenhum pedido selecionado.'
});
</script>

<?php
/*
 * Não usa layouts/footer.php:
 * esta página utiliza .shell-ops com grid próprio,
 * igual à Central Operacional, Ocorrências, Carteiras e Guinchos.
 */
?>

<script<?php echo csp_script_nonce_attr(); ?>
    src="<?php echo htmlspecialchars(
        $bp,
        ENT_QUOTES,
        'UTF-8'
    ); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"
></script>


<dialog id="modal-link-pagamento" class="gf-dlg" aria-labelledby="gf-dlg-title">
    <form method="dialog">
        <h3 id="gf-dlg-title">Link de pagamento</h3>
        <p class="gf-dlg-sub" id="gf-dlg-sub"></p>
        <div class="gf-dlg-row">
            <input type="text" id="gf-dlg-url" readonly>
            <button type="button" class="gf-dlg-btn" id="gf-dlg-copy">Copiar</button>
        </div>
        <div class="gf-dlg-actions">
            <a id="gf-dlg-wa" class="gf-dlg-btn gf-dlg-btn-wa" href="#" target="_blank" rel="noopener">Enviar no WhatsApp</a>
            <button type="submit" class="gf-dlg-btn gf-dlg-btn-close">Fechar</button>
        </div>
    </form>
</dialog>

<style>
dialog.gf-dlg{border:0;border-radius:12px;padding:0;max-width:560px;width:92vw;box-shadow:0 12px 40px rgba(0,0,0,.25)}
dialog.gf-dlg::backdrop{background:rgba(0,0,0,.45)}
dialog.gf-dlg form{padding:22px 24px;display:flex;flex-direction:column;gap:12px;margin:0}
dialog.gf-dlg h3{margin:0;font-size:1.15rem}
.gf-dlg-sub{margin:0;font-size:.85rem;color:#5b6a76}
.gf-dlg-row{display:flex;gap:8px}
.gf-dlg-row input{flex:1;padding:9px 11px;border:1px solid #cfd6de;border-radius:8px;font:inherit;background:#f8fafb;color:#111}
.gf-dlg-btn{padding:9px 16px;border-radius:8px;border:1px solid #cfd6de;background:#fff;cursor:pointer;font:inherit;font-weight:600;text-decoration:none;color:#111}
.gf-dlg-btn:hover{border-color:#2fb34a;color:#1f7a34}
.gf-dlg-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:4px}
.gf-dlg-btn-wa{background:#25d366;border-color:#25d366;color:#fff}
.gf-dlg-btn-wa:hover{background:#1eb257;border-color:#1eb257;color:#fff}
.gf-dlg-btn-close{background:#2fb34a;border-color:#2fb34a;color:#fff}
.gf-dlg-btn-close:hover{background:#248f3a;border-color:#248f3a;color:#fff}
</style>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    'use strict';
    <?php if (!empty($_SESSION['admin_link_pagamento_gerado'])):
        $lf = $_SESSION['admin_link_pagamento_gerado'];
        unset($_SESSION['admin_link_pagamento_gerado']);
    ?>
    var GF_LINK = <?php echo json_encode([
        'pedido_id' => (int)($lf['pedido_id'] ?? 0),
        'link'      => (string)($lf['link'] ?? ''),
        'externo'   => (bool)($lf['externo'] ?? false),
        'provedor'  => (string)($lf['provedor'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        var dlg = document.getElementById('modal-link-pagamento');
        if (!dlg || !GF_LINK.link) return;

        var urlEl = document.getElementById('gf-dlg-url');
        var subEl = document.getElementById('gf-dlg-sub');
        var waEl  = document.getElementById('gf-dlg-wa');
        var copyBtn = document.getElementById('gf-dlg-copy');

        urlEl.value = GF_LINK.link;
        subEl.textContent = GF_LINK.externo
            ? 'Pedido #' + GF_LINK.pedido_id + ' - link do gateway ' + (GF_LINK.provedor || '') + '.'
            : 'Pedido #' + GF_LINK.pedido_id + ' - link interno. O cliente precisa entrar na conta dele para pagar.';

        waEl.href = 'https://wa.me/?text=' + encodeURIComponent(
            'Pedido #' + GF_LINK.pedido_id + ' - pague aqui: ' + GF_LINK.link
        );

        copyBtn.addEventListener('click', function () {
            urlEl.select();
            urlEl.setSelectionRange(0, 99999);
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            if (navigator.clipboard && !ok) {
                try { navigator.clipboard.writeText(GF_LINK.link); ok = true; } catch (e2) {}
            }
            copyBtn.textContent = ok ? 'Copiado!' : 'Falha';
            setTimeout(function () { copyBtn.textContent = 'Copiar'; }, 1500);
        });

        dlg.showModal();
    });
    <?php endif; ?>
})();
</script>
</body>
</html>
