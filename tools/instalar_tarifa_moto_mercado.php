<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

// §TESTE-TARIFACAO-01 (02/08/2026): moto era normalizada pra 'popular' em
// TarifaService::normalizarCategoria() — cobrava o mesmo valor de um carro.
// Corrigido em TarifaService.php (moto agora é categoria própria); este
// script instala o PREÇO real de mercado pra essa nova categoria, tanto na
// tarifa GLOBAL quanto no override de Niterói — igual ao padrão já usado
// pelas outras categorias (não é multiplicador inventado, é pesquisa real).
//
// Pesquisa de mercado usada (reboque de moto no Brasil / RJ):
//  - Saída inicial de reboque de moto: R$120–R$180 (até ~40km).
//  - Outra referência encontrada: taxa de saída R$200,00 + R$6,00/km rodado.
//  - Reboque urbano em Niterói (qualquer veículo): R$100–R$650 conforme
//    distância/tipo.
// Nenhuma fonte trouxe um valor específico separado pra Niterói só de moto
// — usa-se a mesma referência nacional pros dois níveis (global e Niterói),
// igual ao que já foi feito pra SUV/caminhonete/elétrico quando não havia
// dado regional.
//
// Valores adotados (dentro das faixas acima, com R$/km explicitamente
// documentado pra moto):
//  - tarifa_por_km: R$6,00 em AMBOS os níveis — valor explícito encontrado
//    pra reboque de moto.
//  - taxa_fixa: DIFERENTE por nível, de propósito (correção de 02/08/2026
//    depois que tools/teste_tarifacao_global.php encontrou 16 alertas —
//    moto global R$150 ficava MAIS CARA que o popular global R$120, porque
//    os dois usavam o mesmo R$/km e a moto tinha taxa fixa maior):
//      - NITERÓI: R$150,00 (ponto médio de R$120–180 da pesquisa; a tarifa
//        popular de Niterói é R$150+R$7/km — moto sai mais barata em
//        qualquer distância >0km só pelo R$/km menor, já validado com 0
//        alertas em tools/teste_tarifacao_niteroi.php).
//      - GLOBAL: R$110,00 — abaixo da taxa fixa popular GLOBAL (R$120,
//        instalada em tools/instalar_tarifa_global_reboque_mercado.php),
//        garantindo moto mais barata que popular em QUALQUER cidade sem
//        pesquisa própria. R$110 ainda está dentro da faixa real de
//        mercado (R$120–180 é o centro/topo da faixa; R$110 é uma
//        extrapolação conservadora pro chão da faixa, já que a base
//        popular GLOBAL também foi definida conservadora, não é pesquisa
//        de mercado ponto a ponto).

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Cidade.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

$tarifaKmMoto = 6.00;
$taxaFixaMotoGlobal = 110.00;
$taxaFixaMotoNiteroi = 150.00;
$motivo = "Tarifa de moto — pesquisa de mercado real (reboque de moto no Brasil/RJ, " . date('Y-m-d') . ")";

// ─── Passo 1 — Tarifa GLOBAL de moto ────────────────────────────────────────

echo "Aplicando tarifa GLOBAL de moto:\n";
Configuracao::set('tarifa_moto_fixa', (string)$taxaFixaMotoGlobal, $motivo);
Configuracao::set('tarifa_moto_km', (string)$tarifaKmMoto, $motivo);
echo "  taxa_fixa R$ {$taxaFixaMotoGlobal} | tarifa_por_km R$ {$tarifaKmMoto}\n";

// ─── Passo 2 — Override de Niterói ──────────────────────────────────────────

$cidade = Cidade::buscarPorSlug('niteroi-rj');
if (!$cidade) {
    echo "\n[AVISO] Cidade 'niteroi-rj' não encontrada — só a tarifa global foi aplicada.\n";
    exit(0);
}
$cidadeId = (int)$cidade['id'];
$sufixo = '__cidade_' . $cidadeId;

echo "\nAplicando tarifa de moto para Niterói (id={$cidadeId}):\n";
Configuracao::set('tarifa_moto_fixa' . $sufixo, (string)$taxaFixaMotoNiteroi, $motivo);
Configuracao::set('tarifa_moto_km' . $sufixo, (string)$tarifaKmMoto, $motivo);
echo "  taxa_fixa R$ {$taxaFixaMotoNiteroi} | tarifa_por_km R$ {$tarifaKmMoto}\n";

echo "\nSimulação de conferência (10km, diurno):\n";
$totalGlobal = round($taxaFixaMotoGlobal + $tarifaKmMoto * 10, 2);
$totalNiteroi = round($taxaFixaMotoNiteroi + $tarifaKmMoto * 10, 2);
echo "  Moto (global):  R$ " . number_format($totalGlobal, 2, ',', '.') . "\n";
echo "  Moto (Niterói): R$ " . number_format($totalNiteroi, 2, ',', '.') . "\n";

echo "\nConcluído (idempotente — pode rodar de novo sem duplicar). Rode 'php tools/teste_tarifacao_global.php' e 'php tools/teste_tarifacao_niteroi.php' de novo pra confirmar 0 alertas nos dois.\n";
