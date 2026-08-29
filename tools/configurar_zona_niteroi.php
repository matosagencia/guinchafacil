<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Acesso negado. Use o terminal.\n");
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Pricing/PricingZone.php';
require_once dirname(__DIR__) . '/src/Models/Pricing/ServicePriceRule.php';

$pdo = getPDO();
$cidade = $pdo->prepare("SELECT id, nome FROM cidades WHERE slug = ? LIMIT 1");
$cidade->execute(['niteroi-rj']);
$cidade = $cidade->fetch(PDO::FETCH_ASSOC);

if (!$cidade) {
    throw new RuntimeException('Cidade niteroi-rj não encontrada. Execute as migrations de cidades primeiro.');
}

// Polígono operacional inicial de Niterói. É uma cobertura administrativa
// conservadora para a fase inicial e pode ser refinada visualmente no admin.
$poligono = json_encode([
    'type' => 'Polygon',
    'coordinates' => [[
        [-43.18, -23.10],
        [-42.94, -23.10],
        [-42.94, -22.78],
        [-43.18, -22.78],
        [-43.18, -23.10],
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$zonaId = PricingZone::criar(
    'NITEROI_GERAL',
    'Niterói — Operação Geral',
    'NITEROI',
    $poligono,
    (int)$cidade['id']
);
if ($zonaId <= 0) {
    throw new RuntimeException('Não foi possível criar ou localizar a zona NITEROI_GERAL.');
}

echo "Zona NITEROI_GERAL id={$zonaId}, cidade={$cidade['nome']} ({$cidade['id']})\n";

$comissao = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = 'comissao_plataforma' LIMIT 1");
$comissao->execute();
$comissaoValor = (float)($comissao->fetchColumn() ?: 0.20);
$comissaoValor = $comissaoValor > 1 ? $comissaoValor / 100 : $comissaoValor;

$stmt = $pdo->query(
    "SELECT spr.*, st.code, st.name
       FROM service_pricing_rules spr
       JOIN service_types st ON st.id = spr.service_type_id
      WHERE spr.cidade_id IS NULL AND spr.active = 1 AND st.active = 1
      ORDER BY st.name ASC"
);
$regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

$consultaExistente = $pdo->prepare(
    "SELECT id FROM service_price_rules
      WHERE pricing_zone_id = ? AND service_type_id = ?
        AND vehicle_category IS NULL AND active = 1
        AND (effective_from IS NULL OR effective_from <= CURDATE())
        AND (effective_until IS NULL OR effective_until >= CURDATE())
      ORDER BY version DESC LIMIT 1"
);

$criados = 0;
$mantidos = 0;
foreach ($regras as $regra) {
    $serviceTypeId = (int)$regra['service_type_id'];
    $consultaExistente->execute([$zonaId, $serviceTypeId]);
    if ($consultaExistente->fetchColumn() !== false) {
        $mantidos++;
        continue;
    }

    $id = ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, [
        'base_customer_price' => (float)$regra['base_fee'] + (float)$regra['labor_fee'],
        'minimum_customer_price' => (float)$regra['minimum_price'],
        'provider_base_amount' => 0,
        'platform_fee_type' => 'PERCENTAGE',
        'platform_fee_value' => $comissaoValor,
        'included_distance_km' => 0,
        'extra_distance_price' => (float)$regra['pickup_km_price'],
        'night_multiplier' => (float)$regra['night_multiplier'],
        'holiday_multiplier' => (float)$regra['holiday_multiplier'],
        'effective_from' => date('Y-m-d'),
    ], null);
    $criados++;
    echo "  regra {$regra['code']} ({$regra['name']}) id={$id}\n";
}

echo "Regras criadas={$criados}; já existentes mantidas={$mantidos}; catálogo considerado=" . count($regras) . "\n";
echo "Carga concluída sem apagar dados.\n";
