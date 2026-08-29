<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/Models/Catalog/ServicePricingRule.php';

/**
 * §DESLOCAMENTO-01 (26/07/2026) — antes desta correção, base_fee/
 * pickup_km_price/labor_fee configurados em /admin/catalogo-servicos/tarifas
 * eram salvos mas NUNCA lidos por nenhum cálculo real de pedido (confirmado
 * por grep: só o próprio admin controller/view referenciavam a classe).
 * calcularTotal() é o método que fecha essa lacuna — estes testes fixam o
 * comportamento esperado.
 */
final class ServicePricingRuleCalcularTotalTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['service_pricing_rules', 'feriados', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('turno_noturno_inicio', '20:00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('turno_noturno_fim', '06:00')");
    }

    private function criarRegra(int $serviceTypeId, array $dados): void
    {
        ServicePricingRule::salvar($serviceTypeId, $dados + ['active' => true]);
    }

    public function testSemRegraAtivaDevolveNull(): void
    {
        $this->assertNull(ServicePricingRule::calcularTotal(999, 5.0));
    }

    public function testDeslocamentoRealEntraNoCalculo(): void
    {
        // base_fee=20, pickup_km_price=3/km, labor_fee=0, sem mínimo alto.
        $this->criarRegra(1, ['base_fee' => 20.00, 'pickup_km_price' => 3.00, 'labor_fee' => 0, 'minimum_price' => 0]);

        // 10km de deslocamento: 20 + 10*3 = 50.
        $meioDia = new DateTimeImmutable('2026-07-27 14:00:00');
        $resultado = ServicePricingRule::calcularTotal(1, 10.0, $meioDia);

        $this->assertNotNull($resultado);
        $this->assertEqualsWithDelta(50.00, $resultado['valor'], 0.01);
        $this->assertEqualsWithDelta(30.00, $resultado['detalhe']['deslocamento_total'], 0.01);
    }

    public function testMinimoFuncionaComoPiso(): void
    {
        $this->criarRegra(1, ['base_fee' => 10.00, 'pickup_km_price' => 1.00, 'labor_fee' => 0, 'minimum_price' => 108.00]);

        // 1km: 10 + 1 = 11, bem abaixo do mínimo (108) -> aplica mínimo.
        $meioDia = new DateTimeImmutable('2026-07-27 14:00:00');
        $resultado = ServicePricingRule::calcularTotal(1, 1.0, $meioDia);
        $this->assertEqualsWithDelta(108.00, $resultado['valor'], 0.01);
    }

    public function testMultiplicadorNoturnoAplicado(): void
    {
        $this->criarRegra(1, ['base_fee' => 100.00, 'pickup_km_price' => 0, 'labor_fee' => 0, 'minimum_price' => 0, 'night_multiplier' => 1.25]);

        $meiaNoite = new DateTimeImmutable('2026-07-27 23:30:00');
        $resultado = ServicePricingRule::calcularTotal(1, 0.0, $meiaNoite);
        $this->assertEqualsWithDelta(125.00, $resultado['valor'], 0.01);
        $this->assertTrue($resultado['detalhe']['noturno']);
    }

    public function testHorarioDiurnoNaoAplicaMultiplicadorNoturno(): void
    {
        $this->criarRegra(1, ['base_fee' => 100.00, 'pickup_km_price' => 0, 'labor_fee' => 0, 'minimum_price' => 0, 'night_multiplier' => 1.25]);

        $meioDia = new DateTimeImmutable('2026-07-27 14:00:00');
        $resultado = ServicePricingRule::calcularTotal(1, 0.0, $meioDia);
        $this->assertEqualsWithDelta(100.00, $resultado['valor'], 0.01);
        $this->assertFalse($resultado['detalhe']['noturno']);
    }

    public function testRegraInativaDevolveNull(): void
    {
        ServicePricingRule::salvar(1, ['base_fee' => 100.00, 'active' => false]);
        $this->assertNull(ServicePricingRule::calcularTotal(1, 5.0));
    }
}
