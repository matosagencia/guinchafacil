<?php
// File: guinchafacil/tools/reset_admin_password.php
// Uso (CLI apenas): php tools/reset_admin_password.php admin@guinchafacil.com "NovaSenhaForte123"
// §SEC-TOOLS-01: reset de senha sem autenticação — só pode rodar via CLI.
// Antes aceitava ?email=&senha= por GET (qualquer um com a URL redefinia a
// senha de qualquer usuário). Removido: acesso via navegador agora é 403,
// independente do .htaccess estar ou não honrando o bloqueio de tools/.
// ATENÇÃO: apague este arquivo após usar.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Services/Logger.php';

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo $msg;
    exit;
}

$email = $argv[1] ?? '';
$senha = $argv[2] ?? '';

$email = filter_var(trim((string)$email), FILTER_VALIDATE_EMAIL);
if (!$email) {
    fail("Email inválido.\n");
}

$senha = (string)$senha;
if (strlen($senha) < 8) {
    fail("Senha fraca: mínimo 8 caracteres.\n");
}

try {
    $pdo = getPDO();
    $hash = password_hash($senha, PASSWORD_BCRYPT);

    $st = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE email = ? LIMIT 1');
    $st->execute([$hash, $email]);

    if ($st->rowCount() < 1) {
        fail("Nenhum usuário encontrado com esse email.\n", 404);
    }

    Logger::log(Logger::LEVEL_INFO, 'Tools', 'reset_admin_password', 'bcrypt', 'Senha redefinida com sucesso', [
        'email' => $email,
        'mode' => 'cli',
    ]);

    echo "OK: senha atualizada para {$email}.\n";
    exit;
} catch (Throwable $e) {
    Logger::exception('Tools', 'reset_admin_password', 'pdo', $e, ['email' => $email]);
    fail("Erro ao atualizar senha (ver error_log).\n", 500);
}
