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
require_once dirname(__DIR__) . '/src/Services/POR/ProofOfRoadService.php';

const QA_CLIENTE_EMAIL = 'pw_teste@guinchafacil.com';
const QA_GUINCHO_EMAIL = 'pw_guincho@guinchafacil.com';
const QA_GUINCHO_2_EMAIL = 'pw_guincho2@guinchafacil.com';
const QA_PASSWORD = 'test123';

function upsertUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
{
    $pdo = getPDO();
    $usuario = Usuario::buscarPorEmail($email);
    if (!$usuario) {
        $id = (int)Usuario::criar([
            'nome' => $nome,
            'email' => $email,
            'senha_hash' => password_hash(QA_PASSWORD, PASSWORD_BCRYPT),
            'telefone' => $telefone,
            'cpf' => $cpf,
            'tipo' => $tipo,
        ]);
        $usuario = Usuario::buscarPorId($id);
    }

    $pdo->prepare(
        "UPDATE usuarios
            SET nome = ?, telefone = ?, cpf = ?, tipo = ?, ativo = 1, senha_hash = ?
          WHERE id = ?"
    )->execute([
        $nome,
        $telefone,
        $cpf,
        $tipo,
        password_hash(QA_PASSWORD, PASSWORD_BCRYPT),
        (int)$usuario['id'],
    ]);

    return Usuario::buscarPorId((int)$usuario['id']) ?: $usuario;
}

function hasColumn(string $table, string $column): bool
{
    $stmt = getPDO()->prepare(
        "SELECT COUNT(*)
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureGuincho(array $usuario, string $placa): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario((int)$usuario['id']);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro((int)$usuario['id'], [
            'cnh_numero' => substr(preg_replace('/\D/', '', (string)$usuario['cpf']), 0, 11),
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => $placa,
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 50,
            'chave_pix' => $usuario['email'],
            'chave_pix_tipo' => 'email',
        ]);
        $guincho = Guincho::buscarPorId((int)$guinchoId);
    }

    $fields = [
        'aprovado = ?' => 1,
        'disponivel = ?' => 1,
        'lat_atual = ?' => -23.55052,
        'lng_atual = ?' => -46.63331,
        'placa_guincho = ?' => $placa,
        'capacidade_ton = ?' => 8.0,
        'raio_cobertura_km = ?' => 50,
    ];
    if (hasColumn('guinchos', 'lat_operacao')) {
        $fields['lat_operacao = ?'] = -23.55052;
    }
    if (hasColumn('guinchos', 'lng_operacao')) {
        $fields['lng_operacao = ?'] = -46.63331;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function ensureVeiculo(int $clienteId): array
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

    $stmt = getPDO()->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function findPedidoByDescription(string $description, string $status): ?array
{
    $stmt = getPDO()->prepare(
        "SELECT *
           FROM pedidos
          WHERE descricao_problema = ?
            AND status = ?
          ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute([$description, $status]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ensureUploadPedido(array $cliente, array $veiculo, array $guincho, array $guinchoUsuario): array
{
    $description = 'Seed local para upload-seguranca';
    $pedido = findPedidoByDescription($description, 'no_local');
    if ($pedido) {
        return $pedido;
    }

    $pedidoId = Pedido::criar([
        'cliente_id' => (int)$cliente['id'],
        'veiculo_id' => (int)$veiculo['id'],
        'tipo_problema' => 'Pane mecânica QA',
        'descricao_problema' => $description,
        'lat_origem' => -23.55052,
        'lng_origem' => -46.63331,
        'endereco_origem' => 'Praça da Sé, São Paulo - SP',
        'lat_destino' => -23.56140,
        'lng_destino' => -46.65650,
        'endereco_destino' => 'Av. Paulista, São Paulo - SP',
        'distancia_km' => 5.8,
        'custo_estimado' => 189.90,
        'status' => 'no_local',
    ]);
    Pedido::atribuirGuincho((int)$pedidoId, (int)$guincho['id']);
    $point = ProofOfRoadService::ingestPoint((int)$pedidoId, (int)$guincho['id'], (int)$guinchoUsuario['id'], [
        'latitude' => -23.55052,
        'longitude' => -46.63331,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 0,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-upload-seed-' . bin2hex(random_bytes(4)),
    ]);
    if (empty($point['ok'])) {
        throw new RuntimeException('Falha ao gerar seed POR para upload.');
    }

    return Pedido::buscarPorId((int)$pedidoId);
}

function ensureCheckoutPedido(array $cliente, array $veiculo): array
{
    $description = 'Seed local para pagamento-sandbox';
    $pedido = findPedidoByDescription($description, 'aguardando_pagamento');
    if ($pedido) {
        return $pedido;
    }

    $pedidoId = Pedido::criar([
        'cliente_id' => (int)$cliente['id'],
        'veiculo_id' => (int)$veiculo['id'],
        'tipo_problema' => 'Bateria QA',
        'descricao_problema' => $description,
        'lat_origem' => -23.54890,
        'lng_origem' => -46.63880,
        'endereco_origem' => 'Rua Líbero Badaró, São Paulo - SP',
        'lat_destino' => -23.56140,
        'lng_destino' => -46.65650,
        'endereco_destino' => 'Av. Paulista, São Paulo - SP',
        'distancia_km' => 6.3,
        'custo_estimado' => 209.90,
        'status' => 'aguardando_pagamento',
    ]);

    return Pedido::buscarPorId((int)$pedidoId);
}

function ensureConcorrenciaPedido(array $cliente, array $veiculo): array
{
    $description = 'Seed local para concorrencia-aceite';
    $pedido = findPedidoByDescription($description, 'aguardando_guincho');
    if ($pedido) {
        return $pedido;
    }

    $pedidoId = Pedido::criar([
        'cliente_id' => (int)$cliente['id'],
        'veiculo_id' => (int)$veiculo['id'],
        'tipo_problema' => 'Pneu furado QA',
        'descricao_problema' => $description,
        'lat_origem' => -23.54790,
        'lng_origem' => -46.63610,
        'endereco_origem' => 'Praça João Mendes, São Paulo - SP',
        'lat_destino' => -23.56200,
        'lng_destino' => -46.65400,
        'endereco_destino' => 'Rua Haddock Lobo, São Paulo - SP',
        'distancia_km' => 4.9,
        'custo_estimado' => 169.90,
        'status' => 'aguardando_guincho',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    Pedido::definirExpiracao((int)$pedidoId, date('Y-m-d H:i:s', strtotime('+30 minutes')), 10);
    return Pedido::buscarPorId((int)$pedidoId);
}

try {
    $cliente = upsertUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Upload', '21999990001', '12345678901');
    $guinchoAUser = upsertUsuario(QA_GUINCHO_EMAIL, 'guincho', 'Guincho QA Upload', '21999990002', '10987654321');
    $guinchoBUser = upsertUsuario(QA_GUINCHO_2_EMAIL, 'guincho', 'Guincho QA Reserva', '21999990003', '10987654322');

    $guinchoA = ensureGuincho($guinchoAUser, 'QAT1234');
    $guinchoB = ensureGuincho($guinchoBUser, 'QBT1234');
    $veiculo = ensureVeiculo((int)$cliente['id']);

    $uploadPedido = ensureUploadPedido($cliente, $veiculo, $guinchoA, $guinchoAUser);
    $checkoutPedido = ensureCheckoutPedido($cliente, $veiculo);
    $concorrenciaPedido = ensureConcorrenciaPedido($cliente, $veiculo);

    echo json_encode([
        'ok' => true,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'guincho_email' => QA_GUINCHO_EMAIL,
        'guincho_2_email' => QA_GUINCHO_2_EMAIL,
        'upload_pedido_id' => (int)$uploadPedido['id'],
        'pedido_status_id' => (int)$uploadPedido['id'],
        'checkout_pedido_id' => (int)$checkoutPedido['id'],
        'concorrencia_pedido_id' => (int)$concorrenciaPedido['id'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
