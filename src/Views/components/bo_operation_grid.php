<?php
/**
 * Split View exato (310px / restante), sem dados de exemplo.
 * $conteudo_lista_pedidos e $conteudo_detalhe_pedido vêm da View/controller reais.
 * Não define handlers de despacho/cancelamento: continuam nos serviços existentes.
 */
require_once __DIR__ . '/../layouts/backoffice_helpers.php';
$conteudo_lista_pedidos = (string)($conteudo_lista_pedidos ?? '<p class="bo-empty-state">Nenhum pedido para exibir.</p>');
$conteudo_detalhe_pedido = (string)($conteudo_detalhe_pedido ?? '<p class="bo-empty-state">Selecione um pedido.</p>');
?>
<div class="bo-operation-grid">
  <section class="bo-orders" aria-label="Fila de pedidos"><?= $conteudo_lista_pedidos ?></section>
  <section class="bo-incident" aria-label="Detalhe do pedido selecionado"><?= $conteudo_detalhe_pedido ?></section>
</div>
