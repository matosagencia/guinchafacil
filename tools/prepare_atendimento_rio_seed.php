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
require_once dirname(__DIR__) . '/src/Models/Oficina.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';
require_once dirname(__DIR__) . '/src/Services/POR/ProofOfRoadService.php';

const QA_PASSWORD = 'test123';
const CLIENTE_EMAIL = 'mariaelianaferreira@gmail.com';
const GUINCHO_EMAIL = 'matosagenciaxyz@gmail.com';
const PEDIDO_DESCRICAO = 'Seed local Rio para atendimento-completo com chat';

const ORIGEM = [
    'endereco' => 'Rua do Propósito 59, Gamboa, Rio de Janeiro - RJ',
    'lat' => -22.895633,
    'lng' => -43.194425,
];

const DESTINO = [
    'endereco' => 'Rua da Gamboa 249, Gamboa, Rio de Janeiro - RJ',
    'lat' => -22.897437,
    'lng' => -43.196741,
];

const GUINCHO_INICIO = [
    'endereco' => 'Rua do Livramento 221, Gamboa, Rio de Janeiro - RJ',
    'lat' => -22.89577,
    'lng' => -43.19186,
];

const ROUTE_POINTS = [
    ['lat' => -22.89577, 'lng' => -43.19186, 'street' => 'Rua do Livramento'],
    ['lat' => -22.895468, 'lng' => -43.193492, 'street' => 'Rua do Propósito'],
    ['lat' => -22.895633, 'lng' => -43.194425, 'street' => 'Rua do Propósito'],
    ['lat' => -22.896166, 'lng' => -43.193339, 'street' => 'Rua Leôncio de Albuquerque'],
    ['lat' => -22.897346, 'lng' => -43.193165, 'street' => 'Rua do Livramento'],
    ['lat' => -22.897530, 'lng' => -43.195868, 'street' => 'Rua do Livramento'],
    ['lat' => -22.897277, 'lng' => -43.196072, 'street' => 'Rua Rivadávia Corrêa'],
    ['lat' => -22.897437, 'lng' => -43.196741, 'street' => 'Rua da Gamboa'],
];

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

function upsertUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
{
    $pdo = getPDO();
    $cpf = ensureUniqueCpf($cpf);
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

function ensureUniqueCpf(string $preferred): string
{
    $digits = preg_replace('/\D/', '', $preferred);
    $candidates = array_filter([
        str_pad(substr($digits, 0, 11), 11, '0'),
        '771' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        '772' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        '773' . str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
    ]);

    foreach ($candidates as $candidate) {
        $stmt = getPDO()->prepare("SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Não foi possível gerar um CPF de QA livre.');
}

function ensureGuincho(array $usuario): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario((int)$usuario['id']);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro((int)$usuario['id'], [
            'cnh_numero' => '12345678901',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'MTS2210',
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 40,
            'chave_pix' => $usuario['email'],
            'chave_pix_tipo' => 'email',
        ]);
        $guincho = Guincho::buscarPorId((int)$guinchoId);
    }

    $fields = [
        'aprovado = ?' => 1,
        'disponivel = ?' => 1,
        'placa_guincho = ?' => 'MTS2210',
        'capacidade_ton = ?' => 8.0,
        'raio_cobertura_km = ?' => 40,
        'lat_atual = ?' => GUINCHO_INICIO['lat'],
        'lng_atual = ?' => GUINCHO_INICIO['lng'],
    ];
    if (hasColumn('guinchos', 'lat_operacao')) {
        $fields['lat_operacao = ?'] = GUINCHO_INICIO['lat'];
    }
    if (hasColumn('guinchos', 'lng_operacao')) {
        $fields['lng_operacao = ?'] = GUINCHO_INICIO['lng'];
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
        'placa' => 'RIO2210',
        'marca' => 'Hyundai',
        'modelo' => 'HB20',
        'ano' => 2021,
        'cor' => 'Prata',
        'tipo' => 'passeio',
    ]);

    $stmt = getPDO()->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function ensureOficina(int $clienteId): array
{
    $oficinas = Oficina::listarPorUsuario($clienteId);
    foreach ($oficinas as $oficina) {
        if (($oficina['nome'] ?? '') === 'Oficina do Diego') {
            Oficina::atualizar((int)$oficina['id'], [
                'nome' => 'Oficina do Diego',
                'telefone' => '21988887777',
                'endereco' => DESTINO['endereco'],
                'latitude' => DESTINO['lat'],
                'longitude' => DESTINO['lng'],
            ]);
            return Oficina::buscarPorId((int)$oficina['id']) ?: $oficina;
        }
    }

    $id = (int)Oficina::criar([
        'usuario_id' => $clienteId,
        'nome' => 'Oficina do Diego',
        'telefone' => '21988887777',
        'endereco' => DESTINO['endereco'],
        'latitude' => DESTINO['lat'],
        'longitude' => DESTINO['lng'],
    ]);

    return Oficina::buscarPorId($id) ?: [];
}

function findReusablePedido(int $clienteId): ?array
{
    $stmt = getPDO()->prepare(
        "SELECT *
           FROM pedidos
          WHERE cliente_id = ?
          ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute([$clienteId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function resetPedido(array $pedido, int $guinchoId): array
{
    $pdo = getPDO();
    $pdo->prepare("DELETE FROM chat_mensagens WHERE pedido_id = ?")->execute([(int)$pedido['id']]);
    $pdo->prepare("DELETE FROM pedido_evidencias WHERE pedido_id = ?")->execute([(int)$pedido['id']]);
    $pdo->prepare("DELETE FROM pedido_localizacoes WHERE pedido_id = ?")->execute([(int)$pedido['id']]);
    $pdo->prepare("DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?")->execute([(int)$pedido['id']]);

    $fields = [
        'status = ?' => 'a_caminho',
        'guincho_id = ?' => $guinchoId,
        'tipo_problema = ?' => 'Pane mecânica',
        'descricao_problema = ?' => PEDIDO_DESCRICAO,
        'lat_origem = ?' => ORIGEM['lat'],
        'lng_origem = ?' => ORIGEM['lng'],
        'endereco_origem = ?' => ORIGEM['endereco'],
        'lat_destino = ?' => DESTINO['lat'],
        'lng_destino = ?' => DESTINO['lng'],
        'endereco_destino = ?' => DESTINO['endereco'],
        'distancia_km = ?' => 1.1,
        'custo_estimado = ?' => 0,
    ];
    if (hasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (hasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (hasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

try {
    Configuracao::set('payment_required', '0', 'QA local: fluxo livre para atendimento completo.');
    Configuracao::set('system_mode', 'freeflow', 'QA local: atendimento sem bloqueio de pagamento.');

    $cliente = upsertUsuario(CLIENTE_EMAIL, 'cliente', 'Maria Eliana Ferreira Campos', '21999111222', '22345678901');
    $guinchoUsuario = upsertUsuario(GUINCHO_EMAIL, 'guincho', 'Matos Agencia', '21993334444', '98765432100');
    $guincho = ensureGuincho($guinchoUsuario);
    $veiculo = ensureVeiculo((int)$cliente['id']);
    $oficina = ensureOficina((int)$cliente['id']);

    $pdo = getPDO();
    $pdo->prepare(
        "DELETE FROM pedidos WHERE cliente_id = ? AND descricao_problema = ?"
    )->execute([(int)$cliente['id'], PEDIDO_DESCRICAO]);

    $pedido = findReusablePedido((int)$cliente['id']);
    if ($pedido) {
        $pedido = resetPedido($pedido, (int)$guincho['id']);
    } else {
        $pedidoId = Pedido::criar([
            'cliente_id' => (int)$cliente['id'],
            'veiculo_id' => (int)$veiculo['id'],
            'tipo_problema' => 'Pane mecânica',
            'descricao_problema' => PEDIDO_DESCRICAO,
            'lat_origem' => ORIGEM['lat'],
            'lng_origem' => ORIGEM['lng'],
            'endereco_origem' => ORIGEM['endereco'],
            'lat_destino' => DESTINO['lat'],
            'lng_destino' => DESTINO['lng'],
            'endereco_destino' => DESTINO['endereco'],
            'distancia_km' => 1.1,
            'custo_estimado' => 0,
            'status' => 'a_caminho',
            'raio_atual_km' => 10,
            'score_minimo_atual' => 0.5,
        ]);
        if (!$pedidoId) {
            throw new RuntimeException('Falha ao criar pedido QA do Rio.');
        }
        Pedido::atribuirGuincho((int)$pedidoId, (int)$guincho['id']);
        $pedido = Pedido::buscarPorId((int)$pedidoId);
    }

    $point = ProofOfRoadService::ingestPoint((int)$pedido['id'], (int)$guincho['id'], (int)$guinchoUsuario['id'], [
        'latitude' => GUINCHO_INICIO['lat'],
        'longitude' => GUINCHO_INICIO['lng'],
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 90,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-rio-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial do seed Rio.');
    }

    echo json_encode([
        'ok' => true,
        'free_payment' => true,
        'pedido_id' => (int)$pedido['id'],
        'cliente_email' => CLIENTE_EMAIL,
        'cliente_password' => QA_PASSWORD,
        'guincho_email' => GUINCHO_EMAIL,
        'guincho_password' => QA_PASSWORD,
        'cliente_nome' => 'Maria Eliana Ferreira Campos',
        'guincho_nome' => 'Matos Agencia',
        'oficina_nome' => $oficina['nome'] ?? 'Oficina do Diego',
        'route_points' => ROUTE_POINTS,
        'expected_street_regex' => 'Rua do Livramento|Rua do Propósito|Rua Leôncio de Albuquerque|Rua Rivadávia Corrêa|Rua da Gamboa|Gamboa',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
