<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


require_once dirname(__DIR__) . '/config.php';

$pedidoId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($pedidoId <= 0) {
    fwrite(STDERR, "Uso: php tools/fix_seed_chat_placeholder.php <pedido_id>\n");
    exit(1);
}

$pdo = getPDO();

$stmtPedido = $pdo->prepare(
    "SELECT p.id, u.nome AS cliente_nome
       FROM pedidos p
       JOIN usuarios u ON u.id = p.cliente_id
      WHERE p.id = ?"
);
$stmtPedido->execute([$pedidoId]);
$pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    fwrite(STDERR, "Pedido não encontrado.\n");
    exit(2);
}

$needle = '{$clienteNome}';
$replacement = (string)$pedido['cliente_nome'];

$stmtPreview = $pdo->prepare(
    "SELECT id, mensagem
       FROM chat_mensagens
      WHERE pedido_id = ?
        AND mensagem LIKE ?"
);
$stmtPreview->execute([$pedidoId, '%' . $needle . '%']);
$rows = $stmtPreview->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode([
        'ok' => true,
        'pedido_id' => $pedidoId,
        'cliente_nome' => $replacement,
        'updated' => 0,
        'message' => 'Nenhuma mensagem com placeholder encontrada.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$pdo->beginTransaction();
try {
    $stmtUpdate = $pdo->prepare(
        "UPDATE chat_mensagens
            SET mensagem = REPLACE(mensagem, ?, ?)
          WHERE id = ?"
    );

    foreach ($rows as $row) {
        $stmtUpdate->execute([$needle, $replacement, (int)$row['id']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Falha ao atualizar mensagens: ' . $e->getMessage() . PHP_EOL);
    exit(3);
}

$stmtAfter = $pdo->prepare(
    "SELECT id, mensagem
       FROM chat_mensagens
      WHERE id IN (" . implode(',', array_fill(0, count($rows), '?')) . ")
      ORDER BY id ASC"
);
$stmtAfter->execute(array_map(static fn(array $row): int => (int)$row['id'], $rows));

echo json_encode([
    'ok' => true,
    'pedido_id' => $pedidoId,
    'cliente_nome' => $replacement,
    'updated' => count($rows),
    'messages' => $stmtAfter->fetchAll(PDO::FETCH_ASSOC),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
