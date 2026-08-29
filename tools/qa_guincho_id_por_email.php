<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// Contas de guincho criadas via UI real (onboarding-stress.spec.ts) só
// existem depois do cadastro — o teste não tem o guincho_id de antemão
// (diferente dos seeds fixos, que reusam sempre o mesmo e-mail). Este
// utilitário resolve usuarios.email -> guinchos.id pra permitir checar o
// estado real de capacidade (qa_get_capacidade_status.php) logo depois do
// fluxo de UI.
//
// Uso: php qa_guincho_id_por_email.php <email>

if ($argc < 2 || trim((string)$argv[1]) === '') {
    fwrite(STDERR, '[ERRO] Uso: php qa_guincho_id_por_email.php <email>' . PHP_EOL);
    exit(1);
}

$email = trim((string)$argv[1]);

try {
    $stmt = getPDO()->prepare(
        'SELECT g.id AS guincho_id FROM guinchos g
          JOIN usuarios u ON u.id = g.usuario_id
         WHERE u.email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $guinchoId = $stmt->fetchColumn();

    if ($guinchoId === false) {
        echo json_encode(['ok' => false, 'erro' => "nenhum guincho encontrado para o e-mail {$email}"], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit;
    }

    echo json_encode(['ok' => true, 'guincho_id' => (int)$guinchoId], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
