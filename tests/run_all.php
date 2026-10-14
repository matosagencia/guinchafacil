<?php
declare(strict_types=1);

/**
 * Runner unificado de testes da rodada 12/10/2026.
 * Cobre: pedido_eventos (§10), bloqueio financeiro (§9),
 *        anéis de disponibilidade (§7), configurações (§5.7).
 *
 * Uso:
 *   php tests/run_all.php           -- coord Rio (default)
 *   php tests/run_all.php --campinas -- coord Nazaré Paulista
 *   php tests/run_all.php --pedido=50 -- inclui pedido específico
 *
 * Gera tests/last_run.json. Nunca persiste mudança.
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

$_SERVER['REQUEST_URI']     = '/cli';
$_SERVER['REQUEST_METHOD']  = 'CLI';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli-runner';
$_SERVER['SCRIPT_NAME']     = '/cli.php';
if (!defined('REQUEST_ID'))       define('REQUEST_ID', 'cli_' . bin2hex(random_bytes(4)));
if (!defined('CSP_SCRIPT_NONCE')) define('CSP_SCRIPT_NONCE', 'cli');

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';
require_once $raiz . '/src/Services/PedidoEventoService.php';
require_once $raiz . '/src/Services/Finance/BloqueioFinanceiroService.php';
require_once $raiz . '/src/Services/GuinchoDisponibilidade.php';

$argv = $_SERVER['argv'] ?? [];
$coord = [
    'nome' => 'Rio (Gamboa)',
    'lat'  => -22.89732378,
    'lng'  => -43.19546092,
];
if (in_array('--campinas', $argv, true)) {
    $coord = ['nome' => 'Campinas (Nazaré Paulista)', 'lat' => -23.1794793, 'lng' => -46.4372655];
}
$pedidoId = null;
foreach ($argv as $a) {
    if (preg_match('~^--pedido=(\d+)$~', $a, $m)) { $pedidoId = (int)$m[1]; }
}

$resultado = [
    'gerado_em' => date('c'),
    'coordenada'=> $coord,
    'pedido_id' => $pedidoId,
    'blocos'    => [],
];

function bloco(string $nome, callable $fn): array {
    $inicio = microtime(true);
    try {
        $dados = $fn();
        return [
            'ok'         => true,
            'duracao_ms' => round((microtime(true) - $inicio) * 1000, 2),
            'dados'      => $dados,
        ];
    } catch (Throwable $e) {
        return [
            'ok'         => false,
            'duracao_ms' => round((microtime(true) - $inicio) * 1000, 2),
            'erro'       => $e->getMessage(),
            'arquivo'    => basename($e->getFile()) . ':' . $e->getLine(),
        ];
    }
}

$pdo = getPDO();

// —— Bloco 1: estado geral de pedido_eventos ——
$resultado['blocos']['pedido_eventos_geral'] = bloco('geral', function () use ($pdo) {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM pedido_eventos')->fetchColumn();
    $porEvento = $pdo->query(
        "SELECT evento, COUNT(*) AS qtd FROM pedido_eventos GROUP BY evento ORDER BY qtd DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    return ['total' => $total, 'por_evento' => $porEvento];
});

// —— Bloco 2: schema de pedido_eventos tem colunas v2 ——
$resultado['blocos']['pedido_eventos_schema'] = bloco('schema', function () use ($pdo) {
    $stmt = $pdo->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedido_eventos'"
    );
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = ['id','pedido_id','ator_id','ator_tipo','evento',
                  'status_anterior','status_novo','motivo','context_json','created_at'];
    $faltando = array_values(array_diff($esperadas, $cols));
    return ['colunas' => $cols, 'faltando' => $faltando, 'ok_migracao_v2' => empty($faltando)];
});

// —— Bloco 3: simulação de gravação com rollback ——
$resultado['blocos']['pedido_eventos_simulacao'] = bloco('simulacao', function () use ($pdo, $pedidoId) {
    $id = $pedidoId ?? (int)$pdo->query('SELECT id FROM pedidos ORDER BY id LIMIT 1')->fetchColumn();
    if ($id <= 0) return ['skip' => 'nenhum pedido no banco'];
    $pdo->beginTransaction();
    try {
        PedidoEventoService::registrar(
            $pdo, $id, 'teste_simulado',
            'de_teste', 'para_teste',
            'system', 0, 'runner unificado'
        );
        $stmt = $pdo->prepare(
            'SELECT id, ator_tipo, status_anterior, status_novo, motivo
               FROM pedido_eventos WHERE pedido_id = ? AND evento = ?
               ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$id, 'teste_simulado']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $pdo->rollBack();
        return [
            'pedido_id'   => $id,
            'gravou'      => (bool)$row,
            'linha'       => $row ?: null,
            'rollback_ok' => !$pdo->inTransaction(),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
});

// —— Bloco 4: bloqueio financeiro (§9) ——
$resultado['blocos']['bloqueio_financeiro'] = bloco('bloqueio', function () use ($pdo) {
    BloqueioFinanceiroService::limparCache();
    $ids = BloqueioFinanceiroService::idsBloqueados();
    $faturas = $pdo->query(
        "SELECT id, parceiro_id, status, saldo, vencimento_em
           FROM faturas_parceiro
          WHERE status IN ('vencida','bloqueada')
          ORDER BY vencimento_em"
    )->fetchAll(PDO::FETCH_ASSOC);
    return [
        'ids_bloqueados'  => $ids,
        'faturas_abertas' => $faturas,
        'total_faturas'   => (int)$pdo->query('SELECT COUNT(*) FROM faturas_parceiro')->fetchColumn(),
    ];
});

// —— Bloco 5: anéis de disponibilidade (§7) ——
$resultado['blocos']['aneis_disponibilidade'] = bloco('aneis', function () use ($coord) {
    $aneis = GuinchoDisponibilidade::candidatosPorAnel($coord['lat'], $coord['lng']);
    $escolhido = GuinchoDisponibilidade::escolherAnel($aneis);
    $resumo = [];
    foreach ($aneis as $a) {
        $resumo[] = [
            'min_km'     => $a['min_km'],
            'max_km'     => $a['max_km'],
            'centro_km'  => $a['centro_km'],
            'elegiveis'  => $a['elegiveis'],
            'top3'       => array_slice($a['candidatos'], 0, 3),
        ];
    }
    return [
        'aneis'             => $resumo,
        'anel_escolhido'    => $escolhido,
        'fallback_whatsapp' => $escolhido === null,
    ];
});

// —— Bloco 6: configurações (§5.7) ——
$resultado['blocos']['configuracoes'] = bloco('config', function () use ($pdo) {
    $stmt = $pdo->query(
        "SELECT chave, valor FROM configuracoes
          WHERE chave IN (
            'comissao_plataforma',
            'comissao_assistencia_percentual',
            'reserva_gateway_percentual',
            'reserva_gateway_pix',
            'reserva_gateway_debito',
            'reserva_gateway_credito',
            'reserva_gateway_boleto',
            'ganho_elevacao_faixas',
            'ganho_elevacao_mult',
            'guincho_anel_min_candidatos',
            'guincho_aneis_km',
            'raio_maximo_km',
            'tempo_expiracao_min'
          ) ORDER BY chave"
    );
    $mapa = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mapa[$r['chave']] = $r['valor'];
    }
    return [
        'valores'            => $mapa,
        'comissao_ok'        => isset($mapa['comissao_plataforma'])
                                && abs((float)$mapa['comissao_plataforma'] - 0.08) < 0.0001,
        'duplicata_removida' => !isset($mapa['comissao_assistencia_percentual']),
        'aneis_cfg_ok'       => isset($mapa['guincho_aneis_km']),
        'min_cand_ok'        => isset($mapa['guincho_anel_min_candidatos']),
        'gateway_dinamico_ok'=> isset($mapa['reserva_gateway_pix'])
                                && isset($mapa['reserva_gateway_credito']),
        'elevacao_cfg_ok'    => isset($mapa['ganho_elevacao_faixas'])
                                && isset($mapa['ganho_elevacao_mult']),
    ];
});

// —— Bloco 7: pedido específico (se informado) ——
if ($pedidoId !== null) {
    $resultado['blocos']['pedido_especifico'] = bloco('pedido', function () use ($pdo, $pedidoId) {
        $stmt = $pdo->prepare('SELECT id, status, service_type_id, tipo_problema FROM pedidos WHERE id = ?');
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare(
            "SELECT id, evento, ator_tipo, ator_id, status_anterior, status_novo, motivo, created_at
               FROM pedido_eventos WHERE pedido_id = ? ORDER BY id"
        );
        $stmt->execute([$pedidoId]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['pedido' => $pedido, 'eventos' => $eventos, 'qtd_eventos' => count($eventos)];
    });
}

// —— Bloco 8: distribuição de service_type_id (§8 backfill) ——
$resultado['blocos']['service_type_distribuicao'] = bloco('catalogo', function () use ($pdo) {
    $stmt = $pdo->query(
        "SELECT tipo_problema, service_type_id, COUNT(*) AS qtd
           FROM pedidos
          GROUP BY tipo_problema, service_type_id
          ORDER BY tipo_problema, service_type_id"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

// —— Saída ——
$json = json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(__DIR__ . '/last_run.json', $json);

echo "=== Runner rodada 12/10/2026 ===\n";
echo "Coordenada: {$coord['nome']} ({$coord['lat']}, {$coord['lng']})\n";
if ($pedidoId !== null) echo "Pedido: #{$pedidoId}\n";
echo "\n";

foreach ($resultado['blocos'] as $nome => $b) {
    $st = $b['ok'] ? 'OK ' : 'ERR';
    printf("[%s] %-32s %6.1fms\n", $st, $nome, $b['duracao_ms']);
}

echo "\n--- Resumo dos checks ---\n";
$c = $resultado['blocos'];
$checks = [
    'migracao_v2_aplicada'  => ($c['pedido_eventos_schema']['dados']['ok_migracao_v2'] ?? false),
    'gravacao_funciona'     => ($c['pedido_eventos_simulacao']['dados']['gravou'] ?? false),
    'rollback_ok'           => ($c['pedido_eventos_simulacao']['dados']['rollback_ok'] ?? false),
    'comissao_8pct'         => ($c['configuracoes']['dados']['comissao_ok'] ?? false),
    'duplicata_removida'    => ($c['configuracoes']['dados']['duplicata_removida'] ?? false),
    'aneis_configurados'    => ($c['configuracoes']['dados']['aneis_cfg_ok'] ?? false),
    'min_candidatos_ok'     => ($c['configuracoes']['dados']['min_cand_ok'] ?? false),
    'gateway_dinamico_ok'   => ($c['configuracoes']['dados']['gateway_dinamico_ok'] ?? false),
    'elevacao_cfg_ok'       => ($c['configuracoes']['dados']['elevacao_cfg_ok'] ?? false),
    'bloqueio_sem_sujeira'  => empty($c['bloqueio_financeiro']['dados']['ids_bloqueados'] ?? [1]),
    'aneis_retornam_array'  => is_array($c['aneis_disponibilidade']['dados']['aneis'] ?? null),
];
foreach ($checks as $k => $v) {
    printf("  %-26s %s\n", $k, $v ? 'sim' : 'NAO');
}

echo "\nJSON salvo em: tests/last_run.json\n";