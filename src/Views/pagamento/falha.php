<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$pedidoId = (int)($pedido['id'] ?? 0);
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/cliente-pagamento.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Pagamento</span>
            <h1><i class="fas fa-circle-xmark me-2 text-danger"></i>Pagamento Não Concluído</h1>
            <p>Pedido #<?php echo $pedidoId; ?></p>
        </div>
    </header>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-danger mb-3" role="alert">
                <div class="fw-semibold mb-1">Motivo da falha</div>
                <div><?php echo htmlspecialchars($motivoPagamento ?? 'Não foi possível concluir o pagamento agora.'); ?></div>
            </div>

            <?php if (!empty($gatewayPagamento) || !empty($codigoDiagnostico)): ?>
                <div class="mb-3 text-muted small">
                    <?php if (!empty($gatewayPagamento)): ?>
                        <div><strong>Provedor:</strong> <?php echo htmlspecialchars((string)$gatewayPagamento); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($codigoDiagnostico)): ?>
                        <div><strong>Código de diagnóstico:</strong> <?php echo htmlspecialchars((string)$codigoDiagnostico); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($mostrarDetalheDev) && !empty($detalhePagamento)): ?>
                <details class="mb-3">
                    <summary class="fw-semibold">Detalhes técnicos para desenvolvimento</summary>
                    <pre class="bg-light border rounded p-3 mt-2 mb-0 small pagamento-pre-detalhe"><?php echo htmlspecialchars((string)$detalhePagamento); ?></pre>
                </details>
            <?php endif; ?>

            <div class="d-flex gap-2 flex-wrap">
                <?php if ($pedidoId > 0): ?>
                    <a class="btn btn-primary" href="<?php echo $bp; ?>/pagamento/checkout/<?php echo $pedidoId; ?>">Tentar novamente</a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?php echo $bp; ?>/cliente/dashboard">Voltar ao painel</a>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
