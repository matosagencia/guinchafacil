<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/SimulationRun.php';
require_once dirname(__DIR__) . '/src/Services/QA/PlaywrightRunnerService.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERRO] qa_debug_command.php deve ser executado via CLI.\n");
    exit(1);
}

$runId = $argv[1] ?? '';
if ($runId === '') {
    fwrite(STDERR, "[ERRO] Informe o run_id.\n");
    exit(1);
}

$run = SimulationRun::buscarPorRunId($runId);
if (!$run) {
    fwrite(STDERR, "[ERRO] Run não encontrada.\n");
    exit(1);
}

echo json_encode(PlaywrightRunnerService::buildCommand($run), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
