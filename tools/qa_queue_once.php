<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/QA/PlaywrightRunnerService.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERRO] qa_queue_once.php deve ser executado via CLI.\n");
    exit(1);
}

$suite = $argv[1] ?? 'upload-seguranca';
$browser = $argv[2] ?? 'chromium';
$targetUrl = $argv[3] ?? 'http://127.0.0.1';

$config = [
    'suite' => $suite,
    'browser' => $browser,
    'viewport' => 'desktop',
    'locale' => 'pt-BR',
    'timezone' => 'America/Sao_Paulo',
    'target_environment' => 'xampp-local',
    'target_url' => $targetUrl,
    'pix_dry_run' => true,
    'free_payment' => true,
    'stop_on_failure' => true,
    'record_video' => true,
    'record_trace' => true,
    'cleanup_after_run' => false,
];
for ($i = 4; $i < $argc; $i++) {
    $arg = (string)$argv[$i];
    if ($arg === '' || !str_contains($arg, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $arg, 2);
    $key = strtolower(trim($key));
    $value = trim($value);
    if ($key === '') {
        continue;
    }

    $config[$key] = $value;
}

try {
    $runId = PlaywrightRunnerService::queue($config, 0);

    fwrite(STDOUT, $runId . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
