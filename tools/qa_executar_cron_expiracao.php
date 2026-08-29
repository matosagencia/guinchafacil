<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/ExpiracaoPedidosService.php';

// §COBERTURA-RAIO-01 (05/08/2026): mesma lógica de
// tools/cron_cancelar_pedidos_expirados.php (ambos chamam
// ExpiracaoPedidosService::executar(), sem duplicar nada), mas aqui a saída é
// JSON PURO — o cron real imprime "[timestamp] mensagem {json}" pra log
// legível por humano, o que quebraria o parser de qa/helpers/seed.ts
// (runSeedScript espera só JSON na última linha de stdout). Usado por
// qa/suites/cobertura-timeout-estorno.spec.ts pra disparar a expiração sem
// esperar o cron real rodar (que só existe agendado no Task Scheduler do
// ambiente do usuário, fora do controle do teste).

try {
    $metrics = ExpiracaoPedidosService::executar();
    echo json_encode(array_merge(['ok' => true], $metrics), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}
