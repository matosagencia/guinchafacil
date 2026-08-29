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
require_once dirname(__DIR__) . '/src/Models/Veiculo.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';
require_once dirname(__DIR__) . '/src/Models/Pagamento.php';

// §COBERTURA-RAIO-01 (05/08/2026): seed pro cenário "ninguém aceitou em 30
// min" — cria (ou reseta, idempotente) um pedido em aguardando_guincho com
// expiracao_aceite já VENCIDA e um pagamento aprovado com id_externo fictício,
// pra qa/suites/cobertura-timeout-estorno.spec.ts rodar
// ExpiracaoPedidosService::executar() (via tools/qa_executar_cron_expiracao.php)
// e confirmar: (1) pedido cancelado automaticamente, (2) pagamento nunca fica
// preso em 'estornando' (vira 'estornado' se o gateway aceitar o refund, ou
// volta pra 'aprovado' se a chamada falhar — o id_externo é fictício de
// propósito, então falhar é o esperado num ambiente sem credenciais reais de
// sandbox; o que importa provar é que o pedido é cancelado e o pagamento
// nunca fica num limbo).
const QA_CLIENTE_EMAIL = 'pw_teste@guinchafacil.com';
const QA_PASSWORD = 'test123';

function qteExec(): PDO
{
    return getPDO();
}

function qteUsuario(string $email): array
{
    $usuario = Usuario::buscarPorEmail($email);
    if (!$usuario) {
        throw new RuntimeException("Usuário QA não encontrado: {$email}. Rode tools/prepare_p1_qa_seeds.php antes.");
    }
    return $usuario;
}

function qteVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }
    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAT9Z99',
        'marca' => 'Fiat',
        'modelo' => 'Mobi',
        'ano' => 2022,
        'cor' => 'Branco',
        'tipo' => 'passeio',
    ]);
    $stmt = qteExec()->prepare('SELECT * FROM veiculos WHERE id = ? LIMIT 1');
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

try {
    $cliente = qteUsuario(QA_CLIENTE_EMAIL);
    $veiculo = qteVeiculo((int)$cliente['id']);
    $pdo = qteExec();

    $marker = 'Seed timeout-estorno - aceite expirado';
    $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE cliente_id = ? AND descricao_problema = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int)$cliente['id'], $marker]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        $pedidoId = Pedido::criar([
            'cliente_id' => (int)$cliente['id'],
            'veiculo_id' => (int)$veiculo['id'],
            'descricao_problema' => $marker,
            'tipo_problema' => 'Pane elétrica QA (timeout)',
            'lat_origem' => -23.55052, 'lng_origem' => -46.63331,
            'endereco_origem' => 'Praça da Sé, São Paulo - SP',
            'lat_destino' => -23.56140, 'lng_destino' => -46.65650,
            'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
            'distancia_km' => 5.8, 'custo_estimado' => 149.90,
            'status' => 'aguardando_guincho',
            'raio_atual_km' => 10, 'score_minimo_atual' => 0.5,
        ]);
        if (!$pedidoId) {
            throw new RuntimeException('Falha ao criar pedido seed de timeout-estorno.');
        }
        $pedido = Pedido::buscarPorId((int)$pedidoId);
    }
    $pedidoId = (int)$pedido['id'];

    // Reseta pro estado esperado ANTES da expiração — idempotente mesmo se um
    // teste anterior já cancelou/estornou este pedido.
    $pdo->prepare(
        "UPDATE pedidos
            SET status = 'aguardando_guincho',
                guincho_id = NULL,
                cancelado_por = NULL,
                motivo_cancelamento = NULL,
                cancelado_em = NULL,
                expiracao_aceite = DATE_SUB(NOW(), INTERVAL 1 MINUTE)
          WHERE id = ?"
    )->execute([$pedidoId]);

    // Pagamento aprovado com id_externo FICTÍCIO — o gateway real vai rejeitar
    // o refund (esperado, ver comentário no topo do arquivo); o que este seed
    // garante é que exista ALGO pra EstornoService tentar estornar.
    $stmtPag = $pdo->prepare("SELECT id FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
    $stmtPag->execute([$pedidoId]);
    $pagamentoId = $stmtPag->fetchColumn();

    if (!$pagamentoId) {
        $pagamentoId = Pagamento::criar($pedidoId, 'mercadopago', 149.90, 119.92, 29.98);
        if (!$pagamentoId) {
            throw new RuntimeException('Falha ao criar pagamento seed de timeout-estorno.');
        }
    }
    $pdo->prepare("UPDATE pagamentos SET status = 'aprovado', id_externo = ? WHERE id = ?")
        ->execute(['mp_qa_timeout_' . $pedidoId, (int)$pagamentoId]);

    echo json_encode([
        'ok' => true,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'pedido_id' => $pedidoId,
        'pagamento_id' => (int)$pagamentoId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
