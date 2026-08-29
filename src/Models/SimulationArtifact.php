<?php

declare(strict_types=1);

class SimulationArtifact
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

        $requiredColumns = ['run_id', 'step_id', 'kind', 'filename', 'private_path', 'created_at'];

        if (!self::tableExists('simulation_artifacts')) {
            throw new RuntimeException('Schema ausente: tabela simulation_artifacts não existe. Execute install/migrate.php ou a migration simulation_runner_v2.');
        }

        foreach ($requiredColumns as $column) {
            if (!self::hasColumn('simulation_artifacts', $column)) {
                throw new RuntimeException("Schema incompleto: coluna simulation_artifacts.{$column} não existe. Execute install/migrate.php ou a migration simulation_runner_v2.");
            }
        }

        self::$schemaChecked = true;
    }

    private static function nowExpression(): string
    {
        $driver = (string)getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
        return $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    public static function registrar(array $data): int
    {
        self::assertSchemaReady();
        getPDO()->prepare(sprintf(
            "INSERT INTO simulation_artifacts (run_id, step_id, kind, filename, private_path, mime_type, size_bytes, sha256, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, %s)",
            self::nowExpression()
        ))->execute([
            (string)$data['run_id'],
            $data['step_id'] ?? null,
            (string)$data['kind'],
            (string)$data['filename'],
            (string)$data['private_path'],
            $data['mime_type'] ?? null,
            $data['size_bytes'] ?? null,
            $data['sha256'] ?? null,
        ]);

        return (int)getPDO()->lastInsertId();
    }

    public static function registrarSeAusente(array $data): int
    {
        self::assertSchemaReady();
        $existing = self::buscarPorRunECaminho((string)$data['run_id'], (string)$data['private_path']);
        if ($existing) {
            if (($existing['step_id'] === null || $existing['step_id'] === '') && !empty($data['step_id'])) {
                getPDO()->prepare('UPDATE simulation_artifacts SET step_id = ? WHERE id = ?')->execute([
                    (int)$data['step_id'],
                    (int)$existing['id'],
                ]);
            }
            return (int)$existing['id'];
        }

        return self::registrar($data);
    }

    public static function listarPorRun(string $runId): array
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare("SELECT * FROM simulation_artifacts WHERE run_id = ? ORDER BY id ASC");
        $stmt->execute([$runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarPorId(int $id): array|false
    {
        self::assertSchemaReady();
        $stmt = getPDO()->prepare("SELECT * FROM simulation_artifacts WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function buscarPorRunECaminho(string $runId, string $privatePath): array|false
    {
        $stmt = getPDO()->prepare("SELECT * FROM simulation_artifacts WHERE run_id = ? AND private_path = ? LIMIT 1");
        $stmt->execute([$runId, $privatePath]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
