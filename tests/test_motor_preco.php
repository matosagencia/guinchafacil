<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$_SERVER['REQUEST_URI'] = '/cli';
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'cli';
$_SERVER['SCRIPT_NAME'] = '/cli.php';
if (!defined('REQUEST_ID')) define('REQUEST_ID', 'cli_' . bin2hex(random_bytes(4)));
if (!defined('CSP_SCRIPT_NONCE')) define('CSP_SCRIPT_NONCE', 'cli');

$raiz = dirname(__DIR__);
require_once $raiz . '/config.php';
if (!function_exists('getPDO')) require_once $raiz . '/src/Database.php';
require_once $raiz . '/src/Services/GeoService.php';
require_once $raiz . '/src/Services/Precificacao/PrecificacaoService.php';

// =====================================================================
// CASOS — Nazaré Paulista -> Campinas (correto: -22.8975, -47.0683)
// =====================================================================
$casos = [
    [
        'nome'             => 'Campinas (real)',
        'lat_origem'       => -23.1794793,
        'lng_origem'       => -46.4372655,
        'lat_destino'      => -22.8975,
        'lng_destino'      => -47.0683,
        'service_type_id'  => 1,
        'vehicle_category' => 'carro',
        'pricing_zone_id'  => 1,
        'pedagio'          => 27.20,
        'quando' => '2026-10-13 14:00:00',
    ],
    [
        'nome'             => 'Curto urbano (Rio, ~2 km)',
        'lat_origem'       => -22.89732378,
        'lng_origem'       => -43.19546092,
        'lat_destino'      => -22.91000000,
        'lng_destino'      => -43.19000000,
        'service_type_id'  => 1,
        'vehicle_category' => 'carro',
        'pricing_zone_id'  => 1,
        'pedagio'          => 0.0,
    ],
    [
        'nome'             => 'Moto (mesmo Campinas)',
        'lat_origem'       => -23.1794793,
        'lng_origem'       => -46.4372655,
        'lat_destino'      => -22.8975,
        'lng_destino'      => -47.0683,
        'service_type_id'  => 2,
        'vehicle_category' => 'moto',
        'pricing_zone_id'  => 1,
        'pedagio'          => 27.20,
    ],
];

$sep = str_repeat('=', 60);
$pdo = getPDO();

// =====================================================================
// BLOCO 1 — CÁLCULO DE PREÇO
// =====================================================================
foreach ($casos as $caso) {
    echo "\n{$sep}\n";
    echo "  {$caso['nome']}\n";
    echo "{$sep}\n";

    $r = PrecificacaoService::calcular($caso);
    if (!$r['ok']) {
        echo "  ERRO: {$r['erro']}\n";
        continue;
    }

    echo "  PREÇO CLIENTE:  R$ " . number_format($r['preco_cliente'], 2, ',', '.') . "\n\n";
    echo "  Detalhamento:\n";
    foreach ($r['detalhamento'] as $k => $v) {
        if (is_array($v)) {
            echo sprintf("    %-22s %s\n", $k, json_encode($v, JSON_UNESCAPED_UNICODE));
        } else {
            echo sprintf("    %-22s %s\n", $k, $v);
        }
    }
    if ($r['anel_usado']) {
        echo "\n  Anel usado: {$r['anel_usado']['min_km']}–{$r['anel_usado']['max_km']} km "
           . "(deadhead ref {$r['anel_usado']['centro_km']} km, {$r['anel_usado']['elegiveis']} elegíveis)\n";
    }
    echo "\n  Regra: id=" . ($r['regra_id'] ?? '-') . " version=" . ($r['regra_version'] ?? '-') . "\n";
}

// =====================================================================
// BLOCO 2 — DIAGNÓSTICO: service_price_rules
// =====================================================================
echo "\n{$sep}\n";
echo "  DIAGNÓSTICO: service_price_rules\n";
echo "{$sep}\n";

try {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM service_price_rules')->fetchColumn();
    echo "  Total de linhas: {$total}\n\n";

    if ($total > 0) {
        $stmt = $pdo->query(
            "SELECT id, pricing_zone_id, service_type_id, vehicle_category,
                    base_customer_price, minimum_customer_price, maximum_customer_price,
                    included_distance_km, extra_distance_price,
                    night_multiplier, holiday_multiplier,
                    active, version, effective_from, effective_until
               FROM service_price_rules
              ORDER BY service_type_id, version DESC
              LIMIT 30"
        );
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cols = array_keys($linhas[0]);
        foreach ($linhas as $i => $l) {
            echo "  --- Linha " . ($i + 1) . " ---\n";
            foreach ($cols as $c) {
                echo sprintf("    %-24s %s\n", $c, $l[$c] ?? 'NULL');
            }
            echo "\n";
        }
    } else {
        echo "  Tabela vazia. Nenhuma regra cadastrada.\n";
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// =====================================================================
// BLOCO 3 — DIAGNÓSTICO: colunas de service_price_rules
// =====================================================================
echo "{$sep}\n";
echo "  COLUNAS de service_price_rules\n";
echo "{$sep}\n";
try {
    $stmt = $pdo->query(
        "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'service_price_rules'
          ORDER BY ORDINAL_POSITION"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        echo sprintf("  %-30s %-20s null=%s default=%s\n",
            $c['COLUMN_NAME'], $c['COLUMN_TYPE'],
            $c['IS_NULLABLE'], $c['COLUMN_DEFAULT'] ?? '-');
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// =====================================================================
// BLOCO 4 — DIAGNÓSTICO: order_price_snapshots (para o INSERT funcionar)
// =====================================================================
echo "\n{$sep}\n";
echo "  COLUNAS de order_price_snapshots\n";
echo "{$sep}\n";
try {
    $stmt = $pdo->query(
        "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'order_price_snapshots'
          ORDER BY ORDINAL_POSITION"
    );
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$cols) {
        echo "  Tabela nao existe.\n";
    } else {
        foreach ($cols as $c) {
            echo sprintf("  %-30s %-20s null=%s\n",
                $c['COLUMN_NAME'], $c['COLUMN_TYPE'], $c['IS_NULLABLE']);
        }
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

// =====================================================================
// BLOCO 5 — DIAGNÓSTICO: fallbacks em configuracoes
// =====================================================================
echo "\n{$sep}\n";
echo "  Chaves legadas em configuracoes (fallbacks do motor)\n";
echo "{$sep}\n";
try {
    $stmt = $pdo->query(
        "SELECT chave, valor FROM configuracoes
          WHERE chave IN (
            'taxa_fixa','tarifa_por_km','tarifa_moto_fixa','tarifa_moto_km',
            'tarifa_suv_fixa','tarifa_suv_km','tarifa_noturna_fixa','tarifa_noturna_km',
            'comissao_plataforma','reserva_gateway_percentual',
            'ganho_elevacao_faixas','ganho_elevacao_mult'
          )
          ORDER BY chave"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo sprintf("  %-32s %s\n", $r['chave'], $r['valor']);
    }
} catch (Throwable $e) {
    echo "  ERRO: " . $e->getMessage() . "\n";
}

echo "\n{$sep}\n  FIM\n{$sep}\n";