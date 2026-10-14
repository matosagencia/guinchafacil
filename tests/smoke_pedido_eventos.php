<?php
declare(strict_types=1);

/**
 * Smoke test da instrumentação de pedido_eventos.
 *
 * Uso:
 *   php tests/smoke_pedido_eventos.php              -- relatório geral
 *   php tests/smoke_pedido_eventos.php 50           -- eventos do pedido 50
 *   php tests/smoke_pedido_eventos.php --simulate 50 -- grava em transação e desfaz
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script só roda via CLI.\n");
    exit(1);
}

// Ambiente mínimo para o config.php não reclamar
$_SERVER['REQUEST_URI']    = $_SERVER['REQUEST_URI']    ?? '/cli';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
$_SERVER['REMOTE_ADDR']    = $_SERVER['REMOTE_ADDR']    ?? '127.0.0.1';
$_SERVER['HTTP_USER_AGENT']= $_SERVER['HTTP_USER_AGENT']?? 'cli';
$_SERVER['SCRIPT_NAME']    = $_SERVER['SCRIPT_NAME']    ?? '/cli.php';

if (!defined('REQUEST_ID')) {
    define('REQUEST_ID', 'cli_' . bin2hex(random_bytes(4)));
}
if (!defined('CSP_SCRIPT_NONCE')) {
    define('CSP_SCRIPT_NONCE', 'cli');
}

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';

if (!function_exists('getPDO')) {
    require_once $raiz . '/src/Database.php';
}

require_once $raiz . '/src/Services/PedidoEventoService.php';

$argv = $_SERVER['argv'] ?? [];
$simulate = in_array('--simulate', $argv, true);
$pedidoId = null;
foreach ($argv as $a) {
    if (ctype_digit((string)$a)) { $pedidoId = (int)$a; break; }
}

function linha(string $s = ''): void { echo $s . PHP_EOL; }
function titulo(string $s): void { linha(); linha('== ' . $s . ' =='); }

$pdo = getPDO();

titulo('Estado de pedido_eventos');
$total = (int)$pdo->query('SELECT COUNT(*) FROM pedido_eventos')->fetchColumn();
linha("Total de eventos: {$total}");

$porEvento = $pdo->query(
    "SELECT evento, COUNT(*) AS total
       FROM pedido_eventos
      GROUP BY evento
      ORDER BY total DESC, evento ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if ($porEvento) {
    linha();
    linha(sprintf('  %-40s %s', 'evento', 'qtd'));
    foreach ($porEvento as $r) {
        linha(sprintf('  %-40s %d', $r['evento'], $r['total']));
    }
} else {
    linha('Nenhum evento. A instrumentação ainda não foi acionada.');
}

if ($pedidoId !== null) {
    titulo("Eventos do pedido {$pedidoId}");
    $stmt = $pdo->prepare(
        "SELECT id, evento, created_at,
                JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.status_anterior')) AS de,
                JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.status_novo'))     AS para,
                JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.ator_tipo'))       AS ator,
                JSON_EXTRACT(context_json, '$.ator_id')                       AS ator_id
           FROM pedido_eventos
          WHERE pedido_id = ?
          ORDER BY id ASC"
    );
    $stmt->execute([$pedidoId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        linha('Nenhum evento para este pedido.');
    } else {
        foreach ($rows as $r) {
            linha(sprintf(
                '  #%d %-32s %s | %s -> %s | ator=%s/%s',
                (int)$r['id'],
                (string)$r['evento'],
                (string)$r['created_at'],
                $r['de']   ?? '-',
                $r['para'] ?? '-',
                $r['ator'] ?? '-',
                $r['ator_id'] ?? '-'
            ));
        }
    }
}

if ($simulate && $pedidoId !== null) {
    titulo('Simulação de gravação (rollback ao final)');
    try {
        $pdo->beginTransaction();
        PedidoEventoService::registrar(
            $pdo, $pedidoId, 'teste_simulado',
            'de_teste', 'para_teste',
            'system', 0, 'simulação via CLI'
        );
        $stmt = $pdo->prepare(
            'SELECT id, evento FROM pedido_eventos
              WHERE pedido_id = ? AND evento = ?
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$pedidoId, 'teste_simulado']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            linha("OK — evento gravado dentro da transação (id=" . $row['id'] . ").");
        } else {
            linha('FALHA — o INSERT não produziu linha visível.');
        }
        $pdo->rollBack();
        linha('Rollback executado. Nada foi persistido.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        linha('ERRO: ' . $e->getMessage());
    }
}

titulo('Fim');