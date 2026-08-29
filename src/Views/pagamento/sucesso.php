<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$pedidoId = (int)($pedido['id'] ?? 0);
$marketingPageEvent = 'purchase';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/pagamento-sucesso.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <div class="pg-sucesso-wrap">
        <div class="pg-sucesso-card">

            <div class="pg-emblema">
                <div class="pg-anel"></div>
                <div class="pg-anel pg-anel-2"></div>
                <div class="pg-anel pg-anel-3"></div>
                <div class="pg-logo-circulo">
                    <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-128.png" alt="GuinchaFácil">
                </div>
                <div class="pg-selo-check" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 12.5 L9.5 18 L20 6"></path></svg>
                </div>
            </div>

            <h1 class="pg-titulo">Pagamento aprovado!</h1>
            <p class="pg-subtitulo">
                Obrigado pelo seu pagamento<?php echo $pedidoId ? ' — pedido <strong>#' . $pedidoId . '</strong>' : ''; ?>.
                Já está tudo confirmado por aqui.
            </p>

            <div class="pg-status-guincho">
                <div class="pg-radar"><i class="fas fa-satellite-dish"></i></div>
                <div class="pg-status-texto">
                    <strong>Procurando o guincho mais próximo<span class="pg-status-pontos"></span></strong>
                    <span>Assim que um guincheiro aceitar, você recebe o aviso por aqui. Aguarde só um instante.</span>
                </div>
            </div>

            <div class="pg-estrada" aria-hidden="true">
                <i class="fas fa-truck-pickup"></i>
            </div>

            <div class="pg-acoes">
                <a class="btn btn-primary" href="<?php echo $pedidoId ? ($bp . '/cliente/pedido/' . $pedidoId) : ($bp . '/cliente/dashboard'); ?>">
                    <i class="fas fa-location-arrow me-1"></i>Acompanhar meu pedido
                </a>
            </div>

        </div>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
