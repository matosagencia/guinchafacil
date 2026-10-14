<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$arquivo = __DIR__ . '/../install/migration_expand_usuario_documento.sql';
$sql = file_get_contents($arquivo);
if ($sql === false) {
    fwrite(STDERR, "[MIG-DOC-CPF-CNPJ][BOOT-FAIL] Migration não encontrada: {$arquivo}\n");
    exit(1);
}

try {
    $pdo = getPDO();
    for ($execucao = 1; $execucao <= 2; $execucao++) {
        $pdo->exec($sql);
        echo "[MIG-DOC-CPF-CNPJ][RUN-{$execucao}][OK] migration executada.\n";
    }
    $stmt = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH AS tamanho FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'cpf'");
    $tamanho = (int)($stmt->fetch(PDO::FETCH_ASSOC)['tamanho'] ?? 0);
    if ($tamanho < 14) {
        throw new RuntimeException("usuarios.cpf permanece com {$tamanho} caracteres.");
    }
    echo "[MIG-DOC-CPF-CNPJ][ASSERT][OK] usuarios.cpf suporta {$tamanho} caracteres.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[MIG-DOC-CPF-CNPJ][FAIL][' . get_class($e) . '] ' . $e->getMessage() . "\n");
    exit(1);
}
