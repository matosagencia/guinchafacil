<?php
/**
 * Cria/atualiza clientes de teste com senha conhecida.
 * Uso: C:\xampp\php\php.exe tools\criar_clientes_teste.php
 */
require_once __DIR__ . '/../config.php';

$senha = 'teste123';
$hash  = password_hash($senha, PASSWORD_BCRYPT);

$clientes = [
    ['nome' => 'Cliente Teste 1', 'email' => 'cliente1@teste.local', 'telefone' => '21999990001'],
    ['nome' => 'Cliente Teste 2', 'email' => 'cliente2@teste.local', 'telefone' => '21999990002'],
    ['nome' => 'Cliente Teste 3', 'email' => 'cliente3@teste.local', 'telefone' => '21999990003'],
];

$pdo = getPDO();

foreach ($clientes as $c) {
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$c['email']]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $pdo->prepare("UPDATE usuarios SET nome = ?, senha_hash = ?, telefone = ?, tipo = 'cliente', ativo = 1 WHERE id = ?")
            ->execute([$c['nome'], $hash, $c['telefone'], (int)$existing]);
        echo "[OK] Atualizado: {$c['email']} (id={$existing})\n";
    } else {
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, telefone, tipo, ativo, perfil_status, criado_em)
                       VALUES (?, ?, ?, ?, 'cliente', 1, 'COMPLETO', NOW())")
            ->execute([$c['nome'], $c['email'], $hash, $c['telefone']]);
        echo "[OK] Criado: {$c['email']} (id=" . $pdo->lastInsertId() . ")\n";
    }
}

echo "\nSenha para todos: $senha\n";