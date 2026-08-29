<?php
// Seed idempotente: cria as duas contas de teste pedidas (funcionário e
// gerente), só para ambiente de simulação/QA. Rodar via CLI:
//   C:\xampp\php\php.exe install\seed_funcionario_gerente_sim.php
// Seguro rodar de novo — se o e-mail já existir, só atualiza a senha/tipo
// em vez de duplicar.

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$pdo = getPDO();

$contas = [
    ['nome' => 'Funcionário Simulação', 'email' => 'employee.sim@test.com', 'tipo' => 'funcionario'],
    ['nome' => 'Gerente Simulação',     'email' => 'manager.sim@test.com',  'tipo' => 'gerente'],
];

$senhaHash = password_hash('Admin@123', PASSWORD_BCRYPT);

foreach ($contas as $c) {
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$c['email']]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        $pdo->prepare('UPDATE usuarios SET senha_hash = ?, tipo = ?, ativo = 1 WHERE id = ?')
            ->execute([$senhaHash, $c['tipo'], $existente['id']]);
        echo "[OK]   {$c['email']} já existia — senha/tipo atualizados (id={$existente['id']}).\n";
        continue;
    }

    $pdo->prepare(
        'INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
    )->execute([
        $c['nome'],
        $c['email'],
        $senhaHash,
        '11999999999',
        // CPF placeholder único por conta (evita colisão com UNIQUE(cpf) se existir) — troque depois se for usar de verdade.
        $c['tipo'] === 'funcionario' ? '11111111111' : '22222222222',
        $c['tipo'],
    ]);
    echo "[NOVO] {$c['email']} criado como '{$c['tipo']}' (senha: Admin@123).\n";
}

echo "\nPronto. Login em /login com as senhas acima.\n";
