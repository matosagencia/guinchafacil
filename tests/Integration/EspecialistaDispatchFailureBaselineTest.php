<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';

final class EspecialistaDispatchFailureBaselineTest extends TestCase
{
    private const NULL_SERVICE_CODE = 'TEST_DISPATCH_NULL';
    private const THROW_SERVICE_CODE = 'TEST_DISPATCH_THROW';

    protected function setUp(): void
    {
        $pdo = getPDO();

        try {
            $pdo->exec('ALTER TABLE pedidos ADD COLUMN incidente_id INTEGER');
        } catch (Throwable) {
            // A coluna já existe quando a suíte compartilha o mesmo banco.
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS incidentes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            veiculo_id INTEGER NOT NULL,
            tipo_problema TEXT NOT NULL,
            descricao_problema TEXT,
            lat_origem REAL NOT NULL,
            lng_origem REAL NOT NULL,
            endereco_origem TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'aberto\',
            resolucao_tipo TEXT,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS especialistas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            aprovado INTEGER NOT NULL DEFAULT 0,
            disponivel INTEGER NOT NULL DEFAULT 0,
            lat_atual REAL,
            lng_atual REAL,
            raio_atendimento_km REAL NOT NULL DEFAULT 10,
            reputacao REAL NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS servicos_especialista (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo TEXT NOT NULL UNIQUE,
            nome TEXT NOT NULL,
            categoria TEXT NOT NULL,
            tipo_cobranca TEXT NOT NULL,
            ativo INTEGER NOT NULL DEFAULT 1
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS especialista_servicos (
            especialista_id INTEGER NOT NULL,
            servico_id INTEGER NOT NULL,
            habilitado INTEGER NOT NULL DEFAULT 1,
            PRIMARY KEY (especialista_id, servico_id)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS atendimentos_especialista (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            incidente_id INTEGER NOT NULL,
            especialista_id INTEGER,
            servico_solicitado_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            ofertado_em TEXT,
            expiracao_oferta TEXT,
            provider_amount REAL NOT NULL DEFAULT 0,
            platform_amount REAL NOT NULL DEFAULT 0,
            customer_amount REAL NOT NULL DEFAULT 0
        )');

        foreach (['incidentes', 'especialistas', 'especialista_servicos', 'servicos_especialista', 'atendimentos_especialista'] as $table) {
            $pdo->exec("DELETE FROM {$table}");
        }
        $pdo->exec('DELETE FROM app_logs WHERE pedido_id IN (601, 602)');
        $pdo->exec('DELETE FROM service_types WHERE code IN (\'TEST_DISPATCH_NULL\', \'TEST_DISPATCH_THROW\')');

        $pdo->exec("INSERT INTO service_types (code, name, attendance_mode, active)
                    VALUES ('TEST_DISPATCH_NULL', 'Teste despacho nulo', 'ON_SITE', 1)");
        $pdo->exec("INSERT INTO service_types (code, name, attendance_mode, active)
                    VALUES ('TEST_DISPATCH_THROW', 'Teste despacho excecao', 'ON_SITE', 1)");

        foreach ([
            'comissao_plataforma' => '0.15',
            'reserva_gateway_percentual' => '0',
            'tempo_expiracao_min' => '5',
            'raio_inicial_km' => '10',
        ] as $key => $value) {
            $pdo->prepare('INSERT OR REPLACE INTO configuracoes (chave, valor) VALUES (?, ?)')->execute([$key, $value]);
        }

        $pdo->exec("INSERT OR IGNORE INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo)
                    VALUES (1, 'Cliente Dispatch Teste', 'dispatch-client@example.com', 'hash', '11999999991', '11111111191', 'cliente')");
        $pdo->exec("INSERT OR IGNORE INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo)
                    VALUES (2, 'Especialista Dispatch Teste', 'dispatch-specialist@example.com', 'hash', '11999999992', '22222222292', 'especialista')");
        $pdo->exec("INSERT OR IGNORE INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo)
                    VALUES (1, 1, 'DSP1A23', 'Teste', 'Dispatch', 2024, 'Prata', 'carro')");
    }

    protected function tearDown(): void
    {
        getPDO()->exec('DROP TRIGGER IF EXISTS fail_especialista_dispatch_insert');
    }

    public function testBaselineRetornoNuloMantemPagamentoAprovadoEIncidentePreso(): void
    {
        $pdo = getPDO();
        $serviceTypeId = (int)$pdo->query("SELECT id FROM service_types WHERE code = 'TEST_DISPATCH_NULL'")->fetchColumn();
        $this->criarPedidoEPagamento(601, $serviceTypeId);

        $result = PedidoTransitionService::approvePayment(601, 'baseline-null-601', '{}');

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertSame('aprovado', $pdo->query('SELECT status FROM pagamentos WHERE pedido_id = 601')->fetchColumn());
        $incidentStatus = $pdo->query('SELECT i.status FROM incidentes i JOIN pedidos p ON p.incidente_id=i.id WHERE p.id=601')->fetchColumn();
        $this->assertSame('procurando_especialista', $incidentStatus);
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM atendimentos_especialista')->fetchColumn());
    }

    public function testBaselineExcecaoMantemPagamentoAprovadoEIncidentePreso(): void
    {
        $pdo = getPDO();
        $serviceTypeId = (int)$pdo->query("SELECT id FROM service_types WHERE code = 'TEST_DISPATCH_THROW'")->fetchColumn();
        $servicoId = $this->criarServicoEspecialista(self::THROW_SERVICE_CODE);

        $pdo->exec("INSERT INTO especialistas (id, usuario_id, aprovado, disponivel, lat_atual, lng_atual, raio_atendimento_km, reputacao)
                    VALUES (701, 2, 1, 1, -23.5501, -46.6301, 10, 1)");
        $pdo->prepare('INSERT INTO especialista_servicos (especialista_id, servico_id) VALUES (?, ?)')->execute([701, $servicoId]);
        $pdo->exec("UPDATE service_types SET code = 'TEST_DISPATCH_THROW' WHERE id = {$serviceTypeId}");
        $this->criarPedidoEPagamento(602, $serviceTypeId);
        $pdo->exec("CREATE TRIGGER fail_especialista_dispatch_insert
                    BEFORE INSERT ON atendimentos_especialista
                    BEGIN SELECT RAISE(ABORT, 'falha de despacho simulada'); END");

        $result = PedidoTransitionService::approvePayment(602, 'baseline-throw-602', '{}');

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertSame('aprovado', $pdo->query('SELECT status FROM pagamentos WHERE pedido_id = 602')->fetchColumn());
        $incidentStatus = $pdo->query('SELECT i.status FROM incidentes i JOIN pedidos p ON p.incidente_id=i.id WHERE p.id=602')->fetchColumn();
        $this->assertSame('procurando_especialista', $incidentStatus);
        $this->assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM atendimentos_especialista')->fetchColumn());
    }

    private function criarPedidoEPagamento(int $pedidoId, int $serviceTypeId): void
    {
        $pdo = getPDO();
        $pdo->prepare('INSERT INTO pedidos
            (id, status, custo_estimado, cliente_id, veiculo_id, tipo_problema, descricao_problema,
             lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
             service_type_id, attendance_mode)
            VALUES (?, \'aguardando_pagamento\', 100, 1, 1, \'bateria\', \'Falha de bateria\',
                    -23.55, -46.63, \'Origem\', -23.56, -46.64, \'Destino\', ?, \'ON_SITE\')')
            ->execute([$pedidoId, $serviceTypeId]);
        $pdo->prepare('INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES (?, \'mercadopago\', \'pendente\', 100)')
            ->execute([$pedidoId]);
    }

    private function criarServicoEspecialista(string $codigo): int
    {
        $pdo = getPDO();
        $pdo->prepare('INSERT INTO servicos_especialista (codigo, nome, categoria, tipo_cobranca) VALUES (?, ?, ?, ?)')
            ->execute([$codigo, 'Serviço de teste', 'bateria', 'fixo']);
        return (int)$pdo->lastInsertId();
    }
}
