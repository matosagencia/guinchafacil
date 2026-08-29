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
const QA_PASSWORD = 'test123';
const QA_MODE = 'upload';

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

function ensureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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

function ensureGuincho(int $usuarioId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '12345678901',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QAT1234',
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 50,
            'chave_pix' => 'qa-guincho-chave-pix',
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
        'lat_atual = ?' => -23.55052,
        'lng_atual = ?' => -46.63331,
        'placa_guincho = ?' => 'QAT1234',
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
    $pdo = getPDO();
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

    $stmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function findReusablePedido(int $guinchoId): ?array
{
    $stmt = getPDO()->prepare(
        "SELECT *
           FROM pedidos
          WHERE guincho_id = ?
            AND status IN ('no_local', 'em_reboque')
          ORDER BY id DESC
          LIMIT 1"
    );
    $stmt->execute([$guinchoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function findReusableAtendimentoPedido(int $guinchoId): ?array
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
    $stmt->execute([$guinchoId, 'Seed local para atendimento-completo']);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function resetAtendimentoPedido(array $pedido, int $guinchoId): array
{
    getPDO()->prepare("DELETE FROM pedido_evidencias WHERE pedido_id = ?")->execute([(int)$pedido['id']]);
    getPDO()->prepare("DELETE FROM pedido_localizacoes WHERE pedido_id = ?")->execute([(int)$pedido['id']]);
    getPDO()->prepare("DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?")->execute([(int)$pedido['id']]);

    $fields = [
        'status = ?' => 'a_caminho',
        'guincho_id = ?' => $guinchoId,
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
    if (hasColumn('pedidos', 'cancelado_por')) {
        $fields['cancelado_por = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    getPDO()->prepare($sql)->execute($params);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

try {
    $mode = $argv[1] ?? QA_MODE;
    $cliente = ensureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Upload', '21999990001', '12345678901');
    $guinchoUsuario = ensureUsuario(QA_GUINCHO_EMAIL, 'guincho', 'Guincho QA Upload', '21999990002', '10987654321');
    $guincho = ensureGuincho((int)$guinchoUsuario['id']);
    $veiculo = ensureVeiculo((int)$cliente['id']);

    if ($mode === 'atendimento-completo') {
        getPDO()->prepare(
            "UPDATE guinchos
                SET lat_atual = ?, lng_atual = ?
              WHERE id = ?"
        )->execute([-23.56140, -46.65650, (int)$guincho['id']]);

        if (hasColumn('guinchos', 'lat_operacao')) {
            getPDO()->prepare("UPDATE guinchos SET lat_operacao = ? WHERE id = ?")->execute([-23.56140, (int)$guincho['id']]);
        }
        if (hasColumn('guinchos', 'lng_operacao')) {
            getPDO()->prepare("UPDATE guinchos SET lng_operacao = ? WHERE id = ?")->execute([-46.65650, (int)$guincho['id']]);
        }

        $pedido = findReusableAtendimentoPedido((int)$guincho['id']);
        if ($pedido) {
            $pedido = resetAtendimentoPedido($pedido, (int)$guincho['id']);
        } else {
            $pedidoId = Pedido::criar([
                'cliente_id' => (int)$cliente['id'],
                'veiculo_id' => (int)$veiculo['id'],
                'tipo_problema' => 'Pane mecânica QA',
                'descricao_problema' => 'Seed local para atendimento-completo',
                'lat_origem' => -23.55052,
                'lng_origem' => -46.63331,
                'endereco_origem' => 'Praça da Sé, São Paulo - SP',
                'lat_destino' => -23.56140,
                'lng_destino' => -46.65650,
                'endereco_destino' => 'Av. Paulista, São Paulo - SP',
                'distancia_km' => 5.8,
                'custo_estimado' => 0,
                'status' => 'a_caminho',
                'raio_atual_km' => 10,
                'score_minimo_atual' => 0.5,
            ]);
            if (!$pedidoId) {
                throw new RuntimeException('Falha ao criar pedido QA de atendimento completo.');
            }
            Pedido::atribuirGuincho((int)$pedidoId, (int)$guincho['id']);
            $pedido = Pedido::buscarPorId((int)$pedidoId);
        }

        $point = ProofOfRoadService::ingestPoint((int)$pedido['id'], (int)$guincho['id'], (int)$guinchoUsuario['id'], [
            'latitude' => -23.56140,
            'longitude' => -46.65650,
            'accuracy_m' => 8,
            'speed_mps' => 0,
            'heading_deg' => 180,
            'device_timestamp' => (string)(time() * 1000),
            'sequence' => 1,
            'client_point_id' => 'qa-atendimento-seed-' . bin2hex(random_bytes(4)),
        ]);

        if (empty($point['ok']) || empty($point['accepted'])) {
            throw new RuntimeException('Falha ao criar ponto GPS inicial do atendimento QA.');
        }

        echo json_encode([
            'ok' => true,
            'free_payment' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => 'a_caminho',
            'guincho_email' => QA_GUINCHO_EMAIL,
            'cliente_email' => QA_CLIENTE_EMAIL,
            'cliente_url' => '/cliente/pedido/' . (int)$pedido['id'],
            'guincho_url' => '/guincho/atendimento/' . (int)$pedido['id'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(0);
    }

    $pedido = findReusablePedido((int)$guincho['id']);
    if (!$pedido) {
        $pedidoId = Pedido::criar([
            'cliente_id' => (int)$cliente['id'],
            'veiculo_id' => (int)$veiculo['id'],
            'tipo_problema' => 'Pane mecânica QA',
            'descricao_problema' => 'Seed local para upload-seguranca',
            'lat_origem' => -23.55052,
            'lng_origem' => -46.63331,
            'endereco_origem' => 'Praça da Sé, São Paulo - SP',
            'lat_destino' => -23.56140,
            'lng_destino' => -46.65650,
            'endereco_destino' => 'Av. Paulista, São Paulo - SP',
            'distancia_km' => 5.8,
            'custo_estimado' => 189.90,
            'status' => 'no_local',
            'raio_atual_km' => 10,
            'score_minimo_atual' => 0.5,
        ]);
        if (!$pedidoId) {
            throw new RuntimeException('Falha ao criar pedido QA.');
        }

        Pedido::atribuirGuincho((int)$pedidoId, (int)$guincho['id']);
        $pedido = Pedido::buscarPorId((int)$pedidoId);

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

        if (empty($point['ok']) || empty($point['accepted'])) {
            throw new RuntimeException('Falha ao criar ponto GPS válido para evidência QA.');
        }
    }

    echo json_encode([
        'ok' => true,
        'pedido_id' => (int)$pedido['id'],
        'status' => $pedido['status'],
        'guincho_email' => QA_GUINCHO_EMAIL,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'url' => '/guincho/atendimento/' . (int)$pedido['id'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
