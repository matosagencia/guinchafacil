<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$resultadoLabels = [
    'RECOMMENDED_SERVICE'    => ['Serviço recomendado', 'success'],
    'ALTERNATIVE_SERVICES'   => ['Algumas opções para você', 'info'],
    'SAFETY_RISK'            => ['Atenção — risco de segurança', 'danger'],
    'TOWING_REQUIRED'        => ['Reboque necessário', 'warning'],
    'MANUAL_REVIEW_REQUIRED' => ['Vamos avaliar no local', 'secondary'],
];
[$labelResultado, $corResultado] = $resultadoLabels[$sessao['resultado']] ?? ['Resultado da triagem', 'secondary'];
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Pedir socorro</span>
            <h1>Resultado da triagem</h1>
        </div>
    </header>

    <div class="card mb-4" style="max-width:640px;">
        <div class="card-body">
            <span class="badge text-bg-<?php echo $corResultado; ?> mb-3"><?php echo $labelResultado; ?></span>

            <?php if (!empty($sessao['safety_risk'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($sessao['explicacao']); ?>
            </div>
            <?php else: ?>
            <p><?php echo htmlspecialchars($sessao['explicacao']); ?></p>
            <?php endif; ?>

            <?php if ($servicoRecomendado): ?>
            <div class="d-flex align-items-center justify-content-between border rounded p-3 mt-3">
                <div>
                    <strong><?php echo htmlspecialchars($servicoRecomendado['name']); ?></strong>
                    <?php if (!empty($servicoRecomendado['description'])): ?>
                    <div class="small text-muted"><?php echo htmlspecialchars($servicoRecomendado['description']); ?></div>
                    <?php endif; ?>
                </div>
                <a href="<?php echo $bp; ?>/cliente/pedido/novo?service_type_id=<?php echo (int)$servicoRecomendado['id']; ?>" class="btn btn-primary btn-sm">
                    Continuar <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <?php endif; ?>

            <?php if (!empty($alternativas)): ?>
            <p class="small text-muted mt-3 mb-2">Outras opções, se preferir:</p>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($alternativas as $alt): ?>
                <div class="d-flex align-items-center justify-content-between border rounded p-2">
                    <span><?php echo htmlspecialchars($alt['name']); ?></span>
                    <a href="<?php echo $bp; ?>/cliente/pedido/novo?service_type_id=<?php echo (int)$alt['id']; ?>" class="btn btn-outline-secondary btn-sm">Selecionar</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$servicoRecomendado && empty($alternativas)): ?>
            <a href="<?php echo $bp; ?>/cliente/pedido/novo" class="btn btn-primary mt-3">
                Continuar pedido <i class="fas fa-arrow-right ms-1"></i>
            </a>
            <?php endif; ?>

            <div class="mt-4">
                <a href="<?php echo $bp; ?>/cliente/triagem" class="text-muted small">Refazer triagem</a>
            </div>
        </div>
    </div>

</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
