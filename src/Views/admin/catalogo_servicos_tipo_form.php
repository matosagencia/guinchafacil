<?php
// IMPORTANTE: header.php define $tipo = perfil do usuário ('admin'), colidindo
// com a variável $tipo (tipo de serviço) passada pelo controller. Capturamos o
// tipo de serviço ANTES do include para não perder o array.
$servicoTipo = $tipo ?? null;
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$v = function (string $campo, $default = '') use ($servicoTipo) {
    return htmlspecialchars((string)($servicoTipo[$campo] ?? $default), ENT_QUOTES, 'UTF-8');
};
$checked = function (string $campo) use ($servicoTipo) {
    return !empty($servicoTipo[$campo]) ? 'checked' : '';
};
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Catálogo estruturado</span>
            <h1><?php echo $servicoTipo ? 'Editar tipo de serviço' : 'Novo tipo de serviço'; ?></h1>
            <p>Estes campos decidem o comportamento do pedido — nenhum controller deve inferir isso por texto livre.</p>
        </div>
    </header>

    <form method="post" action="<?php echo $bp; ?>/admin/catalogo-servicos/tipo/salvar" class="card">
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
            <?php if ($servicoTipo): ?><input type="hidden" name="id" value="<?php echo (int)$servicoTipo['id']; ?>"><?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Categoria</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>" <?php echo (isset($servicoTipo['category_id']) && (int)$servicoTipo['category_id'] === (int)$c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Código (imutável, ex.: JUMP_START)</label>
                    <input type="text" name="code" class="form-control font-monospace text-uppercase"
                           value="<?php echo $v('code'); ?>" <?php echo $servicoTipo ? 'readonly' : 'required'; ?>>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Nome</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $v('name'); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Modo de atendimento</label>
                    <select name="attendance_mode" class="form-select">
                        <?php foreach (['TOWING' => 'Reboque', 'ON_SITE' => 'No local', 'HYBRID' => 'Híbrido'] as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($servicoTipo['attendance_mode'] ?? 'ON_SITE') === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Descrição</label>
                    <textarea name="description" class="form-control" rows="2"><?php echo $v('description'); ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Duração estimada (minutos)</label>
                    <input type="number" name="estimated_duration_minutes" class="form-control" min="1"
                           value="<?php echo $v('estimated_duration_minutes', 30); ?>">
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold d-block">Regras estruturais</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="requires_destination" id="rd" <?php echo $checked('requires_destination'); ?>>
                        <label class="form-check-label" for="rd">Requer destino</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="allows_conversion_to_towing" id="act" <?php echo $checked('allows_conversion_to_towing'); ?>>
                        <label class="form-check-label" for="act">Permite conversão p/ reboque</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="requires_diagnostic" id="rdi" <?php echo $checked('requires_diagnostic'); ?>>
                        <label class="form-check-label" for="rdi">Requer diagnóstico</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="requires_parts" id="rp" <?php echo $checked('requires_parts'); ?>>
                        <label class="form-check-label" for="rp">Requer peças</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="requires_before_evidence" id="rbe" <?php echo ($servicoTipo === null || !empty($servicoTipo['requires_before_evidence'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rbe">Evidência antes</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="requires_after_evidence" id="rae" <?php echo ($servicoTipo === null || !empty($servicoTipo['requires_after_evidence'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rae">Evidência depois</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="active" id="ativo" <?php echo ($servicoTipo === null || !empty($servicoTipo['active'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativo">Ativo</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?php echo $bp; ?>/admin/catalogo-servicos/tipos" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1"></i>Salvar</button>
        </div>
    </form>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
