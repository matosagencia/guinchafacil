<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// Investigação de 30/07/2026: mensagem enviada pelo prestador (com retry
// confirmado no próprio #chatBox dele) não apareceu no #chatBox do cliente
// em até 15s via esperarMensagem(). Duas causas bem diferentes explicam o
// mesmo sintoma, e sem olhar o banco não dá pra distinguir:
//   a) a mensagem nunca foi persistida em chat_mensagens (bug no POST/salvar)
//   b) foi persistida normalmente, mas o SSE do cliente não entregou a
//      tempo (conexão caiu/reconectando — ver logs [pedidostatus][SSE]
//      agora adicionados em pedidostatus.php)
// Este snapshot resolve (a) direto no banco, sem depender do navegador —
// se a mensagem aparecer aqui, o problema é (b): entrega/SSE, não persistência.
//
// Uso: php qa_get_chat_snapshot.php <pedido_id>

if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_get_chat_snapshot.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $stmt = getPDO()->prepare(
        'SELECT id, usuario_id, mensagem, criado_em FROM chat_mensagens WHERE pedido_id = ? ORDER BY id ASC'
    );
    $stmt->execute([$pedidoId]);
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'total' => count($mensagens),
        'mensagens' => array_map(static fn(array $m): array => [
            'id' => (int)$m['id'],
            'usuario_id' => (int)$m['usuario_id'],
            'mensagem' => $m['mensagem'],
            'criado_em' => $m['criado_em'],
        ], $mensagens),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
