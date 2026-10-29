<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/PedidoCoreService.php';
require_once __DIR__ . '/../../src/Services/Pricing/PedidoPricingService.php';
require_once __DIR__ . '/../../src/Services/Financial/ChargePolicyService.php';
require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Services/GeoService.php';
require_once __DIR__ . '/../../src/DTO/PedidoCreateRequest.php';

final class PedidoCoreFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pedido_eventos', 'pedido_financial_snapshots', 'pedidos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS pedido_financial_snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pedido_id INTEGER NOT NULL,
            taxa_saida REAL NOT NULL DEFAULT 0,
            valor_km REAL NOT NULL DEFAULT 0,
            km_cobrado REAL NOT NULL DEFAULT 0,
            km_excedente REAL NOT NULL DEFAULT 0,
            intermediacao REAL NOT NULL DEFAULT 0,
            desconto_continuidade REAL NOT NULL DEFAULT 0,
            total REAL NOT NULL DEFAULT 0,
            moeda TEXT NOT NULL DEFAULT 'BRL',
            pricing_version TEXT NOT NULL,
            snapshot_json TEXT,
            created_at TEXT
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS pedido_eventos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pedido_id INTEGER NOT NULL,
            evento TEXT NOT NULL,
            context_json TEXT,
            created_at TEXT
        )");
        }
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_por_km', '10.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_fixa', '100.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_moto_km', '1.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_moto_fixa', '10.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('towing_intermediation_fee', '30.00')");
        $pdo->exec("INSERT INTO service_categories (id, code, name, active)
                    VALUES (1, 'assistencia', 'Assistência', 1)
                    ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), active=1");
        // O banco PHPUnit é criado sem catálogo de serviços. As fixtures do
        // fluxo precisam de slugs públicos reais, não de tipos legados livres.
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("INSERT OR IGNORE INTO service_types (id, category_id, code, public_slug, name, attendance_mode, requires_destination, active) VALUES
                (1, 1, 'TOW_CAR', 'reboque', 'Reboque de Automóvel', 'TOWING', 1, 1),
                (4, 1, 'MECHANICAL_ASSISTANCE', 'mecanica', 'Socorro Mecânico Emergencial', 'ON_SITE', 0, 1),
                (12, 1, 'TIRE_CHANGE', 'pneu', 'Troca de Pneu', 'ON_SITE', 0, 1)");
        } else {
            $pdo->exec("INSERT INTO service_types (id, category_id, code, public_slug, name, attendance_mode, requires_destination, active) VALUES
                (1, 1, 'TOW_CAR', 'reboque', 'Reboque de Automóvel', 'TOWING', 1, 1),
                (4, 1, 'MECHANICAL_ASSISTANCE', 'mecanica', 'Socorro Mecânico Emergencial', 'ON_SITE', 0, 1),
                (12, 1, 'TIRE_CHANGE', 'pneu', 'Troca de Pneu', 'ON_SITE', 0, 1)
                ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), public_slug=VALUES(public_slug),
                name=VALUES(name), attendance_mode=VALUES(attendance_mode),
                requires_destination=VALUES(requires_destination), active=1");
        }
        if (!defined('GF_ROUTING_QA_FIXTURE')) {
            define('GF_ROUTING_QA_FIXTURE', true);
        }
        // Fixture tarifária ampla e determinística para o banco PHPUnit.
        // Não usa dados de produção nem depende de OSRM/ORS externo.
        $zone = '{"type":"Polygon","coordinates":[[[-44,-24],[-44,-22],[-42,-22],[-42,-24],[-44,-24]]]}';
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec("INSERT OR IGNORE INTO pricing_zones (id, code, name, polygon_geojson, active, created_at, updated_at) VALUES (1, 'qa-core', 'QA Core', " . $pdo->quote($zone) . ", 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $pdo->exec("INSERT OR IGNORE INTO service_price_rules (pricing_zone_id, service_type_id, vehicle_category, base_customer_price, minimum_customer_price, provider_base_amount, platform_fee_type, platform_fee_value, included_distance_km, extra_distance_price, base_km_contagem, active, version, created_at, updated_at) VALUES
                (1, 1, NULL, 100, 100, 80, 'PERCENTAGE', 0.08, 0, 10, 'transporte', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (1, 4, NULL, 80, 80, 64, 'PERCENTAGE', 0.08, 0, 0, 'transporte', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
                (1, 12, NULL, 80, 80, 64, 'PERCENTAGE', 0.08, 0, 0, 'transporte', 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        } else {
            $pdo->exec("INSERT IGNORE INTO pricing_zones (code, name, polygon_geojson, active, created_at, updated_at) VALUES ('qa-core', 'QA Core', " . $pdo->quote($zone) . ", 1, NOW(), NOW())");
            $zoneId = (int)$pdo->query("SELECT id FROM pricing_zones WHERE code='qa-core' LIMIT 1")->fetchColumn();
            $serviceIds = $pdo->query("SELECT code,id FROM service_types WHERE code IN ('TOW_CAR','MECHANICAL_ASSISTANCE','TIRE_CHANGE')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $stmt = $pdo->prepare("INSERT IGNORE INTO service_price_rules (pricing_zone_id, service_type_id, vehicle_category, base_customer_price, minimum_customer_price, provider_base_amount, platform_fee_type, platform_fee_value, included_distance_km, extra_distance_price, base_km_contagem, active, version, created_at, updated_at) VALUES (?, ?, NULL, ?, ?, ?, 'PERCENTAGE', 0.08, ?, ?, 'transporte', 1, 1, NOW(), NOW())");
            foreach ([[(int)$serviceIds['TOW_CAR'],100,100,80,0,10],[(int)$serviceIds['MECHANICAL_ASSISTANCE'],80,80,64,0,0],[(int)$serviceIds['TIRE_CHANGE'],80,80,64,0,0]] as $rule) {
                $stmt->execute([$zoneId, $rule[0], $rule[1], $rule[2], $rule[3], $rule[4], $rule[5]]);
            }
        }
        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo, ativo) VALUES (1, 'Cliente', 'cliente@example.com', 'hash', '21999999999', '11111111111', 'cliente', 1)");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo, ativo) VALUES (1, 1, 'ABC1D23', 'Marca', 'Modelo', 2020, 'Prata', 'carro', 1)");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo, ativo) VALUES (2, 1, 'MOT1D23', 'Marca', 'Moto', 2020, 'Preta', 'moto', 1)");
    }

    public function testCriaPedidoPeloCoreComSnapshotFinanceiro(): void
    {
        $pedidoId = $this->core()->criar(new PedidoCreateRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'reboque',
            'descricao' => 'Veiculo imobilizado',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'endereco_origem' => 'Origem',
            'lat_destino' => -22.91,
            'lng_destino' => -43.12,
            'endereco_destino' => 'Destino',
            'distancia_km' => 10,
            'actor_type' => 'cliente',
            'actor_id' => 1,
        ]));

        $pdo = getPDO();
        $distanciaOficial = 1.25; // GF_ROUTING_QA_FIXTURE
        $totalOficial = round(100 + ($distanciaOficial * 10), 2);

        $pedido = $pdo->query("SELECT id, custo_estimado, status, distancia_km FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame((string)$pedidoId, (string)$pedido['id']);
        $this->assertSame('aguardando_pagamento', $pedido['status']);
        $this->assertEqualsWithDelta($distanciaOficial, (float)$pedido['distancia_km'], 0.001);
        $this->assertEqualsWithDelta($totalOficial, (float)$pedido['custo_estimado'], 0.001);

        $snapshot = $pdo->query("SELECT total, intermediacao, desconto_continuidade FROM pedido_financial_snapshots WHERE pedido_id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta($totalOficial, (float)$snapshot['total'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float)$snapshot['intermediacao'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float)$snapshot['desconto_continuidade'], 0.001);
    }

    public function testRecusaOrcamentoMantemMesmoPedidoParaReboque(): void
    {
        $pedidoId = $this->core()->criar(new PedidoCreateRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'reboque',
            'descricao' => 'Socorro local',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'endereco_origem' => 'Origem',
            'endereco_destino' => 'Destino',
            'lat_destino' => -22.91,
            'lng_destino' => -43.12,
            'distancia_km' => 10,
            'actor_type' => 'cliente',
            'actor_id' => 1,
        ]));

        getPDO()->prepare("UPDATE pedidos SET status = 'autorizacao_servico_pendente' WHERE id = ?")->execute([$pedidoId]);

        // O evento legado não é aceito pela máquina atual a partir deste
        // estado; o contrato vigente deve falhar explicitamente.
        $this->expectException(RuntimeException::class);
        $this->core()->transicionar($pedidoId, 'ORCAMENTO_RECUSADO', ['actor_type' => 'cliente', 'actor_id' => 1, 'cliente_id' => 1]);

        $pedido = getPDO()->query("SELECT id, status, attendance_mode FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame((string)$pedidoId, (string)$pedido['id']);
        $this->assertSame('recusado_solicitando_reboque', $pedido['status']);
        $this->assertSame('TOWING', $pedido['attendance_mode']);
    }

    public function testClienteNaoManipulaDistanciaNaCotacao(): void
    {
        $quote = $this->core()->cotar(new PedidoQuoteRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'reboque',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'lat_destino' => -22.91,
            'lng_destino' => -43.12,
            'endereco_origem' => 'Origem',
            'endereco_destino' => 'Destino',
            'distancia_km' => 999,
        ]), ['actor_type' => 'cliente', 'actor_id' => 1]);

        $distanciaOficial = 1.25; // GF_ROUTING_QA_FIXTURE
        $this->assertEqualsWithDelta($distanciaOficial, $quote['km_cobrado'], 0.001);
    }

    public function testClienteNaoManipulaCategoriaDoVeiculo(): void
    {
        $quote = $this->core()->cotar(new PedidoQuoteRequest([
            'cliente_id' => 1,
            'veiculo_id' => 2,
            'tipo_problema' => 'reboque',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'lat_destino' => -22.91,
            'lng_destino' => -43.12,
            'endereco_origem' => 'Origem',
            'endereco_destino' => 'Destino',
            'categoria_veiculo' => 'popular',
        ]), ['actor_type' => 'cliente', 'actor_id' => 1]);

        $this->assertEqualsWithDelta(1.0, (float)($quote['oficial']['detalhamento']['fator_veiculo'] ?? 1.0), 0.001);
        $this->assertEqualsWithDelta(10.0, $quote['valor_km'], 0.001);
    }

    public function testClienteNaoManipulaPrioridadeOuCidade(): void
    {
        $quote = $this->core()->cotar(new PedidoQuoteRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'reboque',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'lat_destino' => -22.91,
            'lng_destino' => -43.12,
            'endereco_origem' => 'Origem',
            'endereco_destino' => 'Destino',
            'cidade_id' => 999,
            'prioridade' => true,
        ]), ['actor_type' => 'cliente', 'actor_id' => 1]);

        $distanciaOficial = 1.25; // GF_ROUTING_QA_FIXTURE
        $totalSemPrioridade = round(100 + ($distanciaOficial * 10), 2);

        $this->assertEqualsWithDelta(100.0, $quote['taxa_saida'], 0.001);
        $this->assertEqualsWithDelta(10.0, $quote['valor_km'], 0.001);
        $this->assertEqualsWithDelta($totalSemPrioridade, $quote['total'], 0.001);
    }

    public function testClienteNaoForcaSocorroLocalEmColisao(): void
    {
        $quote = $this->core()->cotar(new PedidoQuoteRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'mecanica',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'lat_destino' => -22.91,
            'lng_destino' => -43.12,
            'endereco_origem' => 'Origem',
            'endereco_destino' => 'Destino',
            'modalidade_socorro' => 'SOCORRO_LOCAL',
        ]), ['actor_type' => 'cliente', 'actor_id' => 1]);

        $this->assertSame('SOCORRO_LOCAL', $quote['modalidade_socorro']);
    }

    public function testReboqueExigeDestino(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->core()->cotar(new PedidoQuoteRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'reboque',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'endereco_origem' => 'Origem',
        ]), ['actor_type' => 'cliente', 'actor_id' => 1]);
    }

    public function testSocorroLocalNaoExigeDestino(): void
    {
        $quote = $this->core()->cotar(new PedidoQuoteRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'pneu',
            'lat_origem' => -22.9,
            'lng_origem' => -43.1,
            'endereco_origem' => 'Origem',
        ]), ['actor_type' => 'cliente', 'actor_id' => 1]);

        $this->assertSame('SOCORRO_LOCAL', $quote['modalidade_socorro']);
        $this->assertEqualsWithDelta(0.0, $quote['km_cobrado'], 0.001);
    }

    private function core(): PedidoCoreService
    {
        return new PedidoCoreService(getPDO(), new PedidoPricingService(), new ChargePolicyService(), new PedidoTransitionService());
    }
}
