<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// Snapshot direto do banco pro estado real de um pedido — mais forte que só
// checar a UI (o mesmo raciocínio de qa_pedido_status.php, ampliado pros
// novos cenários de stress): reúne pedido, último pagamento, agregados de
// Proof-of-Road, contagem de chat e evidências num único JSON, pra specs
// longos poderem afirmar o estado final sem reconstruir tudo manualmente.
//
// Uso: php qa_get_pedido_snapshot.php <pedido_id>

function qaSnapTableExists(string $table): bool
{
    $stmt = getPDO()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function qaSnapHasColumn(string $table, string $column): bool
{
    $stmt = getPDO()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_get_pedido_snapshot.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $pdo = getPDO();

    $temAttendanceMode = qaSnapHasColumn('pedidos', 'attendance_mode');
    $colunas = 'id, status, guincho_id' . ($temAttendanceMode ? ', attendance_mode' : '');
    $stmt = $pdo->prepare("SELECT {$colunas} FROM pedidos WHERE id = ? LIMIT 1");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        echo json_encode(['ok' => false, 'erro' => 'Pedido não encontrado.']) . PHP_EOL;
        exit;
    }

    $stmtPag = $pdo->prepare('SELECT status, valor_total FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
    $stmtPag->execute([$pedidoId]);
    $pagamento = $stmtPag->fetch(PDO::FETCH_ASSOC) ?: null;

    $por = ['total_pontos' => 0, 'pontos_aceitos' => 0, 'rota_integra' => false];
    if (qaSnapTableExists('pedido_localizacoes')) {
        $stmtPor = $pdo->prepare(
            'SELECT COUNT(*) AS total, SUM(is_valid) AS aceitos FROM pedido_localizacoes WHERE pedido_id = ?'
        );
        $stmtPor->execute([$pedidoId]);
        $rowPor = $stmtPor->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'aceitos' => 0];
        $total = (int)($rowPor['total'] ?? 0);
        $aceitos = (int)($rowPor['aceitos'] ?? 0);
        $por = [
            'total_pontos' => $total,
            'pontos_aceitos' => $aceitos,
            // "íntegra" aqui é um proxy simples (>=80% dos pontos aceitos),
            // não uma verificação de hash_previous/hash_current encadeado —
            // isso fica pro qa_get_por_snapshot.php dedicado (Fase 2).
            'rota_integra' => $total > 0 && ($aceitos / $total) >= 0.8,
        ];
    }

    $chat = ['mensagens' => 0];
    if (qaSnapTableExists('chat_mensagens')) {
        $stmtChat = $pdo->prepare('SELECT COUNT(*) FROM chat_mensagens WHERE pedido_id = ?');
        $stmtChat->execute([$pedidoId]);
        $chat['mensagens'] = (int)$stmtChat->fetchColumn();
    }

    $evidencias = ['chegada' => false, 'coleta' => false, 'entrega' => false];
    if (qaSnapTableExists('pedido_evidencias')) {
        $stmtEv = $pdo->prepare(
            "SELECT tipo, COUNT(*) AS total FROM pedido_evidencias WHERE pedido_id = ? AND status = 'accepted' GROUP BY tipo"
        );
        $stmtEv->execute([$pedidoId]);
        foreach ($stmtEv->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['tipo'] === 'coleta') $evidencias['coleta'] = (int)$row['total'] > 0;
            if ($row['tipo'] === 'entrega') $evidencias['entrega'] = (int)$row['total'] > 0;
        }
        // "chegada" (a_caminho -> no_local) não exige evidência de foto no
        // sistema real (ver GuinchoController::atualizarStatus) — usamos o
        // próprio avanço de status como proxy.
        $evidencias['chegada'] = in_array((string)$pedido['status'], ['no_local', 'em_reboque', 'concluido'], true);
    }

    echo json_encode([
        'ok' => true,
        'pedido' => [
            'id' => (int)$pedido['id'],
            'status' => $pedido['status'],
            'guincho_id' => $pedido['guincho_id'] !== null ? (int)$pedido['guincho_id'] : null,
            'attendance_mode' => $pedido['attendance_mode'] ?? null,
        ],
        'pagamento' => $pagamento ? [
            'status' => $pagamento['status'],
            'valor' => (float)$pagamento['valor_total'],
        ] : null,
        'por' => $por,
        'chat' => $chat,
        'evidencias' => $evidencias,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
