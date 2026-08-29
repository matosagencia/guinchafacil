<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../../layouts/header.php';
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gestão</span>
            <h1><i class="fas fa-bullhorn me-2 text-primary-custom"></i><?php echo !empty($item['id']) ? 'Editar' : 'Novo'; ?> comunicado</h1>
            <p>Use o mesmo componente que será renderizado no painel real.</p>
        </div>
    </header>
    <div class="card">
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/comunicado/salvar" class="row g-3" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                <input type="hidden" name="id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                <div class="col-md-6"><label class="form-label">Título</label><input class="form-control" name="titulo" value="<?php echo htmlspecialchars((string)($item['titulo'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Subtítulo</label><input class="form-control" name="subtitulo" value="<?php echo htmlspecialchars((string)($item['subtitulo'] ?? '')); ?>"></div>
                <?php
                $publicoAtual = (string)($item['publico'] ?? 'ambos');
                $placementAtual = (string)($item['placement'] ?? ComunicadoService::PLACEMENT_CLIENT_DASHBOARD_TOP);
                $statusAtual = (string)($item['status'] ?? 'rascunho');
                ?>
                <div class="col-md-4"><label class="form-label">Público</label><select class="form-select" name="publico"><option value="ambos" <?php echo $publicoAtual === 'ambos' ? 'selected' : ''; ?>>Ambos</option><option value="cliente" <?php echo $publicoAtual === 'cliente' ? 'selected' : ''; ?>>Cliente</option><option value="guincho" <?php echo $publicoAtual === 'guincho' ? 'selected' : ''; ?>>Guincho</option></select></div>
                <div class="col-md-4"><label class="form-label">Placement</label><select class="form-select" name="placement"><option value="<?php echo ComunicadoService::PLACEMENT_CLIENT_DASHBOARD_TOP; ?>" <?php echo $placementAtual === ComunicadoService::PLACEMENT_CLIENT_DASHBOARD_TOP ? 'selected' : ''; ?>>Cliente dashboard top</option><option value="<?php echo ComunicadoService::PLACEMENT_TOW_DASHBOARD_AFTER_STATS; ?>" <?php echo $placementAtual === ComunicadoService::PLACEMENT_TOW_DASHBOARD_AFTER_STATS ? 'selected' : ''; ?>>Guincho dashboard after stats</option></select></div>
                <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="rascunho" <?php echo $statusAtual === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option><option value="publicado" <?php echo $statusAtual === 'publicado' ? 'selected' : ''; ?>>Publicado</option><option value="pausado" <?php echo $statusAtual === 'pausado' ? 'selected' : ''; ?>>Pausado</option><option value="arquivado" <?php echo $statusAtual === 'arquivado' ? 'selected' : ''; ?>>Arquivado</option></select></div>
                <div class="col-md-6">
                    <label class="form-label">Imagem desktop</label>
                    <input class="form-control mb-2" name="imagem_desktop" value="<?php echo htmlspecialchars((string)($item['imagem_desktop'] ?? '')); ?>" placeholder="/public/uploads/...">
                    <input class="form-control" type="file" name="imagem_desktop_file" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="col-md-6">
                    <label class="form-label">CTA URL</label>
                    <input class="form-control mb-2" name="cta_url" value="<?php echo htmlspecialchars((string)($item['cta_url'] ?? '')); ?>">
                    <label class="form-label">Imagem mobile opcional</label>
                    <input class="form-control mb-2" name="imagem_mobile" value="<?php echo htmlspecialchars((string)($item['imagem_mobile'] ?? '')); ?>" placeholder="/public/uploads/...">
                    <input class="form-control" type="file" name="imagem_mobile_file" accept="image/jpeg,image/png,image/webp">
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Salvar</button></div>
            </form>
        </div>
    </div>
</main>
</div>
<?php include __DIR__ . '/../../layouts/footer.php'; ?>
