<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/DTO/PedidoQuoteRequest.php';
require_once __DIR__ . '/../../src/Services/Pricing/PedidoPricingService.php';

final class PedidoPricingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec('DELETE FROM configuracoes');
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tarifa_por_km', '10.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_fixa', '100.00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('towing_intermediation_fee', '30.00')");
    }

    public function testContinuidadeNaoDescontaFrete(): void
    {
        $request = new PedidoQuoteRequest([
            'continuidade' => true,
        ]);

        $quote = (new PedidoPricingService())->cotar($request->toArray() + [
            'distancia_km_oficial' => 10,
            'modalidade_resolvida' => 'REBOQUE_PRANCHA',
        ]);

        $this->assertEqualsWithDelta(200.00, $quote['frete'], 0.001);
        $this->assertEqualsWithDelta(30.00, $quote['intermediacao'], 0.001);
        $this->assertEqualsWithDelta(6.30, $quote['desconto_continuidade'], 0.001);
        $this->assertEqualsWithDelta(223.70, $quote['total'], 0.001);
    }
}
