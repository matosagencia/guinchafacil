<?php

declare(strict_types=1);

/**
 * Persistência de fases/steps do simulador interno e do runner Playwright.
 */
class SimulationStep
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
            'fase',
            'ok',
            'mensagem',
            'system',
            'code',
            'status',
            'actual_json',
            'criado_em',
        ];

        if (!self::tableExists('simulation_steps')) {
            throw new RuntimeException('Schema ausente: tabela simulation_steps não existe. Execute install/migrate.php ou a migration simulation_runner_v2.');
        }

        foreach ($requiredColumns as $column) {
            if (!self::hasColumn('simulation_steps', $column)) {
                throw new RuntimeException("Schema incompleto: coluna simulation_steps.{$column} não existe. Execute install/migrate.php ou a migration simulation_runner_v2.");
            }
        }

        self::$schemaChecked = true;
    }

    private static function nowExpression(): string
    {
        $driver = (string)getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
        return $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    public static function registrar(string $runId, string $fase, bool $ok, string $msg): int
    {
        return self::registrarDetalhe($runId, [
            'fase' => $fase,
            'ok' => $ok,
            'mensagem' => $msg,
            'status' => $ok ? 'passed' : 'failed',
        ]);
    }

    public static function registrarDetalhe(string $runId, array $data): int
    {
        self::assertSchemaReady();
        getPDO()->prepare(sprintf(
            "INSERT INTO simulation_steps (
                run_id, fase, ok, mensagem, system, class, function, file, phase, code,
                status, duration_ms, expected_json, actual_json, error_message, stack_trace,
                started_at, finished_at, criado_em
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, %s
            )",
            self::nowExpression()
        ))->execute([
            $runId,
            (string)($data['fase'] ?? $data['code'] ?? 'step'),
            !empty($data['ok']) ? 1 : 0,
            (string)($data['mensagem'] ?? ''),
            isset($data['system']) ? (string)$data['system'] : null,
            isset($data['class']) ? (string)$data['class'] : null,
            isset($data['function']) ? (string)$data['function'] : null,
            isset($data['file']) ? (string)$data['file'] : null,
            isset($data['phase']) ? (string)$data['phase'] : null,
            isset($data['code']) ? (string)$data['code'] : null,
            isset($data['status']) ? (string)$data['status'] : null,
            isset($data['duration_ms']) ? (int)$data['duration_ms'] : null,
            isset($data['expected_json']) ? json_encode($data['expected_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            isset($data['actual_json']) ? json_encode($data['actual_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            isset($data['error_message']) ? (string)$data['error_message'] : null,
            isset($data['stack_trace']) ? (string)$data['stack_trace'] : null,
            isset($data['started_at']) ? (string)$data['started_at'] : null,
            isset($data['finished_at']) ? (string)$data['finished_at'] : null,
        ]);

        return (int)getPDO()->lastInsertId();
    }

    public static function listarPorRun(string $runId): array
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare("SELECT * FROM simulation_steps WHERE run_id=? ORDER BY id ASC");
        $stmt->execute([$runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
