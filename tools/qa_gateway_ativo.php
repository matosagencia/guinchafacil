<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


require_once dirname(__DIR__) . '/config.php';

// Utilitário de QA: devolve o PAYMENT_GATEWAY_ACTIVE atual, lido do mesmo
// jeito que o resto do app (via config.php, que reprocessa o .env gerenciado
// a cada request — não precisa reiniciar Apache/PHP pra uma alteração feita
// em /admin/env aparecer aqui). Usado pela Suite E (item E2) pra confirmar
// que a troca de gateway feita pelo admin realmente persistiu no arquivo,
// sem depender de raspar a UI de novo.
echo json_encode([
    'ok' => true,
    'payment_gateway_active' => defined('PAYMENT_GATEWAY_ACTIVE') ? PAYMENT_GATEWAY_ACTIVE : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
