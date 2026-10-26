<?php
declare(strict_types=1);

use App\Services\Finance\CompensacaoBilateralCalculator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Finance/CompensacaoBilateralCalculator.php';

final class CompensacaoBilateralCalculatorTest extends TestCase
{
    public function testPlataformaGeraCreditoLiquidoEParceiroGeraDebito(): void
    {
        $result = CompensacaoBilateralCalculator::apurar([
            ['id' => 'p-1', 'canal' => 'plataforma', 'bruto_centavos' => 10000, 'comissao_centavos' => 800, 'taxa_parceiro_centavos' => 150],
            ['id' => 'p-2', 'canal' => 'parceiro', 'bruto_centavos' => 5000, 'comissao_centavos' => 400],
        ]);

        self::assertSame(9050, $result['creditos_centavos']);
        self::assertSame(400, $result['debitos_centavos']);
        self::assertSame(8650, $result['saldo_centavos']);
        self::assertSame('repassar', $result['resultado']);
    }

    public function testSaldoZeroECompensado(): void
    {
        $result = CompensacaoBilateralCalculator::apurar([
            ['id' => 'p-1', 'canal' => 'plataforma', 'bruto_centavos' => 10000, 'comissao_centavos' => 1000],
            ['id' => 'p-2', 'canal' => 'parceiro', 'bruto_centavos' => 10000, 'comissao_centavos' => 9000],
        ]);

        self::assertSame(0, $result['saldo_centavos']);
        self::assertSame('compensado', $result['resultado']);
    }

    public function testSaldoNegativoIndicaCobranca(): void
    {
        $result = CompensacaoBilateralCalculator::apurar([
            ['id' => 'p-1', 'canal' => 'parceiro', 'bruto_centavos' => 10000, 'comissao_centavos' => 1200],
        ]);

        self::assertSame(-1200, $result['saldo_centavos']);
        self::assertSame('cobrar', $result['resultado']);
    }

    public function testRejeitaIdDuplicado(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompensacaoBilateralCalculator::apurar([
            ['id' => 'same', 'canal' => 'plataforma', 'bruto_centavos' => 100, 'comissao_centavos' => 10],
            ['id' => 'same', 'canal' => 'plataforma', 'bruto_centavos' => 100, 'comissao_centavos' => 10],
        ]);
    }

    public function testRejeitaTaxaNoCanalParceiro(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CompensacaoBilateralCalculator::apurar([
            ['id' => 'p-1', 'canal' => 'parceiro', 'bruto_centavos' => 10000, 'comissao_centavos' => 800, 'taxa_parceiro_centavos' => 10],
        ]);
    }
}
