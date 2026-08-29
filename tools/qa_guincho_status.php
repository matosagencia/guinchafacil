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

// Utilitário de QA: devolve campos de um guincho direto do banco — usado
// pela suíte funcionario-gerente-demandas.spec.ts para confirmar o efeito
// real de uma demanda de alteracao_dados (ex.: chave PIX) depois que o
// gerente aprova.
if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_guincho_status.php <guincho_id>' . PHP_EOL);
    exit(1);
}

$guinchoId = (int)$argv[1];

try {
    $stmt = getPDO()->prepare('SELECT * FROM guinchos WHERE id = ? LIMIT 1');
    $stmt->execute([$guinchoId]);
    $guincho = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$guincho) {
        echo json_encode(['ok' => false, 'erro' => 'Guincho não encontrado.']) . PHP_EOL;
        exit;
    }

    echo json_encode([
        'ok' => true,
        'guincho_id' => (int)$guincho['id'],
        'chave_pix' => $guincho['chave_pix'],
        'chave_pix_tipo' => $guincho['chave_pix_tipo'],
        // §E2E-HIBRIDO-02 (27/07/2026): 'disponivel' e 'aprovado' foram
        // acrescentados (SELECT * já trazia tudo — só faltava expor) para
        // o teste de cancelamento durante 'aguardando_pagamento_reboque_hibrido'
        // conseguir confirmar que o prestador híbrido foi mesmo liberado
        // de volta (disponivel=1) pela transição genérica de cancelamento.
        'disponivel' => isset($guincho['disponivel']) ? (int)$guincho['disponivel'] : null,
        'aprovado' => isset($guincho['aprovado']) ? (int)$guincho['aprovado'] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
