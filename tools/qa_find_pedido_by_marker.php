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

// Utilitário de QA: localiza o pedido mais recente cuja descricao_problema
// contém o marcador passado em argv[1]. Usado por
// qa/suites/onboarding-completo.spec.ts para descobrir o pedido_id de um
// pedido criado de verdade via UI (pedidonovo.php), já que o redirect após
// criar pode ir tanto para /cliente/dashboard?msg=pedido_criado (fluxo livre)
// quanto para /pagamento/checkout/{id} (fluxo com pagamento obrigatório) —
// em vez de tentar adivinhar pela URL, o teste escreve um marcador único no
// campo "descricao" do formulário e busca por ele aqui.
if ($argc < 2 || trim($argv[1]) === '') {
    fwrite(STDERR, '[ERRO] Uso: php qa_find_pedido_by_marker.php <marcador>' . PHP_EOL);
    exit(1);
}

$marker = $argv[1];

try {
    $stmt = getPDO()->prepare(
        "SELECT id, status, cliente_id, guincho_id
           FROM pedidos
          WHERE descricao_problema LIKE ?
          ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute(['%' . $marker . '%']);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode(['ok' => false, 'erro' => 'Nenhum pedido encontrado com esse marcador.']) . PHP_EOL;
        exit;
    }

    echo json_encode([
        'ok' => true,
        'pedido_id' => (int)$pedido['id'],
        'status' => $pedido['status'],
        'cliente_id' => (int)$pedido['cliente_id'],
        'guincho_id' => $pedido['guincho_id'] !== null ? (int)$pedido['guincho_id'] : null,
    ]) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
