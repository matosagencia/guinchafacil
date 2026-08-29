<?php
/**
 * Cron: cancelar pedidos expirados aguardando guincho
 * - marca como cancelado quando expiracao_aceite passou
 * - libera guincho eventualmente preso
 * - solicita estorno integral quando já havia pagamento aprovado
 * Execução recomendada: a cada minuto
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/CronMonitorService.php';
require_once dirname(__DIR__) . '/src/Services/ExpiracaoPedidosService.php';

$run = CronMonitorService::start('cron_cancelar_pedidos_expirados');
// Definido antes do try: se ExpiracaoPedidosService::executar() lançar
// exceção antes de retornar, o catch abaixo ainda precisa de um array
// válido pra incrementar 'errors' e para CronMonitorService::finish().
$metrics = ['expired_found' => 0, 'cancelled' => 0, 'refunds_ok' => 0, 'refunds_failed' => 0, 'errors' => 0];

try {
    // §COBERTURA-RAIO-01 (05/08/2026): lógica movida para
    // ExpiracaoPedidosService::executar() — reaproveitada por
    // tools/qa_executar_cron_expiracao.php, que precisa do mesmo
    // comportamento com saída em JSON puro (sem as linhas de log do cron).
    $metrics = ExpiracaoPedidosService::executar();

    $status = ($metrics['errors'] > 0 || $metrics['refunds_failed'] > 0) ? 'warning' : 'ok';
    $message = $metrics['expired_found'] === 0
        ? 'Nenhum pedido expirado pendente.'
        : 'Expiração automática concluída.';

    CronMonitorService::finish($run, $status, $message, $metrics);
    echo date('[Y-m-d H:i:s]') . ' ' . $message . ' ' . json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $metrics['errors']++;
    CronMonitorService::finish($run, 'error', $e->getMessage(), $metrics);
    fwrite(STDERR, date('[Y-m-d H:i:s]') . ' Falha ao expirar pedidos: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
