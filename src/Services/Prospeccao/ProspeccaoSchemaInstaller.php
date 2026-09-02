<?php

declare(strict_types=1);

final class ProspeccaoSchemaInstaller
{
    public static function ensure(PDO $pdo, string $sqlFile): bool
    {
        if (self::schemaExists($pdo)) {
            return true;
        }

        if (!is_file($sqlFile)) {
            throw new RuntimeException('Migration de prospeccao nao encontrada: ' . $sqlFile);
        }

        self::executarArquivoSql($pdo, $sqlFile);

        return self::schemaExists($pdo);
    }

    public static function ensureAdditional(PDO $pdo, string $sqlFile, array $requiredTables): bool
    {
        if (self::tablesExist($pdo, $requiredTables)) {
            return true;
        }

        if (!is_file($sqlFile)) {
            throw new RuntimeException('Migration de prospeccao nao encontrada: ' . $sqlFile);
        }

        self::executarArquivoSql($pdo, $sqlFile);

        return self::tablesExist($pdo, $requiredTables);
    }

    private static function schemaExists(PDO $pdo): bool
    {
        return self::tablesExist($pdo, ['prospeccao_regioes', 'prospeccao_leads', 'prospeccao_convites']);
    }

    /**
     * @param array<int, string> $requiredTables
     */
    private static function tablesExist(PDO $pdo, array $requiredTables): bool
    {
        $requiredTables = array_values(array_filter(array_map('trim', $requiredTables), static fn(string $v): bool => $v !== ''));
        if ($requiredTables === []) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ({$placeholders})"
        );
        $stmt->execute($requiredTables);

        return (int)$stmt->fetchColumn() >= count($requiredTables);
    }

    private static function executarArquivoSql(PDO $pdo, string $sqlFile): void
    {
        $sql = file_get_contents($sqlFile);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Migration de prospeccao vazia ou ilegivel.');
        }

        foreach (self::splitStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * Parser simples para executar o arquivo SQL de instalacao.
     *
     * @return array<int, string>
     */
    private static function splitStatements(string $sql): array
    {
        $stmts = [];
        $buf = '';
        $inStr = false;
        $strCh = '';
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $nx = $i + 1 < $len ? $sql[$i + 1] : '';

            if (!$inStr && $ch === '-' && $nx === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if (!$inStr && $ch === '/' && $nx === '*') {
                $i += 2;
                while ($i < $len - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if (!$inStr && ($ch === '"' || $ch === "'")) {
                $inStr = true;
                $strCh = $ch;
                $buf .= $ch;
                continue;
            }

            if ($inStr) {
                $buf .= $ch;
                if ($ch === '\\') {
                    if ($i + 1 < $len) {
                        $buf .= $sql[$i + 1];
                        $i++;
                    }
                    continue;
                }
                if ($ch === $strCh) {
                    $inStr = false;
                    $strCh = '';
                }
                continue;
            }

            if ($ch === ';') {
                $stmt = trim($buf);
                if ($stmt !== '') {
                    $stmts[] = $stmt;
                }
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        $last = trim($buf);
        if ($last !== '') {
            $stmts[] = $last;
        }

        return $stmts;
    }
}
