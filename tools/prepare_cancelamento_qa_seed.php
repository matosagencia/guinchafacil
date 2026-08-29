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

// Reusa as mesmas contas fixas dos demais seeds QA (pw_teste / pw_guincho),
// já garantidas por tools/prepare_p1_qa_seeds.php. Este script só cria/reseta
// os PEDIDOS necessários para os 4 cenários de cancelamento cobertos por
// qa/suites/cancelamento.spec.ts:
//   1) cancelamento antes do aceite (aguardando_guincho) — grátis
//   2) cancelamento do cliente após aceite/deslocamento (a_caminho) — com taxa
//   3) bloqueio de cancelamento em fase irreversível (no_local)
//   4) cancelamento pelo guincho após aceite (a_caminho) — penalidade + reabertura
const QA_CLIENTE_EMAIL = 'pw_teste@guinchafacil.com';
const QA_GUINCHO_EMAIL = 'pw_guincho@guinchafacil.com';
const QA_PASSWORD = 'test123';

function qacExec(): PDO
{
    return getPDO();
}

function qacUsuario(string $email): array
{
    $usuario = Usuario::buscarPorEmail($email);
    if (!$usuario) {
        throw new RuntimeException("Usuário QA não encontrado: {$email}. Rode tools/prepare_p1_qa_seeds.php antes.");
    }
    return $usuario;
}

function qacVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }
    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAT1A23',
        'marca' => 'Fiat',
        'modelo' => 'Mobi',
        'ano' => 2022,
        'cor' => 'Branco',
        'tipo' => 'passeio',
    ]);
    $stmt = qacExec()->prepare('SELECT * FROM veiculos WHERE id = ? LIMIT 1');
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Encontra (por marcador único em descricao_problema) ou cria um pedido, depois
 * garante que ele esteja no estado inicial esperado do cenário — idempotente e
 * reexecutável mesmo depois que um teste anterior já cancelou/mudou o pedido.
 */
function qacEnsurePedido(string $marker, int $clienteId, int $veiculoId, array $create, array $resetFields): array
{
    $pdo = qacExec();
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

    $fields = $resetFields;
    $fields['cancelado_por = ?'] = null;
    $fields['motivo_cancelamento = ?'] = null;
    if (qacHasColumn('pedidos', 'taxa_cancelamento')) {
        $fields['taxa_cancelamento = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    // Qualquer snapshot de cancelamento anterior fica órfão do pedido resetado;
    // limpa para não confundir cancelarPorCliente() com um snapshot antigo já
    // confirmado/expirado de uma rodada de teste passada.
    if (qacTableExists('cancelamento_snapshots')) {
        $pdo->prepare('DELETE FROM cancelamento_snapshots WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qacHasColumn(string $table, string $column): bool
{
    $stmt = qacExec()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function qacTableExists(string $table): bool
{
    $stmt = qacExec()->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $cliente = qacUsuario(QA_CLIENTE_EMAIL);
    $guinchoUsuario = qacUsuario(QA_GUINCHO_EMAIL);
    $guincho = Guincho::buscarPorUsuario((int)$guinchoUsuario['id']);
    if (!$guincho) {
        throw new RuntimeException('Guincho QA não encontrado. Rode tools/prepare_p1_qa_seeds.php antes.');
    }
    $veiculo = qacVeiculo((int)$cliente['id']);

    // 1) Antes do aceite — aguardando_guincho, sem guincho atribuído: cancelamento grátis.
    $antesAceite = qacEnsurePedido(
        'Seed cancelamento - antes do aceite',
        (int)$cliente['id'],
        (int)$veiculo['id'],
        [
            'tipo_problema' => 'Pane elétrica QA',
            'lat_origem' => -23.55052, 'lng_origem' => -46.63331,
            'endereco_origem' => 'Praça da Sé, São Paulo - SP',
            'lat_destino' => -23.56140, 'lng_destino' => -46.65650,
            'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
            'distancia_km' => 5.8, 'custo_estimado' => 149.90,
            'status' => 'aguardando_guincho',
            'raio_atual_km' => 10, 'score_minimo_atual' => 0.5,
        ],
        [
            'status = ?' => 'aguardando_guincho',
            'guincho_id = ?' => null,
            'custo_estimado = ?' => 149.90,
        ]
    );

    // 2) Cliente cancela após aceite/deslocamento — a_caminho, fora da janela grátis: taxa cobrada.
    // Coordenadas concentradas na mesma região do restante da frota QA (São
    // Paulo, mesma área usada por prepare_p1_qa_seeds.php e
    // prepare_atendimento_completo_qa_seed.php) — o mapa operacional do admin
    // centraliza a visão nesse ponto (-23.55052,-46.633308), então manter
    // todos os pedidos seed na mesma cidade é o que permite acompanhá-los
    // visualmente sem precisar navegar o mapa manualmente.
    $clienteTaxa = qacEnsurePedido(
        'Seed cancelamento - taxa cliente',
        (int)$cliente['id'],
        (int)$veiculo['id'],
        [
            'tipo_problema' => 'Pneu furado QA',
            'lat_origem' => -23.55643, 'lng_origem' => -46.64591,
            'endereco_origem' => 'Viaduto Nove de Julho, São Paulo - SP',
            'lat_destino' => -23.56140, 'lng_destino' => -46.65650,
            'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
            'distancia_km' => 8.2, 'custo_estimado' => 199.90,
            'status' => 'a_caminho',
            'raio_atual_km' => 10, 'score_minimo_atual' => 0.5,
        ],
        [
            'status = ?' => 'a_caminho',
            'guincho_id = ?' => (int)$guincho['id'],
            'custo_estimado = ?' => 199.90,
        ]
    );
    // Fora da janela de cancelamento_gratis_min (default 5min) para garantir que a taxa seja cobrada.
    qacExec()->prepare('UPDATE pedidos SET criado_em = DATE_SUB(NOW(), INTERVAL 20 MINUTE) WHERE id = ?')
        ->execute([(int)$clienteTaxa['id']]);
    $clienteTaxa = Pedido::buscarPorId((int)$clienteTaxa['id']);

    // 3) Fase irreversível — no_local: cliente não pode mais cancelar.
    $irreversivel = qacEnsurePedido(
        'Seed cancelamento - irreversivel',
        (int)$cliente['id'],
        (int)$veiculo['id'],
        [
            'tipo_problema' => 'Bateria descarregada QA',
            'lat_origem' => -23.56084, 'lng_origem' => -46.65274,
            'endereco_origem' => 'Rua Augusta, São Paulo - SP',
            'lat_destino' => -23.56140, 'lng_destino' => -46.65650,
            'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
            'distancia_km' => 8.2, 'custo_estimado' => 179.90,
            'status' => 'no_local',
            'raio_atual_km' => 10, 'score_minimo_atual' => 0.5,
        ],
        [
            'status = ?' => 'no_local',
            'guincho_id = ?' => (int)$guincho['id'],
            'custo_estimado = ?' => 179.90,
        ]
    );

    // 4) Guincho cancela após aceite — a_caminho: penalidade + pedido volta pra fila.
    $guinchoCancela = qacEnsurePedido(
        'Seed cancelamento - guincho cancela',
        (int)$cliente['id'],
        (int)$veiculo['id'],
        [
            'tipo_problema' => 'Pane seca QA',
            'lat_origem' => -23.55184, 'lng_origem' => -46.63872,
            'endereco_origem' => 'Rua Líbero Badaró, São Paulo - SP',
            'lat_destino' => -23.56140, 'lng_destino' => -46.65650,
            'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
            'distancia_km' => 6.1, 'custo_estimado' => 159.90,
            'status' => 'a_caminho',
            'raio_atual_km' => 10, 'score_minimo_atual' => 0.5,
        ],
        [
            'status = ?' => 'a_caminho',
            'guincho_id = ?' => (int)$guincho['id'],
            'custo_estimado = ?' => 159.90,
        ]
    );

    echo json_encode([
        'ok' => true,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'guincho_email' => QA_GUINCHO_EMAIL,
        'pedido_antes_aceite_id' => (int)$antesAceite['id'],
        'pedido_cliente_taxa_id' => (int)$clienteTaxa['id'],
        'pedido_irreversivel_id' => (int)$irreversivel['id'],
        'pedido_guincho_cancela_id' => (int)$guinchoCancela['id'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
