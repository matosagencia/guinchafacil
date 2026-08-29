<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "403 - Este instalador so pode ser executado via CLI.\n";
    exit(1);
}

require __DIR__ . '/migrate.php';

if (!class_exists('Database')) {
    fwrite(STDERR, "[FALHA] Classe Database indisponivel apos migrate.php.\n");
    exit(1);
}

$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT id, email FROM usuarios WHERE tipo = 'admin' ORDER BY id ASC LIMIT 1");
$existingAdmin = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

if ($existingAdmin) {
    echo "[OK] Ja existe administrador cadastrado: {$existingAdmin['email']} (ID {$existingAdmin['id']}).\n";
    exit(0);
}

function prompt(string $label, bool $secret = false): string
{
    if ($secret && DIRECTORY_SEPARATOR !== '\\') {
        fwrite(STDOUT, $label . ': ');
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
        return $value;
    }

    fwrite(STDOUT, $label . ': ');
    return trim((string) fgets(STDIN));
}

echo "\nBootstrap seguro do primeiro administrador\n";
echo "Nenhum admin padrao sera criado automaticamente.\n\n";

$nome = prompt('Nome do administrador');
$email = prompt('Email do administrador');
$telefone = prompt('Telefone do administrador');
$cpf = prompt('CPF do administrador');
$senha = prompt('Senha do administrador', DIRECTORY_SEPARATOR !== '\\');
$confirmacao = prompt('Confirme a senha do administrador', DIRECTORY_SEPARATOR !== '\\');

if ($nome === '' || $email === '' || $telefone === '' || $cpf === '' || $senha === '') {
    fwrite(STDERR, "[FALHA] Todos os campos sao obrigatorios.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "[FALHA] Email invalido.\n");
    exit(1);
}

if ($senha !== $confirmacao) {
    fwrite(STDERR, "[FALHA] A confirmacao de senha nao confere.\n");
    exit(1);
}

if (strlen($senha) < 10) {
    fwrite(STDERR, "[FALHA] A senha deve ter pelo menos 10 caracteres.\n");
    exit(1);
}

$insert = $pdo->prepare("
    INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo, criado_em)
    VALUES (:nome, :email, :senha_hash, :telefone, :cpf, 'admin', 1, NOW())
");

$insert->execute([
    ':nome' => $nome,
    ':email' => function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email),
    ':senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
    ':telefone' => $telefone,
    ':cpf' => $cpf,
]);

$adminId = (int) $pdo->lastInsertId();
echo "[OK] Administrador criado com sucesso. ID {$adminId}.\n";
