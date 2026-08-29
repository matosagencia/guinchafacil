<?php
/**
 * GuinchaFácil — Runner de migrações de banco de dados
 * Uso: php database/run_migration.php [arquivo.sql]
 * Exemplo: php database/run_migration.php migrate_fase2.sql
 *
 * Requer acesso CLI ao PHP com config.php carregado.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado. Execute via CLI.');
}

$sqlFile = $argv[1] ?? 'migrate_fase2.sql';
$sqlPath = __DIR__ . '/' . $sqlFile;

if (!is_file($sqlPath)) {
    fwrite(STDERR, "Arquivo SQL não encontrado: {$sqlPath}\n");
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

$pdo = getPDO();
$sql = file_get_contents($sqlPath);

// Divide em statements individuais (respeita delimitadores básicos)
// Remove comentários de linha simples
$sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
// Divide por ponto-e-vírgula mas não dentro de strings
$statements = array_filter(array_map('trim', explode(';', $sql)));

$ok = 0;
$fail = 0;

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        $pdo->exec($stmt);
        $ok++;
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 80) . "\n";
    } catch (PDOException $e) {
        $fail++;
        fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
        fwrite(STDERR, "SQL: " . substr($stmt, 0, 120) . "\n\n");
    }
}

echo "\n=== Resultado: {$ok} OK, {$fail} erro(s) ===\n";
exit($fail > 0 ? 1 : 0);
