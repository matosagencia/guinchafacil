<?php
declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once __DIR__ . '/../../config.php';
getPDO()->exec("UPDATE usuarios SET ativo = 1 WHERE email = 'cliente.sim@test.com'");
echo "cliente.sim@test.com reativado.\n";
