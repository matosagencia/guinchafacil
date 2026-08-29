<?php
declare(strict_types=1);

/**
 * tools/security/listar_admins.php
 * Uso único e manual: lista os usuários com tipo='admin' para diagnóstico.
 * Uso: php tools/security/listar_admins.php
 */

// §SEC-TOOLS-01: vaza e-mails/ids de admins — só pode rodar via CLI.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once __DIR__ . '/../../config.php';

$pdo = getPDO();
$admins = $pdo->query("SELECT id, nome, email, ativo FROM usuarios WHERE tipo = 'admin'")->fetchAll(PDO::FETCH_ASSOC);

if (!$admins) {
    echo "Nenhum usuário com tipo='admin' encontrado.\n";
    exit;
}

foreach ($admins as $a) {
    echo "id={$a['id']}  email={$a['email']}  nome={$a['nome']}  ativo={$a['ativo']}\n";
}
