<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// Snapshot detalhado do Proof-of-Road de um pedido — complementa
// qa_get_pedido_snapshot.php (que só dá um resumo agregado) com a última
// rejeição e a tracking_quality persistida em pedido_percurso_resumos,
// pros specs de stress-por.spec.ts poderem afirmar sobre o antifraude
// especificamente, sem reimplementar a query em cada teste.
//
// Uso: php qa_get_por_snapshot.php <pedido_id>

if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_get_por_snapshot.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total, SUM(is_valid) AS aceitos FROM pedido_localizacoes WHERE pedido_id = ?'
    );
    $stmt->execute([$pedidoId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'aceitos' => 0];
    $total = (int)($row['total'] ?? 0);
    $aceitos = (int)($row['aceitos'] ?? 0);

    $stmtUltimaRejeicao = $pdo->prepare(
        "SELECT rejection_code FROM pedido_localizacoes
          WHERE pedido_id = ? AND is_valid = 0
          ORDER BY id DESC LIMIT 1"
    );
    $stmtUltimaRejeicao->execute([$pedidoId]);
    $ultimaRejeicao = $stmtUltimaRejeicao->fetchColumn();

    $stmtQualidade = $pdo->prepare(
        'SELECT tracking_quality FROM pedido_percurso_resumos WHERE pedido_id = ? ORDER BY updated_at DESC LIMIT 1'
    );
    $stmtQualidade->execute([$pedidoId]);
    $qualidade = $stmtQualidade->fetchColumn();

    echo json_encode([
        'ok' => true,
        'total_pontos' => $total,
        'aceitos' => $aceitos,
        'rejeitados' => $total - $aceitos,
        'ultima_rejeicao_code' => $ultimaRejeicao !== false ? $ultimaRejeicao : null,
        'tracking_quality' => $qualidade !== false ? $qualidade : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
