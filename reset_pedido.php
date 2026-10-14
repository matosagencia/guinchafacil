<?php
require_once 'config.php';
require_once 'src/Database.php';
$pdo = getPDO();
$pdo->prepare("UPDATE pedidos SET forma_pagamento_escolhida = NULL, status = 'aguardando_pagamento' WHERE id = 3")->execute();
$pdo->prepare("DELETE FROM pagamentos WHERE pedido_id = 3")->execute();
echo "Pedido #3 resetado.\n";
