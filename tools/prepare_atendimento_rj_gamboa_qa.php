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
require_once dirname(__DIR__) . '/src/Models/Catalog/ServiceType.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ProviderCapability.php';

// Seed para os cenários de stress RJ-COLISAO-001 / RJ-ELETRICA-001 (ver
// qa/fixtures/stress-scenarios.fixture.ts), na sede real da GuinchaFácil
// (Rua da Gamboa, 131 — ver COMPANY_ADDRESS no .env). Segue o mesmo padrão
// já validado em tools/prepare_atendimento_socorro_qa_seed.php: pedido nasce
// em 'aguardando_pagamento', sem guincho atribuído — a atribuição real
// acontece via subcomando separado, depois que o pagamento aprovar de
// verdade pelo Payment Brick (mesmo motivo de lá: atribuição só faz sentido
// com o pedido já em 'aguardando_guincho').
//
// Uso:
//   php prepare_atendimento_rj_gamboa_qa.php colisao
//   php prepare_atendimento_rj_gamboa_qa.php pane-eletrica
//   php prepare_atendimento_rj_gamboa_qa.php atribuir <pedido_id>

// Bug real (stress agregado, 31/07/2026 — QA_STRESS_WORKERS=4): estes
// e-mails eram FIXOS, sem nenhuma variação por execução — diferente de
// praticamente todo o resto do projeto QA, que já usa runTag/e-mails únicos.
// Quando specs diferentes (stress-por, stress-chaos, atendimento-colisao-rj
// etc.) rodam em workers do Playwright simultâneos e cada um chama este seed
// com 'colisao'/'pane-eletrica', todos caíam no MESMO cliente/prestador/
// pedido — resultado: pontos de GPS de um worker "vazavam" pro snapshot que
// outro worker lia (total_pontos maior que o esperado). TEST_WORKER_INDEX é
// setado automaticamente pelo Playwright em cada processo worker, e o
// execFileSync que os specs usam pra chamar este script HERDA o env do
// processo pai por padrão — ou seja, isolar por worker aqui não exige tocar
// em nenhum spec.
$qaWorkerTag = (string)(getenv('TEST_WORKER_INDEX') ?: '0');
define('QA_CLIENTE_EMAIL', 'pw_gamboa_cliente_w' . $qaWorkerTag . '@guinchafacil.com');
define('QA_PRESTADOR_EMAIL', 'pw_gamboa_prestador_w' . $qaWorkerTag . '@guinchafacil.com');
const QA_PASSWORD = 'test123';

/** Gera um CPF (dígitos verificadores reais) a partir de um seed numérico —
 * necessário porque `usuarios.cpf` é UNIQUE; os CPFs fixos antigos
 * ('22233344402'/'33344455503') colidiam entre workers assim como os
 * e-mails. */
function qaGamboaCpf(int $seed): string
{
    $base = str_pad((string)($seed % 1_000_000_000), 9, '0', STR_PAD_LEFT);
    $digits = array_map('intval', str_split($base));
    $factor = count($digits) + 1;
    $sum = 0;
    foreach ($digits as $i => $d) { $sum += $d * ($factor - $i); }
    $digits[] = ((10 * $sum) % 11) % 10;
    $factor = count($digits) + 1;
    $sum = 0;
    foreach ($digits as $i => $d) { $sum += $d * ($factor - $i); }
    $digits[] = ((10 * $sum) % 11) % 10;
    return implode('', $digits);
}

$qaWorkerSeed = (int)$qaWorkerTag;
define('QA_CLIENTE_CPF', qaGamboaCpf(500_000_000 + $qaWorkerSeed));
define('QA_PRESTADOR_CPF', qaGamboaCpf(600_000_000 + $qaWorkerSeed));

// Coordenadas reais (ver stress-scenarios.fixture.ts RJ_GAMBOA) — as MESMAS
// usadas pelas rotas amostradas via OSRM em rjGamboaToVenezuelaRoute /
// rjVenezuelaToOficinaRoute, para o seed e o teste nunca divergirem.
const PRESTADOR_INICIO_LAT = -22.897419;
const PRESTADOR_INICIO_LNG = -43.199037;
const PRESTADOR_ENDERECO = 'Rua da Gamboa, 131, Gamboa, Rio de Janeiro - RJ';

const OCORRENCIA_LAT = -22.8958989;
const OCORRENCIA_LNG = -43.1857243;
const OCORRENCIA_ENDERECO = 'Avenida Venezuela, 134, Gamboa, Rio de Janeiro - RJ';

const OFICINA_LAT = -22.8969477;
const OFICINA_LNG = -43.1994326;
const OFICINA_ENDERECO = 'Rua da Gamboa, 275, Gamboa, Rio de Janeiro - RJ';

function qaGamboaHasColumn(string $table, string $column): bool
{
    $stmt = getPDO()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([DB_NAME, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function qaGamboaEnsureUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
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

    $pdo->prepare('UPDATE usuarios SET senha_hash = ?, ativo = 1, tipo = ?, nome = ?, telefone = ?, cpf = ? WHERE id = ?')
        ->execute([password_hash(QA_PASSWORD, PASSWORD_BCRYPT), $tipo, $nome, $telefone, $cpf, (int)$usuario['id']]);

    return Usuario::buscarPorId((int)$usuario['id']) ?: $usuario;
}

/**
 * Prestador multisserviço (reboque + serviços ON_SITE) — nasce na Rua da
 * Gamboa, 131, com capacidades aprovadas tanto para TOW_CAR quanto
 * ELECTRICAL_DIAGNOSIS, cobrindo os dois cenários (colisao e pane-eletrica)
 * sem precisar de dois prestadores diferentes.
 */
function qaGamboaEnsurePrestador(int $usuarioId): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $guinchoId = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '77788899900',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QAG1001',
            'capacidade_ton' => 6.5,
            'raio_cobertura_km' => 50,
            'chave_pix' => 'qa-gamboa-prestador-chave-pix',
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
        'lat_atual = ?' => PRESTADOR_INICIO_LAT,
        'lng_atual = ?' => PRESTADOR_INICIO_LNG,
        'placa_guincho = ?' => 'QAG1001',
        'chave_pix = ?' => 'qa-gamboa-prestador-chave-pix',
        'chave_pix_tipo = ?' => 'email',
    ];
    if (qaGamboaHasColumn('guinchos', 'oferece_reboque')) {
        $fields['oferece_reboque = ?'] = 1;
    }
    if (qaGamboaHasColumn('guinchos', 'reboque_aprovado')) {
        $fields['reboque_aprovado = ?'] = 1;
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    $eletrico = ServiceType::buscarPorCodigo('ELECTRICAL_DIAGNOSIS');
    if ($eletrico) {
        $capId = ProviderCapability::declarar((int)$guincho['id'], (int)$eletrico['id'], ['estimated_duration_minutes' => 40]);
        ProviderCapability::aprovar($capId, 0);
    }

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function qaGamboaEnsureVeiculo(int $clienteId): array
{
    $veiculos = Veiculo::listarPorUsuario($clienteId);
    if (!empty($veiculos)) {
        return $veiculos[0];
    }
    $veiculoId = (int)Veiculo::criar($clienteId, [
        'placa' => 'QAG0C01',
        'marca' => 'Volkswagen',
        'modelo' => 'Gol',
        'ano' => 2021,
        'cor' => 'Prata',
        'tipo' => 'passeio',
    ]);
    $stmt = getPDO()->prepare('SELECT * FROM veiculos WHERE id = ? LIMIT 1');
    $stmt->execute([$veiculoId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function qaGamboaFindReusablePedido(int $clienteId, string $descricao): ?array
{
    $stmt = getPDO()->prepare(
        'SELECT * FROM pedidos WHERE cliente_id = ? AND descricao_problema = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$clienteId, $descricao]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function qaGamboaLimparPedido(int $pedidoId): void
{
    $pdo = getPDO();
    if (qaGamboaHasColumn('pedido_evidencias', 'pedido_id')) {
        $pdo->prepare('DELETE FROM pedido_evidencias WHERE pedido_id = ?')->execute([$pedidoId]);
    }
    $pdo->prepare('DELETE FROM pedido_localizacoes WHERE pedido_id = ?')->execute([$pedidoId]);
    $pdo->prepare('DELETE FROM pedido_percurso_resumos WHERE pedido_id = ?')->execute([$pedidoId]);
    $pdo->prepare('DELETE FROM chat_mensagens WHERE pedido_id = ?')->execute([$pedidoId]);
    $pdo->prepare('DELETE FROM pagamentos WHERE pedido_id = ?')->execute([$pedidoId]);
}

function qaGamboaCriarOuResetarPedido(string $tipo, int $clienteId, int $veiculoId): array
{
    $pdo = getPDO();
    $descricao = 'Seed QA Gamboa (' . $tipo . ')';
    $ehColisao = $tipo === 'colisao';

    $serviceTypeId = null;
    $attendanceMode = 'TOWING';
    if (!$ehColisao) {
        $eletrico = ServiceType::buscarPorCodigo('ELECTRICAL_DIAGNOSIS');
        if (!$eletrico) {
            throw new RuntimeException("Tipo de serviço 'ELECTRICAL_DIAGNOSIS' não encontrado — rode install/migration_service_catalog_v1.sql.");
        }
        $serviceTypeId = (int)$eletrico['id'];
        $attendanceMode = 'ON_SITE';
    }

    $pedido = qaGamboaFindReusablePedido($clienteId, $descricao);

    $destinoLat = $ehColisao ? OFICINA_LAT : OCORRENCIA_LAT;
    $destinoLng = $ehColisao ? OFICINA_LNG : OCORRENCIA_LNG;
    $destinoEndereco = $ehColisao ? OFICINA_ENDERECO : OCORRENCIA_ENDERECO;
    $distanciaKm = $ehColisao ? 7.2 : 0.0;
    $custoEstimado = $ehColisao ? 189.90 : 89.90;

    if ($pedido) {
        $fields = [
            'status = ?' => 'aguardando_pagamento',
            'guincho_id = ?' => null,
            'custo_estimado = ?' => $custoEstimado,
            'lat_destino = ?' => $destinoLat,
            'lng_destino = ?' => $destinoLng,
            'endereco_destino = ?' => $destinoEndereco,
            'distancia_km = ?' => $distanciaKm,
        ];
        if ($serviceTypeId !== null && qaGamboaHasColumn('pedidos', 'service_type_id')) {
            $fields['service_type_id = ?'] = $serviceTypeId;
        }
        if (qaGamboaHasColumn('pedidos', 'attendance_mode')) {
            $fields['attendance_mode = ?'] = $attendanceMode;
        }
        if (qaGamboaHasColumn('pedidos', 'custo_final')) {
            $fields['custo_final = ?'] = 0;
        }
        if (qaGamboaHasColumn('pedidos', 'foto_plataforma')) {
            $fields['foto_plataforma = ?'] = null;
        }
        if (qaGamboaHasColumn('pedidos', 'foto_destino')) {
            $fields['foto_destino = ?'] = null;
        }
        if (qaGamboaHasColumn('pedidos', 'cancelado_por')) {
            $fields['cancelado_por = ?'] = null;
        }

        $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
        $params = array_values($fields);
        $params[] = (int)$pedido['id'];
        $pdo->prepare($sql)->execute($params);

        qaGamboaLimparPedido((int)$pedido['id']);
        return Pedido::buscarPorId((int)$pedido['id']) ?: $pedido;
    }

    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => $ehColisao ? 'Colisão QA (Gamboa)' : 'Pane elétrica QA (Gamboa)',
        'descricao_problema' => $descricao,
        'lat_origem' => OCORRENCIA_LAT,
        'lng_origem' => OCORRENCIA_LNG,
        'endereco_origem' => OCORRENCIA_ENDERECO,
        'lat_destino' => $destinoLat,
        'lng_destino' => $destinoLng,
        'endereco_destino' => $destinoEndereco,
        'distancia_km' => $distanciaKm,
        'custo_estimado' => $custoEstimado,
        'status' => 'aguardando_pagamento',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido QA Gamboa (' . $tipo . ').');
    }

    $pdo = getPDO();
    $extra = [];
    if ($serviceTypeId !== null && qaGamboaHasColumn('pedidos', 'service_type_id')) {
        $extra['service_type_id = ?'] = $serviceTypeId;
    }
    if (qaGamboaHasColumn('pedidos', 'attendance_mode')) {
        $extra['attendance_mode = ?'] = $attendanceMode;
    }
    if ($extra) {
        $sql = 'UPDATE pedidos SET ' . implode(', ', array_keys($extra)) . ' WHERE id = ?';
        $params = array_values($extra);
        $params[] = (int)$pedidoId;
        $pdo->prepare($sql)->execute($params);
    }

    return Pedido::buscarPorId((int)$pedidoId) ?: [];
}

try {
    $tipo = trim((string)($argv[1] ?? 'colisao'));

    if ($tipo === 'atribuir') {
        $pedidoId = (int)($argv[2] ?? 0);
        if ($pedidoId <= 0) {
            throw new RuntimeException('Uso: php prepare_atendimento_rj_gamboa_qa.php atribuir <pedido_id>');
        }
        $prestadorUsuario = Usuario::buscarPorEmail(QA_PRESTADOR_EMAIL);
        if (!$prestadorUsuario) {
            throw new RuntimeException('Prestador QA Gamboa ainda não existe — rode o setup (colisao/pane-eletrica) primeiro.');
        }
        $prestador = Guincho::buscarPorUsuario((int)$prestadorUsuario['id']);
        Pedido::atribuirGuincho($pedidoId, (int)$prestador['id']);
        getPDO()->prepare('UPDATE pedidos SET status = ? WHERE id = ?')->execute(['a_caminho', $pedidoId]);

        $pedido = Pedido::buscarPorId($pedidoId);
        echo json_encode([
            'ok' => true,
            'pedido_id' => $pedidoId,
            'status' => $pedido['status'] ?? 'a_caminho',
            'guincho_id' => (int)$prestador['id'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit;
    }

    if ($tipo === 'aguardando-guincho') {
        // Atalho só de QA para stress-concorrencia.spec.ts: coloca um pedido
        // 'colisao' direto em 'aguardando_guincho' (sem exigir um pagamento
        // real), pra permitir que N prestadores disputem o aceite ao mesmo
        // tempo — o que está sob teste ali é a corrida de aceite em si
        // (SELECT FOR UPDATE em GuinchoController::aceitar), não o pagamento.
        $cliente = qaGamboaEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Gamboa', '21999992001', QA_CLIENTE_CPF);
        $veiculo = qaGamboaEnsureVeiculo((int)$cliente['id']);
        $pedido = qaGamboaCriarOuResetarPedido('colisao', (int)$cliente['id'], (int)$veiculo['id']);
        getPDO()->prepare('UPDATE pedidos SET status = ?, guincho_id = NULL WHERE id = ?')->execute(['aguardando_guincho', (int)$pedido['id']]);

        echo json_encode([
            'ok' => true,
            'pedido_id' => (int)$pedido['id'],
            'status' => 'aguardando_guincho',
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit;
    }

    if (!in_array($tipo, ['colisao', 'pane-eletrica'], true)) {
        throw new RuntimeException("Tipo inválido: '{$tipo}'. Use 'colisao', 'pane-eletrica', 'aguardando-guincho' ou 'atribuir <pedido_id>'.");
    }

    $cliente = qaGamboaEnsureUsuario(QA_CLIENTE_EMAIL, 'cliente', 'Cliente QA Gamboa', '21999992001', QA_CLIENTE_CPF);
    $prestadorUsuario = qaGamboaEnsureUsuario(QA_PRESTADOR_EMAIL, 'guincho', 'Prestador QA Gamboa', '21999992002', QA_PRESTADOR_CPF);
    qaGamboaEnsurePrestador((int)$prestadorUsuario['id']);
    $veiculo = qaGamboaEnsureVeiculo((int)$cliente['id']);

    $pedido = qaGamboaCriarOuResetarPedido($tipo, (int)$cliente['id'], (int)$veiculo['id']);

    echo json_encode([
        'ok' => true,
        'pedido_id' => (int)$pedido['id'],
        'tipo' => $tipo,
        'status' => 'aguardando_pagamento',
        'service_type_id' => (int)($pedido['service_type_id'] ?? 0),
        'cliente_email' => QA_CLIENTE_EMAIL,
        'prestador_email' => QA_PRESTADOR_EMAIL,
        'cliente_url' => '/cliente/pedido/' . (int)$pedido['id'],
        'checkout_url' => '/pagamento/checkout/' . (int)$pedido['id'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
