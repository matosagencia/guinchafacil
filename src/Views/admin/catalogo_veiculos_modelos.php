<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../components/vehicle_brand_badge.php';

$marca = $marca ?? [];
$modelos = $modelos ?? [];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-3">
        <div class="d-flex align-items-center gap-3">
            <?php echo vehicle_brand_badge_html($marca['name'] ?? '', $marca['logo_path'] ?? null, 48); ?>
            <div>
                <span class="eyebrow">Catálogo de veículos</span>
                <h1>Modelos — <?php echo htmlspecialchars($marca['name'] ?? ''); ?></h1>
                <p><a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos"><i class="fas fa-arrow-left me-1"></i>Voltar pras marcas</a></p>
            </div>
        </div>
    </header>

    <?php $flash = $flash ?? null; if (!empty($flash)): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> mb-3">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-end mb-3">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/novo?marca_id=<?php echo (int)$marca['id']; ?>" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>Novo modelo
        </a>
    </div>

    <div class="row g-3">
        <?php if (empty($modelos)): ?>
        <div class="col-12">
            <div class="ops-empty-state" style="padding:60px 20px">
                <i class="fas fa-car-side"></i>
                Nenhum modelo cadastrado ainda pra <?php echo htmlspecialchars($marca['name'] ?? ''); ?>.
            </div>
        </div>
        <?php else: foreach ($modelos as $mo): ?>
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card h-100 <?php echo empty($mo['active']) ? 'opacity-50' : ''; ?>" style="text-align:center;">
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
                    <i class="fas fa-pen me-1"></i>Editar
                </a>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
