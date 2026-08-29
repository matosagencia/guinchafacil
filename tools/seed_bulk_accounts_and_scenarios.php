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
require_once dirname(__DIR__) . '/src/Models/Pagamento.php';
require_once dirname(__DIR__) . '/src/Models/Chat.php';
require_once dirname(__DIR__) . '/src/Models/Avaliacao.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';

const BATCH_CLIENT_COUNT = 15;
const BATCH_GUINCHO_COUNT = 15;
const BATCH_PASSWORD = 'test12345';

const SCENARIOS = [
    'longa_distancia',
    'curta_distancia',
    'cancelamento_prazo',
    'cancelamento_tardio',
    'disputa',
];

const CLIENT_BASE = [
    'cep' => '20091-007',
    'logradouro' => 'Rua da Gamboa',
    'bairro' => 'Gamboa',
    'cidade' => 'Rio de Janeiro',
    'estado' => 'RJ',
];

const SHORT_ORIGIN = ['lat' => -22.89724, 'lng' => -43.19478, 'endereco' => 'Rua do Propósito 59, Gamboa, Rio de Janeiro - RJ'];
const SHORT_DEST = ['lat' => -22.89633, 'lng' => -43.19842, 'endereco' => 'Rua da Gamboa 249, Gamboa, Rio de Janeiro - RJ'];
const LONG_DEST = ['lat' => -23.00479, 'lng' => -43.36588, 'endereco' => 'Avenida Lúcio Costa 4700, Barra da Tijuca, Rio de Janeiro - RJ'];
const CANCEL_DEST = ['lat' => -22.90310, 'lng' => -43.20960, 'endereco' => 'Praça Mauá 1, Centro, Rio de Janeiro - RJ'];
const DISPUTE_DEST = ['lat' => -22.88454, 'lng' => -43.20961, 'endereco' => 'Rua São Cristóvão 516, São Cristóvão, Rio de Janeiro - RJ'];

$runTag = $argv[1] ?? '20260713seed01';
$pdo = getPDO();

function hasTable(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = getPDO()->prepare(
        "SELECT COUNT(*)
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table]);
    $cache[$table] = (int)$stmt->fetchColumn() > 0;
    return $cache[$table];
}

function hasColumn(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = getPDO()->prepare(
        "SELECT COUNT(*)
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?"
    );
    $stmt->execute([DB_NAME, $table, $column]);
    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
}

function validCpf(string $digits): bool
{
    if (!preg_match('/^\d{11}$/', $digits) || preg_match('/(\d)\1{10}/', $digits)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += (int)$digits[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ((int)$digits[$t] !== $d) {
            return false;
        }
    }
    return true;
}

function cpfCheckDigit(array $partial): int
{
    $factorStart = count($partial) + 1;
    $sum = 0;
    foreach ($partial as $index => $digit) {
        $sum += $digit * ($factorStart - $index);
    }
    return ((10 * $sum) % 11) % 10;
}

function generateCpf(int $seed): string
{
    $base = str_pad((string)($seed % 1000000000), 9, '0', STR_PAD_LEFT);
    $digits = array_map('intval', str_split($base));
    $digits[] = cpfCheckDigit($digits);
    $digits[] = cpfCheckDigit($digits);
    $cpf = implode('', $digits);
    if (!validCpf($cpf)) {
        throw new RuntimeException('CPF gerado inválido: ' . $cpf);
    }
    return $cpf;
}

function formatCpfSeed(string $cpf): string
{
    return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf) ?? $cpf;
}

function generatePhone(int $seed): string
{
    return '219' . str_pad((string)(10000000 + ($seed % 89999999)), 8, '0', STR_PAD_LEFT);
}

function generateMercosulPlate(int $seed): string
{
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $n = abs($seed);
    return $letters[($n + 3) % 26]
        . $letters[($n + 9) % 26]
        . $letters[($n + 15) % 26]
        . (($n + 2) % 10)
        . $letters[($n + 21) % 26]
        . (($n + 4) % 10)
        . (($n + 7) % 10);
}

function uploadFixture(string $prefix): ?string
{
    $source = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo-128.png';
    if (!is_file($source)) {
        return null;
    }
    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0775, true);
    }
    $targetName = $prefix . '_' . bin2hex(random_bytes(4)) . '.png';
    $target = rtrim(UPLOAD_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $targetName;
    copy($source, $target);
    return $targetName;
}

function upsertUsuario(string $email, string $tipo, string $nome, string $telefone, string $cpf): array
{
    $pdo = getPDO();
    $usuario = Usuario::buscarPorEmail($email);
    if (!$usuario) {
        $id = (int)Usuario::criar([
            'nome' => $nome,
            'email' => $email,
            'senha_hash' => password_hash(BATCH_PASSWORD, PASSWORD_BCRYPT),
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
        password_hash(BATCH_PASSWORD, PASSWORD_BCRYPT),
        (int)$usuario['id'],
    ]);

    return Usuario::buscarPorId((int)$usuario['id']) ?: $usuario;
}

function upsertEndereco(int $usuarioId, array $data): void
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM enderecos WHERE usuario_id = ? AND principal = 1 LIMIT 1");
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare(
            "UPDATE enderecos
                SET cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, principal = 1
              WHERE id = ?"
        )->execute([
            $data['cep'],
            $data['logradouro'],
            $data['numero'],
            $data['complemento'],
            $data['bairro'],
            $data['cidade'],
            $data['estado'],
            (int)$row['id'],
        ]);
        return;
    }

    $pdo->prepare(
        "INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, principal)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
    )->execute([
        $usuarioId,
        $data['cep'],
        $data['logradouro'],
        $data['numero'],
        $data['complemento'],
        $data['bairro'],
        $data['cidade'],
        $data['estado'],
    ]);
}

function upsertVeiculo(int $usuarioId, array $data): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM veiculos WHERE usuario_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare(
            "UPDATE veiculos
                SET placa = ?, marca = ?, modelo = ?, ano = ?, cor = ?, tipo = ?, ativo = 1
              WHERE id = ?"
        )->execute([
            $data['placa'],
            $data['marca'],
            $data['modelo'],
            $data['ano'],
            $data['cor'],
            $data['tipo'],
            (int)$row['id'],
        ]);
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: $row;
    }

    $id = (int)Veiculo::criar($usuarioId, $data);
    $stmt = $pdo->prepare("SELECT * FROM veiculos WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function upsertOficina(int $usuarioId, array $data): array
{
    $oficinas = Oficina::listarPorUsuario($usuarioId);
    foreach ($oficinas as $oficina) {
        if (($oficina['nome'] ?? '') === $data['nome']) {
            Oficina::atualizar((int)$oficina['id'], $data);
            return Oficina::buscarPorId((int)$oficina['id']) ?: $oficina;
        }
    }
    $id = (int)Oficina::criar($data + ['usuario_id' => $usuarioId]);
    return Oficina::buscarPorId($id) ?: [];
}

function upsertGuincho(int $usuarioId, array $data): array
{
    $pdo = getPDO();
    $guincho = Guincho::buscarPorUsuario($usuarioId);
    if (!$guincho) {
        $id = Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => $data['cnh_numero'],
            'cnh_validade' => $data['cnh_validade'],
            'placa_guincho' => $data['placa_guincho'],
            'capacidade_ton' => $data['capacidade_ton'],
            'raio_cobertura_km' => $data['raio_cobertura_km'],
            'chave_pix' => $data['chave_pix'],
            'chave_pix_tipo' => $data['chave_pix_tipo'],
            'foto_veiculo' => $data['foto_veiculo'],
            'doc_cnh_frente' => $data['doc_cnh_frente'],
            'doc_cnh_verso' => $data['doc_cnh_verso'],
        ]);
        $guincho = Guincho::buscarPorId((int)$id);
    }

    $fields = [
        'aprovado = ?' => 1,
        'disponivel = ?' => 1,
        'cnh_numero = ?' => $data['cnh_numero'],
        'cnh_validade = ?' => $data['cnh_validade'],
        'placa_guincho = ?' => $data['placa_guincho'],
        'capacidade_ton = ?' => $data['capacidade_ton'],
        'raio_cobertura_km = ?' => $data['raio_cobertura_km'],
        'chave_pix = ?' => $data['chave_pix'],
        'chave_pix_tipo = ?' => $data['chave_pix_tipo'],
        'foto_veiculo = ?' => $data['foto_veiculo'],
        'doc_cnh_frente = ?' => $data['doc_cnh_frente'],
        'doc_cnh_verso = ?' => $data['doc_cnh_verso'],
        'lat_atual = ?' => $data['lat_atual'],
        'lng_atual = ?' => $data['lng_atual'],
    ];

    if (hasColumn('guinchos', 'lat_operacao')) {
        $fields['lat_operacao = ?'] = $data['lat_atual'];
    }
    if (hasColumn('guinchos', 'lng_operacao')) {
        $fields['lng_operacao = ?'] = $data['lng_atual'];
    }
    if (hasColumn('guinchos', 'cidade_placa')) {
        $fields['cidade_placa = ?'] = 'Rio de Janeiro';
    }
    if (hasColumn('guinchos', 'uf_placa')) {
        $fields['uf_placa = ?'] = 'RJ';
    }
    if (hasColumn('guinchos', 'foto_caminhao')) {
        $fields['foto_caminhao = ?'] = $data['foto_veiculo'];
    }

    $sql = 'UPDATE guinchos SET ' . implode(', ', array_keys($fields)) . ' WHERE id = ?';
    $params = array_values($fields);
    $params[] = (int)$guincho['id'];
    $pdo->prepare($sql)->execute($params);

    return Guincho::buscarPorId((int)$guincho['id']) ?: $guincho;
}

function cleanupScenarioOrders(array $clienteIds, string $runTag): void
{
    if (empty($clienteIds)) {
        return;
    }
    $pdo = getPDO();
    $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
    $params = $clienteIds;
    $params[] = '%[seed:' . $runTag . ']%';
    $stmt = $pdo->prepare(
        "SELECT id FROM pedidos
          WHERE cliente_id IN ({$placeholders})
            AND descricao_problema LIKE ?"
    );
    $stmt->execute($params);
    $ids = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    if (empty($ids)) {
        return;
    }

    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $optionalTables = ['chat_mensagens', 'pedido_evidencias', 'pedido_localizacoes', 'pedido_percurso_resumos', 'avaliacoes', 'pagamentos', 'payment_jobs', 'pedido_cancelamentos'];
    foreach ($optionalTables as $table) {
        if (!hasTable($table)) {
            continue;
        }
        $column = $table === 'payment_jobs' ? 'pedido_id' : 'pedido_id';
        $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$idPlaceholders})")->execute($ids);
    }
    $pdo->prepare("DELETE FROM pedidos WHERE id IN ({$idPlaceholders})")->execute($ids);
}

function createPedido(array $cliente, array $veiculo, array $guincho, array $coords, string $scenario, string $runTag, int $sequence): array
{
    $pedidoId = Pedido::criar([
        'cliente_id' => (int)$cliente['id'],
        'veiculo_id' => (int)$veiculo['id'],
        'tipo_problema' => scenarioTitle($scenario),
        'descricao_problema' => scenarioTitle($scenario) . ' [seed:' . $runTag . '] cliente#' . (int)$cliente['id'] . ' seq#' . $sequence,
        'lat_origem' => $coords['origem']['lat'],
        'lng_origem' => $coords['origem']['lng'],
        'endereco_origem' => $coords['origem']['endereco'],
        'lat_destino' => $coords['destino']['lat'],
        'lng_destino' => $coords['destino']['lng'],
        'endereco_destino' => $coords['destino']['endereco'],
        'distancia_km' => $coords['distancia_km'],
        'custo_estimado' => $coords['valor'],
        'status' => 'aguardando_guincho',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);
    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido do cenário ' . $scenario);
    }

    $pedidoId = (int)$pedidoId;
    Pedido::atribuirGuincho($pedidoId, (int)$guincho['id']);
    return Pedido::buscarPorId($pedidoId) ?: [];
}

function scenarioTitle(string $scenario): string
{
    return match ($scenario) {
        'longa_distancia' => 'Pedido de grande distância',
        'curta_distancia' => 'Pedido de curta distância',
        'cancelamento_prazo' => 'Cancelamento no prazo',
        'cancelamento_tardio' => 'Cancelamento tardio com extorno parcial',
        'disputa' => 'Disputa de aceite',
        default => 'Pedido QA',
    };
}

function seedChats(int $pedidoId, int $clienteId, int $guinchoUsuarioId, string $scenario, string $clienteNome, string $guinchoNome): void
{
    $mensagens = match ($scenario) {
        'longa_distancia' => [
            [$clienteId, "{$guinchoNome}, preciso de apoio para uma corrida longa até a Barra."],
            [$guinchoUsuarioId, "Recebido, {$clienteNome}. Já tracei a rota e sigo monitorando o ETA."],
            [$clienteId, 'Perfeito, vou acompanhar pelo mapa e aviso qualquer mudança.'],
            [$guinchoUsuarioId, 'Tudo certo. Qualquer ajuste, seguimos falando por aqui.'],
        ],
        'curta_distancia' => [
            [$clienteId, 'O socorro é perto, consegue chegar rápido?'],
            [$guinchoUsuarioId, 'Sim, estou a poucos minutos e já vou posicionar o reboque.'],
            [$clienteId, 'Ótimo, fico aguardando na rua.'],
            [$guinchoUsuarioId, 'Chegando no ponto combinado, qualquer coisa me chama aqui.'],
        ],
        'cancelamento_prazo' => [
            [$clienteId, 'Consegui resolver antes do guincho chegar, vou cancelar dentro do prazo.'],
            [$guinchoUsuarioId, "Sem problema, {$clienteNome}. Vou liberar o atendimento aqui."],
            [$clienteId, 'Obrigado pela confirmação.'],
        ],
        'cancelamento_tardio' => [
            [$clienteId, 'Estou com atraso e talvez precise cancelar, qual a situação do deslocamento?'],
            [$guinchoUsuarioId, 'Já estou em rota e quase no local, então o cancelamento pode ter retenção parcial.'],
            [$clienteId, 'Entendido, vou seguir com o cancelamento mesmo assim.'],
        ],
        'disputa' => [
            [$clienteId, 'Vi que mais de um guincho recebeu meu chamado. Quem ficou com ele?'],
            [$guinchoUsuarioId, 'Eu aceitei primeiro e já estou a caminho. O pedido saiu da fila para os demais.'],
            [$clienteId, 'Perfeito, vou acompanhar por aqui então.'],
            [$guinchoUsuarioId, 'Combinado, mantenho você atualizado no chat e no mapa.'],
        ],
        default => [],
    };

    foreach ($mensagens as [$userId, $texto]) {
        Chat::enviar($pedidoId, $userId, $texto);
    }
}

function seedPayment(int $pedidoId, float $valorTotal, string $status, float $valorGuincho, float $valorPlataforma, string $metodo = 'freeflow'): void
{
    $pagamentoId = Pagamento::criar($pedidoId, $metodo, $valorTotal, $valorGuincho, $valorPlataforma);
    if (!$pagamentoId) {
        return;
    }

    $sets = ["status = ?"];
    $params = [$status];
    if (hasColumn('pagamentos', 'pago_guincho')) {
        $sets[] = 'pago_guincho = ?';
        $params[] = $status === 'aprovado' ? 1 : 0;
    }
    if (hasColumn('pagamentos', 'status_pix')) {
        $sets[] = 'status_pix = ?';
        $params[] = $status === 'aprovado' ? 'concluido' : ($status === 'estornado' ? 'cancelado' : 'pendente');
    }
    if (hasColumn('pagamentos', 'data_pagamento')) {
        $sets[] = 'data_pagamento = NOW()';
    }
    if (hasColumn('pagamentos', 'data_pagamento_guincho') && $status === 'aprovado') {
        $sets[] = 'data_pagamento_guincho = NOW()';
    }
    if (hasColumn('pagamentos', 'id_externo')) {
        $sets[] = 'id_externo = ?';
        $params[] = $metodo . '_' . $pedidoId;
    }
    $params[] = (int)$pagamentoId;
    getPDO()->prepare('UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
}

function createAvaliacaoIfPossible(int $pedidoId, int $clienteId, int $guinchoId, int $stars, string $comment): void
{
    if (!Avaliacao::jaAvaliou($pedidoId, $clienteId)) {
        Avaliacao::criar($pedidoId, $clienteId, $guinchoId, $stars, $comment);
        Guincho::atualizarReputacao($guinchoId);
    }
}

function updatePedidoStatus(array $pedido, string $status, array $extra = []): void
{
    $sets = ['status = ?'];
    $params = [$status];

    foreach ($extra as $column => $value) {
        if (hasColumn('pedidos', $column)) {
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }
    }

    if ($status === 'cancelado' && hasColumn('pedidos', 'cancelado_em')) {
        $sets[] = 'cancelado_em = NOW()';
    }

    $params[] = (int)$pedido['id'];
    getPDO()->prepare('UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
}

function applyScenario(array $pedido, array $cliente, array $guincho, string $scenario, int $sequence): array
{
    $guinchoUsuarioId = (int)$guincho['usuario_id'];
    seedChats((int)$pedido['id'], (int)$cliente['id'], $guinchoUsuarioId, $scenario, (string)$cliente['nome'], (string)$guincho['nome_operador']);

    return match ($scenario) {
        'longa_distancia' => completeScenario($pedido, $cliente, $guincho, 4, 'Corrida longa concluída com atendimento estável.'),
        'curta_distancia' => completeScenario($pedido, $cliente, $guincho, 5, 'Corrida curta concluída com rapidez e cordialidade.'),
        'cancelamento_prazo' => cancelScenario($pedido, 'cliente', 0.0, 'Cliente cancelou dentro do prazo gratuito.'),
        'cancelamento_tardio' => cancelScenario($pedido, 'cliente', 35.00, 'Cliente cancelou tardiamente com extorno parcial.'),
        'disputa' => disputeScenario($pedido, $cliente, $guincho, $sequence),
        default => [],
    };
}

function completeScenario(array $pedido, array $cliente, array $guincho, int $stars, string $comment): array
{
    updatePedidoStatus($pedido, 'concluido', [
        'custo_final' => $pedido['custo_estimado'],
    ]);
    seedPayment((int)$pedido['id'], (float)$pedido['custo_estimado'], 'aprovado', round((float)$pedido['custo_estimado'] * 0.85, 2), round((float)$pedido['custo_estimado'] * 0.15, 2));
    createAvaliacaoIfPossible((int)$pedido['id'], (int)$cliente['id'], (int)$guincho['id'], $stars, $comment);
    return [
        'pedido_id' => (int)$pedido['id'],
        'status' => 'concluido',
        'avaliacao' => $stars,
    ];
}

function cancelScenario(array $pedido, string $canceladoPor, float $taxa, string $motivo): array
{
    seedPayment((int)$pedido['id'], (float)$pedido['custo_estimado'], $taxa > 0 ? 'estornado' : 'pendente', round((float)$pedido['custo_estimado'] - $taxa, 2), $taxa);
    updatePedidoStatus($pedido, 'cancelado', [
        'cancelado_por' => $canceladoPor,
        'motivo_cancelamento' => $motivo,
        'taxa_cancelamento' => $taxa,
    ]);
    return [
        'pedido_id' => (int)$pedido['id'],
        'status' => 'cancelado',
        'taxa_cancelamento' => $taxa,
    ];
}

function disputeScenario(array $pedido, array $cliente, array $guincho, int $sequence): array
{
    updatePedidoStatus($pedido, 'a_caminho', [
        'motivo_cancelamento' => 'Disputa encerrada, guincho vencedor definido no seed #' . $sequence,
    ]);
    return [
        'pedido_id' => (int)$pedido['id'],
        'status' => 'a_caminho',
        'disputa' => true,
    ];
}

function scenarioCoords(string $scenario, int $offset): array
{
    $adjust = $offset * 0.0003;
    return match ($scenario) {
        'longa_distancia' => [
            'origem' => ['lat' => SHORT_ORIGIN['lat'] + $adjust, 'lng' => SHORT_ORIGIN['lng'] + $adjust, 'endereco' => SHORT_ORIGIN['endereco']],
            'destino' => ['lat' => LONG_DEST['lat'] + $adjust, 'lng' => LONG_DEST['lng'] + $adjust, 'endereco' => LONG_DEST['endereco']],
            'distancia_km' => 41.8,
            'valor' => 420.00,
        ],
        'curta_distancia' => [
            'origem' => ['lat' => SHORT_ORIGIN['lat'] + $adjust, 'lng' => SHORT_ORIGIN['lng'], 'endereco' => SHORT_ORIGIN['endereco']],
            'destino' => ['lat' => SHORT_DEST['lat'], 'lng' => SHORT_DEST['lng'] + $adjust, 'endereco' => SHORT_DEST['endereco']],
            'distancia_km' => 2.8,
            'valor' => 89.90,
        ],
        'cancelamento_prazo' => [
            'origem' => ['lat' => SHORT_ORIGIN['lat'] - $adjust, 'lng' => SHORT_ORIGIN['lng'], 'endereco' => SHORT_ORIGIN['endereco']],
            'destino' => ['lat' => CANCEL_DEST['lat'], 'lng' => CANCEL_DEST['lng'] + $adjust, 'endereco' => CANCEL_DEST['endereco']],
            'distancia_km' => 6.4,
            'valor' => 139.00,
        ],
        'cancelamento_tardio' => [
            'origem' => ['lat' => SHORT_ORIGIN['lat'], 'lng' => SHORT_ORIGIN['lng'] - $adjust, 'endereco' => SHORT_ORIGIN['endereco']],
            'destino' => ['lat' => CANCEL_DEST['lat'] + $adjust, 'lng' => CANCEL_DEST['lng'], 'endereco' => CANCEL_DEST['endereco']],
            'distancia_km' => 8.1,
            'valor' => 185.00,
        ],
        'disputa' => [
            'origem' => ['lat' => SHORT_ORIGIN['lat'] + $adjust, 'lng' => SHORT_ORIGIN['lng'] - $adjust, 'endereco' => SHORT_ORIGIN['endereco']],
            'destino' => ['lat' => DISPUTE_DEST['lat'], 'lng' => DISPUTE_DEST['lng'] + $adjust, 'endereco' => DISPUTE_DEST['endereco']],
            'distancia_km' => 11.3,
            'valor' => 210.00,
        ],
        default => [
            'origem' => SHORT_ORIGIN,
            'destino' => SHORT_DEST,
            'distancia_km' => 5.0,
            'valor' => 100.00,
        ],
    };
}

Configuracao::set('system_mode', 'freeflow', 'Seed local para cenários QA.');
Configuracao::set('payment_required', '0', 'Seed local para cenários QA.');

$clientes = [];
$guinchos = [];

for ($i = 1; $i <= BATCH_CLIENT_COUNT; $i++) {
    $cpf = generateCpf(100000000 + crc32($runTag) + $i);
    $email = "qa.cliente.{$runTag}.{$i}@guinchafacil.com";
    $cliente = upsertUsuario($email, 'cliente', "Cliente Lote {$i} {$runTag}", generatePhone($i), $cpf);
    upsertEndereco((int)$cliente['id'], [
        'cep' => CLIENT_BASE['cep'],
        'logradouro' => CLIENT_BASE['logradouro'],
        'numero' => (string)(240 + $i),
        'complemento' => 'Apto ' . $i,
        'bairro' => CLIENT_BASE['bairro'],
        'cidade' => CLIENT_BASE['cidade'],
        'estado' => CLIENT_BASE['estado'],
    ]);
    $veiculo = upsertVeiculo((int)$cliente['id'], [
        'placa' => generateMercosulPlate($i + crc32($runTag)),
        'marca' => $i % 2 === 0 ? 'Toyota' : 'Fiat',
        'modelo' => $i % 2 === 0 ? 'Corolla ' . $i : 'Mobi ' . $i,
        'ano' => 2020 + ($i % 5),
        'cor' => $i % 2 === 0 ? 'Prata' : 'Branco',
        'tipo' => $i % 4 === 0 ? 'van' : 'carro',
    ]);
    $oficina = upsertOficina((int)$cliente['id'], [
        'nome' => 'Oficina QA ' . $i,
        'telefone' => generatePhone(70 + $i),
        'endereco' => 'Rua da Gamboa ' . (300 + $i) . ', Gamboa, Rio de Janeiro - RJ',
        'latitude' => SHORT_DEST['lat'],
        'longitude' => SHORT_DEST['lng'],
    ]);
    $clientes[] = [
        'usuario' => $cliente,
        'veiculo' => $veiculo,
        'oficina' => $oficina,
    ];
}

for ($i = 1; $i <= BATCH_GUINCHO_COUNT; $i++) {
    $cpf = generateCpf(200000000 + crc32($runTag) + $i);
    $email = "qa.guincho.{$runTag}.{$i}@guinchafacil.com";
    $usuario = upsertUsuario($email, 'guincho', "Guincheiro Lote {$i} {$runTag}", generatePhone(200 + $i), $cpf);
    upsertEndereco((int)$usuario['id'], [
        'cep' => CLIENT_BASE['cep'],
        'logradouro' => CLIENT_BASE['logradouro'],
        'numero' => (string)(400 + $i),
        'complemento' => 'Base ' . $i,
        'bairro' => CLIENT_BASE['bairro'],
        'cidade' => CLIENT_BASE['cidade'],
        'estado' => CLIENT_BASE['estado'],
    ]);
    $foto = uploadFixture('guincho_' . $i);
    $guincho = upsertGuincho((int)$usuario['id'], [
        'cnh_numero' => substr((string)(90000000000 + $i + crc32($runTag)), 0, 11),
        'cnh_validade' => '2030-12-' . str_pad((string)(($i % 20) + 1), 2, '0', STR_PAD_LEFT),
        'placa_guincho' => generateMercosulPlate(500 + $i + crc32($runTag)),
        'capacidade_ton' => $i % 3 === 0 ? 8.0 : 6.5,
        'raio_cobertura_km' => 20 + ($i * 5),
        'chave_pix' => $email,
        'chave_pix_tipo' => 'email',
        'foto_veiculo' => $foto,
        'doc_cnh_frente' => $foto,
        'doc_cnh_verso' => $foto,
        'lat_atual' => SHORT_ORIGIN['lat'] + ($i * 0.0002),
        'lng_atual' => SHORT_ORIGIN['lng'] + ($i * 0.0002),
    ]);
    $guinchos[] = [
        'usuario' => $usuario,
        'guincho' => $guincho,
    ];
}

$clienteIds = array_map(static fn(array $entry): int => (int)$entry['usuario']['id'], $clientes);
cleanupScenarioOrders($clienteIds, $runTag);

$scenarioSummary = [];
$sequence = 0;
foreach ($clientes as $index => $clientePack) {
    foreach (SCENARIOS as $scenario) {
        $sequence++;
        $guinchoPack = $guinchos[($index + $sequence) % count($guinchos)];
        $coords = scenarioCoords($scenario, $sequence);
        $pedido = createPedido($clientePack['usuario'], $clientePack['veiculo'], $guinchoPack['guincho'], $coords, $scenario, $runTag, $sequence);
        $result = applyScenario($pedido, $clientePack['usuario'], $guinchoPack['guincho'], $scenario, $sequence);
        $scenarioSummary[$scenario][] = [
            'pedido_id' => (int)$pedido['id'],
            'cliente_email' => $clientePack['usuario']['email'],
            'guincho_email' => $guinchoPack['usuario']['email'],
            'status' => $result['status'] ?? ($pedido['status'] ?? ''),
        ];
    }
}

echo json_encode([
    'ok' => true,
    'run_tag' => $runTag,
    'password_padrao' => BATCH_PASSWORD,
    'clientes_criados' => count($clientes),
    'guinchos_criados' => count($guinchos),
    'pedidos_criados' => array_sum(array_map('count', $scenarioSummary)),
    'cenarios' => array_map('count', $scenarioSummary),
    'clientes_exemplo' => array_slice(array_map(static fn(array $entry): array => [
        'nome' => $entry['usuario']['nome'],
        'email' => $entry['usuario']['email'],
        'cpf' => formatCpfSeed((string)$entry['usuario']['cpf']),
    ], $clientes), 0, 3),
    'guinchos_exemplo' => array_slice(array_map(static fn(array $entry): array => [
        'nome' => $entry['usuario']['nome'],
        'email' => $entry['usuario']['email'],
        'placa' => $entry['guincho']['placa_guincho'],
    ], $guinchos), 0, 3),
    'scenario_sample' => array_map(static fn(array $items): array => array_slice($items, 0, 3), $scenarioSummary),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
