<?php
declare(strict_types=1);

/**
 * Runner versionado de migrações SQL do GuinchaFácil.
 * - Descobre install/migration_*.sql
 * - Registra checksum/tempo em schema_migrations
 * - Pula arquivos já aplicados com o mesmo conteúdo
 * - Sinaliza drift quando o mesmo arquivo foi alterado após aplicação
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/migration_runtime.php';

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        die('Erro de conexão: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$pdo = getPDO();
$results = [[
    'filename' => 'run_all_migrations.php',
    'status' => 'warn',
    'message' => 'Wrapper de compatibilidade. O fluxo canonico auditavel agora e install/migrate.php.',
]];

try {
    MigrationRuntime::ensureSchemaMigrationsTable($pdo, __DIR__);
} catch (Throwable $e) {
    $results[] = [
        'filename' => 'migration_schema_versions.sql',
        'status' => 'error',
        'message' => $e->getMessage(),
    ];
}

$results = array_merge($results, MigrationRuntime::applyPendingSqlMigrations($pdo, __DIR__));
?>
<html>
<head>
    <title>Migrações GuinchaFácil</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .card { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warn { color: #b45309; font-weight: bold; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Relatório de Migrações</h1>
    <?php foreach ($results as $result): ?>
        <div class="card">
            <strong><?php echo h($result['filename']); ?></strong><br>
            <?php if ($result['status'] === 'success'): ?>
                <span class="success">✔ Sucesso</span>
            <?php elseif ($result['status'] === 'warn'): ?>
                <span class="warn">⚠ Aviso</span>
            <?php elseif ($result['status'] === 'skipped'): ?>
                <span class="muted">• Ignorada</span>
            <?php elseif ($result['status'] === 'drift'): ?>
                <span class="warn">⚠ Divergência</span>
            <?php else: ?>
                <span class="error">✘ Erro</span>
            <?php endif; ?>
            <div class="muted"><?php echo h($result['message']); ?></div>
        </div>
    <?php endforeach; ?>
</body>
</html>
