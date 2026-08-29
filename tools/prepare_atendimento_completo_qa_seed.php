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
const QA_DESCRICAO = 'Seed local para atendimento-completo';

function qaHasColumn(string $table, string $column): bool
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

function qaTableExists(string $table): bool
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

function qaEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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

function qaEnsureGuincho(int $usuarioId): array
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
        'lat_atual = ?' => -23.56140,
        'lng_atual = ?' => -46.65650,
        'placa_guincho = ?' => 'QAT1234',
        'capacidade_ton = ?' => 8.0,
        'raio_cobertura_km = ?' => 50,
        'chave_pix = ?' => 'qa-guincho-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    if (qaHasColumn('guinchos', 'lat_operacao')) {
        $fields['lat_operacao = ?'] = -23.56140;
    }
    if (qaHasColumn('guinchos', 'lng_operacao')) {
        $fields['lng_operacao = ?'] = -46.65650;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function qaEnsureVeiculo(int $clienteId): array
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

function qaFindReusablePedido(int $guinchoId): ?array
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

function qaCreatePedido(int $clienteId, int $veiculoId, int $guinchoId): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'Pane mecânica QA',
        'descricao_problema' => QA_DESCRICAO,
        'lat_origem' => -23.55052,
        'lng_origem' => -46.63331,
        'endereco_origem' => 'Praça da Sé, São Paulo - SP',
        'lat_destino' => -23.56140,
        'lng_destino' => -46.65650,
        'endereco_destino' => 'Avenida Paulista, São Paulo - SP',
        'distancia_km' => 5.8,
        'custo_estimado' => 0.0,
        'status' => 'a_caminho',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido QA de atendimento completo.');
    }

    Pedido::atribuirGuincho((int)$pedidoId, $guinchoId);
    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

function qaResetPedido(array $pedido, int $guinchoId): array
{
    $pdo = getPDO();
    $fields = [
        'status = ?' => 'a_caminho',
        'guincho_id = ?' => $guinchoId,
        'custo_estimado = ?' => 0,
    ];
    if (qaHasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (qaHasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (qaHasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }
    if (qaHasColumn('pedidos', 'cancelado_por')) {
        $fields['cancelado_por = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    // Achado ao ativar este seed em qa/helpers/seed.ts: numa segunda execução,
    // o pedido reaproveitado já tinha pontos de rastreamento de uma rodada
    // anterior (o teste real move o guincho pela rota inteira). qaSeedInitialPoint()
    // sempre pede sequence=1, e ProofOfRoadService::ingestPoint rejeita sequência
    // fora de ordem quando já existe um último ponto com sequência maior — o seed
    // falhava com "Falha ao criar ponto GPS inicial para seed QA." Limpa o
    // histórico de POR do pedido a cada reset para garantir sequence=1 válido.
    //
    // pedido_evidencias.point_id referencia pedido_localizacoes(id) com
    // ON DELETE NO ACTION — depois que o teste passou a rodar até o fim
    // (upload real de foto_plataforma/foto_destino), a segunda execução do
    // seed passou a falhar com erro de FK ao tentar apagar os pontos GPS
    // antigos, pois as evidências da rodada anterior ainda apontavam pra
    // eles. Evidências de rodadas de seed anteriores não têm valor de
    // auditoria real, então são limpas junto.
    if (qaTableExists('pedido_evidencias')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qaSeedInitialPoint(int $pedidoId, int $guinchoId, int $usuarioId): void
{
    $point = ProofOfRoadService::ingestPoint($pedidoId, $guinchoId, $usuarioId, [
        'latitude' => -23.56140,
        'longitude' => -46.65650,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 90,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-atendimento-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial para seed QA.');
    }
}

try {
    $cliente = qaEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Atendimento', '21999990001', '12345678901');
    $guinchoUsuario = qaEnsureUsuario(QA_GUINCHO_EMAIL, 'guincho', 'Guincho QA Atendimento', '21999990002', '10987654321');
    $guincho = qaEnsureGuincho((int)$guinchoUsuario['id']);
    $veiculo = qaEnsureVeiculo((int)$cliente['id']);

    $pedido = qaFindReusablePedido((int)$guincho['id']);
    if ($pedido) {
        $pedido = qaResetPedido($pedido, (int)$guincho['id']);
    } else {
        $pedido = qaCreatePedido((int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id']);
    }

    qaSeedInitialPoint((int)$pedido['id'], (int)$guincho['id'], (int)$guinchoUsuario['id']);

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
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
