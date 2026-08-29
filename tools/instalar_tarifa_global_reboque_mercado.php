<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

// §PRECO-POR-CIDADE-01 (02/08/2026): até aqui, a tarifa GLOBAL de reboque
// (chaves SEM sufixo __cidade_X — usada por QUALQUER cidade sem override
// próprio, ver TarifaService::cfgPorCidade()) nunca tinha sido atualizada
// com uma referência de mercado real — só existiam os valores de
// instalação original (placeholder). Este script corrige isso, calibrando
// a tarifa GLOBAL como uma média conservadora de mercado urbano no Brasil
// (mais branda que a de Niterói/RJ, instalada à parte em
// tools/atualizar_tarifas_niteroi_mercado.php — aquela é específica e
// prevalece sobre esta pra guinchos que atendem Niterói).
//
// Referência de mercado usada (pesquisa geral de reboque urbano no Brasil):
//  - Taxa de acionamento/base: R$80–R$200 dependendo da região.
//  - R$/km rodado: R$4–R$8/km.
//  - Adicional noturno/feriado: +25% a +40% sobre o valor normal.
//
// Valores adotados pra tarifa GLOBAL (ponto médio conservador, pensado pra
// não penalizar cidades sem pesquisa de mercado própria — sempre ajustável
// em /admin/configuracoes, sem cidade selecionada):
//  - taxa_fixa: R$120,00
//  - tarifa_por_km: R$6,00
//  - Simulação p/ 10km: 120 + 6*10 = R$180.
//  - Adicional noturno/feriado: +R$40 fixo e +R$2,00/km, elevando o total
//    de 10km pra R$240 (+33%), dentro da faixa de +25% a +40%.

$valoresMercado = [
    'taxa_fixa' => 120.00,
    'tarifa_por_km' => 6.00,
    'tarifa_noturna_fixa' => 40.00,
    'tarifa_noturna_km' => 2.00,
    'tarifa_feriado_fixa' => 40.00,
    'tarifa_feriado_km' => 2.00,
];

echo "Aplicando tarifa GLOBAL de reboque (fallback pra qualquer cidade sem override) em /admin/configuracoes:\n";
foreach ($valoresMercado as $chave => $valor) {
    Configuracao::set($chave, (string)$valor, "Tarifa global de reboque (pesquisa de mercado em " . date('Y-m-d') . ")");
    echo "  {$chave} = {$valor}\n";
}

echo "\nSimulação de conferência (corrida de 10km, categoria popular):\n";
$totalDia = $valoresMercado['taxa_fixa'] + $valoresMercado['tarifa_por_km'] * 10;
$totalNoite = $totalDia + $valoresMercado['tarifa_noturna_fixa'] + $valoresMercado['tarifa_noturna_km'] * 10;
$percentualNoturno = round((($totalNoite / $totalDia) - 1) * 100, 1);
echo "  Diurno:  R$ " . number_format($totalDia, 2, ',', '.') . "\n";
echo "  Noturno: R$ " . number_format($totalNoite, 2, ',', '.') . " (+{$percentualNoturno}%)\n";

echo "\nConcluído. Cidades SEM override próprio (ex.: nenhuma pesquisa de mercado feita ainda) passam a usar esses valores. Confira/ajuste em /admin/configuracoes (sem selecionar cidade).\n";
