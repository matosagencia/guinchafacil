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

// Captura exceções não tratadas com stack
set_exception_handler(function (Throwable $e) {
    echo "\n!!! EXCECAO NAO TRATADA !!!\n";
    echo "  tipo: " . get_class($e) . "\n";
    echo "  msg:  " . $e->getMessage() . "\n";
    echo "  em:   " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "  trace:\n" . $e->getTraceAsString() . "\n";
});

$pedidoId = (int)($argv[1] ?? 30);

echo "=== Estado inicial ===\n";
echo "  pdo inTransaction: " . ($pdo->inTransaction() ? 'true' : 'false') . "\n";
echo "  err mode: " . $pdo->getAttribute(PDO::ATTR_ERRMODE) . " (0=SILENT 1=WARNING 2=EXCEPTION)\n";
echo "  autocommit: " . $pdo->getAttribute(PDO::ATTR_AUTOCOMMIT) . "\n";

// Estado do pedido
$stmt = $pdo->prepare("SELECT id, status, forma_pagamento_escolhida FROM pedidos WHERE id = ?");
$stmt->execute([$pedidoId]);
$ped = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\n=== Pedido {$pedidoId} ANTES ===\n";
print_r($ped);

// Pagamento existente
$stmt = $pdo->prepare("SELECT id, status, valor_total FROM pagamentos WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
echo "\n=== Pagamento ANTES ===\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Chamando aplicarDestinoPagamento ===\n";
require_once $raiz . '/src/Models/Pagamento.php';

try {
    $r = Pagamento::aplicarDestinoPagamento($pedidoId, 'pago_agora', null, 1);
    echo "  retorno ok: " . ($r['ok'] ? 'true' : 'false') . "\n";
    echo "  retorno erro: " . ($r['erro'] ?? '-') . "\n";
    echo "  retorno evento: " . ($r['evento'] ?? '-') . "\n";
    echo "  retorno pagamento_id: " . ($r['pagamento_id'] ?? '-') . "\n";
} catch (Throwable $e) {
    echo "  EXCECAO: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "  em: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Estado PDO pos ===\n";
echo "  inTransaction: " . ($pdo->inTransaction() ? 'true' : 'false') . "\n";

echo "\n=== Pedido DEPOIS ===\n";
$stmt = $pdo->prepare("SELECT id, status, forma_pagamento_escolhida FROM pedidos WHERE id = ?");
$stmt->execute([$pedidoId]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n=== Pagamento DEPOIS ===\n";
$stmt = $pdo->prepare("SELECT id, status, valor_total, valor_guincho, valor_plataforma FROM pagamentos WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Eventos ===\n";
$stmt = $pdo->prepare("SELECT id, evento, ator_tipo, status_anterior, status_novo FROM pedido_eventos WHERE pedido_id = ? ORDER BY id");
$stmt->execute([$pedidoId]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));