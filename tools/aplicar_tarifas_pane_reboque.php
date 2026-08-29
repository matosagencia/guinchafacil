<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Pricing/PricingZone.php';
require_once dirname(__DIR__) . '/src/Models/Pricing/ServicePriceRule.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ServiceType.php';

// Aplica os valores de tarifa definidos pelo usuário em 26/07/2026 (análise
// financeira + referências de mercado do Rio de Janeiro), via Etapa 13
// (zona de precificação) — não via service_pricing_rules (Etapa 9), porque
// aquela tabela não distingue carro de moto (só uma regra por tipo de
// serviço) e o usuário quer exatamente essa distinção.
//
// Cria uma zona "RJ_GERAL" cobrindo o município do Rio de Janeiro inteiro
// (retângulo generoso, não o contorno real da cidade — suficiente para
// "casar" com qualquer pedido de verdade na operação atual; pode ser
// refinado depois em /admin/precificacao/zonas com o polígono real, sem
// precisar rodar este script de novo). Rodar uma única vez:
//   php tools/aplicar_tarifas_pane_reboque.php

$poligonoRJ = json_encode([
    'type' => 'Polygon',
    'coordinates' => [[
        [-43.85, -23.15], [-43.05, -23.15], [-43.05, -22.70], [-43.85, -22.70], [-43.85, -23.15],
    ]],
], JSON_UNESCAPED_UNICODE);

$zonaId = PricingZone::criar('RJ_GERAL', 'Rio de Janeiro — Geral', null, $poligonoRJ);
echo "Zona RJ_GERAL id={$zonaId}\n";

function aplicarRegra(int $zonaId, string $codigoServico, ?string $categoriaVeiculo, array $dados): void
{
    $tipo = ServiceType::buscarPorCodigo($codigoServico);
    if (!$tipo) {
        echo "  [AVISO] service_type '{$codigoServico}' não encontrado — pulei (rode as migrations do catálogo de serviços primeiro).\n";
        return;
    }
    $regraId = ServicePriceRule::criarNovaVersao($zonaId, (int)$tipo['id'], $dados, $categoriaVeiculo);
    echo "  regra {$codigoServico} / " . ($categoriaVeiculo ?? 'qualquer') . " -> id={$regraId}\n";
}

echo "Pane elétrica (deslocamento + diagnóstico somados em base_customer_price — taxa fixa, sem R$/km):\n";
// base_customer_price aqui já soma deslocamento (R$59) + diagnóstico (R$49)
// = R$108, igual à análise do usuário. O mínimo é o mesmo valor (não há
// componente variável por km nesta regra).
aplicarRegra($zonaId, 'ELECTRICAL_DIAGNOSIS', 'carro', [
    'base_customer_price' => 108.00,
    'minimum_customer_price' => 108.00,
    'provider_base_amount' => 0,
    'platform_fee_type' => 'PERCENTAGE',
    'platform_fee_value' => 0.20,
    'night_multiplier' => 1.25,
    'holiday_multiplier' => 1.25,
]);
aplicarRegra($zonaId, 'ELECTRICAL_DIAGNOSIS', 'moto', [
    'base_customer_price' => 74.00,   // deslocamento R$39 + diagnóstico R$35
    'minimum_customer_price' => 74.00,
    'provider_base_amount' => 0,
    'platform_fee_type' => 'PERCENTAGE',
    'platform_fee_value' => 0.20,
    'night_multiplier' => 1.25,
    'holiday_multiplier' => 1.25,
]);

echo "Reboque (taxa de saída + R\$/km, mínimo):\n";
aplicarRegra($zonaId, 'TOW_CAR', 'carro', [
    'base_customer_price' => 129.00,
    'minimum_customer_price' => 189.00,
    'included_distance_km' => 0,
    'extra_distance_price' => 8.00,
    'provider_base_amount' => 0,
    'platform_fee_type' => 'PERCENTAGE',
    'platform_fee_value' => 0.20,
    'night_multiplier' => 1.25,
    'holiday_multiplier' => 1.25,
]);
aplicarRegra($zonaId, 'TOW_MOTORCYCLE', 'moto', [
    'base_customer_price' => 89.00,
    'minimum_customer_price' => 129.00,
    'included_distance_km' => 0,
    'extra_distance_price' => 5.00,
    'provider_base_amount' => 0,
    'platform_fee_type' => 'PERCENTAGE',
    'platform_fee_value' => 0.20,
    'night_multiplier' => 1.25,
    'holiday_multiplier' => 1.25,
]);

echo "\nConcluído. Regras versionadas em service_price_rules, zona RJ_GERAL cobrindo todo o município.\n";
echo "Revise/ajuste em /admin/precificacao/zonas -> Regras (inclusive o polígono, que hoje é um retângulo aproximado).\n";

