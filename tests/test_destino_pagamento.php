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
require_once $raiz . '/src/Models/Pagamento.php';
require_once $raiz . '/src/Services/PedidoEventoService.php';

$argv = $_SERVER['argv'] ?? [];
$pedidoId = 0;
foreach ($argv as $a) { if (ctype_digit((string)$a)) { $pedidoId = (int)$a; break; } }

$pdo = getPDO();

// Se não informado, pega o primeiro em aguardando_pagamento
if ($pedidoId === 0) {
    $pedidoId = (int)$pdo->query(
        "SELECT id FROM pedidos WHERE status = 'aguardando_pagamento' ORDER BY id LIMIT 1"
    )->fetchColumn();
}

if ($pedidoId <= 0) {
    fwrite(STDERR, "Nenhum pedido aguardando_pagamento.\n");
    exit(1);
}

echo "=== Antes ===\n";
$stmt = $pdo->prepare("SELECT id, status, custo_estimado, custo_final FROM pedidos WHERE id = ?");
$stmt->execute([$pedidoId]);
$antes = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$antes) { fwrite(STDERR, "Pedido {$pedidoId} não existe.\n"); exit(1); }
echo "  pedido #{$pedidoId}  status={$antes['status']}  custo_estimado={$antes['custo_estimado']}\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedido_eventos WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
echo "  eventos antes: " . (int)$stmt->fetchColumn() . "\n";

echo "\n=== Executando aplicarDestinoPagamento('pago_agora', null, 1) ===\n";
$r = Pagamento::aplicarDestinoPagamento($pedidoId, 'pago_agora', null, 1);
echo "  ok=" . ($r['ok'] ? 'true' : 'false') . "\n";
if (!$r['ok']) {
    echo "  erro: " . ($r['erro'] ?? '(sem mensagem)') . "\n";
    exit(2);
}
echo "  evento: " . $r['evento'] . "\n";
echo "  pagamento_id: " . ($r['pagamento_id'] ?? '-') . "\n";

echo "\n=== Depois ===\n";
$stmt = $pdo->prepare("SELECT status, forma_pagamento_escolhida FROM pedidos WHERE id = ?");
$stmt->execute([$pedidoId]);
$depois = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  status: {$depois['status']}  forma: {$depois['forma_pagamento_escolhida']}\n";

echo "\n=== Split do pagamento ===\n";
$stmt = $pdo->prepare(
    "SELECT id, valor_total, valor_guincho, valor_plataforma, status
       FROM pagamentos WHERE pedido_id = ?"
);
$stmt->execute([$pedidoId]);
$pag = $stmt->fetch(PDO::FETCH_ASSOC);
if ($pag) {
    $total    = (float)$pag['valor_total'];
    $guincho  = (float)$pag['valor_guincho'];
    $plataforma = (float)$pag['valor_plataforma'];
    $gateway  = round($total - $guincho - $plataforma, 2);

    echo "  total:      R$ " . number_format($total, 2, ',', '.') . "\n";
    echo "  guincho:    R$ " . number_format($guincho, 2, ',', '.') . " (esperado: " . number_format($total * (1 - 0.08 - 0.0498), 2, ',', '.') . ")\n";
    echo "  plataforma: R$ " . number_format($plataforma, 2, ',', '.') . " (esperado: " . number_format($total * 0.08, 2, ',', '.') . ")\n";
    echo "  gateway:    R$ " . number_format($gateway, 2, ',', '.') . " (esperado: " . number_format($total * 0.0498, 2, ',', '.') . ")\n";
    echo "  soma:       R$ " . number_format($guincho + $plataforma + $gateway, 2, ',', '.') . "\n";

    $difPlataforma = abs($plataforma - $total * 0.08);
    echo "\n  check plataforma 8%: " . ($difPlataforma < 0.02 ? "OK" : "FALHOU (dif R$ " . number_format($difPlataforma, 2, ',', '.') . ")") . "\n";
} else {
    echo "  pagamento nao criado.\n";
}

echo "\n=== Eventos ===\n";
$stmt = $pdo->prepare(
    "SELECT id, evento, ator_tipo, status_anterior, status_novo, motivo
       FROM pedido_eventos WHERE pedido_id = ? ORDER BY id"
);
$stmt->execute([$pedidoId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
    printf("  #%-3d %-32s %s | %s -> %s | %s\n",
        $e['id'], $e['evento'], $e['ator_tipo'] ?? '-',
        $e['status_anterior'] ?? '-', $e['status_novo'] ?? '-',
        $e['motivo'] ?? '-');
}