<?php
declare(strict_types=1);

/**
 * tools/aplicar_poligonos_celulas_niteroi.php
 * §CELULAS-NITEROI-01 (04/08/2026): grava nas 5 células de Niterói
 * (pricing_zones.code = niteroi-celula-1..5, semeadas por
 * migration_pricing_zones_v3_expansao.sql) o polígono real calculado a
 * partir das fronteiras oficiais de bairro do OpenStreetMap (casca convexa
 * da união dos bairros de cada célula — ver install/data/celulas_niteroi.geojson
 * para o racional completo e limitações de cada célula).
 *
 * Escopo DELIBERADAMENTE estreito: usa PricingZone::atualizarPoligono(),
 * que só toca a coluna polygon_geojson. NÃO mexe em name, active,
 * cidade_id, ordem_expansao, status_expansao nem bairros_referencia —
 * campos que o admin já pode ter editado manualmente na tela
 * /admin/precificacao/zonas e que não podem ser sobrescritos por engano.
 *
 * Regra suprema do projeto: sem evidência, não está pronto. Por isso este
 * script SEMPRE roda em modo leitura (dry-run) por padrão — só grava com
 * --confirm explícito. Idempotente: se o polígono já gravado for
 * byte-a-byte igual ao novo, a zona é reportada como "sem mudança" e
 * pulada (nunca gera UPDATE/log à toa).
 *
 * Uso:
 *   php tools/aplicar_poligonos_celulas_niteroi.php            (dry-run, não grava nada)
 *   php tools/aplicar_poligonos_celulas_niteroi.php --confirm  (grava de verdade)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Acesso negado. Use o terminal.\n");
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Pricing/PricingZone.php';
require_once dirname(__DIR__) . '/src/Services/Logger.php';

$confirm = in_array('--confirm', array_slice($argv, 1), true);

$geojsonPath = dirname(__DIR__) . '/install/data/celulas_niteroi.geojson';
if (!is_file($geojsonPath)) {
    fwrite(STDERR, "Arquivo não encontrado: {$geojsonPath}\n");
    exit(1);
}

$dados = json_decode((string)file_get_contents($geojsonPath), true);
if (!is_array($dados)) {
    fwrite(STDERR, "JSON inválido em {$geojsonPath}\n");
    exit(1);
}

echo "Modo: " . ($confirm ? "CONFIRM (vai gravar)" : "DRY-RUN (nada será gravado — use --confirm pra aplicar)") . "\n";
echo str_repeat('-', 70) . "\n";

$totais = ['ok' => 0, 'sem_mudanca' => 0, 'nao_encontrada' => 0, 'invalido' => 0, 'falha' => 0];

foreach ($dados as $code => $entrada) {
    $zona = PricingZone::buscarPorCodigo((string)$code);
    if (!$zona) {
        echo "[NÃO ENCONTRADA] {$code} — zona não existe em pricing_zones (rode install/migrate.php?)\n";
        $totais['nao_encontrada']++;
        continue;
    }

    $geojson = $entrada['geojson'] ?? null;
    if (!is_array($geojson) || ($geojson['type'] ?? '') !== 'Polygon') {
        echo "[INVÁLIDO] {$code} (zona #{$zona['id']}) — geojson ausente ou não é Polygon\n";
        $totais['invalido']++;
        continue;
    }

    $novoRaw = json_encode($geojson, JSON_UNESCAPED_UNICODE);
    $normalizadoNovo = PricingZone::normalizarGeojson($novoRaw);
    if ($normalizadoNovo === null) {
        echo "[INVÁLIDO] {$code} (zona #{$zona['id']}) — falhou na validação de PricingZone::normalizarGeojson\n";
        $totais['invalido']++;
        continue;
    }

    $atual = $zona['polygon_geojson'] ?? null;
    $atualNormalizado = $atual !== null ? json_encode(json_decode((string)$atual, true), JSON_UNESCAPED_UNICODE) : null;
    $novoNormalizado = json_encode(json_decode($normalizadoNovo, true), JSON_UNESCAPED_UNICODE);

    $nPontos = count($geojson['coordinates'][0] ?? []);
    $areaKm2 = $entrada['area_hull_km2'] ?? '?';
    $bairrosOk = $entrada['bairros_ok'] ?? '?';
    $bairrosTotal = $entrada['bairros_total'] ?? '?';

    if ($atualNormalizado === $novoNormalizado) {
        echo "[SEM MUDANÇA] {$code} (zona #{$zona['id']}, {$zona['name']}) — polígono já está gravado e é idêntico\n";
        $totais['sem_mudanca']++;
        continue;
    }

    $estadoAnterior = $atual ? 'já tinha polígono (será substituído)' : 'sem polígono ainda';
    echo "[GRAVAR] {$code} (zona #{$zona['id']}, {$zona['name']})\n";
    echo "         estado anterior: {$estadoAnterior}\n";
    echo "         novo polígono: {$nPontos} pontos, área ~{$areaKm2} km², bairros geocodificados {$bairrosOk}/{$bairrosTotal}\n";

    if (!$confirm) {
        $totais['ok']++;
        continue;
    }

    $sucesso = PricingZone::atualizarPoligono((int)$zona['id'], $normalizadoNovo);
    if (!$sucesso) {
        echo "         FALHA ao gravar.\n";
        $totais['falha']++;
        continue;
    }

    Logger::log(Logger::LEVEL_INFO, 'ToolAplicarPoligonosCelulas', 'run', 'precificacao',
        "Polígono da célula {$code} (zona #{$zona['id']}) atualizado via tools/aplicar_poligonos_celulas_niteroi.php",
        ['zona_id' => (int)$zona['id'], 'code' => $code, 'pontos' => $nPontos, 'area_km2' => $areaKm2]);

    echo "         gravado com sucesso.\n";
    $totais['ok']++;
}

echo str_repeat('-', 70) . "\n";
echo "Resumo: {$totais['ok']} " . ($confirm ? 'gravadas' : 'elegíveis para gravação') . ", {$totais['sem_mudanca']} sem mudança, {$totais['nao_encontrada']} não encontradas, {$totais['invalido']} inválidas, {$totais['falha']} falhas.\n";

if (!$confirm && $totais['ok'] > 0) {
    echo "\nRode novamente com --confirm para gravar de verdade.\n";
}
