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

// Utilitário de QA: devolve o status atual (e alguns campos financeiros) de
// um pedido diretamente do banco. Criado para os testes de
// funcionario-gerente-demandas.spec.ts, que logam como funcionário/gerente
// (não admin/cliente) — as rotas HTTP de status-json exigem esses outros
// perfis, então checar via CLI evita depender de uma segunda sessão só pra
// verificar o efeito real de uma demanda aprovada.
if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_pedido_status.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $stmt = getPDO()->prepare(
        'SELECT id, status, guincho_id, concluido_manualmente, revisao_manual_status, observacao_interna
           FROM pedidos WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        echo json_encode(['ok' => false, 'erro' => 'Pedido não encontrado.']) . PHP_EOL;
        exit;
    }

    $stmtPag = getPDO()->prepare("SELECT status, valor_total FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
    $stmtPag->execute([$pedidoId]);
    $pagamento = $stmtPag->fetch(PDO::FETCH_ASSOC) ?: null;

    echo json_encode([
        'ok' => true,
        'pedido_id' => (int)$pedido['id'],
        'status' => $pedido['status'],
        'guincho_id' => $pedido['guincho_id'] !== null ? (int)$pedido['guincho_id'] : null,
        'concluido_manualmente' => (bool)$pedido['concluido_manualmente'],
        'revisao_manual_status' => $pedido['revisao_manual_status'],
        'observacao_interna' => $pedido['observacao_interna'],
        'pagamento_status' => $pagamento['status'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
