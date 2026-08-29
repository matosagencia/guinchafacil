<?php
/**
 * GuinchaFácil — CLI Health Check (§HEALTH-01)
 *
 * Uso:
 *   php tools/healthcheck_online.php
 *
 * Exit 0 = todos OK/aviso
 * Exit 1 = ao menos um check com nível 'erro'
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/HealthService.php';

$checks   = HealthService::runAll();
$hasError = false;

$labels = ['ok' => 'OK   ', 'aviso' => 'AVISO', 'erro' => 'ERRO '];

echo "\n=== GuinchaFácil Health Check ===\n";
echo str_repeat('-', 80) . "\n";

foreach ($checks as $c) {
    $tag = $labels[$c['nivel']] ?? '?    ';
    echo sprintf("[%s] %-24s %-14s %s\n",
        $tag,
        $c['label'],
        $c['status'],
        $c['info']
    );
    if ($c['nivel'] === 'erro') {
        $hasError = true;
    }
}

echo str_repeat('-', 80) . "\n";

$total  = count($checks);
$okCt   = count(array_filter($checks, fn($c) => $c['nivel'] === 'ok'));
$warnCt = count(array_filter($checks, fn($c) => $c['nivel'] === 'aviso'));
$errCt  = count(array_filter($checks, fn($c) => $c['nivel'] === 'erro'));

echo "Total: {$total}  OK: {$okCt}  Avisos: {$warnCt}  Erros: {$errCt}\n\n";

exit($hasError ? 1 : 0);
