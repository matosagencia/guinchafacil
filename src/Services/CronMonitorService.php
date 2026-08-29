<?php

declare(strict_types=1);

final class CronMonitorService
{
    /** @return array<string, array{descricao:string,schedule_hint:string,tolerancia_atraso_min:int,script_path:string}> */
    public static function definitions(): array
    {
        return [
            'cron_cancelar_pedidos_expirados' => [
                'descricao' => 'Cancela pedidos expirados e solicita estorno quando aplicável.',
                'schedule_hint' => '* * * * *',
                'tolerancia_atraso_min' => 3,
                'script_path' => 'tools/cron_cancelar_pedidos_expirados.php',
            ],
            'cron_reprocessar_pix' => [
                'descricao' => 'Reprocessa payment jobs de repasse PIX.',
                'schedule_hint' => '*/5 * * * *',
                'tolerancia_atraso_min' => 10,
                'script_path' => 'tools/cron_reprocessar_pix.php',
            ],
            'cron_limpar_tokens' => [
                'descricao' => 'Limpa tokens expirados de redefinição de senha.',
                'schedule_hint' => '0 3 * * *',
                'tolerancia_atraso_min' => 1440,
                'script_path' => 'tools/cron_limpar_tokens.php',
            ],
            'cron_limpar_logs' => [
                'descricao' => 'Limpa logs, webhook logs e cache geográfico antigo.',
                'schedule_hint' => '30 0 * * *',
                'tolerancia_atraso_min' => 1440,
                'script_path' => 'tools/cron_limpar_logs.php',
            ],
            'cron_retencao_operacional' => [
                'descricao' => 'Limpa artefatos QA, trilhas antigas e resíduos operacionais.',
                'schedule_hint' => '30 1 * * *',
                'tolerancia_atraso_min' => 1440,
                'script_path' => 'tools/cron_retencao_operacional.php',
            ],
        ];
    }

    public static function start(string $jobCode): array
    {
        $startedAt = microtime(true);
        $executionId = null;

        try {
            self::ensureJobRow($jobCode);
            $stmt = getPDO()->prepare(
                "INSERT INTO cron_executions (job_code, status, started_at, heartbeat_at)
                 VALUES (?, 'running', NOW(), NOW())"
            );
            $stmt->execute([$jobCode]);
            $executionId = (int)getPDO()->lastInsertId();

            getPDO()->prepare(
                "UPDATE cron_jobs
                 SET ultima_execucao_inicio = NOW(),
                     ultima_execucao_status = 'running',
                     heartbeat_at = NOW()
                 WHERE job_code = ?"
            )->execute([$jobCode]);
        } catch (Throwable $e) {
            error_log('[CronMonitorService][start][' . $jobCode . '] ' . $e->getMessage());
        }

        return [
            'job_code' => $jobCode,
            'execution_id' => $executionId,
            'started_at' => $startedAt,
        ];
    }

    public static function heartbeat(array $run): void
    {
        if (empty($run['job_code'])) {
            return;
        }

        try {
            getPDO()->prepare("UPDATE cron_jobs SET heartbeat_at = NOW() WHERE job_code = ?")
                ->execute([(string)$run['job_code']]);

            if (!empty($run['execution_id'])) {
                getPDO()->prepare("UPDATE cron_executions SET heartbeat_at = NOW() WHERE id = ?")
                    ->execute([(int)$run['execution_id']]);
            }
        } catch (Throwable $e) {
            error_log('[CronMonitorService][heartbeat][' . (string)$run['job_code'] . '] ' . $e->getMessage());
        }
    }

    public static function finish(array $run, string $status, string $message = '', array $metrics = []): void
    {
        if (empty($run['job_code'])) {
            return;
        }

        $jobCode = (string)$run['job_code'];
        $durationMs = isset($run['started_at']) ? (int)round((microtime(true) - (float)$run['started_at']) * 1000) : null;
        $status = in_array($status, ['ok', 'warning', 'error'], true) ? $status : 'error';
        $metricsJson = !empty($metrics) ? json_encode($metrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        try {
            if (!empty($run['execution_id'])) {
                getPDO()->prepare(
                    "UPDATE cron_executions
                     SET status = ?, finished_at = NOW(), duration_ms = ?, message = ?, metrics_json = ?, heartbeat_at = NOW()
                     WHERE id = ?"
                )->execute([$status, $durationMs, $message, $metricsJson, (int)$run['execution_id']]);
            }

            getPDO()->prepare(
                "UPDATE cron_jobs
                 SET ultima_execucao_fim = NOW(),
                     ultima_execucao_status = ?,
                     ultima_mensagem = ?,
                     ultimo_duration_ms = ?,
                     ultima_execucao_metrics_json = ?,
                     heartbeat_at = NOW()
                 WHERE job_code = ?"
            )->execute([$status, $message, $durationMs, $metricsJson, $jobCode]);
        } catch (Throwable $e) {
            error_log('[CronMonitorService][finish][' . $jobCode . '] ' . $e->getMessage());
        }
    }

    public static function summarizeExpectedJobs(): array
    {
        try {
            $rows = getPDO()->query(
                "SELECT job_code, descricao, schedule_hint, tolerancia_atraso_min, ultima_execucao_inicio,
                        ultima_execucao_fim, ultima_execucao_status, ultima_mensagem, heartbeat_at
                 FROM cron_jobs
                 ORDER BY job_code"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'label' => 'Cron / Scheduler',
                'status' => 'sem_tabelas',
                'info' => $e->getMessage(),
                'nivel' => 'erro',
            ];
        }

        if (empty($rows)) {
            return [
                'ok' => false,
                'label' => 'Cron / Scheduler',
                'status' => 'sem_jobs',
                'info' => 'Nenhum job cadastrado em cron_jobs.',
                'nivel' => 'erro',
            ];
        }

        $warnings = [];
        $infos = [];
        $now = time();

        foreach ($rows as $row) {
            $jobCode = (string)$row['job_code'];
            $status = (string)($row['ultima_execucao_status'] ?? '');
            $lastHeartbeat = !empty($row['heartbeat_at']) ? strtotime((string)$row['heartbeat_at']) : false;
            $delayMin = max(1, (int)($row['tolerancia_atraso_min'] ?? 15));

            if ($lastHeartbeat === false) {
                $warnings[] = "{$jobCode} sem heartbeat";
                continue;
            }

            $ageMin = (int)floor(($now - $lastHeartbeat) / 60);
            if ($ageMin > $delayMin) {
                $warnings[] = "{$jobCode} atrasado ({$ageMin} min > {$delayMin} min)";
                continue;
            }

            if ($status === 'error') {
                $warnings[] = "{$jobCode} última execução com erro";
                continue;
            }

            $infos[] = "{$jobCode} OK";
        }

        if (!empty($warnings)) {
            return [
                'ok' => false,
                'label' => 'Cron / Scheduler',
                'status' => 'atenção',
                'info' => implode('. ', array_merge($warnings, $infos)),
                'nivel' => 'aviso',
            ];
        }

        return [
            'ok' => true,
            'label' => 'Cron / Scheduler',
            'status' => 'ok',
            'info' => implode('. ', $infos),
            'nivel' => 'ok',
        ];
    }

    public static function listJobs(): array
    {
        try {
            $rows = getPDO()->query(
                "SELECT job_code, descricao, schedule_hint, tolerancia_atraso_min, ativo,
                        ultima_execucao_inicio, ultima_execucao_fim, ultima_execucao_status,
                        ultima_mensagem, ultimo_duration_ms, ultima_execucao_metrics_json, heartbeat_at
                 FROM cron_jobs
                 ORDER BY job_code"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return self::mergeDefinitions($rows);
        } catch (Throwable $e) {
            error_log('[CronMonitorService][listJobs] ' . $e->getMessage());
            return [];
        }
    }

    /** @return list<array<string,mixed>> */
    public static function installationCommands(): array
    {
        $commands = [];
        $projectRoot = dirname(__DIR__, 2);
        $phpBinary = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' ? PHP_BINARY : 'php';

        foreach (self::definitions() as $jobCode => $definition) {
            $absoluteScript = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $definition['script_path']);
            $commands[] = [
                'job_code' => $jobCode,
                'schedule_hint' => $definition['schedule_hint'],
                'descricao' => $definition['descricao'],
                'script_path' => $definition['script_path'],
                'script_absolute' => $absoluteScript,
                'command_unix' => $definition['schedule_hint'] . ' ' . $phpBinary . ' ' . str_replace('\\', '/', $absoluteScript),
                'command_windows' => $phpBinary . ' ' . $absoluteScript,
            ];
        }

        return $commands;
    }

    public static function listRecentExecutions(int $limit = 25): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT id, job_code, status, started_at, finished_at, heartbeat_at, duration_ms, message, metrics_json
                 FROM cron_executions
                 ORDER BY id DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[CronMonitorService][listRecentExecutions] ' . $e->getMessage());
            return [];
        }
    }

    private static function ensureJobRow(string $jobCode): void
    {
        $stmt = getPDO()->prepare("SELECT COUNT(*) FROM cron_jobs WHERE job_code = ?");
        $stmt->execute([$jobCode]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        $definition = self::definitions()[$jobCode] ?? null;
        getPDO()->prepare(
            "INSERT INTO cron_jobs (job_code, descricao, schedule_hint, tolerancia_atraso_min, ativo, criado_em, atualizado_em)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())"
        )->execute([
            $jobCode,
            $definition['descricao'] ?? $jobCode,
            $definition['schedule_hint'] ?? 'manual',
            $definition['tolerancia_atraso_min'] ?? 30,
        ]);
    }

    /** @param list<array<string,mixed>> $rows
     *  @return list<array<string,mixed>>
     */
    private static function mergeDefinitions(array $rows): array
    {
        $definitions = self::definitions();
        $indexed = [];

        foreach ($rows as $row) {
            $jobCode = (string)($row['job_code'] ?? '');
            $definition = $definitions[$jobCode] ?? null;
            if ($definition) {
                $row['descricao'] = (string)($row['descricao'] ?: $definition['descricao']);
                $row['schedule_hint'] = (string)($row['schedule_hint'] ?: $definition['schedule_hint']);
                $row['tolerancia_atraso_min'] = (int)($row['tolerancia_atraso_min'] ?: $definition['tolerancia_atraso_min']);
                $row['script_path'] = $definition['script_path'];
            }
            $indexed[$jobCode] = $row;
        }

        foreach ($definitions as $jobCode => $definition) {
            if (!isset($indexed[$jobCode])) {
                $indexed[$jobCode] = [
                    'job_code' => $jobCode,
                    'descricao' => $definition['descricao'],
                    'schedule_hint' => $definition['schedule_hint'],
                    'tolerancia_atraso_min' => $definition['tolerancia_atraso_min'],
                    'ativo' => 1,
                    'ultima_execucao_inicio' => null,
                    'ultima_execucao_fim' => null,
                    'ultima_execucao_status' => null,
                    'ultima_mensagem' => null,
                    'ultimo_duration_ms' => null,
                    'ultima_execucao_metrics_json' => null,
                    'heartbeat_at' => null,
                    'script_path' => $definition['script_path'],
                ];
            }
        }

        ksort($indexed);
        return array_values($indexed);
    }
}
