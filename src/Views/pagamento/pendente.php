<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Pagamento</span>
            <h1><i class="fas fa-clock me-2 text-warning"></i>Pagamento Pendente</h1>
            <p>Pedido #<?php echo (int)($pedido['id'] ?? 0); ?></p>
        </div>
    </header>

    <div class="card">
        <div class="card-body">
            <p>Seu pagamento está pendente de confirmação.</p>
            <a class="btn btn-primary" href="<?php echo !empty($pedido['id']) ? ($bp . '/cliente/pedido/' . (int)$pedido['id']) : ($bp . '/cliente/dashboard'); ?>">Voltar ao pedido</a>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
