<?php
declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


/**
 * tools/security/reset_passwords_manual.php
 *
 * Uso único e manual: redefine a senha de contas específicas para um valor
 * temporário fornecido pelo próprio admin. NÃO deixe este arquivo rodando
 * em produção além do necessário — apague-o (ou o deixe, pois já está
 * bloqueado por .htaccess) depois de confirmar o resultado.
 *
 * Uso: php tools/security/reset_passwords_manual.php
 */

require_once __DIR__ . '/../../config.php';

$novaSenha = 'Admin@123';
$emails = [
'cliente.sim@test.com',
'guincho.sim@test.com',
];

$hash = password_hash($novaSenha, PASSWORD_DEFAULT);
$pdo = getPDO();

echo "===============================================\n";
echo " GuinchaFácil — Redefinição manual de senhas\n";
echo "===============================================\n\n";

foreach ($emails as $email) {
    $stmt = $pdo->prepare("SELECT id, nome, tipo FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo "[SKIP] Não encontrado: {$email}\n";
        continue;
    }

    $update = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
    $update->execute([$hash, $usuario['id']]);

    echo "[OK]   {$email} (id={$usuario['id']}, tipo={$usuario['tipo']}, nome={$usuario['nome']}) — senha redefinida.\n";
}

echo "\nNova senha temporária para todas: {$novaSenha}\n";
echo "Recomenda-se trocar novamente após o primeiro login.\n";
