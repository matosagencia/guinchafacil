<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Models/Usuario.php';
require_once __DIR__ . '/../../src/Models/Veiculo.php';
require_once __DIR__ . '/../../src/Models/Pedido.php';
require_once __DIR__ . '/../../src/Models/Pagamento.php';
require_once __DIR__ . '/../../src/Models/Guincho.php';
require_once __DIR__ . '/../../src/Models/Configuracao.php';
require_once __DIR__ . '/../../src/Models/Catalog/ProviderCapability.php';
require_once __DIR__ . '/../../src/Services/CoberturaService.php';
require_once __DIR__ . '/../../src/Services/Payment/PagamentoAprovacaoService.php';

/**
 * Cobertura real do gate de aprovacao do pagamento.
 *
 * A ideia aqui e provar, com debug passo a passo, que:
 * - sem cobertura bloqueia
 * - somente reboque bloqueia
 * - cobertura plena aprova
 * - um incidente ja aberto pula o gate
 */
final class PagamentoAprovacaoServiceCoberturaTest extends TestCase
{
    private const CLIENT_EMAIL = 'qa.cobertura.integracao@guinchafacil.com';
    private const CLIENT_NAME = 'Cliente QA Integracao';
    private const CLIENT_PHONE = '21999994001';
    private const CLIENT_CPF = '11122233305';
    private const VEHICLE_PLATE = 'QACI01';
    private const SERVICE_TYPE_ID = 9101;
    private const SERVICE_CODE = 'ELECTRICAL_DIAG';
    private const SERVICE_NAME = 'Diagnostico Eletrico';
    private const TOW_EMAIL = 'qa.cobertura.integracao.tow@guinchafacil.com';
    private const TOW_NAME = 'Guincho QA Integracao';
    private const TOW_PHONE = '21999994002';
    private const TOW_CPF = '10987654305';
    private const TOW_LAT = -22.999862;
    private const TOW_LNG = -43.364890;
    private const ONLY_REBOQUE_LAT = -23.001100;
    private const ONLY_REBOQUE_LNG = -43.366000;
    private const NO_COVERAGE_LAT = -3.101900;
    private const NO_COVERAGE_LNG = -60.025000;

    protected function setUp(): void
    {
        $this->trace('setUp: cleaning database and preparing schema');
        $pdo = getPDO();

        $this->ensureSchema();
        $this->ensureColumn('pedidos', 'attendance_mode', 'TEXT');
        $this->ensureColumn('pedidos', 'service_type_id', 'INTEGER');
        $this->ensureColumn('pedidos', 'incidente_id', 'INTEGER');
        $this->ensureColumn('guinchos', 'cidade_id', 'INTEGER');
        $this->ensureColumn('guinchos', 'cnh_numero', 'TEXT');
        $this->ensureColumn('guinchos', 'cnh_validade', 'TEXT');
        $this->ensureColumn('guinchos', 'cidade_placa', 'TEXT');
        $this->ensureColumn('guinchos', 'uf_placa', 'TEXT');
        $this->ensureColumn('guinchos', 'capacidade_ton', 'REAL');
        $this->ensureColumn('guinchos', 'raio_cobertura_km', 'INTEGER DEFAULT 20');
        $this->ensureColumn('guinchos', 'chave_pix', 'TEXT');
        $this->ensureColumn('guinchos', 'chave_pix_tipo', 'TEXT');
        $this->ensureColumn('guinchos', 'lat_operacao', 'REAL');
        $this->ensureColumn('guinchos', 'lng_operacao', 'REAL');
        $this->ensureColumn('guinchos', 'foto_veiculo', 'TEXT');
        $this->ensureColumn('guinchos', 'doc_cnh_frente', 'TEXT');
        $this->ensureColumn('guinchos', 'doc_cnh_verso', 'TEXT');
        $this->ensureColumn('guinchos', 'oferece_reboque', 'INTEGER DEFAULT 0');
        $this->ensureColumn('guinchos', 'reboque_aprovado', 'INTEGER DEFAULT 0');
        $this->ensureColumn('guinchos', 'lat_atual', 'REAL');
        $this->ensureColumn('guinchos', 'lng_atual', 'REAL');

        foreach ([
            'pagamentos',
            'pedidos',
            'veiculos',
            'guinchos',
            'usuarios',
            'servicos_especialista',
            'especialista_servicos',
            'especialistas',
            'service_types',
            'configuracoes',
        ] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable $e) {
                $this->trace("setUp: could not clean {$table}: " . $e->getMessage());
            }
        }

        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");
        $pdo->exec("INSERT OR IGNORE INTO configuracoes (chave, valor) VALUES ('raio_maximo_km', '50')");

        $this->trace('setUp: seeding client, vehicle, service catalog and tow provider');
        $colsStmt = $pdo->query('PRAGMA table_info(guinchos)');
        $cols = $colsStmt ? array_map(static fn(array $row): string => (string)($row['name'] ?? ''), $colsStmt->fetchAll(PDO::FETCH_ASSOC)) : [];
        $this->trace('setUp: guinchos columns=' . implode(',', $cols));
        $this->seedBaseData();
    }

    public function testBloqueiaQuandoHaSomenteReboque(): void
    {
        $this->trace('scenario: somente_reboque');
        $pedidoId = $this->criarPedido('Seed cobertura somente_reboque', self::ONLY_REBOQUE_LAT, self::ONLY_REBOQUE_LNG);
        $result = $this->aprovarComDebug($pedidoId, 'mp-test-somente-reboque', 'scenario somente_reboque');

        $this->assertFalse($result['ok'], $result['erro'] ?? 'expected block');
        $this->assertStringContainsString('cobertura de reboque', (string)($result['erro'] ?? ''));

        $pedido = getPDO()->query("SELECT status, incidente_id FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $pagamento = getPDO()->query("SELECT status, id_externo FROM pagamentos WHERE pedido_id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);

        $this->trace('scenario: somente_reboque result=' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame('aguardando_pagamento', (string)($pedido['status'] ?? ''));
        $this->assertNull($pagamento['id_externo'] ?? null);
        $this->assertSame('pendente', (string)($pagamento['status'] ?? ''));
    }

    public function testBloqueiaQuandoNaoHaCobertura(): void
    {
        $this->trace('scenario: sem_cobertura');
        $pedidoId = $this->criarPedido('Seed cobertura sem_cobertura', self::NO_COVERAGE_LAT, self::NO_COVERAGE_LNG);
        $result = $this->aprovarComDebug($pedidoId, 'mp-test-sem-cobertura', 'scenario sem_cobertura');

        $this->assertFalse($result['ok'], $result['erro'] ?? 'expected block');
        $this->assertStringContainsString('não há cobertura', mb_strtolower((string)($result['erro'] ?? '')));

        $pedido = getPDO()->query("SELECT status, incidente_id FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $pagamento = getPDO()->query("SELECT status, id_externo FROM pagamentos WHERE pedido_id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);

        $this->trace('scenario: sem_cobertura result=' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame('aguardando_pagamento', (string)($pedido['status'] ?? ''));
        $this->assertNull($pagamento['id_externo'] ?? null);
        $this->assertSame('pendente', (string)($pagamento['status'] ?? ''));
    }

    public function testAprovaQuandoHaEspecialistaDisponivel(): void
    {
        $this->trace('scenario: cobertura_ok');
        $especialistaId = $this->seedEspecialistaDisponivel();
        $pedidoId = $this->criarPedido('Seed cobertura ok', -22.999800, -43.364800);
        $result = $this->aprovarComDebug($pedidoId, 'mp-test-cobertura-ok', 'scenario cobertura ok');

        $this->assertTrue($result['ok'], $result['erro'] ?? 'expected approval');
        $this->assertNull($result['erro']);

        $pedido = getPDO()->query("SELECT status, incidente_id FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $pagamento = getPDO()->query("SELECT status, id_externo FROM pagamentos WHERE pedido_id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);

        $this->trace('scenario: cobertura_ok especialista=' . $especialistaId . ' result=' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame('aguardando_guincho', (string)($pedido['status'] ?? ''));
        $this->assertSame('aprovado', (string)($pagamento['status'] ?? ''));
        $this->assertSame('mp-test-cobertura-ok', (string)($pagamento['id_externo'] ?? ''));
    }

    public function testIncidenteIdPulaOGateDeCobertura(): void
    {
        $this->trace('scenario: incidente_id_pula_gate');
        $pedidoId = $this->criarPedido('Seed cobertura incidente', self::NO_COVERAGE_LAT, self::NO_COVERAGE_LNG, 7001);
        $result = $this->aprovarComDebug($pedidoId, 'mp-test-incidente', 'scenario incidente');

        $this->assertTrue($result['ok'], $result['erro'] ?? 'expected approval');
        $this->assertNull($result['erro']);

        $pedido = getPDO()->query("SELECT status, incidente_id FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $pagamento = getPDO()->query("SELECT status, id_externo FROM pagamentos WHERE pedido_id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);

        $this->trace('scenario: incidente_id_pula_gate result=' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame('aguardando_guincho', (string)($pedido['status'] ?? ''));
        $this->assertSame(7001, (int)($pedido['incidente_id'] ?? 0));
        $this->assertSame('aprovado', (string)($pagamento['status'] ?? ''));
    }

    private function seedBaseData(): void
    {
        $pdo = getPDO();
        $this->trace('seedBaseData: inserting cliente QA fixo');

        $pdo->prepare('INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo, ativo)
                       VALUES (?,?,?,?,?,?,?,1)')
            ->execute([
                9101,
                self::CLIENT_NAME,
                self::CLIENT_EMAIL,
                password_hash('test123', PASSWORD_BCRYPT),
                self::CLIENT_PHONE,
                self::CLIENT_CPF,
                'cliente',
            ]);

        $this->trace('seedBaseData: inserting veiculo QA fixo');
        $pdo->prepare('INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo, ativo, criado_em)
                       VALUES (?,?,?,?,?,?,?,?,1,NOW())')
            ->execute([
                9101,
                9101,
                self::VEHICLE_PLATE,
                'Volkswagen',
                'Gol',
                2020,
                'Prata',
                'carro',
            ]);

        $this->trace('seedBaseData: inserting catalogo de servico e servico do especialista');
        $pdo->prepare("INSERT INTO service_types (id, code, name, attendance_mode, active, created_at, updated_at)
                       VALUES (?, ?, ?, 'ON_SITE', 1, NOW(), NOW())")
            ->execute([self::SERVICE_TYPE_ID, self::SERVICE_CODE, self::SERVICE_NAME]);
        $pdo->prepare("INSERT INTO servicos_especialista (id, codigo, nome, ativo)
                       VALUES (?, ?, ?, 1)")
            ->execute([self::SERVICE_TYPE_ID, self::SERVICE_CODE, self::SERVICE_NAME]);

        $this->trace('seedBaseData: inserting guincho QA fixo');
        $towUserId = 9102;
        $pdo->prepare('INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo, ativo)
                       VALUES (?,?,?,?,?,?,?,1)')
            ->execute([
                $towUserId,
                self::TOW_NAME,
                self::TOW_EMAIL,
                password_hash('test123', PASSWORD_BCRYPT),
                self::TOW_PHONE,
                self::TOW_CPF,
                'guincho',
            ]);

        $guinchoId = Guincho::criarDeRegistro($towUserId, [
            'cnh_numero' => '33344455568',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
            'placa_guincho' => 'QATI01',
            'cidade_placa' => 'Rio de Janeiro',
            'uf_placa' => 'RJ',
            'capacidade_ton' => 8.0,
            'raio_cobertura_km' => 25,
            'chave_pix' => 'qa-cobertura-integracao',
            'chave_pix_tipo' => 'email',
            'lat_operacao' => self::TOW_LAT,
            'lng_operacao' => self::TOW_LNG,
            'foto_veiculo' => null,
            'doc_cnh_frente' => null,
            'doc_cnh_verso' => null,
        ]);
        if (!$guinchoId) {
            throw new RuntimeException('Falha ao criar guincho QA de integracao.');
        }
        $pdo->prepare('UPDATE guinchos SET id = ? WHERE rowid = ?')
            ->execute([(int)$guinchoId, (int)$guinchoId]);
        Guincho::solicitarReboque((int)$guinchoId, [
            'placa_guincho' => 'QATI01',
            'cidade_placa' => 'Rio de Janeiro',
            'uf_placa' => 'RJ',
            'capacidade_ton' => 8.0,
            'cnh_numero' => '33344455568',
            'cnh_validade' => date('Y-m-d', strtotime('+5 years')),
        ]);
        Guincho::aprovar((int)$guinchoId);
        Guincho::atualizarLocalizacao((int)$guinchoId, self::TOW_LAT, self::TOW_LNG);
        Guincho::atualizarDisponibilidade((int)$guinchoId, true);
        try {
            $pdo->prepare('UPDATE guinchos
                              SET aprovado = 1,
                                  disponivel = 1,
                                  reboque_aprovado = 1,
                                  oferece_reboque = 1,
                                  lat_atual = ?,
                                  lng_atual = ?
                            WHERE id = ?')
                ->execute([self::TOW_LAT, self::TOW_LNG, (int)$guinchoId]);
        } catch (Throwable $e) {
            $this->trace('setUp: tow SQL hardening skipped: ' . $e->getMessage());
        }
        $snapTowStmt = $pdo->query('SELECT aprovado, disponivel, reboque_aprovado, lat_atual, lng_atual, raio_cobertura_km
                                      FROM guinchos WHERE id = ' . (int)$guinchoId);
        $snapTow = $snapTowStmt ? $snapTowStmt->fetch(PDO::FETCH_ASSOC) : [];
        $this->trace('seedBaseData: tow snapshot=' . json_encode($snapTow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->trace('seedBaseData: tow guincho pronto id=' . $guinchoId);
    }

    private function seedEspecialistaDisponivel(): int
    {
        $pdo = getPDO();
        $this->trace('seedEspecialistaDisponivel: inserting especialista QA');
        $pdo->prepare('INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo, ativo)
                       VALUES (?,?,?,?,?,?,?,1)')
            ->execute([
                9201,
                'Especialista QA Integracao',
                'qa.cobertura.integracao.esp@guinchafacil.com',
                password_hash('test123', PASSWORD_BCRYPT),
                '21999994003',
                '10987654306',
                'guincho',
            ]);

        $pdo->prepare('INSERT INTO especialistas
                        (id, usuario_id, nome_profissional, cpf_cnpj, documento_tipo, documento_numero,
                         chave_pix, chave_pix_tipo, bio, raio_atendimento_km, lat_atual, lng_atual,
                         aprovado, disponivel, reputacao, criado_em, atualizado_em)
                       VALUES
                        (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([
                9201,
                9201,
                'Especialista QA Integracao',
                '10987654306',
                'CPF',
                '10987654306',
                'qa-especialista-integracao',
                'email',
                'QA especialista',
                20,
                -22.999800,
                -43.364800,
                1,
                1,
                5.0,
            ]);

        $pdo->prepare('INSERT INTO especialista_servicos (especialista_id, servico_id, habilitado, created_at, updated_at)
                       VALUES (?,?,1,NOW(),NOW())')
            ->execute([9201, self::SERVICE_TYPE_ID]);

        $this->trace('seedEspecialistaDisponivel: especialista pronto id=9201');
        return 9201;
    }

    private function criarPedido(string $descricao, float $lat, float $lng, ?int $incidenteId = null): int
    {
        $this->trace('criarPedido: start descricao=' . $descricao . ' lat=' . $lat . ' lng=' . $lng . ' incidente=' . (string)($incidenteId ?? 'null'));
        $pedidoId = Pedido::criar([
            'cliente_id' => 9101,
            'veiculo_id' => 9101,
            'tipo_problema' => 'QA Cobertura',
            'descricao_problema' => $descricao,
            'lat_origem' => $lat,
            'lng_origem' => $lng,
            'endereco_origem' => $descricao,
            'lat_destino' => $lat,
            'lng_destino' => $lng,
            'endereco_destino' => $descricao,
            'distancia_km' => 0.0,
            'custo_estimado' => 120.0,
            'status' => 'aguardando_pagamento',
            'raio_atual_km' => 10,
            'score_minimo_atual' => 0.5,
        ]);
        if (!$pedidoId) {
            throw new RuntimeException('Falha ao criar pedido QA.');
        }

        $pdo = getPDO();
        $params = [self::SERVICE_TYPE_ID, $pedidoId];
        $sql = "UPDATE pedidos SET attendance_mode='ON_SITE', service_type_id=?, guincho_id=NULL";
        if ($incidenteId !== null) {
            $sql .= ', incidente_id=?';
            $params = [self::SERVICE_TYPE_ID, $incidenteId, $pedidoId];
        }
        $sql .= ' WHERE id=?';
        $pdo->prepare($sql)->execute($params);

        $pagamentoId = Pagamento::criar((int)$pedidoId, 'mercadopago', 120.0, 0, 0);
        if (!$pagamentoId) {
            throw new RuntimeException('Falha ao criar pagamento QA.');
        }
        $this->trace('criarPedido: pedido=' . $pedidoId . ' pagamento=' . $pagamentoId);

        return (int)$pedidoId;
    }

    private function aprovarComDebug(int $pedidoId, string $idExterno, string $label): array
    {
        $this->trace("aprovar start {$label}: pedido={$pedidoId} id_externo={$idExterno}");
        $pedidoAntes = Pedido::buscarPorId($pedidoId);
        $diagAntes = CoberturaService::diagnosticarAtendimento($pedidoAntes ?: []);
        $this->trace('diagnostico before=' . json_encode($diagAntes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $result = PagamentoAprovacaoService::aprovar($pedidoId, $idExterno, '{}', 'integration-test');
            $this->trace('aprovar result=' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $result;
        } catch (Throwable $e) {
            $this->trace('aprovar exception=' . $e->getMessage());
            throw $e;
        }
    }

    private function ensureSchema(): void
    {
        $this->ensureColumn('pedidos', 'attendance_mode', 'TEXT');
        $this->ensureColumn('pedidos', 'service_type_id', 'INTEGER');
        $this->ensureColumn('pedidos', 'incidente_id', 'INTEGER');

        $pdo = getPDO();
        $pdo->exec('CREATE TABLE IF NOT EXISTS especialistas (
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS servicos_especialista (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo TEXT NOT NULL UNIQUE,
            nome TEXT,
            ativo INTEGER NOT NULL DEFAULT 1
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS especialista_servicos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            especialista_id INTEGER NOT NULL,
            servico_id INTEGER NOT NULL,
            habilitado INTEGER NOT NULL DEFAULT 1,
            created_at TEXT,
            updated_at TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS incidentes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            veiculo_id INTEGER NOT NULL,
            tipo_problema TEXT NOT NULL,
            descricao_problema TEXT,
            lat_origem REAL,
            lng_origem REAL,
            endereco_origem TEXT,
            status TEXT NOT NULL DEFAULT "aberto",
            resolucao_tipo TEXT,
            criado_em TEXT,
            atualizado_em TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS atendimentos_especialista (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            incidente_id INTEGER NOT NULL,
            especialista_id INTEGER NOT NULL,
            servico_solicitado_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "ofertado",
            ofertado_em TEXT,
            expiracao_oferta TEXT,
            provider_amount REAL NOT NULL DEFAULT 0,
            platform_amount REAL NOT NULL DEFAULT 0,
            customer_amount REAL NOT NULL DEFAULT 0,
            criado_em TEXT,
            atualizado_em TEXT
        )');
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $pdo = getPDO();
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($cols as $col) {
            if ((string)($col['name'] ?? '') === $column) {
                return;
            }
        }

        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    private function trace(string $message): void
    {
        fwrite(STDERR, '[CoberturaTest] ' . $message . PHP_EOL);
    }
}
