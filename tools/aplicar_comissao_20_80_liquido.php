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

// Aplica a decisão confirmada em 26/07/2026: comissão de 20% sobre o valor
// LÍQUIDO (após reserva de gateway), repasse de 80% líquido ao prestador —
// ver §SPLIT-LIQUIDO-01 em PedidoTransitionService::approvePayment. Corrige
// o problema identificado: comissão de 21% + repasse de 80% + taxa de
// gateway de ~4% somava mais de 100% do valor bruto recebido do cliente.
//
// Estes mesmos valores também ficam editáveis em /admin/configuracoes —
// este script só evita a digitação manual na primeira aplicação. Rodar uma
// única vez: php tools/aplicar_comissao_20_80_liquido.php

$valores = [
    'comissao_plataforma' => ['valor' => '0.20', 'descricao' => 'Comissão da plataforma sobre o valor líquido (pós-gateway). Repasse ao prestador = 1 - esta comissão.'],
    'reserva_gateway_percentual' => ['valor' => '0.045', 'descricao' => 'Reserva conservadora de taxa de gateway (cartão à vista ~4,5%) descontada do bruto antes de comissão/repasse.'],
    'credito_conversao_percentual' => ['valor' => '0.30', 'descricao' => 'Percentual do valor do socorro inicial creditado ao cliente quando há conversão para reboque.'],
    'credito_conversao_maximo' => ['valor' => '40.00', 'descricao' => 'Teto em R$ do crédito de conversão pane->reboque, independente do percentual.'],
];

echo "Aplicando configuração de comissão 20%/80% sobre valor líquido pós-gateway...\n";
foreach ($valores as $chave => $dados) {
    $anterior = Configuracao::get($chave, null);
    Configuracao::set($chave, $dados['valor'], $dados['descricao']);
    echo sprintf("  %-32s %s -> %s\n", $chave, $anterior ?? '(vazio)', $dados['valor']);
}

echo "\nConcluído. Nenhum código foi alterado por este script — só a tabela `configuracoes`.\n";
echo "A correção de CÁLCULO (comissão incidir sobre líquido, não bruto) já está no código-fonte\n";
echo "de PedidoTransitionService::approvePayment (§SPLIT-LIQUIDO-01) e entra em vigor no próximo\n";
echo "deploy/atualização de arquivos no seu XAMPP.\n";
