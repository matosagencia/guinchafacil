<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../components/vehicle_brand_badge.php';

$modelo = $modelo ?? null;
$marca = $marca ?? [];
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">Catálogo de veículos — <?php echo htmlspecialchars($marca['name'] ?? ''); ?></span>
            <h1><?php echo $modelo ? 'Editar modelo' : 'Novo modelo'; ?></h1>
            <p><a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/marca/<?php echo (int)$marca['id']; ?>"><i class="fas fa-arrow-left me-1"></i>Voltar pros modelos</a></p>
        </div>
    </header>

    <div class="card" style="max-width:560px">
        <div class="card-body">
            <form method="post" action="<?php echo $bp; ?>/admin/catalogo-veiculos/modelo/salvar" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo $modelo ? (int)$modelo['id'] : 0; ?>">
                <input type="hidden" name="marca_id" value="<?php echo (int)$marca['id']; ?>">

                <div class="mb-3 text-center">
                    <?php if (!empty($modelo['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($modelo['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="" style="width:160px;height:110px;object-fit:contain;">
                    <?php else: ?>
                        <?php echo vehicle_model_placeholder_html(160); ?>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nome do modelo</label>
                    <input type="text" class="form-control" name="name" maxlength="100" required
                           value="<?php echo htmlspecialchars($modelo['name'] ?? ''); ?>" placeholder="Ex.: Gol">
                </div>

                <div class="mb-3">
                    <label class="form-label">Imagem ilustrativa (opcional)</label>
                    <input type="file" class="form-control" name="imagem" accept="image/jpeg,image/png,image/webp">
                    <p class="text-muted small mt-1 mb-0">JPEG/PNG/WEBP, até 3MB. Sem imagem, aparece um placeholder genérico.</p>
                    <?php if (!empty($modelo['image_path'])): ?>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="removerImagem" name="remover_imagem" value="1">
                        <label class="form-check-label" for="removerImagem">Remover imagem atual (volta pro placeholder)</label>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="modeloAtivo" name="active" value="1" <?php echo (!$modelo || !empty($modelo['active'])) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="modeloAtivo">Ativo (visível no cadastro do cliente)</label>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
            </form>
            <?php if ($modelo): ?>
            <hr>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-veiculos/modelo/<?php echo (int)$modelo['id']; ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list me-1"></i>Gerenciar versões/dados técnicos
            </a>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
