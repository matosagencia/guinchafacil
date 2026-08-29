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
require_once dirname(__DIR__) . '/src/Models/Usuario.php';

// Garante uma conta admin fixa para QA (idempotente, mesmo padrão dos
// outros seeds). AuthService::requireAuth() aceita tipo='admin' pra
// qualquer perfil (ver AuthService.php:63), então basta usuarios.tipo='admin'
// + ativo=1 — não existe tabela de permissões separada.
//
// A suíte qa/fixtures/test-data.fixture.ts::adminCreds() não tem default
// (só lê TEST_ADMIN_EMAIL/PASSWORD), de propósito, pra não rodar testes
// administrativos sem opt-in explícito em CI compartilhado. Este seed é
// consumido diretamente pelo spec de onboarding completo
// (qa/suites/onboarding-completo.spec.ts), que precisa aprovar um guincho
// recém-cadastrado — não altera o comportamento da fixture compartilhada.
const QA_ADMIN_EMAIL = 'pw_admin@guinchafacil.com';
const QA_ADMIN_PASSWORD = 'test123';

try {
    $usuario = Usuario::buscarPorEmail(QA_ADMIN_EMAIL);
    if (!$usuario) {
        // '00000000000' já estava em uso por outra conta (colisão de
        // UNIQUE KEY cpf) — em vez de um CPF fixo, gera um a partir de um
        // prefixo fixo + timestamp, praticamente livre de colisão entre
        // execuções e fácil de reconhecer como conta de QA em uma consulta.
        $cpf = '999' . str_pad((string)(time() % 100000000), 8, '0', STR_PAD_LEFT);
        $id = (int)Usuario::criar([
            'nome' => 'Admin QA',
            'email' => QA_ADMIN_EMAIL,
            'senha_hash' => password_hash(QA_ADMIN_PASSWORD, PASSWORD_BCRYPT),
            'telefone' => '21999990099',
            'cpf' => $cpf,
            'tipo' => 'admin',
        ]);
        $usuario = Usuario::buscarPorId($id);
    }

    getPDO()->prepare(
        "UPDATE usuarios SET senha_hash = ?, ativo = 1, tipo = 'admin', nome = 'Admin QA' WHERE id = ?"
    )->execute([password_hash(QA_ADMIN_PASSWORD, PASSWORD_BCRYPT), (int)$usuario['id']]);

    // Higiene: pedidos de rodadas anteriores de onboarding-completo.spec.ts
    // que ficaram "aguardando_guincho" (porque o teste falhou antes do
    // aceite, ou porque outro pedido "roubou" o destaque do dashboard —
    // exatamente o bug encontrado nesta suíte) continuam válidos e
    // competindo por ranking com o pedido novo desta rodada, fazendo o
    // dashboard do guincho mostrar em destaque um pedido velho em vez do
    // atual. Cancela pedidos de teste com mais de 10 minutos pra não
    // acumular indefinidamente a cada execução do gate.
    getPDO()->prepare(
        "UPDATE pedidos
            SET status = 'cancelado', motivo_cancelamento = '[limpeza QA] pedido de teste antigo (onboarding) cancelado para não competir com pedidos novos.'
          WHERE status = 'aguardando_guincho'
            AND descricao_problema LIKE 'QA Onboarding%'
            AND criado_em < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    )->execute();

    echo json_encode([
        'ok' => true,
        'admin_email' => QA_ADMIN_EMAIL,
        'admin_password' => QA_ADMIN_PASSWORD,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
