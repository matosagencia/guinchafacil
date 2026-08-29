<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


// tools/qa_fix_test_domain_emails.php
//
// Corrige usuários de QA já seedados no banco com e-mail no domínio
// reservado ".test" (pw_teste@guinchafacil.test etc.) — a API do
// MercadoPago recusa payer.email nesse domínio ("payer.email must be a
// valid email", visto nos logs em checkout transparente). Os scripts de
// seed (prepare_*_qa_seed.php) já foram atualizados pra usar
// "guinchafacil.com" em novos usuários; este script só atualiza quem já
// existe, direto no banco — sem recriar linha (evitaria conflito de
// telefone/cpf únicos com o usuário antigo).
//
// Uso: php tools/qa_fix_test_domain_emails.php

require_once __DIR__ . '/../config.php';

$pdo = getPDO();

$stmt = $pdo->query("SELECT id, email FROM usuarios WHERE email LIKE '%@guinchafacil.test'");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$usuarios) {
    echo json_encode(['ok' => true, 'atualizados' => 0, 'mensagem' => 'Nenhum usuário com domínio .test encontrado.']);
    exit;
}

$atualizados = [];
$erros = [];

foreach ($usuarios as $usuario) {
    $emailNovo = preg_replace('/@guinchafacil\.test$/', '@guinchafacil.com', (string)$usuario['email']);

    try {
        $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?")
            ->execute([$emailNovo, (int)$usuario['id']]);
        $atualizados[] = ['id' => (int)$usuario['id'], 'de' => $usuario['email'], 'para' => $emailNovo];
    } catch (Throwable $e) {
        $erros[] = ['id' => (int)$usuario['id'], 'email' => $usuario['email'], 'erro' => $e->getMessage()];
    }
}

echo json_encode([
    'ok' => empty($erros),
    'atualizados' => count($atualizados),
    'detalhe' => $atualizados,
    'erros' => $erros,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
