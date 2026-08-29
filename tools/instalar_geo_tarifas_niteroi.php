<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Cidade.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

// §PRECO-POR-CIDADE-01: instala o centro geográfico de Niterói/RJ (pra
// Cidade::resolverPorCoordenada() passar a reconhecer pedidos dessa
// cidade-alvo automaticamente) + uma tarifa de reboque ESPECÍFICA para ela
// em /admin/configuracoes?cidade_id=<id>, usada como fallback quando
// nenhuma zona de precificação (pricing_zones) casar com a coordenada.
//
// IMPORTANTE — pré-requisito: rode `php install/migrate.php` ANTES deste
// script (precisa das migrations migration_cidades_v2_geo.sql,
// migration_pricing_zones_v2_cidade.sql e
// migration_service_pricing_v2_cidade.sql já aplicadas, senão as colunas
// novas — lat_centro/lng_centro/raio_km em `cidades` — ainda não existem).
//
// Rodar uma única vez:
//   php tools/instalar_geo_tarifas_niteroi.php
//
// Valores usados:
//  - Coordenadas do centro de Niterói/RJ: -22.8832, -43.1034 (Praça Arariboia,
//    referência geográfica pública do centro da cidade).
//  - Raio de abrangência: 20km (cobre o município de Niterói e a franja de
//    São Gonçalo/Maricá mais próxima — ajustável depois em /admin/cidades).
//  - Tarifas: como o usuário ainda não informou valores DIFERENTES dos
//    globais especificamente para Niterói, este script clona os valores
//    GLOBAIS atuais (o que já está em /admin/configuracoes hoje) para a
//    chave segmentada de Niterói — ponto de partida seguro e 100%
//    reversível, editável a qualquer momento em
//    /admin/configuracoes?cidade_id=<id> sem rodar este script de novo.

$cidade = Cidade::buscarPorSlug('niteroi-rj');
if (!$cidade) {
    echo "[ERRO] Cidade 'niteroi-rj' não encontrada. Rode primeiro: php install/migrate.php\n";
    exit(1);
}
$cidadeId = (int)$cidade['id'];
echo "Cidade encontrada: {$cidade['nome']}/{$cidade['uf']} (id={$cidadeId})\n";

// --- 1) Geo (centro + raio) ---
$latCentro = -22.8832;
$lngCentro = -43.1034;
$raioKm = 20;
Cidade::atualizarGeo($cidadeId, $latCentro, $lngCentro, $raioKm);
echo "Geo instalada: lat_centro={$latCentro}, lng_centro={$lngCentro}, raio_km={$raioKm}\n";

// --- 2) Tarifas de reboque (fallback global -> específico de Niterói) ---
$camposTarifa = [
    'tarifa_por_km' => 5.00,
    'taxa_fixa' => 10.00,
    'tarifa_noturna_km' => 5.50,
    'tarifa_noturna_fixa' => 15.00,
    'tarifa_feriado_km' => 5.50,
    'tarifa_feriado_fixa' => 15.00,
    'tarifa_suv_km' => 4.20,
    'tarifa_suv_fixa' => 12.00,
    'tarifa_caminhonete_km' => 4.80,
    'tarifa_caminhonete_fixa' => 14.00,
    'tarifa_eletrico_km' => 3.50,
    'tarifa_eletrico_fixa' => 10.00,
];

$configGlobal = Configuracao::getAll();
echo "\nAplicando tarifas de Niterói (clonadas do valor GLOBAL atual — ajuste em /admin/configuracoes?cidade_id={$cidadeId}):\n";
foreach ($camposTarifa as $chave => $default) {
    $valorGlobal = $configGlobal[$chave] ?? $default;
    $chaveCidade = $chave . '__cidade_' . $cidadeId;
    Configuracao::set($chaveCidade, (string)$valorGlobal, "Tarifa de Niterói/RJ (clonada do global em " . date('Y-m-d') . ")");
    echo "  {$chaveCidade} = {$valorGlobal}\n";
}

echo "\nConcluído. Verifique/ajuste em:\n";
echo "  - /admin/cidades (geo)\n";
echo "  - /admin/configuracoes?cidade_id={$cidadeId} (tarifas)\n";
