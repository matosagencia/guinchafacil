<?php
/**
 * Cron: reprocessar transferências Pix com falha (§11)
 * Execução recomendada: a cada 5 minutos
 * cPanel: *\/5 * * * * php /home/usuario/public_html/tools/cron_reprocessar_pix.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Workers/PixPayoutWorker.php';
require_once dirname(__DIR__) . '/src/Services/CronMonitorService.php';

$run = CronMonitorService::start('cron_reprocessar_pix');
$summary = PixPayoutWorker::processBatch(10, 'cron_pix');
if (($summary['processed'] ?? 0) === 0) {
    CronMonitorService::finish($run, 'ok', 'Nenhum payment job pendente.', [
        'processed' => 0,
        'errors' => 0,
    ]);
    echo date('[Y-m-d H:i:s]') . " Nenhum payment job pendente.\n";
    exit(0);
}

CronMonitorService::finish($run, ((int)($summary['errors'] ?? 0)) > 0 ? 'warning' : 'ok', 'Reprocessamento PIX concluído.', [
    'processed' => (int)($summary['processed'] ?? 0),
    'errors' => (int)($summary['errors'] ?? 0),
]);
echo date('[Y-m-d H:i:s]') . " Payment jobs processados: {$summary['processed']}; erros: {$summary['errors']}\n";

exit(0);
