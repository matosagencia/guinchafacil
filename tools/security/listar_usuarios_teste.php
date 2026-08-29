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
 * tools/security/listar_usuarios_teste.php
 * Uso único e manual: lista todos os usuários (id, email, tipo, ativo)
 * para diagnóstico de contas de teste do gate Playwright.
 * Uso: php tools/security/listar_usuarios_teste.php
 */
require_once __DIR__ . '/../../config.php';

$pdo = getPDO();
$rows = $pdo->query("SELECT id, nome, email, tipo, ativo FROM usuarios ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "Nenhum usuario encontrado.\n";
    exit;
}

foreach ($rows as $u) {
    echo $u['id'] . ' | ' . $u['email'] . ' | ' . $u['tipo'] . ' | ativo=' . $u['ativo'] . ' | ' . $u['nome'] . "\n";
}
