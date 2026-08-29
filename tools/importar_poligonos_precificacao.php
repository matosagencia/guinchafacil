<?php

declare(strict_types=1);

// Importa polígonos GeoJSON por código de zona, de forma idempotente.
// Uso: php tools/importar_poligonos_precificacao.php caminho\poligonos.geojson
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Models/Pricing/PricingZone.php';

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Uso: php tools/importar_poligonos_precificacao.php arquivo.geojson\n");
    exit(2);
}
$data = json_decode((string)file_get_contents($path), true);
if (!is_array($data)) throw new RuntimeException('JSON inválido.');
$features = ($data['type'] ?? '') === 'FeatureCollection' ? ($data['features'] ?? []) : [$data];
$ok = 0;
foreach ($features as $feature) {
    $properties = $feature['properties'] ?? [];
    $code = strtoupper(trim((string)($properties['code'] ?? $properties['zone_code'] ?? '')));
    $geometry = $feature['geometry'] ?? $feature;
    if ($code === '' || ($geometry['type'] ?? '') !== 'Polygon') {
        echo "[SKIP] feature sem code ou sem Polygon\n";
        continue;
    }
    $zone = PricingZone::buscarPorCodigo($code);
    if (!$zone) { echo "[SKIP] zona não encontrada: {$code}\n"; continue; }
    $geojson = json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (PricingZone::normalizarGeojson($geojson) === null) { echo "[SKIP] Polygon inválido: {$code}\n"; continue; }
    PricingZone::atualizarPoligono((int)$zone['id'], $geojson);
    echo "[OK] {$code} -> zona #{$zone['id']}\n";
    $ok++;
}
echo "Concluído: {$ok} polígono(s) aplicado(s).\n";