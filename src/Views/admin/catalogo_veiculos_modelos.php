<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();

require_once __DIR__ . '/../components/vehicle_brand_badge.php';

$marca = $marca ?? [];
$modelos = $modelos ?? [];
$gruposAlfabeticos = [];
foreach ($modelos as $modeloItem) {
    $letra = strtoupper(mb_substr(trim((string)($modeloItem['name'] ?? '#')), 0, 1, 'UTF-8'));
    $gruposAlfabeticos[$letra][] = $modeloItem;
}
ksort($gruposAlfabeticos, SORT_NATURAL | SORT_FLAG_CASE);
?>
<div class="bo-page vehicle-catalog-page">

    <header class="page-head vehicle-catalog-hero mb-3">
        <div class="d-flex align-items-center gap-3">
            <?php echo vehicle_brand_badge_html($marca['name'] ?? '', $marca['logo_path'] ?? null, 48); ?>
            <div class="vehicle-catalog-hero__copy">
                <span class="eyebrow">Catálogo de veículos</span>
                <h1>Modelos — <?php echo htmlspecialchars($marca['name'] ?? ''); ?></h1>
                <p class="vehicle-catalog-breadcrumb"><a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos">← Voltar para marcas</a><span> / </span> catálogo de <?php echo count($modelos); ?> modelos</p>
            </div>
            <div class="vehicle-catalog-hero__actions">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/novo?marca_id=<?php echo (int)$marca['id']; ?>" class="btn btn-primary">+ Novo modelo</a>
            </div>
        </div>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3 vehicle-catalog-legacy-action">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/novo?marca_id=<?php echo (int)$marca['id']; ?>" class="btn btn-primary">
            Novo modelo
        </a>
    </div>

    <nav class="vehicle-catalog-index" aria-label="Índice alfabético de modelos">
        <?php foreach (array_keys($gruposAlfabeticos) as $letra): ?><a href="#modelo-<?php echo htmlspecialchars($letra); ?>"><?php echo htmlspecialchars($letra); ?></a><?php endforeach; ?>
    </nav>
    <div>
        <?php if (empty($modelos)): ?>
        <div class="col-12">
            <div class="vehicle-catalog-empty">Nenhum modelo cadastrado ainda para <?php echo htmlspecialchars($marca['name'] ?? ''); ?>.</div>
        </div>
        <?php else: foreach ($gruposAlfabeticos as $letra => $modelosDoGrupo): ?>
        <section class="vehicle-catalog-letter-group" id="modelo-<?php echo htmlspecialchars($letra); ?>">
            <h2 class="vehicle-catalog-letter"><span><?php echo htmlspecialchars($letra); ?></span><span class="vehicle-catalog-letter__count"><?php echo count($modelosDoGrupo); ?> modelos</span></h2>
            <div class="row g-3">
        <?php foreach ($modelosDoGrupo as $mo): ?>
        <div class="col-6 col-md-4 col-lg-3 col-xl-2">
            <div class="card vehicle-catalog-card vehicle-model-card <?php echo empty($mo['active']) ? 'is-inactive' : ''; ?>">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/<?php echo (int)$mo['id']; ?>" class="card-body d-flex flex-column align-items-center justify-content-center gap-2 text-decoration-none">
                    <?php if (!empty($mo['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($mo['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($mo['name']); ?>" style="width:96px;height:64px;object-fit:contain;">
                    <?php else: ?>
                        <?php echo vehicle_model_placeholder_html(96); ?>
                    <?php endif; ?>
                    <strong class="text-body"><?php echo htmlspecialchars($mo['name']); ?></strong>
                    <?php if (empty($mo['active'])): ?><span class="badge text-bg-secondary">Inativo</span><?php endif; ?>
                </a>
                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/novo?id=<?php echo (int)$mo['id']; ?>" class="btn btn-sm btn-outline-primary">
                    Editar
                </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?></div></section>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Modelos de veículos'; $breadcrumb=['Catálogo e preços','Modelos de veículos']; $assets_css=['/public/assets/css/pages/bo-admin-catalogo.css','/public/assets/css/pages/gf-admin-catalogo-veiculos-v2.css','/public/assets/css/pages/gf-admin-catalogo-veiculos-logo-fix.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
