<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Models/Pedido.php';

final class PedidoTipoProblemaPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        getPDO()->exec('DELETE FROM pedidos');
    }

    public function testPersisteSlugReboqueSemConverterParaVazio(): void
    {
        $id = Pedido::criar([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => 'reboque',
            'descricao_problema' => '',
            'lat_origem' => -22.9068578,
            'lng_origem' => -43.1729439,
            'endereco_origem' => 'Origem de teste',
            'lat_destino' => -22.90,
            'lng_destino' => -43.18,
            'endereco_destino' => 'Destino de teste',
            'distancia_km' => 5.0,
            'custo_estimado' => 150.0,
            'status' => 'aguardando_pagamento',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $stmt = getPDO()->prepare('SELECT tipo_problema FROM pedidos WHERE id = ?');
        $stmt->execute([$id]);

        $this->assertSame('reboque', (string)$stmt->fetchColumn());
    }

    public function testTipoProblemaVazioNuncaEhPersistidoComoVazio(): void
    {
        $id = Pedido::criar([
            'cliente_id' => 1,
            'veiculo_id' => 1,
            'tipo_problema' => '',
            'descricao_problema' => '',
            'lat_origem' => -22.9068578,
            'lng_origem' => -43.1729439,
            'endereco_origem' => 'Origem de teste',
            'lat_destino' => null,
            'lng_destino' => null,
            'endereco_destino' => '',
            'distancia_km' => 0.0,
            'custo_estimado' => 80.0,
            'status' => 'aguardando_pagamento',
        ]);

        $stmt = getPDO()->prepare('SELECT tipo_problema FROM pedidos WHERE id = ?');
        $stmt->execute([$id]);

        $this->assertSame('outro', (string)$stmt->fetchColumn());
    }
}
