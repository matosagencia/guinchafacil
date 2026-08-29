<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

// §TESTE-TARIFACAO-01 (02/08/2026, correção): tools/teste_tarifacao_niteroi.php
// encontrou 48 alertas, todos da mesma causa raiz — as tarifas por categoria
// de veículo (SUV, caminhonete, elétrico) ainda carregavam valores antigos
// (clonados do global de instalação, nunca recalibrados), muito ABAIXO da
// tarifa popular. Exemplo real reportado a 3km em Niterói:
//   Popular R$ 171,00 | SUV R$ 24,60 | Caminhonete R$ 28,40 | Elétrico R$ 20,50
// Isso permitiria pedir reboque de SUV/caminhonete/elétrico por uma fração
// do preço real — bug de precificação com impacto financeiro direto.
//
// Correção aprovada pelo admin: recalibrar cada categoria como um percentual
// sobre a tarifa POPULAR (não há pesquisa de mercado específica por
// categoria — isso é um multiplicador de negócio razoável, não pesquisa de
// mercado, ao contrário dos scripts anteriores que usaram dados reais):
//   - SUV:         +20% sobre taxa_fixa e tarifa_por_km do popular
//   - Caminhonete: +35% sobre taxa_fixa e tarifa_por_km do popular
//   - Elétrico:    +20% sobre taxa_fixa e tarifa_por_km do popular (sem
//                  dado específico de mercado — usa o mesmo fator do SUV)
//
// Aplica em DOIS níveis, pra corrigir o problema tanto em Niterói quanto em
// qualquer outra cidade que ainda não tenha pesquisa própria:
//   1. Chaves GLOBAIS (tarifa_suv_km, tarifa_suv_fixa, etc.) — calculadas
//      sobre o popular GLOBAL (taxa_fixa R$120 / tarifa_por_km R$6,00,
//      instalado em tools/instalar_tarifa_global_reboque_mercado.php).
//   2. Chaves de NITERÓI (tarifa_suv_km__cidade_X, etc.) — calculadas sobre
//      o popular de NITERÓI (taxa_fixa R$150 / tarifa_por_km R$7,00,
//      instalado em tools/atualizar_tarifas_niteroi_mercado.php).
//
// Idempotente: rodar de novo apenas regrava os mesmos valores.

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Cidade.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

$multiplicadores = [
    'suv' => 1.20,
    'caminhonete' => 1.35,
    'eletrico' => 1.20,
];

// ─── Passo 1 — Corrige as chaves GLOBAIS (fallback de qualquer cidade) ─────

$cfgAtual = Configuracao::getAll();
$popularFixaGlobal = (float)($cfgAtual['taxa_fixa'] ?? 120.00);
$popularKmGlobal = (float)($cfgAtual['tarifa_por_km'] ?? 6.00);

echo "Base popular GLOBAL: taxa_fixa R$ {$popularFixaGlobal} | tarifa_por_km R$ {$popularKmGlobal}\n";
echo "Corrigindo tarifas por categoria GLOBAIS:\n";
foreach ($multiplicadores as $categoria => $fator) {
    $fixa = round($popularFixaGlobal * $fator, 2);
    $km = round($popularKmGlobal * $fator, 2);
    Configuracao::set("tarifa_{$categoria}_fixa", (string)$fixa, "Tarifa de categoria recalibrada (fator {$fator}x sobre popular, correção de bug de tarifação em " . date('Y-m-d') . ")");
    Configuracao::set("tarifa_{$categoria}_km", (string)$km, "Tarifa de categoria recalibrada (fator {$fator}x sobre popular, correção de bug de tarifação em " . date('Y-m-d') . ")");
    echo "  {$categoria}: taxa_fixa R$ {$fixa} | tarifa_por_km R$ {$km}\n";
}

// ─── Passo 2 — Corrige as chaves de NITERÓI (override específico) ─────────

$cidade = Cidade::buscarPorSlug('niteroi-rj');
if (!$cidade) {
    echo "\n[AVISO] Cidade 'niteroi-rj' não encontrada — pulando correção específica de Niterói (só a global foi aplicada acima).\n";
    exit(0);
}
$cidadeId = (int)$cidade['id'];

$cfgAtual = Configuracao::getAll();
$sufixo = '__cidade_' . $cidadeId;
$popularFixaNiteroi = (float)($cfgAtual['taxa_fixa' . $sufixo] ?? $popularFixaGlobal);
$popularKmNiteroi = (float)($cfgAtual['tarifa_por_km' . $sufixo] ?? $popularKmGlobal);

echo "\nBase popular de NITERÓI (id={$cidadeId}): taxa_fixa R$ {$popularFixaNiteroi} | tarifa_por_km R$ {$popularKmNiteroi}\n";
echo "Corrigindo tarifas por categoria de NITERÓI:\n";
foreach ($multiplicadores as $categoria => $fator) {
    $fixa = round($popularFixaNiteroi * $fator, 2);
    $km = round($popularKmNiteroi * $fator, 2);
    $chaveFixa = "tarifa_{$categoria}_fixa{$sufixo}";
    $chaveKm = "tarifa_{$categoria}_km{$sufixo}";
    Configuracao::set($chaveFixa, (string)$fixa, "Tarifa de categoria recalibrada para Niterói (fator {$fator}x sobre popular, correção de bug de tarifação em " . date('Y-m-d') . ")");
    Configuracao::set($chaveKm, (string)$km, "Tarifa de categoria recalibrada para Niterói (fator {$fator}x sobre popular, correção de bug de tarifação em " . date('Y-m-d') . ")");
    echo "  {$categoria}: taxa_fixa R$ {$fixa} | tarifa_por_km R$ {$km}\n";
}

echo "\nSimulação de conferência (3km, Niterói):\n";
foreach (array_merge(['popular' => 1.00], $multiplicadores) as $categoria => $fator) {
    $fixa = $categoria === 'popular' ? $popularFixaNiteroi : round($popularFixaNiteroi * $fator, 2);
    $km = $categoria === 'popular' ? $popularKmNiteroi : round($popularKmNiteroi * $fator, 2);
    $total = round($fixa + $km * 3, 2);
    echo "  {$categoria}: R$ " . number_format($total, 2, ',', '.') . "\n";
}

echo "\nConcluído. Rode 'php tools/teste_tarifacao_niteroi.php' de novo pra confirmar que os 48 alertas de categoria somem.\n";
