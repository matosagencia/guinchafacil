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

// §PRECO-POR-CIDADE-01 (atualização 02/08/2026): substitui os valores
// clonados do global (instalados em tools/instalar_geo_tarifas_niteroi.php)
// por valores baseados na média de mercado efetivamente praticada por
// empresas de guincho em Niterói/RJ hoje, segundo pesquisa de mercado:
//
//  - Reboque urbano dentro de Niterói (até ~10km): R$150–R$350 no total.
//  - Taxa de acionamento (deslocamento até o veículo): R$100–R$250.
//  - R$/km rodado após o resgate: R$5–R$10/km.
//  - Adicional noturno/fim de semana/feriado: +30% a +50% sobre o valor normal.
//    (TarifaService soma um adicional em R$ fixo + R$/km, não multiplica —
//    os valores abaixo foram calibrados pra que o efeito final, numa
//    corrida típica de ~10km, fique dentro dessa faixa de 30–50%.)
//
// Valores adotados (ponto médio das faixas de mercado — ajustável a
// qualquer momento em /admin/configuracoes?cidade_id=<id>, sem precisar
// rodar este script de novo):
//  - taxa_fixa: R$150,00 (base do intervalo R$100–250, no centro da faixa
//    observada pra corridas curtas de até 10km).
//  - tarifa_por_km: R$7,00 (ponto médio de R$5–10/km).
//  - Simulação p/ 10km: 150 + 7*10 = R$220 — dentro do R$150–350 observado.
//  - Adicional noturno/feriado: +R$50 fixo e +R$2,50/km, o que numa
//    corrida de 10km eleva o total de R$220 pra R$295 (~+34%), dentro da
//    faixa de +30% a +50% relatada.
//
// Categorias sem dado específico de mercado pra Niterói (SUV, caminhonete,
// elétrico, taxa de prioridade) permanecem herdando o valor GLOBAL — não
// são tocadas aqui.
//
// Rodar uma única vez (depois de tools/instalar_geo_tarifas_niteroi.php):
//   php tools/atualizar_tarifas_niteroi_mercado.php

$cidade = Cidade::buscarPorSlug('niteroi-rj');
if (!$cidade) {
    echo "[ERRO] Cidade 'niteroi-rj' não encontrada. Rode primeiro: php install/migrate.php\n";
    exit(1);
}
$cidadeId = (int)$cidade['id'];
echo "Cidade encontrada: {$cidade['nome']}/{$cidade['uf']} (id={$cidadeId})\n";

$valoresMercado = [
    'taxa_fixa' => 150.00,
    'tarifa_por_km' => 7.00,
    'tarifa_noturna_fixa' => 50.00,
    'tarifa_noturna_km' => 2.50,
    'tarifa_feriado_fixa' => 50.00,
    'tarifa_feriado_km' => 2.50,
];

echo "\nAplicando tarifas de mercado (Niterói/RJ) em /admin/configuracoes?cidade_id={$cidadeId}:\n";
foreach ($valoresMercado as $chave => $valor) {
    $chaveCidade = $chave . '__cidade_' . $cidadeId;
    Configuracao::set($chaveCidade, (string)$valor, "Tarifa de mercado de Niterói/RJ (pesquisa de mercado em " . date('Y-m-d') . ")");
    echo "  {$chaveCidade} = {$valor}\n";
}

echo "\nSimulação de conferência (corrida de 10km, categoria popular):\n";
$totalDia = $valoresMercado['taxa_fixa'] + $valoresMercado['tarifa_por_km'] * 10;
$totalNoite = $totalDia + $valoresMercado['tarifa_noturna_fixa'] + $valoresMercado['tarifa_noturna_km'] * 10;
$percentualNoturno = round((($totalNoite / $totalDia) - 1) * 100, 1);
echo "  Diurno:  R$ " . number_format($totalDia, 2, ',', '.') . "\n";
echo "  Noturno: R$ " . number_format($totalNoite, 2, ',', '.') . " (+{$percentualNoturno}%)\n";

echo "\nConcluído. Confira/ajuste em /admin/configuracoes?cidade_id={$cidadeId}.\n";
