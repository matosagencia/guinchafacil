<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

// Utilitário de QA: simula a aprovação de pagamento de UM pedido específico
// (recebido como argv[1]) sem depender do gateway real (Mercado Pago/PagSeguro)
// nem de mudar a configuração global 'payment_required'/'system_mode', que
// afetaria todos os outros pedidos e specs do gate (ex: pagamento-sandbox.spec.ts).
//
// Só é usado por qa/suites/onboarding-completo.spec.ts quando o pedido criado
// via UI (registro completo + cadastro do zero) nasce em 'aguardando_pagamento'
// (ou seja, o ambiente atual tem payment_required=1) — nesse caso, este
// script reproduz o que ClienteController::criarPedido faz quando
// podeIniciarAtendimento() é true, sem tocar na config global.
if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_simular_pagamento_aprovado.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];

try {
    $pedido = Pedido::buscarPorId($pedidoId);
    if (!$pedido) {
        throw new RuntimeException("Pedido {$pedidoId} não encontrado.");
    }

    if ($pedido['status'] !== 'aguardando_pagamento') {
        echo json_encode(['ok' => true, 'pedido_id' => $pedidoId, 'status' => $pedido['status'], 'mensagem' => 'Pedido já não está aguardando pagamento; nada a fazer.']) . PHP_EOL;
        exit;
    }

    $cfg = Configuracao::getAll();
    $expMin = (int)($cfg['tempo_expiracao_min'] ?? 5);
    $raioInicial = (int)($cfg['raio_inicial_km'] ?? 10);

    $pdo = getPDO();
    $pdo->prepare(
        "UPDATE pedidos
            SET status = 'aguardando_guincho',
                expiracao_aceite = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                raio_atual_km = ?
          WHERE id = ?"
    )->execute([$expMin, $raioInicial, $pedidoId]);

    echo json_encode(['ok' => true, 'pedido_id' => $pedidoId, 'status' => 'aguardando_guincho']) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
