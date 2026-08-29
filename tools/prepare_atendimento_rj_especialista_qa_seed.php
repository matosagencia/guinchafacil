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

// Seed para RJ-TOW-002 (qa/suites/atendimento-rj-tempo-real.spec.ts) —
// especialista que virou guincho. Ao contrário de
// prepare_atendimento_rj_tow_qa_seed.php (guincho "comum", nasce com
// reboque já aprovado), este seed exercita o MESMO caminho de produção da
// tela "tornar-se guincho": nasce como especialista puro (aprovado=1,
// oferece_reboque=0), chama Guincho::solicitarReboque() (o prestador pede
// para virar guincho) e depois Guincho::aprovar() (o admin aprova) — as
// mesmas duas chamadas que GuinchoController::tornarSeGuinchoSalvar() e
// AdminController fazem em produção. Só depois disso reboque_aprovado=1 e
// o prestador pode ser atribuído a um pedido de reboque, igual a qualquer
// guincho comum.
//
// Rota: mesma origem/destino de RJ-TOW-001 (Av. Ayrton Senna, Barra da
// Tijuca), mas o especialista-guincho nasce 1km da origem (contra 700m do
// guincho comum) — ver rjEspecialistaApproachRoute em qa/helpers/atendimento.ts.
const QA_CLIENTE_EMAIL = 'pw_rj_cliente_esp@guinchafacil.com';
const QA_ESPECIALISTA_EMAIL = 'pw_rj_especialista@guinchafacil.com';
const QA_PASSWORD = 'test123';
const QA_DESCRICAO = 'Seed local RJ-TOW-002 (especialista virou guincho, Av. Ayrton Senna)';

// especialista -> origem: 1000m (início de rjEspecialistaApproachRoute)
const ESPECIALISTA_INICIO_LAT = -22.999814;
const ESPECIALISTA_INICIO_LNG = -43.365498;

// origem = mesma de RJ-TOW-001 (fim da aproximação = início da entrega)
const ORIGEM_LAT = -22.997682;
const ORIGEM_LNG = -43.36643;
const ORIGEM_ENDERECO = 'Avenida Ayrton Senna, Barra da Tijuca, Rio de Janeiro - RJ';

// destino = mesmo de RJ-TOW-001 (1,2km adiante, mesma avenida)
const DESTINO_LAT = -22.987421;
const DESTINO_LNG = -43.36559;
const DESTINO_ENDERECO = 'Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ';

const DISTANCIA_KM = 1.2;

function qaRjeHasColumn(string $table, string $column): bool
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

function qaRjeTableExists(string $table): bool
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

function qaRjeEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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

/**
 * Garante o prestador como ESPECIALISTA PURO (aprovado=1, oferece_reboque=0,
 * reboque_aprovado=0) — o ponto de partida real de quem nunca ofereceu
 * reboque, antes de qualquer upgrade.
 */
function qaRjeEnsureEspecialistaPuro(int $usuarioId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '33344455566',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => '',
            'capacidade_ton' => 0,
            'raio_cobertura_km' => 30,
            'chave_pix' => 'qa-rj-especialista-chave-pix',
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
        'lat_atual = ?' => ESPECIALISTA_INICIO_LAT,
        'lng_atual = ?' => ESPECIALISTA_INICIO_LNG,
        'chave_pix = ?' => 'qa-rj-especialista-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    // Zera o estado de reboque a cada rodada do seed, pra sempre exercitar o
    // upgrade completo (solicitarReboque + aprovar) do zero, e não reusar um
    // upgrade de uma execução anterior do teste.
    if (qaRjeHasColumn('guinchos', 'oferece_reboque')) {
        $fields['oferece_reboque = ?'] = 0;
    }
    if (qaRjeHasColumn('guinchos', 'reboque_aprovado')) {
        $fields['reboque_aprovado = ?'] = 0;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

/**
 * Exercita o caminho de produção real do upgrade "especialista -> guincho":
 * 1) o prestador pede para virar guincho (Guincho::solicitarReboque) —
 *    mesma chamada de GuinchoController::tornarSeGuinchoSalvar();
 * 2) o admin aprova (Guincho::aprovar) — mesma chamada usada em
 *    AdminController quando o admin aprova a fila (inclui os pedidos de
 *    upgrade, ver Guincho::listarPendentes()).
 * Ao final, reboque_aprovado = 1 só porque passou pelas DUAS chamadas reais,
 * não por termos ligado a flag na mão.
 */
function qaRjeUpgradeParaGuincho(int $guinchoId): array
{
    $ok1 = Guincho::solicitarReboque($guinchoId, [
        'placa_guincho' => 'QAR2002',
        'cidade_placa' => 'Rio de Janeiro',
        'uf_placa' => 'RJ',
        'capacidade_ton' => 6.0,
        'cnh_numero' => '33344455566',
        'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
    ]);
    if (!$ok1) {
        throw new RuntimeException('Falha ao solicitar upgrade para guincho (solicitarReboque).');
    }

    $ok2 = Guincho::aprovar($guinchoId);
    if (!$ok2) {
        throw new RuntimeException('Falha ao aprovar upgrade para guincho (aprovar).');
    }

    $atualizado = Guincho::buscarPorId($guinchoId);
    if (empty($atualizado['reboque_aprovado'])) {
        throw new RuntimeException('Upgrade não refletiu reboque_aprovado=1 — fluxo real falhou.');
    }

    return $atualizado;
}

function qaRjeEnsureVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }

    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAR2A02',
        'marca' => 'Volkswagen',
        'modelo' => 'Polo',
        'ano' => 2021,
        'cor' => 'Preto',
        'tipo' => 'passeio',
    ]);

    $stmt = getPDO()->prepare("SELECT * FROM veiculos WHERE id = ? LIMIT 1");
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function qaRjeFindReusablePedido(int $guinchoId): ?array
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

function qaRjeCreatePedido(int $clienteId, int $veiculoId, int $guinchoId): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'Pane mecânica QA (especialista virou guincho)',
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
        throw new RuntimeException('Falha ao criar pedido QA RJ-TOW-002.');
    }

    Pedido::atribuirGuincho((int)$pedidoId, $guinchoId);
    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

function qaRjeResetPedido(array $pedido, int $guinchoId): array
{
    $pdo = getPDO();
    $fields = [
        'status = ?' => 'a_caminho',
        'guincho_id = ?' => $guinchoId,
        'custo_estimado = ?' => 0,
    ];
    if (qaRjeHasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (qaRjeHasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (qaRjeHasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }
    if (qaRjeHasColumn('pedidos', 'cancelado_por')) {
        $fields['cancelado_por = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    if (qaRjeTableExists('pedido_evidencias')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qaRjeSeedInitialPoint(int $pedidoId, int $guinchoId, int $usuarioId): void
{
    $point = ProofOfRoadService::ingestPoint($pedidoId, $guinchoId, $usuarioId, [
        'latitude' => ESPECIALISTA_INICIO_LAT,
        'longitude' => ESPECIALISTA_INICIO_LNG,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 30,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-rj-esp-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial para seed QA RJ-TOW-002.');
    }
}

try {
    $cliente = qaRjeEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA RJ Especialista', '21999992001', '11122233302');
    $especialistaUsuario = qaRjeEnsureUsuario(QA_ESPECIALISTA_EMAIL, 'guincho', 'Especialista QA RJ', '21999992002', '10987654302');
    $guincho = qaRjeEnsureEspecialistaPuro((int)$especialistaUsuario['id']);
    $guincho = qaRjeUpgradeParaGuincho((int)$guincho['id']);
    $veiculo = qaRjeEnsureVeiculo((int)$cliente['id']);

    $pedido = qaRjeFindReusablePedido((int)$guincho['id']);
    if ($pedido) {
        $pedido = qaRjeResetPedido($pedido, (int)$guincho['id']);
    } else {
        $pedido = qaRjeCreatePedido((int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id']);
    }

    qaRjeSeedInitialPoint((int)$pedido['id'], (int)$guincho['id'], (int)$especialistaUsuario['id']);

    echo json_encode([
        'ok' => true,
        'pedido_id' => (int)$pedido['id'],
        'status' => 'a_caminho',
        'guincho_email' => QA_ESPECIALISTA_EMAIL,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'cliente_url' => '/cliente/pedido/' . (int)$pedido['id'],
        'guincho_url' => '/guincho/atendimento/' . (int)$pedido['id'],
        'reboque_aprovado' => (int)$guincho['reboque_aprovado'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
