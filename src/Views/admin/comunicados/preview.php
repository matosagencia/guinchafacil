<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
?>

    <div class="card p-4">
        <h3 class="mb-3">Preview</h3>
        <?php if (!empty($item)): ?>
            <div class="dash-hero p-4">
                <div class="dash-chip mb-2"><?php echo htmlspecialchars((string)($item['publico'] ?? '')); ?></div>
                <h4><?php echo htmlspecialchars((string)($item['titulo'] ?? '')); ?></h4>
                <p class="text-muted mb-0"><?php echo htmlspecialchars((string)($item['subtitulo'] ?? '')); ?></p>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Comunicado não encontrado ou tabela ainda não criada.</p>
        <?php endif; ?>
    </div>


<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Preview de comunicado'; $breadcrumb=['Sistema','Comunicados','Preview']; $assets_css=['/public/assets/css/pages/bo-admin-operacao.css']; require __DIR__ . '/../../layouts/backoffice_shell.php';
