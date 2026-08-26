<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$csrfToken = AuthService::gerarCsrfToken();
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div><div class="page-title"><i class="fas fa-hand-point-up me-2 text-primary-custom"></i>Aceitar Pedido</div></div>
    </div>

    <div class="row g-4 justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-bell me-2"></i>Pedido de Socorro #<?php echo (int)$pedido['id']; ?></div>
                <div class="card-body">

                    <p><i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                       <strong>Problema:</strong> <?php echo htmlspecialchars(ucfirst($pedido['tipo_problema'] ?? '-')); ?></p>

                    <?php if (!empty($pedido['descricao_problema'])): ?>
                    <p><i class="fas fa-comment me-2 text-primary-custom"></i>
                       <strong>Descrição:</strong> <?php echo htmlspecialchars($pedido['descricao_problema']); ?></p>
                    <?php endif; ?>

                    <p><i class="fas fa-car me-2 text-primary-custom"></i>
                       <strong>Veículo:</strong>
                       <?php echo htmlspecialchars(($pedido['marca'] ?? '') . ' ' . ($pedido['modelo'] ?? '') . ' — ' . ($pedido['placa'] ?? '')); ?></p>

                    <p><i class="fas fa-map-marker-alt me-2 text-danger"></i>
                       <strong>Origem:</strong> <?php echo htmlspecialchars($pedido['endereco_origem'] ?? '-'); ?></p>

                    <p><i class="fas fa-map-pin me-2 text-success"></i>
                       <strong>Destino:</strong> <?php echo htmlspecialchars($pedido['endereco_destino'] ?? '-'); ?></p>

                    <p><i class="fas fa-road me-2 text-primary-custom"></i>
                       <strong>Distância:</strong> <?php echo number_format((float)($pedido['distancia_km'] ?? 0), 1, ',', '.'); ?> km</p>

                    <p class="fs-5 fw-bold text-success">
                       <i class="fas fa-coins me-2"></i>Valor: R$ <?php echo number_format((float)($pedido['custo_estimado'] ?? 0), 2, ',', '.'); ?>
                    </p>

                    <hr>

                    <div class="d-flex gap-3 mt-3">
                        <form method="POST" action="<?php echo $bp; ?>/guincho/aceitar/<?php echo (int)$pedido['id']; ?>" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="fas fa-check me-2"></i>Aceitar
                            </button>
                        </form>
                        <form method="POST" action="<?php echo $bp; ?>/guincho/recusar/<?php echo (int)$pedido['id']; ?>" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <button type="submit" class="btn btn-outline-danger w-100 py-2">
                                <i class="fas fa-times me-2"></i>Recusar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
