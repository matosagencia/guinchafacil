<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../components/vehicle_brand_badge.php';

$marca = $marca ?? null;
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">Catálogo de veículos</span>
            <h1><?php echo $marca ? 'Editar marca' : 'Nova marca'; ?></h1>
            <p><a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos"><i class="fas fa-arrow-left me-1"></i>Voltar pras marcas</a></p>
        </div>
    </header>

    <div class="card" style="max-width:560px">
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/catalogo-veiculos/marca/salvar" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo $marca ? (int)$marca['id'] : 0; ?>">

                <div class="mb-3 text-center">
                    <?php echo vehicle_brand_badge_html($marca['name'] ?? 'Nova marca', $marca['logo_path'] ?? null, 96); ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nome da marca</label>
                    <input type="text" class="form-control" name="name" maxlength="80" required
                           value="<?php echo htmlspecialchars($marca['name'] ?? ''); ?>" placeholder="Ex.: Volkswagen">
                </div>

                <div class="mb-3">
                    <label class="form-label">Logo (opcional)</label>
                    <input type="file" class="form-control" name="logo" accept="image/jpeg,image/png,image/webp">
                    <p class="text-muted small mt-1 mb-0">JPEG/PNG/WEBP, até 1MB. Sem logo, a marca aparece com um badge de inicial. Evite usar logos oficiais de marca registrada sem autorização.</p>
                    <?php if (!empty($marca['logo_path'])): ?>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="removerLogo" name="remover_logo" value="1">
                        <label class="form-check-label" for="removerLogo">Remover logo atual (volta pro badge de inicial)</label>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="marcaAtiva" name="active" value="1" <?php echo (!$marca || !empty($marca['active'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="marcaAtiva">Ativa (visível no cadastro do cliente)</label>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
            </form>
        </div>
    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
