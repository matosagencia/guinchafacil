<?php
/**
 * Cria (ou atualiza) usuario admin.
 * Uso: C:\xampp\php\php.exe tools\criar_admin_rapido.php
 */
require_once __DIR__ . '/../config.php';

$email = 'admin@email.com';
$senha = 'teste123';
$nome  = 'Admin Teste';

$pdo = getPDO();
$hash = password_hash($senha, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("SELECT id, tipo FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $pdo->prepare("UPDATE usuarios SET nome = ?, senha_hash = ?, tipo = 'admin', ativo = 1 WHERE id = ?")
        ->execute([$nome, $hash, (int)$existing['id']]);
    echo "[OK] Admin atualizado: id={$existing['id']}\n";
} else {
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo, perfil_status, criado_em)
                   VALUES (?, ?, ?, 'admin', 1, 'COMPLETO', NOW())")
        ->execute([$nome, $email, $hash]);
    echo "[OK] Admin criado: id=" . $pdo->lastInsertId() . "\n";
}

echo "  email: $email\n";
echo "  senha: $senha\n";