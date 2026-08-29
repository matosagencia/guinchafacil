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
require_once dirname(__DIR__) . '/src/Models/Guincho.php';
require_once dirname(__DIR__) . '/src/Models/Veiculo.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';

// Seed idempotente para a suíte funcionario-gerente-demandas.spec.ts.
// Reusa cliente/guincho fixos de tools/prepare_p1_qa_seeds.php (rode-o antes
// se este script reclamar que não encontrou as contas). Cria/garante:
//   - 1 conta funcionário + 2 contas gerente (dupla aprovação precisa de
//     dois gerentes DIFERENTES — ver DemandaService::decidir()).
//   - pedido em a_caminho p/ demanda de cancelamento.
//   - pedido em a_caminho (sem evidência GPS) p/ demanda de conclusão manual.
//   - payment_job em 'failed' p/ demanda de pagamento (reprocessar repasse).
//   - pedido concluído com pagamento aprovado de baixo valor p/ demanda de
//     reembolso com aprovação única.
//   - pedido concluído com pagamento aprovado de alto valor (acima do
//     limiar demanda_valor_dupla_aprovacao) p/ demanda de reembolso com
//     dupla aprovação.
const QA_CLIENTE_EMAIL = 'pw_teste@guinchafacil.com';
const QA_GUINCHO_EMAIL = 'pw_guincho@guinchafacil.com';
const QA_FUNCIONARIO_EMAIL = 'pw_funcionario@guinchafacil.com';
const QA_GERENTE_EMAIL = 'pw_gerente@guinchafacil.com';
const QA_GERENTE2_EMAIL = 'pw_gerente2@guinchafacil.com';
const QA_PASSWORD = 'test123';

function fgExec(): PDO
{
    return getPDO();
}

function fgUsuario(string $email): array
{
    $usuario = Usuario::buscarPorEmail($email);
    if (!$usuario) {
        throw new RuntimeException("Usuário QA não encontrado: {$email}. Rode tools/prepare_p1_qa_seeds.php antes.");
    }
    return $usuario;
}

function fgEnsureConta(string $email, string $nome, string $tipo, string $cpf): array
{
    $usuario = Usuario::buscarPorEmail($email);
    $hash = password_hash(QA_PASSWORD, PASSWORD_BCRYPT);
    if (!$usuario) {
        $id = (int)Usuario::criar([
            'nome' => $nome,
            'email' => $email,
            'senha_hash' => $hash,
            'telefone' => '21999990000',
            'cpf' => $cpf,
            'tipo' => $tipo,
        ]);
        $usuario = Usuario::buscarPorId($id);
    }
    fgExec()->prepare("UPDATE usuarios SET senha_hash = ?, ativo = 1, tipo = ?, nome = ? WHERE id = ?")
        ->execute([$hash, $tipo, $nome, (int)$usuario['id']]);
    return Usuario::buscarPorId((int)$usuario['id']);
}

function fgEnsurePedido(string $marker, int $clienteId, int $veiculoId, int $guinchoId, array $create): array
{
    $pdo = fgExec();
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE cliente_id = ? AND descricao_problema = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$clienteId, $marker]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $pedidoId = Pedido::criar(array_merge([
            'cliente_id' => $clienteId,
            'veiculo_id' => $veiculoId,
            'descricao_problema' => $marker,
        ], $create));
        if (!$pedidoId) {
            throw new RuntimeException("Falha ao criar pedido seed: {$marker}");
        }
        $pedido = Pedido::buscarPorId((int)$pedidoId);
    }

    $pdo->prepare(
        "UPDATE pedidos SET status = ?, guincho_id = ?, custo_estimado = ?, custo_final = ?,
                concluido_manualmente = 0, revisao_manual_status = NULL
          WHERE id = ?"
    )->execute([
        $create['status'],
        $guinchoId ?: null,
        $create['custo_estimado'],
        $create['custo_final'] ?? null,
        (int)$pedido['id'],
    ]);

    return Pedido::buscarPorId((int)$pedido['id']);
}

function fgEnsurePagamento(int $pedidoId, float $valorTotal, float $valorGuincho): int
{
    $pdo = fgExec();
    $stmt = $pdo->prepare("SELECT id FROM pagamentos WHERE pedido_id = ? LIMIT 1");
    $stmt->execute([$pedidoId]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existe) {
        $pdo->prepare("UPDATE pagamentos SET status = 'aprovado', valor_total = ?, valor_guincho = ?, valor_plataforma = ?, pago_guincho = 0 WHERE id = ?")
            ->execute([$valorTotal, $valorGuincho, $valorTotal - $valorGuincho, (int)$existe['id']]);
        return (int)$existe['id'];
    }
    $pdo->prepare(
        "INSERT INTO pagamentos (pedido_id, metodo, valor_total, valor_guincho, valor_plataforma, status, id_externo, criado_em)
         VALUES (?, 'mercadopago', ?, ?, ?, 'aprovado', ?, NOW())"
    )->execute([$pedidoId, $valorTotal, $valorGuincho, $valorTotal - $valorGuincho, 'QA-FG-' . $pedidoId]);
    return (int)$pdo->lastInsertId();
}

function fgEnsurePaymentJobFalho(int $pedidoId, int $pagamentoId): int
{
    $pdo = fgExec();
    $stmt = $pdo->prepare("SELECT id FROM payment_jobs WHERE pedido_id = ? LIMIT 1");
    $stmt->execute([$pedidoId]);
    $existe = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existe) {
        $pdo->prepare("UPDATE payment_jobs SET status = 'failed', last_error = 'QA: falha simulada', attempt_count = max_attempts WHERE id = ?")
            ->execute([(int)$existe['id']]);
        return (int)$existe['id'];
    }
    $pdo->prepare(
        "INSERT INTO payment_jobs (pedido_id, pagamento_id, job_type, idempotency_key, status, attempt_count, max_attempts, last_error, created_at)
         VALUES (?, ?, 'pix_payout', ?, 'failed', 5, 5, 'QA: falha simulada', NOW())"
    )->execute([$pedidoId, $pagamentoId, 'qa-fg-job-' . $pedidoId]);
    return (int)$pdo->lastInsertId();
}

try {
    $cliente = fgUsuario(QA_CLIENTE_EMAIL);
    $guinchoUsuario = fgUsuario(QA_GUINCHO_EMAIL);
    $guincho = Guincho::buscarPorUsuario((int)$guinchoUsuario['id']);
    if (!$guincho) {
        throw new RuntimeException('Guincho QA não encontrado. Rode tools/prepare_p1_qa_seeds.php antes.');
    }
    $veiculos = Veiculo::listarPorUsuario((int)$cliente['id']);
    $veiculo = $veiculos[0] ?? null;
    if (!$veiculo) {
        throw new RuntimeException('Veículo QA não encontrado. Rode tools/prepare_p1_qa_seeds.php antes.');
    }

    // Reset da chave PIX do guincho QA a cada rodada. FG-FLUXO-005 afirma
    // que a chave só muda para 'chave-pix-qa-teste@example.com' DEPOIS da
    // dupla aprovação — sem resetar aqui, uma execução anterior bem-sucedida
    // deixa esse valor já aplicado, e a asserção "ainda não mudou" (feita
    // antes da segunda aprovação) falha eternamente em toda run seguinte.
    $pdo = fgExec();
    $pdo->prepare("UPDATE guinchos SET chave_pix = ?, chave_pix_tipo = ? WHERE id = ?")
        ->execute(['seed-baseline-pix@example.com', 'email', (int)$guincho['id']]);

    $funcionario = fgEnsureConta(QA_FUNCIONARIO_EMAIL, 'Funcionário QA', 'funcionario', '91100000001');
    $gerente = fgEnsureConta(QA_GERENTE_EMAIL, 'Gerente QA', 'gerente', '91100000002');
    $gerente2 = fgEnsureConta(QA_GERENTE2_EMAIL, 'Gerente QA 2', 'gerente', '91100000003');

    $baseCampos = [
        'tipo_problema' => 'Pane elétrica QA',
        'lat_origem' => -23.55052, 'lng_origem' => -46.63331,
        'endereco_origem' => 'Praça da Sé, São Paulo - SP',
        'lat_destino' => -23.56140, 'lng_destino' => -46.65650,
        'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
        'distancia_km' => 5.8,
        'raio_atual_km' => 10, 'score_minimo_atual' => 0.5,
    ];

    $pedidoCancelamento = fgEnsurePedido('Seed FG - demanda cancelamento', (int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id'], array_merge($baseCampos, [
        'status' => 'a_caminho', 'custo_estimado' => 149.90,
    ]));

    $pedidoConclusaoManual = fgEnsurePedido('Seed FG - demanda conclusao manual', (int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id'], array_merge($baseCampos, [
        'status' => 'a_caminho', 'custo_estimado' => 179.90,
    ]));

    $pedidoPagamento = fgEnsurePedido('Seed FG - demanda pagamento', (int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id'], array_merge($baseCampos, [
        'status' => 'concluido', 'custo_estimado' => 129.90, 'custo_final' => 129.90,
    ]));
    $pagamentoPagamento = fgEnsurePagamento((int)$pedidoPagamento['id'], 129.90, 100.00);
    $paymentJobId = fgEnsurePaymentJobFalho((int)$pedidoPagamento['id'], $pagamentoPagamento);

    $pedidoReembolsoSimples = fgEnsurePedido('Seed FG - demanda reembolso simples', (int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id'], array_merge($baseCampos, [
        'status' => 'concluido', 'custo_estimado' => 89.90, 'custo_final' => 89.90,
    ]));
    fgEnsurePagamento((int)$pedidoReembolsoSimples['id'], 89.90, 70.00);

    // Acima do limiar padrão (demanda_valor_dupla_aprovacao = 500.00) — força dupla aprovação.
    $pedidoReembolsoAlto = fgEnsurePedido('Seed FG - demanda reembolso dupla aprovacao', (int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id'], array_merge($baseCampos, [
        'status' => 'concluido', 'custo_estimado' => 890.00, 'custo_final' => 890.00,
    ]));
    fgEnsurePagamento((int)$pedidoReembolsoAlto['id'], 890.00, 700.00);

    echo json_encode([
        'ok' => true,
        'funcionario_email' => QA_FUNCIONARIO_EMAIL,
        'gerente_email' => QA_GERENTE_EMAIL,
        'gerente2_email' => QA_GERENTE2_EMAIL,
        'password' => QA_PASSWORD,
        'guincho_id' => (int)$guincho['id'],
        'pedido_cancelamento_id' => (int)$pedidoCancelamento['id'],
        'pedido_conclusao_manual_id' => (int)$pedidoConclusaoManual['id'],
        'pedido_pagamento_id' => (int)$pedidoPagamento['id'],
        'payment_job_id' => $paymentJobId,
        'pedido_reembolso_simples_id' => (int)$pedidoReembolsoSimples['id'],
        'pedido_reembolso_alto_id' => (int)$pedidoReembolsoAlto['id'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
