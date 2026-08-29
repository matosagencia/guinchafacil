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

// Seed dedicado ao cenário de tempo real (qa/suites/atendimento-tempo-real.spec.ts).
//
// Diferença fundamental para tools/prepare_atendimento_completo_qa_seed.php:
// aquele seed existe para testar a MÁQUINA DE ESTADOS (aceite → deslocamento →
// coleta → entrega → conclusão) o mais rápido possível, e por isso o teste
// "acelera o relógio" (device_timestamp simulado avançando 120s por ponto,
// mas o teste real leva ~250ms por ponto). Isso NUNCA exercita de verdade o
// antifraude de velocidade/tempo (LocationValidationService::validatePoint) —
// ele sempre vê "tempo suficiente" artificialmente, então uma regressão que
// permitisse, por exemplo, aceitar 130km/h+ ou pular etapas de tempo, não
// seria pega por aquele teste.
//
// Este seed usa uma rota CURTA (~1,8km, dados reais do OSRM, ruas de verdade
// de São Paulo — Praça da Sé → Rua Senador Feijó → Rua Cristóvão Colombo →
// Viaduto/Av. Brigadeiro Luís Antônio → Av. 23 de Maio → Rua Maestro Cardim →
// Rua Monsenhor Passaláqua) para que o teste correspondente rode o trajeto
// em TEMPO REAL (sem inflar o relógio), validando de verdade que o
// antifraude aceita uma viagem real e plausível — e serve de vara de medir
// para detectar o cenário oposto (50km "concluídos" em 5 minutos).
const QA_CLIENTE_EMAIL = 'pw_teste@guinchafacil.com';
const QA_GUINCHO_EMAIL = 'pw_guincho@guinchafacil.com';
const QA_PASSWORD = 'test123';
const QA_DESCRICAO = 'Seed local para atendimento-tempo-real (rota curta ~1.8km)';

// Ponto de partida do guincho: início da rota de aproximação (real, via OSRM).
const GUINCHO_INICIO_LAT = -23.550782;
const GUINCHO_INICIO_LNG = -46.63388;

// Origem do pedido = fim da rota de aproximação = início da rota de entrega.
const ORIGEM_LAT = -23.56064;
const ORIGEM_LNG = -46.641541;
const ORIGEM_ENDERECO = 'Rua Monsenhor Passaláqua, São Paulo - SP';

// Destino do pedido = fim da rota de entrega.
const DESTINO_LAT = -23.568304;
const DESTINO_LNG = -46.646114;
const DESTINO_ENDERECO = 'Rua Cincinato Braga, São Paulo - SP';

const DISTANCIA_KM = 1.75;

function qaRtHasColumn(string $table, string $column): bool
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

function qaRtTableExists(string $table): bool
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

function qaRtEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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

function qaRtEnsureGuincho(int $usuarioId): array
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
        'lat_atual = ?' => GUINCHO_INICIO_LAT,
        'lng_atual = ?' => GUINCHO_INICIO_LNG,
        'placa_guincho = ?' => 'QAT1234',
        'capacidade_ton = ?' => 8.0,
        'raio_cobertura_km = ?' => 50,
        'chave_pix = ?' => 'qa-guincho-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    if (qaRtHasColumn('guinchos', 'lat_operacao')) {
        $fields['lat_operacao = ?'] = GUINCHO_INICIO_LAT;
    }
    if (qaRtHasColumn('guinchos', 'lng_operacao')) {
        $fields['lng_operacao = ?'] = GUINCHO_INICIO_LNG;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function qaRtEnsureVeiculo(int $clienteId): array
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

function qaRtFindReusablePedido(int $guinchoId): ?array
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

function qaRtCreatePedido(int $clienteId, int $veiculoId, int $guinchoId): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'Pane mecânica QA (rota curta)',
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
        throw new RuntimeException('Falha ao criar pedido QA de tempo real.');
    }

    Pedido::atribuirGuincho((int)$pedidoId, $guinchoId);
    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

function qaRtResetPedido(array $pedido, int $guinchoId): array
{
    $pdo = getPDO();
    $fields = [
        'status = ?' => 'a_caminho',
        'guincho_id = ?' => $guinchoId,
        'custo_estimado = ?' => 0,
    ];
    if (qaRtHasColumn('pedidos', 'custo_final')) {
        $fields['custo_final = ?'] = 0;
    }
    if (qaRtHasColumn('pedidos', 'foto_plataforma')) {
        $fields['foto_plataforma = ?'] = null;
    }
    if (qaRtHasColumn('pedidos', 'foto_destino')) {
        $fields['foto_destino = ?'] = null;
    }
    if (qaRtHasColumn('pedidos', 'cancelado_por')) {
        $fields['cancelado_por = ?'] = null;
    }

    $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$pedido['id'];
    $pdo->prepare($sql)->execute($params);

    if (qaRtTableExists('pedido_evidencias')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([(int)$pedido['id']]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([(int)$pedido['id']]);

    return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
}

function qaRtSeedInitialPoint(int $pedidoId, int $guinchoId, int $usuarioId): void
{
    $point = ProofOfRoadService::ingestPoint($pedidoId, $guinchoId, $usuarioId, [
        'latitude' => GUINCHO_INICIO_LAT,
        'longitude' => GUINCHO_INICIO_LNG,
        'accuracy_m' => 8,
        'speed_mps' => 0,
        'heading_deg' => 90,
        'device_timestamp' => (string)(time() * 1000),
        'sequence' => 1,
        'client_point_id' => 'qa-tempo-real-seed-' . bin2hex(random_bytes(4)),
    ]);

    if (empty($point['ok']) || empty($point['accepted'])) {
        throw new RuntimeException('Falha ao criar ponto GPS inicial para seed QA (tempo real).');
    }
}

try {
    $cliente = qaRtEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Atendimento', '21999990001', '12345678901');
    $guinchoUsuario = qaRtEnsureUsuario(QA_GUINCHO_EMAIL, 'guincho', 'Guincho QA Atendimento', '21999990002', '10987654321');
    $guincho = qaRtEnsureGuincho((int)$guinchoUsuario['id']);
    $veiculo = qaRtEnsureVeiculo((int)$cliente['id']);

    $pedido = qaRtFindReusablePedido((int)$guincho['id']);
    if ($pedido) {
        $pedido = qaRtResetPedido($pedido, (int)$guincho['id']);
    } else {
        $pedido = qaRtCreatePedido((int)$cliente['id'], (int)$veiculo['id'], (int)$guincho['id']);
    }

    qaRtSeedInitialPoint((int)$pedido['id'], (int)$guincho['id'], (int)$guinchoUsuario['id']);

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
