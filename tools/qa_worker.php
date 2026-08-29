<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/SimulationRun.php';
require_once dirname(__DIR__) . '/src/Models/SimulationStep.php';
require_once dirname(__DIR__) . '/src/Models/SimulationArtifact.php';
require_once dirname(__DIR__) . '/src/Services/QA/PlaywrightRunnerService.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[ERRO] qa_worker.php deve ser executado via CLI.\n");
    exit(1);
}

if (!PlaywrightRunnerService::isEnabled()) {
    fwrite(STDERR, "[ERRO] SIMULATION_ENABLED=false. Worker QA abortado.\n");
    exit(1);
}

$workerId = gethostname() . ':' . getmypid();
$run = PlaywrightRunnerService::claim($workerId, getmypid());
if (!$run) {
    fwrite(STDOUT, "[INFO] Nenhum job Playwright em fila.\n");
    exit(0);
}

$runId = (string)$run['run_id'];
$info = PlaywrightRunnerService::buildCommand($run);

$stdoutFile = $info['run_dir'] . DIRECTORY_SEPARATOR . 'stdout.log';
$stderrFile = $info['run_dir'] . DIRECTORY_SEPARATOR . 'stderr.log';

SimulationStep::registrarDetalhe($runId, [
    'fase' => 'worker-start',
    'ok' => true,
    'mensagem' => 'Worker Playwright iniciou a execução.',
    'system' => 'E2E',
    'class' => 'qa_worker',
    'function' => 'main',
    'file' => 'tools/qa_worker.php',
    'phase' => 'start',
    'code' => 'E2E-RUN-002',
    'status' => 'running',
]);

$descriptor = [
    0 => ['pipe', 'r'],
    1 => ['file', $stdoutFile, 'w'],
    2 => ['file', $stderrFile, 'w'],
];

// L1.10 #50: buildCommand() agora devolve argv/env/cwd portáveis (sem
// PowerShell embutido). O ambiente do processo filho precisa herdar o
// ambiente do worker (PATH etc., para localizar node/npx) e só então
// sobrepor as variáveis específicas da execução Playwright.
$childEnv = array_merge(getenv() ?: [], $info['env']);

$process = proc_open($info['argv'], $descriptor, $pipes, $info['cwd'], $childEnv);
if (!is_resource($process)) {
    SimulationRun::finalizarPlaywright($runId, [
        'status' => 'failed',
        'exit_code' => 1,
        'total_steps' => 1,
        'failed_steps' => 1,
        'duration_ms' => 0,
    ]);
    fwrite(STDERR, "[ERRO] Não foi possível iniciar o processo Playwright.\n");
    exit(1);
}

if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}

$status = proc_get_status($process);
$startedAt = time();
$timeoutSeconds = 900;

while ($status['running']) {
    PlaywrightRunnerService::heartbeat($runId, $workerId);
    if ((time() - $startedAt) > $timeoutSeconds) {
        proc_terminate($process);
        SimulationStep::registrarDetalhe($runId, [
            'fase' => 'timeout',
            'ok' => false,
            'mensagem' => 'Processo Playwright encerrado por timeout.',
            'system' => 'E2E',
            'class' => 'qa_worker',
            'function' => 'main',
            'file' => 'tools/qa_worker.php',
            'phase' => 'timeout',
            'code' => 'E2E-RUN-009',
            'status' => 'failed',
        ]);
        break;
    }
    sleep(2);
    $status = proc_get_status($process);
}

$exitCode = proc_close($process);
$stdout = is_file($stdoutFile) ? (string)file_get_contents($stdoutFile) : '';
$stderr = is_file($stderrFile) ? (string)file_get_contents($stderrFile) : '';

PlaywrightRunnerService::importResultFile($runId, $info['result_json'], $exitCode, $stdout, $stderr);
PlaywrightRunnerService::importArtifactsFromRunDir($runId, $info['run_dir']);

fwrite(STDOUT, "[OK] Job {$runId} finalizado com exit_code={$exitCode}\n");
