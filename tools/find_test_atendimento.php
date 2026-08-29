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

$email = getenv('TEST_GUINCHO_EMAIL');
if ($email === false || trim($email) === '') {
    $email = 'pw_guincho@guinchafacil.com';
}

$pdo = getPDO();
$stmt = $pdo->prepare(
    "SELECT
        p.id,
        p.status,
        p.guincho_id,
        u.email AS guincho_email,
        u.nome AS guincho_nome,
        p.criado_em
     FROM pedidos p
     INNER JOIN guinchos g ON g.id = p.guincho_id
     INNER JOIN usuarios u ON u.id = g.usuario_id
     WHERE u.email = ?
       AND p.status IN ('no_local', 'em_reboque', 'a_caminho')
     ORDER BY
       CASE p.status
         WHEN 'no_local' THEN 1
         WHEN 'em_reboque' THEN 2
         WHEN 'a_caminho' THEN 3
         ELSE 9
       END,
       p.id DESC"
);
$stmt->execute([$email]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allStmt = $pdo->query(
    "SELECT
        p.id,
        p.status,
        p.guincho_id,
        u.email AS guincho_email,
        u.nome AS guincho_nome,
        p.criado_em
     FROM pedidos p
     INNER JOIN guinchos g ON g.id = p.guincho_id
     INNER JOIN usuarios u ON u.id = g.usuario_id
     WHERE p.status IN ('no_local', 'em_reboque', 'a_caminho')
     ORDER BY
       CASE p.status
         WHEN 'no_local' THEN 1
         WHEN 'em_reboque' THEN 2
         WHEN 'a_caminho' THEN 3
         ELSE 9
       END,
       p.id DESC
     LIMIT 20"
);
$allRows = $allStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'email' => $email,
    'count' => count($rows),
    'candidates' => $rows,
    'fallback_count' => count($allRows),
    'fallback_candidates' => $allRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
