<?php

declare(strict_types=1);

/**
 * Reconciles checksums for migrations already marked successful.
 *
 * This is intentionally separate from the normal migration runner. It never
 * executes SQL and requires --apply, preventing an accidental mass rewrite
 * of migration history from a browser or a plain CLI invocation.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script só pode ser executado via CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config.php';

$apply = in_array('--apply', array_slice($argv, 1), true);
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$rows = $pdo->query('SELECT id, filename, checksum_sha256 FROM schema_migrations WHERE success = 1 ORDER BY id')->fetchAll();
$changed = 0;
$missing = 0;

foreach ($rows as $row) {
    $filename = basename((string)$row['filename']);
    $path = __DIR__ . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        $missing++;
        echo "[WARN] arquivo ausente: {$filename}\n";
        continue;
    }

    $checksum = hash_file('sha256', $path);
    if ($checksum === (string)$row['checksum_sha256']) {
        continue;
    }

    $changed++;
    echo ($apply ? '[UPDATE] ' : '[DRY-RUN] ') . $filename . "\n";
    if ($apply) {
        $stmt = $pdo->prepare('UPDATE schema_migrations SET checksum_sha256 = ?, error_message = NULL WHERE id = ? AND success = 1');
        $stmt->execute([$checksum, (int)$row['id']]);
    }
}

echo sprintf("%s: %d checksum(s) divergente(s), %d arquivo(s) ausente(s).\n", $apply ? 'Concluído' : 'Simulação', $changed, $missing);
if (!$apply && $changed > 0) {
    echo "Para aplicar somente aos registros success=1: php install/reconcile_migration_checksums.php --apply\n";
}
