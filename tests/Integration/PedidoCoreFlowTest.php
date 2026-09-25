<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/PedidoCoreService.php';
require_once __DIR__ . '/../../src/Services/Pricing/PedidoPricingService.php';
require_once __DIR__ . '/../../src/Services/Financial/ChargePolicyService.php';
require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
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
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_por_km', '10.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_fixa', '100.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('towing_intermediation_fee', '30.00')");
        $pdo->exec("INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo, ativo) VALUES (1, 'Cliente', 'cliente@example.com', 'hash', '21999999999', '11111111111', 'cliente', 1)");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo, ativo) VALUES (1, 1, 'ABC1D23', 'Marca', 'Modelo', 2020, 'Prata', 'carro', 1)");
    }

    public function testCriaPedidoPeloCoreComSnapshotFinanceiro(): void
    {
        $pedidoId = $this->core()->criar(new PedidoCreateRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'roda travada',
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
        $pedido = $pdo->query("SELECT id, custo_estimado, status FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame((string)$pedidoId, (string)$pedido['id']);
        $this->assertSame('aguardando_pagamento', $pedido['status']);
        $this->assertEqualsWithDelta(230.0, (float)$pedido['custo_estimado'], 0.001);

        $snapshot = $pdo->query("SELECT total, intermediacao, desconto_continuidade FROM pedido_financial_snapshots WHERE pedido_id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(230.0, (float)$snapshot['total'], 0.001);
        $this->assertEqualsWithDelta(30.0, (float)$snapshot['intermediacao'], 0.001);
        $this->assertEqualsWithDelta(0.0, (float)$snapshot['desconto_continuidade'], 0.001);
    }

    public function testRecusaOrcamentoMantemMesmoPedidoParaReboque(): void
    {
        $pedidoId = $this->core()->criar(new PedidoCreateRequest([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'pneu',
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

        $this->core()->transicionar($pedidoId, 'ORCAMENTO_RECUSADO', ['cliente_id' => 1]);

        $pedido = getPDO()->query("SELECT id, status, attendance_mode FROM pedidos WHERE id = {$pedidoId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame((string)$pedidoId, (string)$pedido['id']);
        $this->assertSame('recusado_solicitando_reboque', $pedido['status']);
        $this->assertSame('TOWING', $pedido['attendance_mode']);
    }

    private function core(): PedidoCoreService
    {
        return new PedidoCoreService(getPDO(), new PedidoPricingService(), new ChargePolicyService(), new PedidoTransitionService());
    }
}
