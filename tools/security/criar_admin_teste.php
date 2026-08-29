<?php
declare(strict_types=1);

/**
 * tools/security/criar_admin_teste.php
 * Uso único e manual: cria (ou atualiza a senha de) uma conta admin de
 * teste, no mesmo padrão das contas QA já usadas em cliente.sim@test.com
 * e guincho.sim@test.com. NÃO usar em produção.
 * Uso: php tools/security/criar_admin_teste.php
 */

// §SEC-TOOLS-01: cria admin com senha fixa e conhecida — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once __DIR__ . '/../../config.php';

$email = 'admin.sim@test.com';
$senha = 'Admin@123';
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existente = $stmt->fetch(PDO::FETCH_ASSOC);

$hash = password_hash($senha, PASSWORD_DEFAULT);

if ($existente) {
    $pdo->prepare("UPDATE usuarios SET senha_hash = ?, tipo = 'admin', ativo = 1 WHERE id = ?")
        ->execute([$hash, $existente['id']]);
    echo "[OK] Admin de teste já existia (id={$existente['id']}) — senha redefinida.\n";
} else {
    $pdo->prepare("
        INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo, criado_em)
        VALUES ('Admin Teste', ?, ?, '21999999999', '00000000000', 'admin', 1, NOW())
    ")->execute([$email, $hash]);
    echo "[OK] Admin de teste criado (id=" . $pdo->lastInsertId() . ").\n";
}

echo "Email: {$email}\n";
echo "Senha: {$senha}\n";
