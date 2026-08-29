<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Models/SimulationRun.php';
require_once __DIR__ . '/../../Models/SimulationStep.php';
require_once __DIR__ . '/../../Models/SimulationArtifact.php';

class PlaywrightRunnerService
{
    public static function isEnabled(): bool
    {
        $simEnabled = defined('SIMULATION_ENABLED') ? SIMULATION_ENABLED : (env('SIMULATION_ENABLED', 'false') === 'true');
        return (bool)$simEnabled;
    }

    public static function qaRoot(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'qa';
    }

    public static function runsRoot(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'qa-runs';
    }

    public static function ensureDirectories(string $runId = ''): string
    {
        $root = self::runsRoot();
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        if ($runId === '') {
            return $root;
        }

        $dir = $root . DIRECTORY_SEPARATOR . $runId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public static function queue(array $config, int $requestedBy): string
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('SIMULATION_ENABLED=false');
        }

        $runId = bin2hex(random_bytes(16));
        $config['suite'] = self::sanitizeSuite((string)($config['suite'] ?? 'smoke'));
        $config['browser'] = self::sanitizeBrowser((string)($config['browser'] ?? 'chromium'));
        $config['viewport'] = self::sanitizeViewport((string)($config['viewport'] ?? 'desktop'));
        $config['locale'] = trim((string)($config['locale'] ?? 'pt-BR'));
        $config['timezone'] = trim((string)($config['timezone'] ?? 'America/Sao_Paulo'));
        $config['target_environment'] = trim((string)($config['target_environment'] ?? 'staging'));
        $config['target_url'] = trim((string)($config['target_url'] ?? (defined('APP_URL') ? APP_URL : '')));
        $config['pix_dry_run'] = !empty($config['pix_dry_run']);
        $config['run_id'] = $runId;

        self::ensureDirectories($runId);
        SimulationRun::enfileirarPlaywright($runId, $config, $requestedBy);
        SimulationStep::registrarDetalhe($runId, [
            'fase' => 'queue',
            'ok' => true,
            'mensagem' => 'Execução Playwright enfileirada.',
            'system' => 'E2E',
            'class' => __CLASS__,
            'function' => __FUNCTION__,
            'file' => 'src/Services/QA/PlaywrightRunnerService.php',
            'phase' => 'enqueue',
            'code' => 'E2E-RUN-001',
            'status' => 'queued',
        ]);

        return $runId;
    }

    public static function claim(string $workerId, int $workerPid): array|false
    {
        return SimulationRun::claimQueuedPlaywright($workerId, $workerPid);
    }

    public static function heartbeat(string $runId, string $workerId): bool
    {
        return SimulationRun::heartbeat($runId, $workerId);
    }

    public static function cancel(string $runId): bool
    {
        return SimulationRun::cancelar($runId);
    }

    /**
     * L1.10 #50: buildCommand() gerava uma string de shell em sintaxe
     * PowerShell (`$env:X='Y'; Set-Location ...; cmd.exe /c npx.cmd ...`),
     * inutilizável fora do Windows. Agora devolve `argv` (array de
     * argumentos, sem depender de nenhum shell específico) + `env` (array
     * associativo) + `cwd`, para que o caller monte o processo com
     * proc_open() usando os parâmetros nativos $cwd/$env — que já existem
     * na assinatura de proc_open() e sempre foram portáveis, só não eram
     * usados.
     *
     * @return array{argv:array<int,string>, env:array<string,string>, cwd:string, result_json:string, run_dir:string, report_dir:string, artifacts_dir:string}
     */
    public static function buildCommand(array $run): array
    {
        $cfg = json_decode((string)($run['configuration_json'] ?? '{}'), true);
        if (!is_array($cfg)) {
            $cfg = [];
        }

        $qaRoot = self::qaRoot();
        $runDir = self::ensureDirectories((string)$run['run_id']);
        $resultJson = $runDir . DIRECTORY_SEPARATOR . 'result.json';
        $reportDir = $runDir . DIRECTORY_SEPARATOR . 'html-report';
        $artifactsDir = $runDir . DIRECTORY_SEPARATOR . 'artifacts';
        if (!is_dir($artifactsDir)) {
            @mkdir($artifactsDir, 0775, true);
        }

        $suiteFile = self::suiteToSpec((string)($run['suite'] ?? 'smoke'));
        $browser = self::sanitizeBrowser((string)($run['browser'] ?? 'chromium'));

        $env = [
            'QA_RUN_ID' => (string)$run['run_id'],
            'QA_RESULT_JSON' => $resultJson,
            'QA_ARTIFACTS_DIR' => $artifactsDir,
            'PLAYWRIGHT_HTML_REPORT' => $reportDir,
            'PLAYWRIGHT_BASE_URL' => (string)($run['target_url'] ?? ''),
            'PLAYWRIGHT_LOCALE' => (string)($run['locale'] ?? 'pt-BR'),
            'PLAYWRIGHT_TIMEZONE' => (string)($run['timezone'] ?? 'America/Sao_Paulo'),
        ];

        foreach ($cfg as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            if (in_array($key, ['suite', 'browser', 'viewport', 'locale', 'timezone', 'target_url', 'target_environment', 'run_id'], true)) {
                continue;
            }
            $env[strtoupper((string)$key)] = (string)$value;
        }

        // No Windows, npx é um script .cmd — proc_open() com array de argv
        // não passa por cmd.exe por padrão, então .cmd não é executável
        // diretamente; usamos `cmd /c npx ...` (cmd.exe resolve a extensão
        // via PATHEXT). No Linux/Mac, `npx` é um binário normal.
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $playwrightArgs = ['playwright', 'test', $suiteFile, '--config=playwright.config.ts', '--project=' . $browser];
        $argv = $isWindows
            ? array_merge(['cmd', '/c', 'npx'], $playwrightArgs)
            : array_merge(['npx'], $playwrightArgs);

        return [
            'argv' => $argv,
            'env' => $env,
            'cwd' => $qaRoot,
            'result_json' => $resultJson,
            'run_dir' => $runDir,
            'report_dir' => $reportDir,
            'artifacts_dir' => $artifactsDir,
        ];
    }

    public static function importArtifactsFromRunDir(string $runId, string $runDir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($runDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $name = $file->getFilename();
            $lower = strtolower($name);
            $kind = match (true) {
                str_ends_with($lower, '.png') => 'screenshot',
                str_ends_with($lower, '.webm') => 'video',
                str_ends_with($lower, '.zip') => 'trace',
                str_ends_with($lower, '.html') => 'html_report',
                str_ends_with($lower, '.json') => 'json',
                str_ends_with($lower, '.log') => 'stdout',
                default => 'file',
            };

            SimulationArtifact::registrarSeAusente([
                'run_id' => $runId,
                'kind' => $kind,
                'filename' => $name,
                'private_path' => $path,
                'mime_type' => self::guessMimeType($name),
                'size_bytes' => $file->getSize(),
                'sha256' => @hash_file('sha256', $path) ?: null,
            ]);
        }
    }

    public static function importResultFile(string $runId, string $resultJson, int $exitCode, string $stdout = '', string $stderr = ''): void
    {
        $summary = [
            'status' => $exitCode === 0 ? 'completed' : 'failed',
            'exit_code' => $exitCode,
            'duration_ms' => null,
            'total_steps' => 0,
            'failed_steps' => 0,
        ];

        if (is_file($resultJson)) {
            $json = json_decode((string)file_get_contents($resultJson), true);
            if (is_array($json)) {
                $summary = array_merge($summary, $json);
            }
        }

        if ($stdout !== '') {
            SimulationStep::registrarDetalhe($runId, [
                'fase' => 'stdout',
                'ok' => true,
                'mensagem' => trim($stdout),
                'system' => 'E2E',
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'file' => 'src/Services/QA/PlaywrightRunnerService.php',
                'phase' => 'stdout_capture',
                'code' => 'E2E-RUN-STDOUT',
                'status' => 'info',
            ]);
        }

        if ($stderr !== '') {
            SimulationStep::registrarDetalhe($runId, [
                'fase' => 'stderr',
                'ok' => false,
                'mensagem' => trim($stderr),
                'system' => 'E2E',
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'file' => 'src/Services/QA/PlaywrightRunnerService.php',
                'phase' => 'stderr_capture',
                'code' => 'E2E-RUN-STDERR',
                'status' => 'failed',
            ]);
        }

        if (!empty($summary['steps']) && is_array($summary['steps'])) {
            foreach ($summary['steps'] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $stepId = SimulationStep::registrarDetalhe($runId, [
                    'fase' => (string)($step['title'] ?? $step['code'] ?? 'step'),
                    'ok' => (($step['status'] ?? 'passed') === 'passed'),
                    'mensagem' => (string)($step['message'] ?? $step['title'] ?? ''),
                    'system' => (string)($step['system'] ?? 'E2E'),
                    'class' => (string)($step['class'] ?? 'PlaywrightSuite'),
                    'function' => (string)($step['function'] ?? 'test'),
                    'file' => (string)($step['file'] ?? ''),
                    'phase' => (string)($step['phase'] ?? 'step'),
                    'code' => (string)($step['code'] ?? ''),
                    'status' => (string)($step['status'] ?? 'passed'),
                    'duration_ms' => isset($step['duration_ms']) ? (int)$step['duration_ms'] : null,
                    'actual_json' => $step,
                    'error_message' => isset($step['error']) ? (string)$step['error'] : null,
                ]);

                $attachments = $step['artifacts'] ?? [];
                if (is_array($attachments)) {
                    foreach ($attachments as $artifact) {
                        if (!is_array($artifact) || empty($artifact['path']) || !is_file((string)$artifact['path'])) {
                            continue;
                        }

                        $artifactPath = (string)$artifact['path'];
                        $filename = basename($artifactPath);
                        SimulationArtifact::registrarSeAusente([
                            'run_id' => $runId,
                            'step_id' => $stepId,
                            'kind' => self::normalizeArtifactKind((string)($artifact['kind'] ?? 'attachment'), $filename),
                            'filename' => $filename,
                            'private_path' => $artifactPath,
                            'mime_type' => self::guessMimeType($filename, (string)($artifact['content_type'] ?? '')),
                            'size_bytes' => @filesize($artifactPath) ?: null,
                            'sha256' => @hash_file('sha256', $artifactPath) ?: null,
                        ]);
                    }
                }
            }
        }

        SimulationRun::finalizarPlaywright($runId, $summary);
    }

    private static function sanitizeSuite(string $suite): string
    {
        $allowed = [
            'smoke',
            'pedido-novo',
            'atendimento-completo',
            'cancelamento',
            'sessao-seguranca',
            'constituicao-fluxo',
            'por-antifraude',
            'concorrencia-aceite',
            'pagamento-sandbox',
            'upload-seguranca',
            'cadastro-cliente-bulk',
            'cadastro-guincho-bulk',
        ];
        return in_array($suite, $allowed, true) ? $suite : 'smoke';
    }

    private static function suiteToSpec(string $suite): string
    {
        return match (self::sanitizeSuite($suite)) {
            'pedido-novo' => 'suites/pedido-novo.spec.ts',
            'atendimento-completo' => 'suites/atendimento-completo.spec.ts',
            'cancelamento' => 'suites/cancelamento.spec.ts',
            'sessao-seguranca' => 'suites/sessao-seguranca.spec.ts',
            'constituicao-fluxo' => 'suites/constituicao-fluxo.spec.ts',
            'por-antifraude' => 'suites/por-antifraude.spec.ts',
            'concorrencia-aceite' => 'suites/concorrencia-aceite.spec.ts',
            'pagamento-sandbox' => 'suites/pagamento-sandbox.spec.ts',
            'upload-seguranca' => 'suites/upload-seguranca.spec.ts',
            'cadastro-cliente-bulk' => 'suites/cadastro-cliente-bulk.spec.ts',
            'cadastro-guincho-bulk' => 'suites/cadastro-guincho-bulk.spec.ts',
            default => 'suites/smoke.spec.ts',
        };
    }

    private static function sanitizeBrowser(string $browser): string
    {
        return in_array($browser, ['chromium', 'firefox', 'webkit'], true) ? $browser : 'chromium';
    }

    private static function sanitizeViewport(string $viewport): string
    {
        return in_array($viewport, ['desktop', 'mobile'], true) ? $viewport : 'desktop';
    }

    private static function normalizeArtifactKind(string $kind, string $filename): string
    {
        $kind = strtolower(trim($kind));
        if ($kind !== '') {
            return match ($kind) {
                'trace', 'video', 'screenshot', 'html_report', 'json', 'stdout', 'attachment' => $kind,
                default => self::inferArtifactKindByFilename($filename),
            };
        }

        return self::inferArtifactKindByFilename($filename);
    }

    private static function inferArtifactKindByFilename(string $filename): string
    {
        $lower = strtolower($filename);
        return match (true) {
            str_ends_with($lower, '.png'), str_ends_with($lower, '.jpg'), str_ends_with($lower, '.jpeg') => 'screenshot',
            str_ends_with($lower, '.webm') => 'video',
            str_ends_with($lower, '.zip') => 'trace',
            str_ends_with($lower, '.html') => 'html_report',
            str_ends_with($lower, '.json') => 'json',
            str_ends_with($lower, '.log') => 'stdout',
            default => 'attachment',
        };
    }

    private static function guessMimeType(string $filename, string $fallback = ''): string
    {
        if ($fallback !== '') {
            return $fallback;
        }

        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'json' => 'application/json',
            'html' => 'text/html',
            'zip' => 'application/zip',
            'log' => 'text/plain',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }
}
