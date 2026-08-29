<?php

declare(strict_types=1);

/**
 * Persistência das execuções de simulação/QA.
 * Mantém compatibilidade com o simulador PHP existente e amplia suporte
 * para jobs Playwright enfileirados.
 */
class SimulationRun
{
    private static bool $schemaChecked = false;

    private static function tableExists(string $table): bool
    {
        $pdo = getPDO();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function hasColumn(string $table, string $column): bool
    {
        $pdo = getPDO();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
            return in_array($column, array_column($cols, 'name'), true);
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    }

    private static function assertSchemaReady(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        $requiredColumns = [
            'run_id',
            'engine',
            'suite',
            'status',
            'pix_dry_run',
            'requested_by',
            'target_environment',
            'browser',
            'configuration_json',
            'summary_json',
        ];

        if (!self::tableExists('simulation_runs')) {
            throw new RuntimeException('Schema ausente: tabela simulation_runs não existe. Execute install/migrate.php ou a migration simulation_runner_v2.');
        }

        foreach ($requiredColumns as $column) {
            if (!self::hasColumn('simulation_runs', $column)) {
                throw new RuntimeException("Schema incompleto: coluna simulation_runs.{$column} não existe. Execute install/migrate.php ou a migration simulation_runner_v2.");
            }
        }

        self::$schemaChecked = true;
    }

    private static function nowExpression(): string
    {
        $driver = (string)getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
        return $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    public static function criar(string $runId, bool $dryRun): void
    {
        self::assertSchemaReady();
        $sql = sprintf(
            "INSERT INTO simulation_runs (run_id, engine, suite, status, pix_dry_run, requested_at, started_at, iniciado_em)
             VALUES (?, 'php_internal', 'full', 'running', ?, %s, %s, %s)",
            self::nowExpression(),
            self::nowExpression(),
            self::nowExpression()
        );
        getPDO()->prepare($sql)->execute([$runId, $dryRun ? 1 : 0]);
    }

    public static function enfileirarPlaywright(string $runId, array $config, int $requestedBy): void
    {
        self::assertSchemaReady();

        $stmt = getPDO()->prepare(sprintf(
            "INSERT INTO simulation_runs (
                run_id, engine, suite, status, pix_dry_run, requested_by, requested_at,
                target_environment, target_url, browser, viewport, locale, timezone,
                configuration_json, app_version, git_commit, iniciado_em
            ) VALUES (
                ?, 'playwright', ?, 'queued', ?, ?, %s,
                ?, ?, ?, ?, ?, ?, ?, ?, ?, %s
            )",
            self::nowExpression(),
            self::nowExpression()
        ));

        $stmt->execute([
            $runId,
            (string)($config['suite'] ?? 'smoke'),
            !empty($config['pix_dry_run']) ? 1 : 0,
            $requestedBy,
            (string)($config['target_environment'] ?? 'staging'),
            (string)($config['target_url'] ?? ''),
            (string)($config['browser'] ?? 'chromium'),
            (string)($config['viewport'] ?? 'desktop'),
            (string)($config['locale'] ?? 'pt-BR'),
            (string)($config['timezone'] ?? 'America/Sao_Paulo'),
            json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string)($config['app_version'] ?? ''),
            (string)($config['git_commit'] ?? ''),
        ]);
    }

    public static function buscarPorRunId(string $runId): array|false
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare("SELECT * FROM simulation_runs WHERE run_id=? LIMIT 1");
        $stmt->execute([$runId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function listarRecentes(int $limite = 20): array
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare("SELECT * FROM simulation_runs ORDER BY iniciado_em DESC LIMIT ?");
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function claimQueuedPlaywright(string $workerId, int $workerPid): array|false
    {
        self::assertSchemaReady();
        $pdo = getPDO();

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM simulation_runs WHERE engine = 'playwright' AND status = 'queued' ORDER BY iniciado_em ASC LIMIT 1");
            $stmt->execute();
            $run = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$run) {
                $pdo->commit();
                return false;
            }

            $ok = $pdo->prepare(sprintf(
                "UPDATE simulation_runs
                 SET status = 'running', worker_id = ?, worker_pid = ?, heartbeat_at = %s, started_at = COALESCE(started_at, %s)
                 WHERE run_id = ? AND status = 'queued'",
                self::nowExpression(),
                self::nowExpression()
            ))->execute([$workerId, $workerPid, $run['run_id']]);

            if (!$ok) {
                $pdo->rollBack();
                return false;
            }

            $pdo->commit();
            return self::buscarPorRunId((string)$run['run_id']);
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    public static function heartbeat(string $runId, string $workerId): bool
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare(sprintf(
            "UPDATE simulation_runs SET heartbeat_at = %s WHERE run_id = ? AND worker_id = ?",
            self::nowExpression()
        ));
        return $stmt->execute([$runId, $workerId]);
    }

    public static function cancelar(string $runId): bool
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare(sprintf(
            "UPDATE simulation_runs
             SET status = 'cancelled', finished_at = COALESCE(finished_at, %s), finalizado_em = COALESCE(finalizado_em, %s)
             WHERE run_id = ? AND status IN ('queued', 'running')",
            self::nowExpression(),
            self::nowExpression()
        ));
        $stmt->execute([$runId]);
        return $stmt->rowCount() > 0;
    }

    public static function finalizar(
        string $runId,
        bool $ok,
        ?int $pedidoId,
        int $totalFases,
        int $fasesErro,
        int $duracaoMs
    ): void {
        self::assertSchemaReady();
        getPDO()->prepare(sprintf(
            "UPDATE simulation_runs
             SET status=?, pedido_id=?, total_fases=?, fases_ok=?, fases_erro=?, duracao_ms=?,
                 finished_at = COALESCE(finished_at, %s), finalizado_em = COALESCE(finalizado_em, %s)
             WHERE run_id=?",
            self::nowExpression(),
            self::nowExpression()
        ))->execute([
            $ok ? 'completed' : 'failed',
            $pedidoId,
            $totalFases,
            $totalFases - $fasesErro,
            $fasesErro,
            $duracaoMs,
            $runId,
        ]);
    }

    public static function finalizarPlaywright(string $runId, array $summary): void
    {
        self::assertSchemaReady();

        $status = (string)($summary['status'] ?? 'failed');
        $total = (int)($summary['total_steps'] ?? 0);
        $erros = (int)($summary['failed_steps'] ?? 0);
        $ok = max(0, $total - $erros);
        $duracao = isset($summary['duration_ms']) ? (int)$summary['duration_ms'] : null;
        $exitCode = isset($summary['exit_code']) ? (int)$summary['exit_code'] : null;

        getPDO()->prepare(sprintf(
            "UPDATE simulation_runs
             SET status=?, total_fases=?, fases_ok=?, fases_erro=?, duracao_ms=?, exit_code=?,
                 summary_json=?, finished_at = COALESCE(finished_at, %s), finalizado_em = COALESCE(finalizado_em, %s)
             WHERE run_id=?",
            self::nowExpression(),
            self::nowExpression()
        ))->execute([
            $status,
            $total,
            $ok,
            $erros,
            $duracao,
            $exitCode,
            json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $runId,
        ]);
    }
}
