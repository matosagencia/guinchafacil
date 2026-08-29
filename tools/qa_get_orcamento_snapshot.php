<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// Snapshot do orçamento complementar (pedido_orcamentos) de um pedido —
// prova real de VAR-004 (especialista propõe serviço adicional, cliente
// recusa): sem isso o teste só conseguiria inferir o resultado pelo status
// do pedido, que fica em 'autorizacao_servico_pendente' tanto se o cliente
// ainda não decidiu quanto se recusou (DiagnosticoService::decidirOrcamento
// não muda o status do pedido quando recusa, de propósito — ver comentário
// lá). Só olhando pedido_orcamentos.status dá pra confirmar a recusa de
// fato.
//
// Uso: php qa_get_orcamento_snapshot.php <pedido_id>

if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_get_orcamento_snapshot.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $stmt = getPDO()->prepare('SELECT * FROM pedido_orcamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$pedidoId]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        echo json_encode(['ok' => true, 'existe' => false], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit;
    }

    echo json_encode([
        'ok' => true,
        'existe' => true,
        'status' => $orcamento['status'],
        'valor_total' => (float)$orcamento['valor_total'],
        'itens' => json_decode((string)$orcamento['itens_json'], true) ?: [],
        'decidido_em' => $orcamento['decidido_em'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
