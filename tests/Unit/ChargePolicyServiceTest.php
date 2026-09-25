<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Financial/ChargePolicyService.php';

final class ChargePolicyServiceTest extends TestCase
{
    public function testDescontoContinuidadeIncideSomenteNaIntermediacao(): void
    {
        $this->assertEqualsWithDelta(6.30, ChargePolicyService::calculateContinuityDiscount(30.00), 0.001);

        $result = ChargePolicyService::applyContinuityDiscount(200.00, 30.00);

        $this->assertEqualsWithDelta(200.00, $result['frete'], 0.001);
        $this->assertEqualsWithDelta(30.00, $result['intermediacao_original'], 0.001);
        $this->assertEqualsWithDelta(6.30, $result['desconto_continuidade'], 0.001);
        $this->assertEqualsWithDelta(23.70, $result['intermediacao_final'], 0.001);
        $this->assertEqualsWithDelta(223.70, $result['total'], 0.001);
    }
}
