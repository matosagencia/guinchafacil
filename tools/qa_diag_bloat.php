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

// Diagnóstico pontual: checar se o acúmulo de dados de teste (rodadas
// repetidas do gate ao longo da sessão) já é grande o bastante pra explicar
// o timeout persistente de E2E-CAN-002 (POST /cliente/cancelar/ demorando
// mais que 180s só no gate completo, nunca isolado).
$pdo = getPDO();
$tabelas = ['pedidos', 'pedido_localizacoes', 'pedido_evidencias', 'pedido_percurso_resumos', 'guinchos', 'usuarios', 'cancelamento_snapshots', 'chat_mensagens'];

foreach ($tabelas as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "$t: $count\n";
    } catch (Throwable $e) {
        echo "$t: erro (" . $e->getMessage() . ")\n";
    }
}

echo "\n--- EXPLAIN da query de cancelamento (preview) ---\n";
$stmt = $pdo->query("SHOW TABLE STATUS LIKE 'pedidos'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

echo "\n--- Indices em cancelamento_snapshots ---\n";
try {
    print_r($pdo->query("SHOW INDEX FROM cancelamento_snapshots")->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "erro: " . $e->getMessage() . "\n";
}

echo "\n--- Indices em pedido_percurso_resumos ---\n";
try {
    print_r($pdo->query("SHOW INDEX FROM pedido_percurso_resumos")->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
    echo "erro: " . $e->getMessage() . "\n";
}
