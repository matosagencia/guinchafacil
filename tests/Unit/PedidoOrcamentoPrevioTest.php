<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Models/PedidoOrcamentoPrevio.php';

final class PedidoOrcamentoPrevioTest extends TestCase
{
    public function testRejeitaTetoMenorQueMinimo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PedidoOrcamentoPrevio::criar([
            'pedido_id' => 1, 'provider_id' => 2,
            'estimativa_minima' => 1000, 'estimativa_maxima' => 900,
            'descricao_avaria' => 'teste',
        ], $this->createMock(PDO::class));
    }

    public function testCalculaAbatimentoSemExcederOrdemDeServico(): void
    {
        $this->assertSame(350.0, PedidoOrcamentoPrevio::calcularAbatimento([
            'abater_diagnostico_na_os' => 1, 'taxa_diagnostico_local' => 350,
        ], 1200));
        $this->assertSame(100.0, PedidoOrcamentoPrevio::calcularAbatimento([
            'abater_diagnostico_na_os' => 1, 'taxa_diagnostico_local' => 350,
        ], 100));
    }
}
