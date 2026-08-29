<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$pedidoId = (int)$payment['order_id'];
$chargePaymentId = (int)$payment['id'];
$valorPedido = (float)$payment['amount'];
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/cliente-pagamento.css">
<div class="main-wrapper"><main class="main-content">
<header class="page-head mb-4"><div><span class="eyebrow">Cotação complementar</span><h1><i class="fas fa-receipt me-2 text-primary-custom"></i>Confirme os itens adicionais</h1><p>O serviço só será executado após a confirmação do pagamento.</p></div></header>
<div id="checkout-mensagem" class="alert d-none" role="alert"></div>
<div class="card mb-4"><div class="card-body"><div class="d-flex justify-content-between"><span>Pedido #<?php echo $pedidoId; ?></span><strong>R$ <?php echo number_format($valorPedido, 2, ',', '.'); ?></strong></div><p class="text-muted small mb-0 mt-2">O pagamento inicial do atendimento permanece separado. Esta cobrança corresponde somente aos itens adicionais aprovados.</p></div></div>
<?php if ($mostrarMercadoPago): ?><div class="card"><div class="card-header"><i class="fas fa-lock me-2"></i>Pagamento seguro</div><div class="card-body"><div id="mp-payment-brick-container"></div><div id="mp-payment-status" class="mt-3"></div></div></div><?php else: ?><div class="alert alert-warning">Mercado Pago não está disponível no momento.</div><?php endif; ?>
<?php if (!empty($payment['charge_items'])): ?><div class="card mb-4"><div class="card-body"><h2 class="h6">Itens aprovados</h2><ul class="list-group list-group-flush"><?php foreach ($payment['charge_items'] as $item): ?><li class="list-group-item px-0 d-flex justify-content-between gap-3"><span><?php echo htmlspecialchars((string)$item['description']); ?><?php if ((float)$item['quantity'] != 1.0): ?> <small class="text-muted">(<?php echo rtrim(rtrim(number_format((float)$item['quantity'], 2, ',', '.'), '0'), ','); ?>x)</small><?php endif; ?></span><strong>R$ <?php echo number_format((float)$item['gross_amount'], 2, ',', '.'); ?></strong></li><?php endforeach; ?></ul><p class="text-muted small mb-0 mt-3"><i class="fas fa-circle-info me-1"></i>Deslocamento e diagnóstico/parecer pertencem ao pagamento inicial. Aqui estão somente peças, pneus e serviços adicionais.</p></div></div><?php endif; ?>
</main></div>
<?php if ($mostrarMercadoPago): ?><script src="https://sdk.mercadopago.com/js/v2"<?php echo csp_script_nonce_attr(); ?>></script><?php endif; ?>
<script<?php echo csp_script_nonce_attr(); ?>>
(function(){'use strict';var BP=<?php echo json_encode($bp); ?>,CSRF=<?php echo json_encode($csrfToken); ?>,ID=<?php echo $chargePaymentId; ?>,AMOUNT=<?php echo json_encode($valorPedido); ?>,KEY=<?php echo json_encode($mpPublicKey); ?>;function msg(t,c){var e=document.getElementById('checkout-mensagem');if(e){e.textContent=t;e.className='alert alert-'+(c||'danger');}}if(!KEY||typeof MercadoPago==='undefined'){msg('Mercado Pago não pôde ser carregado.','warning');return;}new MercadoPago(KEY,{locale:'pt-BR'}).bricks().create('payment','mp-payment-brick-container',{initialization:{amount:AMOUNT},customization:{paymentMethods:{creditCard:'all',bankTransfer:'all',ticket:'all'}},callbacks:{onSubmit:function(d){var f=d&&d.formData?d.formData:d;return fetch(BP+'/pagamento/complementar/mercadopago/pagar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:CSRF,charge_payment_id:ID,formData:f})}).then(function(r){return r.json()}).then(function(x){if(x.status==='aprovado'){msg('Pagamento aprovado. Liberando o serviço...','success');location.href=x.redirect;}else{msg(x.erro||'Pagamento pendente.','info');}});}}});})();
</script>
