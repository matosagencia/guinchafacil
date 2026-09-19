<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Usuario.php';
require_once dirname(__DIR__) . '/src/Models/Veiculo.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';
require_once dirname(__DIR__) . '/src/Models/Guincho.php';

const QA_CLIENTE_EMAIL = 'qa.cobertura.cliente@guinchafacil.com';
const QA_CLIENTE_PASSWORD = 'test123';
const QA_CLIENTE_NOME = 'Cliente QA Cobertura';
const QA_CLIENTE_TELEFONE = '21999993001';
const QA_CLIENTE_CPF = '11122233304';
const QA_VEICULO_PLACA = 'QAC001';
const QA_SERVICE_CODE = 'ELECTRICAL_DIAG';
const QA_SERVICE_NAME = 'Diagnostico Eletrico';
const QA_SERVICE_ID = 9001;
const QA_SERVICE_ESPECIALISTA_ID = 9001;

const TOW_GUINCHO_EMAIL = 'qa.cobertura.tow@guinchafacil.com';
const TOW_GUINCHO_NOME = 'Guincho QA Cobertura';
const TOW_GUINCHO_TELEFONE = '21999993002';
const TOW_GUINCHO_CPF = '10987654304';

const TOW_LAT = -22.999862;
const TOW_LNG = -43.364890;
const ONLY_REBOQUE_LAT = -23.001100;
const ONLY_REBOQUE_LNG = -43.366000;
const NO_COVERAGE_LAT = -3.101900;
const NO_COVERAGE_LNG = -60.025000;

function trace(string $msg): void
{
    fwrite(STDERR, "[seed-cobertura] {$msg}\n");
}

function ensureColumn(string $table, string $column, string $definition): void
{
    $pdo = getPDO();
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($cols as $col) {
            if ((string)($col['name'] ?? '') === $column) {
                return;
            }
        }
    } else {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

function ensureTable(string $sql): void
{
    getPDO()->exec($sql);
}

function ensureUsuario(string $email, string $nome, string $telefone, string $cpf, string $tipo): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $id = (int)$stmt->fetchColumn();

    $senhaHash = password_hash(QA_CLIENTE_PASSWORD, PASSWORD_BCRYPT);
    if ($id > 0) {
        $pdo->prepare('UPDATE usuarios SET nome=?, senha_hash=?, telefone=?, cpf=?, tipo=?, ativo=1 WHERE id=?')
            ->execute([$nome, $senhaHash, $telefone, $cpf, $tipo, $id]);
        return $id;
    }

    $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo, ativo) VALUES (?,?,?,?,?,?,1)')
        ->execute([$nome, $email, $senhaHash, $telefone, $cpf, $tipo]);
    return (int)$pdo->lastInsertId();
}

function ensureServiceCategory(string $code, string $name, string $description, string $icon, int $sortOrder): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM service_categories WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $id = (int)$stmt->fetchColumn();
    if ($id > 0) {
        $pdo->prepare('UPDATE service_categories SET name=?, description=?, icon=?, active=1, sort_order=? WHERE id=?')
            ->execute([$name, $description, $icon, $sortOrder, $id]);
        return $id;
    }

    $pdo->prepare('INSERT INTO service_categories (code, name, description, icon, active, sort_order, created_at, updated_at) VALUES (?,?,?,?,1,?,NOW(),NOW())')
        ->execute([$code, $name, $description, $icon, $sortOrder]);
    return (int)$pdo->lastInsertId();
}

function ensureVeiculo(int $usuarioId): int
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM veiculos WHERE usuario_id = ? AND placa = ? LIMIT 1');
    $stmt->execute([$usuarioId, QA_VEICULO_PLACA]);
    $id = (int)$stmt->fetchColumn();
    if ($id > 0) {
        return $id;
    }

    $veiculoId = Veiculo::criar($usuarioId, [
        'placa' => QA_VEICULO_PLACA,
        'marca' => 'Volkswagen',
        'modelo' => 'Gol',
        'ano' => 2020,
        'cor' => 'Prata',
        'tipo' => 'carro',
    ]);
    if (!$veiculoId) {
        throw new RuntimeException('Falha ao criar veículo QA.');
    }
    return (int)$veiculoId;
}

function ensureTowGuincho(int $usuarioId): int
{
    $pdo = getPDO();
    foreach (['aprovado', 'disponivel', 'reboque_aprovado', 'oferece_reboque'] as $column) {
        try {
            ensureColumn('guinchos', $column, 'INTEGER DEFAULT 0');
        } catch (Throwable $e) {
            trace("ignorado ensureColumn guinchos.{$column}: " . $e->getMessage());
        }
    }
    foreach (['lat_atual', 'lng_atual'] as $column) {
        try {
            ensureColumn('guinchos', $column, 'REAL');
        } catch (Throwable $e) {
            trace("ignorado ensureColumn guinchos.{$column}: " . $e->getMessage());
        }
    }
    $stmt = $pdo->prepare('SELECT id FROM guinchos WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $id = (int)$stmt->fetchColumn();
    if ($id <= 0) {
        $id = (int)Guincho::criarDeRegistro($usuarioId, [
            'cnh_numero' => '33344455567',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QATW001',
            'cidade_placa' => 'Rio de Janeiro',
            'uf_placa' => 'RJ',
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 25,
            'chave_pix' => 'qa-cobertura-tow-chave',
            'chave_pix_tipo' => 'email',
            'lat_operacao' => TOW_LAT,
            'lng_operacao' => TOW_LNG,
            'foto_veiculo' => null,
            'doc_cnh_frente' => null,
            'doc_cnh_verso' => null,
        ]);
        if ($id <= 0) {
            throw new RuntimeException('Falha ao criar guincho QA.');
        }
    }

    Guincho::solicitarReboque($id, [
        'placa_guincho' => 'QATW001',
        'cidade_placa' => 'Rio de Janeiro',
        'uf_placa' => 'RJ',
        'capacidade_ton' => 8.0,
        'cnh_numero' => '33344455567',
        'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
    ]);
    Guincho::aprovar($id);
    Guincho::atualizarLocalizacao($id, TOW_LAT, TOW_LNG);
    Guincho::atualizarDisponibilidade($id, true);
    trace("guincho {$id}: model calls executed");

    return $id;
}

function createPedido(int $clienteId, int $veiculoId, float $lat, float $lng, string $descricao, string $endereco): int
{
    $pedidoId = Pedido::criar([
        'cliente_id' => $clienteId,
        'veiculo_id' => $veiculoId,
        'tipo_problema' => 'QA Cobertura',
        'descricao_problema' => $descricao,
        'lat_origem' => $lat,
        'lng_origem' => $lng,
        'endereco_origem' => $endereco,
        'lat_destino' => $lat,
        'lng_destino' => $lng,
        'endereco_destino' => $endereco,
        'distancia_km' => 0.0,
        'custo_estimado' => 120.0,
        'status' => 'aguardando_pagamento',
        'raio_atual_km' => 10,
        'score_minimo_atual' => 0.5,
    ]);

    if (!$pedidoId) {
        throw new RuntimeException('Falha ao criar pedido QA de cobertura.');
    }

    $pdo = getPDO();
    $pdo->prepare("UPDATE pedidos SET attendance_mode='ON_SITE', service_type_id=?, guincho_id=NULL WHERE id=?")
        ->execute([QA_SERVICE_ID, (int)$pedidoId]);

    return (int)$pedidoId;
}

try {
    trace('preparando schema mínimo');
    $driver = (string)getPDO()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        ensureTable('CREATE TABLE IF NOT EXISTS especialistas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            nome_profissional TEXT,
            cpf_cnpj TEXT,
            documento_tipo TEXT,
            documento_numero TEXT,
            chave_pix TEXT,
            chave_pix_tipo TEXT,
            bio TEXT,
            raio_atendimento_km REAL DEFAULT 10,
            lat_atual REAL,
            lng_atual REAL,
            aprovado INTEGER DEFAULT 1,
            disponivel INTEGER DEFAULT 1,
            reputacao REAL DEFAULT 0,
            criado_em TEXT,
            atualizado_em TEXT
        )');
        ensureTable('CREATE TABLE IF NOT EXISTS servicos_especialista (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo TEXT NOT NULL UNIQUE,
            nome TEXT,
            ativo INTEGER NOT NULL DEFAULT 1
        )');
        ensureTable('CREATE TABLE IF NOT EXISTS especialista_servicos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            especialista_id INTEGER NOT NULL,
            servico_id INTEGER NOT NULL,
            habilitado INTEGER NOT NULL DEFAULT 1,
            created_at TEXT,
            updated_at TEXT
        )');
    } else {
        ensureTable('CREATE TABLE IF NOT EXISTS especialistas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            nome_profissional TEXT,
            cpf_cnpj TEXT,
            documento_tipo TEXT,
            documento_numero TEXT,
            chave_pix TEXT,
            chave_pix_tipo TEXT,
            bio TEXT,
            raio_atendimento_km DOUBLE DEFAULT 10,
            lat_atual DOUBLE,
            lng_atual DOUBLE,
            aprovado TINYINT(1) DEFAULT 1,
            disponivel TINYINT(1) DEFAULT 1,
            reputacao DOUBLE DEFAULT 0,
            criado_em DATETIME NULL,
            atualizado_em DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        ensureTable('CREATE TABLE IF NOT EXISTS servicos_especialista (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(255) NOT NULL UNIQUE,
            nome VARCHAR(255),
            ativo TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        ensureTable('CREATE TABLE IF NOT EXISTS especialista_servicos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            especialista_id INT NOT NULL,
            servico_id INT NOT NULL,
            habilitado TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    ensureColumn('pedidos', 'attendance_mode', 'TEXT');
    ensureColumn('pedidos', 'service_type_id', 'INTEGER');
    ensureColumn('pedidos', 'incidente_id', 'INTEGER');

    trace('limpando dados QA anteriores');
    $pdo = getPDO();
    $pdo->prepare("DELETE FROM pagamentos WHERE pedido_id IN (
        SELECT id FROM pedidos WHERE descricao_problema LIKE 'Seed QA cobertura %'
    )")->execute();
    $pdo->prepare("DELETE FROM pedidos WHERE descricao_problema LIKE 'Seed QA cobertura %'")->execute();
    $pdo->prepare("DELETE FROM veiculos WHERE placa = ?")->execute([QA_VEICULO_PLACA]);
    $pdo->prepare("DELETE FROM guinchos WHERE usuario_id IN (
        SELECT id FROM usuarios WHERE email IN (?, ?) OR cpf IN (?, ?)
    )")->execute([QA_CLIENTE_EMAIL, TOW_GUINCHO_EMAIL, QA_CLIENTE_CPF, TOW_GUINCHO_CPF]);
    $pdo->prepare("DELETE FROM usuarios WHERE email IN (?, ?) OR cpf IN (?, ?)")
        ->execute([QA_CLIENTE_EMAIL, TOW_GUINCHO_EMAIL, QA_CLIENTE_CPF, TOW_GUINCHO_CPF]);
    $pdo->prepare("DELETE FROM servicos_especialista WHERE codigo = ?")->execute([QA_SERVICE_CODE]);
    $pdo->prepare("DELETE FROM especialista_servicos WHERE servico_id = ?")->execute([QA_SERVICE_ESPECIALISTA_ID]);

    trace('seeding usuário e veículo');
    $clienteId = ensureUsuario(QA_CLIENTE_EMAIL, QA_CLIENTE_NOME, QA_CLIENTE_TELEFONE, QA_CLIENTE_CPF, 'cliente');
    $veiculoId = ensureVeiculo($clienteId);

    trace('seeding catálogo de serviço');
    $categoriaId = ensureServiceCategory(
        'ELECTRICAL_ASSISTANCE',
        'Assistência Elétrica',
        'Partida auxiliar, teste e troca de bateria, pane elétrica.',
        'bolt',
        30
    );
    trace('categoria de serviço garantida: ' . $categoriaId);

    $pdo->prepare("INSERT INTO service_types (id, category_id, code, name, attendance_mode, active, created_at, updated_at)
                   VALUES (?, ?, ?, ?, 'ON_SITE', 1, NOW(), NOW())
                   ON DUPLICATE KEY UPDATE category_id = VALUES(category_id), name = VALUES(name), attendance_mode = VALUES(attendance_mode), active = 1, updated_at = NOW()")
        ->execute([QA_SERVICE_ID, $categoriaId, QA_SERVICE_CODE, QA_SERVICE_NAME]);
    trace('service_type QA garantido: ' . QA_SERVICE_CODE . ' -> ' . QA_SERVICE_ID);
    $pdo->prepare("INSERT INTO servicos_especialista (id, codigo, nome, ativo) VALUES (?, ?, ?, 1)")
        ->execute([QA_SERVICE_ESPECIALISTA_ID, QA_SERVICE_CODE, QA_SERVICE_NAME]);
    trace('servico especialista QA garantido: ' . QA_SERVICE_ESPECIALISTA_ID);

    trace('seeding guincho de reboque');
    $towUserId = ensureUsuario(TOW_GUINCHO_EMAIL, TOW_GUINCHO_NOME, TOW_GUINCHO_TELEFONE, TOW_GUINCHO_CPF, 'guincho');
    $towGuinchoId = ensureTowGuincho($towUserId);

    trace('criando pedidos');
    $pedidoSomenteReboqueId = createPedido(
        $clienteId,
        $veiculoId,
        ONLY_REBOQUE_LAT,
        ONLY_REBOQUE_LNG,
        'Seed QA cobertura somente_reboque',
        'Avenida próxima ao guincho QA'
    );
    $pedidoSemCoberturaId = createPedido(
        $clienteId,
        $veiculoId,
        NO_COVERAGE_LAT,
        NO_COVERAGE_LNG,
        'Seed QA cobertura sem_cobertura',
        'Manaus, Amazonas'
    );

    echo json_encode([
        'ok' => true,
        'cliente_email' => QA_CLIENTE_EMAIL,
        'cliente_password' => QA_CLIENTE_PASSWORD,
        'pedido_somente_reboque_id' => $pedidoSomenteReboqueId,
        'pedido_sem_cobertura_id' => $pedidoSemCoberturaId,
        'service_type_id' => QA_SERVICE_ID,
        'guincho_email' => TOW_GUINCHO_EMAIL,
        'guincho_id' => $towGuinchoId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
