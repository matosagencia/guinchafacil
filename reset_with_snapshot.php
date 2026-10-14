<?php
require_once 'config.php';
require_once 'src/Database.php';
$pdo = getPDO();
$pdo->prepare("UPDATE pedidos SET forma_pagamento_escolhida = NULL, status = 'aguardando_pagamento' WHERE id = 3")->execute();
$pdo->prepare("DELETE FROM pagamentos WHERE pedido_id = 3")->execute();
$pdo->prepare("DELETE FROM order_price_snapshots WHERE order_id = 3")->execute();

$json = json_encode([
    'base_comissao' => 20.00,
    'pedagio' => 7.50,
    'preco_cliente' => 27.50
]);
$stmt = $pdo->prepare("INSERT INTO order_price_snapshots (order_id, provider_total, platform_fee, calculation_context_json, customer_total, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
// provider_total (com gateway) = 27.50 - 1.60 - 1.37 = 24.53
$stmt->execute([3, 24.53, 1.60, $json, 27.50]);

echo "Pedido #3 resetado com SNAPSHOT.\n";
