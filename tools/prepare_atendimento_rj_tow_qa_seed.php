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

// Seed para RJ-TOW-001 (qa/suites/atendimento-rj-tempo-real.spec.ts) —
// guincho "comum" (não veio de upgrade de especialista). Rota real via OSRM
// no Rio de Janeiro (Avenida Ayrton Senna, Barra da Tijuca): guincho nasce
// 700m da origem, origem->destino tem 1,2km. Ver rjGuinchoApproachRoute /
// rjDeliveryRoute em qa/helpers/atendimento.ts — os mesmos pontos, para o
// teste e o seed nunca divergirem.
const QA_CLIENTE_EMAIL = 'pw_rj_cliente@guinchafacil.com';
const QA_GUINCHO_EMAIL = 'pw_rj_guincho@guinchafacil.com';
const QA_PASSWORD = 'test123';
const QA_DESCRICAO = 'Seed local RJ-TOW-001 (guincho comum, Av. Ayrton Senna)';

// guincho -> origem: 700m (início de rjGuinchoApproachRoute)
const GUINCHO_INICIO_LAT = -22.999862;
const GUINCHO_INICIO_LNG = -43.36489;

// origem = fim da aproximação = início da entrega
const ORIGEM_LAT = -22.997682;
const ORIGEM_LNG = -43.36643;
const ORIGEM_ENDERECO = 'Avenida Ayrton Senna, Barra da Tijuca, Rio de Janeiro - RJ';

// destino = fim da entrega (1,2km adiante, mesma avenida)
const DESTINO_LAT = -22.987421;
const DESTINO_LNG = -43.36559;
const DESTINO_ENDERECO = 'Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ';

const DISTANCIA_KM = 1.2;

function qaRjHasColumn(string $table, string $column): bool
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

function qaRjTableExists(string $table): bool
{
    $stmt = getPDO()->prepare(
        "SELECT COUNT(*)
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function qaRjEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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
            SET senha_hash = ?, ativo = 1, tipo = ?, nome = ?, telefone = ?, cpf = ?
          WHERE id = ?"
    )->execute([
        password_hash(QA_PASSWORD, PASSWORD_BCRYPT),
        $tipo,
        $nome,
        $telefone,
        $cpf,
        (int)$usuario['id'],
    ]);

    return Usuario::buscarPorId((int)$usuario['id']) ?: $usuario;
}

function qaRjEnsureGuincho(int $usuarioId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '22233344455',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QAR1001',
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 50,
            'chave_pix' => 'qa-rj-guincho-chave-pix',
            'chave_pix_tipo' => 'email',
            'foto_veiculo' => null,
            'doc_cnh_frente' => null,
            'doc_cnh_verso' => null,
        ]);
        $guincho = Guincho::buscarPorId((int)$guinchoId);
    }

    $fields = [
        'aprovado = ?' => 1,
        'disponivel = ?' => 1,
        'lat_atual = ?' => GUINCHO_INICIO_LAT,
        'lng_atual = ?' => GUINCHO_INICIO_LNG,
        'placa_guincho = ?' => 'QAR1001',
        'capacidade_ton = ?' => 8.0,
        'raio_cobertura_km = ?' => 50,
        'chave_pix = ?' => 'qa-rj-guincho-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    // Guincho "comum": já nasce podendo rebocar, sem passar pelo fluxo de
    // upgrade de especialista (esse é o cenário RJ-TOW-002, seed separado).
    if (qaRjHasColumn('guinchos', 'oferece_reboque')) {
        $fields['oferece_reboque = ?'] = 1;
    }
    if (qaRjHasColumn('guinchos', 'reboque_aprovado')) {
        $fields['reboque_aprovado = ?'] = 1;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function qaRjEnsureVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }

    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAR1A01',
        'marca' => 'Chevrolet',
        'modelo' => 'Onix',
        'ano' => 2023,
        'cor' => 'Prata',
        'tipo' => 'passeio',
    ]);

    $stmt = getPDO()->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function qaRjFindReusablePedido(int $guinchoId): ?array
{
    $stmt = getPDO()->prepare(
        "SELECT *
           FROM pedidos
          WHERE guincho_id = ?
            AND descricao_problema = ?
            AND status IN ('a_caminho', 'no_local', 'em_reboque')
          ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute([$guinchoId, QA_DESCRICAO]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function qaRjCreatePedido(int $clienteId, int $veiculoId, int $guinchoId): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'Pane mecânica QA (Rio de Janeiro)',
        'descricao_problema' => QA_DESCRICAO,
        'lat_origem' => ORIGEM_LAT,
        'lng_origem' => ORIGEM_LNG,
        'endereco_origem' => ORIGEM_ENDERECO,
        'lat_destino' => DESTINO_LAT,
        'lng_destino' => DESTINO_LNG,
        'endereco_destino' => DESTINO_ENDERECO,
        'distancia_km' => DISTANCIA_KM,
        'custo_estimado' => 0.0,
        'status' => 'a_caminho',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido QA RJ-TOW-001.');
    }

    Pedido::atribuirGuincho((int)$pedidoId, $guinchoId);
    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

function qaRjResetPedido(array $pedido, int $guinchoId): array
{
    $pdo = getPDO();
    $fields = [
        'status = ?' => 'a_caminho',
        'guincho_id = ?' => $guinchoId,
        'custo_estimado = ?' => 0,
    ];
    if (qaRjHasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (qaRjHasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (qaRjHasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }
    if (qaRjHasColumn('pedidos', 'cancelado_por')) {
        $fields['cancelado_por = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    if (qaRjTableExists('pedido_evidencias')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qaRjSeedInitialPoint(int $pedidoId, int $guinchoId, int $usuarioId): void
{
    $point = ProofOfRoadService::ingestPoint($pedidoId, $guinchoId, $usuarioId, [
        'latitude' => GUINCHO_INICIO_LAT,
        'longitude' => GUINCHO_INICIO_LNG,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 30,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-rj-tow-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial para seed QA RJ-TOW-001.');
    }
}

try {
    $cliente = qaRjEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA RJ', '21999991001', '11122233301');
    $guinchoUsuario = qaRjEnsureUsuario(QA_GUINCHO_EMAIL, 'guincho', 'Guincho QA RJ', '21999991002', '10987654301');
    $guincho = qaRjEnsureGuincho((int)$guinchoUsuario['id']);
    $veiculo = qaRjEnsureVeiculo((int)$cliente['id']);

    $pedido = qaRjFindReusablePedido((int)$guincho['id']);
    if ($pedido) {
        $pedido = qaRjResetPedido($pedido, (int)$guincho['id']);
    } else {
        $pedido = qaRjCreatePedido((int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id']);
    }

    qaRjSeedInitialPoint((int)$pedido['id'], (int)$guincho['id'], (int)$guinchoUsuario['id']);

    echo json_encode([
        'ok' => true,
        'pedido_id' => (int)$pedido['id'],
        'status' => 'a_caminho',
        'guincho_email' => QA_GUINCHO_EMAIL,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'cliente_url' => '/cliente/pedido/' . (int)$pedido['id'],
        'guincho_url' => '/guincho/atendimento/' . (int)$pedido['id'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
