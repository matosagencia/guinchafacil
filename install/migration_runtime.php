<?php
declare(strict_types=1);

final class MigrationRuntime
{
    public static function ensureSchemaMigrationsTable(PDO $pdo, string $installDir): void
    {
        $sql = file_get_contents($installDir . '/migration_schema_versions.sql');
        if ($sql === false) {
            throw new RuntimeException('Nao foi possivel ler install/migration_schema_versions.sql');
        }
        $pdo->exec($sql);
    }

    public static function countAppliedMigrations(PDO $pdo): int
    {
        return (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    }

    public static function fetchAppliedMigration(PDO $pdo, string $filename): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, version, filename, checksum_sha256, applied_at, success, error_message
               FROM schema_migrations
              WHERE filename = ?
              ORDER BY id DESC
              LIMIT 1'
        );
        $stmt->execute([$filename]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function registerMigration(PDO $pdo, string $version, string $filename, string $checksum, string $appliedBy, int $executionMs = 0): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO schema_migrations
                (version, filename, checksum_sha256, applied_by, execution_ms, success, error_message)
             VALUES (?, ?, ?, ?, ?, 1, NULL)'
        );
        $stmt->execute([$version, $filename, $checksum, $appliedBy, $executionMs]);
    }

    public static function migrationFiles(string $installDir): array
    {
        $files = glob($installDir . '/migration_*.sql') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    public static function adoptExistingSqlMigrations(PDO $pdo, string $installDir, string $appliedBy = 'bootstrap_adopted'): array
    {
        $results = [];

        foreach (self::migrationFiles($installDir) as $file) {
            $filename = basename($file);
            $version = pathinfo($filename, PATHINFO_FILENAME);
            $sql = file_get_contents($file);

            if ($sql === false) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'error',
                    'message' => 'Falha ao ler o arquivo SQL para adocao.',
                ];
                continue;
            }

            $checksum = hash('sha256', $sql);
            $applied = self::fetchAppliedMigration($pdo, $filename);
            if ($applied) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'skipped',
                    'message' => 'Ja registrada anteriormente.',
                ];
                continue;
            }

            self::registerMigration($pdo, $version, $filename, $checksum, $appliedBy, 0);
            $results[] = [
                'filename' => $filename,
                'status' => 'adopted',
                'message' => 'Marcada como adotada pelo bootstrap canonico.',
            ];
        }

        return $results;
    }

    public static function applyPendingSqlMigrations(PDO $pdo, string $installDir, string $appliedBy = null): array
    {
        $results = [];
        $appliedBy = $appliedBy ?? (PHP_SAPI === 'cli' ? 'cli' : 'web');

        foreach (self::migrationFiles($installDir) as $file) {
            $filename = basename($file);
            $version = pathinfo($filename, PATHINFO_FILENAME);
            $sql = file_get_contents($file);

            if ($sql === false) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'error',
                    'message' => 'Falha ao ler o arquivo SQL.',
                ];
                continue;
            }

            $checksum = hash('sha256', $sql);
            $applied = self::fetchAppliedMigration($pdo, $filename);

            if ($applied && (string)$applied['checksum_sha256'] === $checksum && (int)$applied['success'] === 1) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'skipped',
                    'message' => 'Ja aplicada com o mesmo checksum em ' . (string)$applied['applied_at'],
                ];
                continue;
            }

            if ($applied && (string)$applied['checksum_sha256'] !== $checksum && (int)$applied['success'] === 1) {
                $results[] = [
                    'filename' => $filename,
                    'status' => 'drift',
                    'message' => 'Checksum divergente da versao ja aplicada. Revise antes de reaplicar.',
                ];
                continue;
            }

            $startedAt = microtime(true);
            try {
                // Nota (Pacote L1.2): DDL (CREATE/ALTER/DROP TABLE) causa commit
                // implícito no MySQL, encerrando a transação por baixo dos panos.
                // Por isso NUNCA assumimos que a transação segue ativa depois do
                // exec() — sempre checamos inTransaction() antes de commit/rollback,
                // senão o PDO lança "There is no active transaction" em migrations
                // que contenham DDL (a maioria).
                $pdo->beginTransaction();
                $pdo->exec($sql);
                self::registerMigration(
                    $pdo,
                    $version,
                    $filename,
                    $checksum,
                    $appliedBy,
                    (int)round((microtime(true) - $startedAt) * 1000)
                );
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }

                $results[] = [
                    'filename' => $filename,
                    'status' => 'success',
                    'message' => 'Aplicada com sucesso.',
                ];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $results[] = [
                    'filename' => $filename,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
