<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();

require_once __DIR__ . '/../components/vehicle_brand_badge.php';

$marcas = $marcas ?? [];
$contagemModelos = $contagemModelos ?? [];
$totalMarcas = count($marcas);
$marcasAtivas = count(array_filter($marcas, static fn($m) => !empty($m['active'])));
$totalModelos = array_sum($contagemModelos);
$catalogoMidiaUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/public/assets/vehicle-catalog/midia/logos/';
$catalogoMidiaDir = dirname(__DIR__, 3) . '/public/assets/vehicle-catalog/midia/logos/';
$slugMarca = static function (string $nome): string {
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: $nome;
    return trim((string)preg_replace('/[^a-z0-9]+/i', '-', strtolower($nome)), '-');
};
$gruposAlfabeticos = [];
foreach ($marcas as $marcaItem) {
    $letra = strtoupper(mb_substr(trim((string)($marcaItem['name'] ?? '#')), 0, 1, 'UTF-8'));
    $gruposAlfabeticos[$letra][] = $marcaItem;
}
ksort($gruposAlfabeticos, SORT_NATURAL | SORT_FLAG_CASE);
?>
    <div class="bo-page vehicle-catalog-page">

    <div class="bo-toolbar">
        <div class="bo-search"><input type="search" id="marcasBusca" placeholder="Buscar marca" autocomplete="off" aria-label="Buscar marca"></div>
        <div class="bo-toolbar-meta">
            <span class="bo-toolbar-status"><span class="bo-toolbar-status-dot"></span><?php echo $totalMarcas; ?> marcas</span>
        </div>
    </div>

    <header class="page-head vehicle-catalog-hero mb-3">
        <div>
            <span class="eyebrow">Catálogo de veículos</span>
            <h1>Marcas e modelos</h1>
            <p>Biblioteca visual usada pelo cliente pra selecionar o veículo dele no cadastro — marca → modelo → versão. Cadastre aos poucos; o cliente sempre pode digitar manualmente quando a marca/modelo dele ainda não estiver aqui.</p>
        </div>
        <div class="vehicle-catalog-hero__actions">
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/novo" class="btn btn-primary">+ Nova marca</a>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/novo" class="btn btn-outline-primary">+ Novo modelo</a>
        </div>
    </header>

    <section class="ops-summary mb-4" aria-label="Resumo do catálogo de veículos">
        <article class="ops-metric">
            <span class="ops-metric__label">Marcas cadastradas</span>
            <strong class="ops-metric__value"><?php echo $totalMarcas; ?></strong>
        </article>
        <article class="ops-metric">
            <span class="ops-metric__label">Ativas</span>
            <strong class="ops-metric__value"><?php echo $marcasAtivas; ?></strong>
        </article>
        <article class="ops-metric">
            <span class="ops-metric__label">Modelos cadastrados</span>
            <strong class="ops-metric__value"><?php echo $totalModelos; ?></strong>
        </article>
    </section>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3 vehicle-catalog-legacy-action">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/novo" class="btn btn-primary">
            Nova marca
        </a>
    </div>

    <nav class="vehicle-catalog-index" aria-label="Índice alfabético de marcas">
        <?php foreach (array_keys($gruposAlfabeticos) as $letra): ?><a href="#marca-<?php echo htmlspecialchars($letra); ?>"><?php echo htmlspecialchars($letra); ?></a><?php endforeach; ?>
    </nav>
    <div id="marcasGrid" class="vehicle-catalog-directory">
        <?php if (empty($marcas)): ?>
        <div class="col-12">
            <div class="vehicle-catalog-empty">Nenhuma marca cadastrada ainda.</div>
        </div>
        <?php else: foreach ($gruposAlfabeticos as $letra => $marcasDoGrupo): ?>
        <section class="vehicle-catalog-letter-group" id="marca-<?php echo htmlspecialchars($letra); ?>">
            <h2 class="vehicle-catalog-letter"><span><?php echo htmlspecialchars($letra); ?></span><span class="vehicle-catalog-letter__count"><?php echo count($marcasDoGrupo); ?> marcas</span></h2>
            <div class="row g-3">
        <?php foreach ($marcasDoGrupo as $m): $qtdModelos = $contagemModelos[(int)$m['id']] ?? 0; ?>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2" data-search="<?php echo htmlspecialchars(strtolower($m['name']), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card vehicle-catalog-card vehicle-brand-card <?php echo empty($m['active']) ? 'is-inactive' : ''; ?>">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/<?php echo (int)$m['id']; ?>" class="card-body d-flex flex-column align-items-center justify-content-center gap-2 text-decoration-none">
                    <span class="vehicle-catalog-card__media">
                        <?php $logoSlug = $slugMarca((string)$m['name']); $logoFile = is_file($catalogoMidiaDir . $logoSlug . '.png') ? $logoSlug . '.png' : (is_file($catalogoMidiaDir . $logoSlug . '.svg') ? $logoSlug . '.svg' : null); ?>
                        <?php if ($logoFile): ?><img class="vehicle-catalog-logo" src="<?php echo htmlspecialchars($catalogoMidiaUrl . $logoFile); ?>" alt="Logo <?php echo htmlspecialchars($m['name']); ?>">
                        <?php else: ?><?php echo vehicle_brand_badge_html($m['name'], $m['logo_path'] ?? null, 64); ?><?php endif; ?>
                    </span>
                    <strong class="vehicle-catalog-card__name"><?php echo htmlspecialchars($m['name']); ?></strong>
                    <span class="vehicle-catalog-card__meta"><?php echo $qtdModelos; ?> modelo(s)</span>
                    <?php if (empty($m['active'])): ?><span class="badge text-bg-secondary">Inativa</span><?php endif; ?>
                </a>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/novo?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-outline-primary">
                    Editar
                </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?></div></section>
        <?php endforeach; endif; ?>
    </div>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('marcasBusca');
        var itens = Array.prototype.slice.call(document.querySelectorAll('#marcasGrid [data-search]'));
        if (!busca) return;
        busca.addEventListener('input', function () {
            var query = busca.value.trim().toLowerCase();
            itens.forEach(function (item) {
                item.hidden = Boolean(query) && item.getAttribute('data-search').indexOf(query) < 0;
            });
        });
    })();
    </script>
</div>

<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Marcas de veículos'; $breadcrumb=['Catálogo e preços','Marcas de veículos']; $assets_css=['/public/assets/css/pages/bo-admin-catalogo.css','/public/assets/css/pages/gf-admin-catalogo-veiculos-v2.css','/public/assets/css/pages/gf-admin-catalogo-veiculos-logo-fix.css','/public/assets/css/pages/gf-admin-catalogo-veiculos-directory.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
