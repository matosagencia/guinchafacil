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

// Utilitário de QA: devolve o registro mais recente de env_auditoria para
// uma chave específica. Criado pra qa/suites/governanca-env-gateway.spec.ts
// (Suite E, item E1) confirmar direto no banco que salvar o formulário de
// /admin/env realmente gravou uma linha de auditoria — não dá pra confiar só
// na mensagem de sucesso da UI, que aparece mesmo se a query de auditoria
// falhar silenciosamente (ver AdminController::envSalvar(), que trata a
// auditoria como best-effort e não bloqueia o save).
if ($argc < 2 || trim((string)$argv[1]) === '') {
    fwrite(STDERR, '[ERRO] Uso: php qa_env_auditoria_ultima.php <CHAVE>' . PHP_EOL);
    exit(1);
}

$chave = strtoupper(trim((string)$argv[1]));

try {
    $stmt = getPDO()->prepare(
        'SELECT admin_id, chave, valor_mascarado, acao, hash_alteracao, criado_em
           FROM env_auditoria
          WHERE chave = ?
          ORDER BY id DESC
          LIMIT 1'
    );
    $stmt->execute([$chave]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => false, 'erro' => "Nenhum registro de auditoria para {$chave}."]) . PHP_EOL;
        exit;
    }

    echo json_encode([
        'ok' => true,
        'admin_id' => (int)$row['admin_id'],
        'chave' => $row['chave'],
        'valor_mascarado' => $row['valor_mascarado'],
        'acao' => $row['acao'],
        'hash_alteracao' => $row['hash_alteracao'],
        'criado_em' => $row['criado_em'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
