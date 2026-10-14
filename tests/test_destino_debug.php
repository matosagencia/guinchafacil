<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$_SERVER['REQUEST_URI'] = '/cli';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli';
$_SERVER['SCRIPT_NAME'] = '/cli.php';
if (!defined('REQUEST_ID')) define('REQUEST_ID', 'cli_' . bin2hex(random_bytes(4)));
if (!defined('CSP_SCRIPT_NONCE')) define('CSP_SCRIPT_NONCE', 'cli');

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';

$pdo = getPDO();
echo "=== Estado inicial do PDO ===\n";
echo "  inTransaction: " . ($pdo->inTransaction() ? 'true' : 'false') . "\n";
echo "  driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
echo "  autocommit: " . var_export($pdo->getAttribute(PDO::ATTR_AUTOCOMMIT), true) . "\n";

echo "\n=== Colunas da migration ===\n";
$stmt = $pdo->query(
    "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos'
        AND COLUMN_NAME IN ('forma_pagamento_escolhida','provedor_pagamento_escolhido',
                            'pagamento_destino_admin_id','pagamento_destino_registrado_em',
                            'pagamento_destino_evento')"
);
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "  encontradas: " . implode(', ', $cols) . "\n";
echo "  faltando:    " . (count($cols) === 5 ? '(nenhuma)' : 'ALGUMAS') . "\n";

echo "\n=== Pedido 50 ===\n";
$stmt = $pdo->prepare("SELECT id, status, forma_pagamento_escolhida, custo_estimado FROM pedidos WHERE id = 50");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== Pagamento do pedido 50 ===\n";
$stmt = $pdo->prepare("SELECT id, pedido_id, metodo, status, valor_total FROM pagamentos WHERE pedido_id = 50");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Chamando aplicarDestinoPagamento com trace ===\n";
require_once $raiz . '/src/Models/Pagamento.php';
try {
    $r = Pagamento::aplicarDestinoPagamento(50, 'pago_agora', null, 1);
    echo "  ok=" . ($r['ok'] ? 'true' : 'false') . "\n";
    echo "  erro=" . ($r['erro'] ?? '-') . "\n";
} catch (Throwable $e) {
    echo "  EXCECAO: " . $e->getMessage() . "\n";
    echo "  em: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  trace:\n" . $e->getTraceAsString() . "\n";
}