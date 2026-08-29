<?php
declare(strict_types=1);

/**
 * install/rebaseline_migration_checksum.php
 *
 * Ferramenta pontual do Pacote L1.2.
 *
 * Uso: quando uma migration SQL já aplicada tem seu conteúdo corrigido
 * de forma INTENCIONAL e SEGURA (ex: remoção de um índice duplicado que
 * só causava erro em instalações novas, sem alterar o efeito em bancos
 * onde a migration já rodou), o checksum salvo em `schema_migrations`
 * fica desatualizado e o runner passa a reportar "drift" a cada execução.
 *
 * Este script recalcula o checksum SHA-256 do arquivo atual e atualiza
 * o registro correspondente em `schema_migrations`, SEM re-executar o SQL.
 *
 * Uso (CLI):
 *   php install/rebaseline_migration_checksum.php migration_simulation_runner_v2.sql
 *
 * Só use isto quando tiver certeza de que a mudança no arquivo é segura
 * para bancos onde a migration já foi aplicada.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script só pode ser executado via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../config.php';

$filename = $argv[1] ?? '';
if ($filename === '') {
    echo "Uso: php install/rebaseline_migration_checksum.php <nome_do_arquivo.sql>\n";
    exit(1);
}

$path = __DIR__ . '/' . $filename;
if (!is_file($path)) {
    echo "[ERRO] Arquivo não encontrado: {$path}\n";
    exit(1);
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "[ERRO] Conexão: " . $e->getMessage() . "\n";
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, checksum_sha256 FROM schema_migrations WHERE filename = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$filename]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "[INFO] Nenhum registro encontrado para '{$filename}' em schema_migrations.\n";
    echo "       Nada a rebaselinizar — a migration será tratada como pendente na próxima execução normal.\n";
    exit(0);
}

$newChecksum = hash('sha256', (string) file_get_contents($path));

if ($newChecksum === $row['checksum_sha256']) {
    echo "[OK] Checksum já está atualizado. Nada a fazer.\n";
    exit(0);
}

$update = $pdo->prepare('UPDATE schema_migrations SET checksum_sha256 = ?, success = 1, error_message = NULL WHERE id = ?');
$update->execute([$newChecksum, $row['id']]);

echo "[OK] Checksum de '{$filename}' rebaselinizado com sucesso.\n";
echo "     Checksum anterior: {$row['checksum_sha256']}\n";
echo "     Checksum novo:     {$newChecksum}\n";
