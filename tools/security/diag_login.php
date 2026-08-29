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
 * Diagnóstico pontual de login — uso único e manual (L1.10 QA).
 * Apague depois de usar.
 */
require_once __DIR__ . '/../../config.php';

$pdo = getPDO();
$emails = ['cliente.sim@test.com', 'guincho.sim@test.com'];

foreach ($emails as $email) {
    $stmt = $pdo->prepare("SELECT id, nome, email, tipo, ativo, senha_hash FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "=== {$email} ===\n";
    if (!$u) {
        echo "  NAO ENCONTRADO no banco.\n\n";
        continue;
    }
    echo "  id={$u['id']} nome={$u['nome']} tipo={$u['tipo']} ativo={$u['ativo']}\n";
    $ok = password_verify('Admin@123', $u['senha_hash'] ?? '');
    echo "  password_verify('Admin@123', hash) = " . ($ok ? 'TRUE' : 'FALSE') . "\n";
    echo "  hash prefix: " . substr((string)$u['senha_hash'], 0, 10) . "...\n\n";
}

echo "APP_URL configurado: " . (defined('APP_URL') ? APP_URL : '(nao definido)') . "\n";
