<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/Models/Pricing/PricingZone.php';
require_once __DIR__ . '/../../../src/Models/Pricing/ServicePriceRule.php';
require_once __DIR__ . '/../../../src/Models/Catalog/ServiceType.php';
require_once __DIR__ . '/../../../src/Services/Pricing/ZonePricingService.php';

/**
 * Etapa 13 (precificação por zona/cidade) — construída em 26/07/2026 a
 * pedido do usuário ("as zonas de preço"). Cobre: point-in-polygon
 * (ray-casting), resolução de zona por coordenada, cálculo de preço com
 * distância excedente/multiplicadores/mínimo-máximo, e o fallback (null)
 * quando não há zona/regra aplicável — que é o comportamento que TODO o
 * resto do sistema depende para não mudar nada fora de zonas configuradas.
 */
final class ZonePricingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pricing_zones', 'service_price_rules', 'service_types', 'configuracoes', 'feriados'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('turno_noturno_inicio', '20:00')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('turno_noturno_fim', '06:00')");
    }

    private function poligonoQuadrado(): string
    {
        // Quadrado simples: lat/lng entre 0 e 10 (mais fácil de raciocinar
        // que coordenadas reais do Rio — a matemática do ray-casting não
        // muda com a escala).
        return json_encode([
            'type' => 'Polygon',
            'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]],
        ]);
    }

    public function testResolverZonaPorCoordenadaDentroDoPoligono(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $this->assertTrue(PricingZone::atualizarExpansao($zonaId, 1, 'pedra_viva', 'QA — zona de teste'));

        $zona = ZonePricingService::resolverZonaPorCoordenada(5.0, 5.0);
        $this->assertNotNull($zona, 'Ponto (5,5) deveria estar dentro do quadrado (0,0)-(10,10).');
        $this->assertSame('QUAD', $zona['code']);
        $this->assertSame('pedra_viva', $zona['status_expansao']);
    }

    public function testResolverZonaPorCoordenadaForaDoPoligono(): void
    {
        PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());

        $zona = ZonePricingService::resolverZonaPorCoordenada(50.0, 50.0);
        $this->assertNull($zona, 'Ponto (50,50) está bem fora do quadrado — não deveria casar com nenhuma zona.');
    }

    public function testZonaSemPoligonoNuncaCasa(): void
    {
        // Zona cadastrada mas SEM polígono desenhado (polygon_geojson null)
        // — comportamento documentado: existe, mas não afeta preço nenhum.
        PricingZone::criar('SEM_POLIGONO', 'Zona sem polígono', null, null);

        $zona = ZonePricingService::resolverZonaPorCoordenada(5.0, 5.0);
        $this->assertNull($zona, 'Zona sem polígono desenhado nunca deve casar com nenhuma coordenada.');
    }

    public function testCalcularPrecoSemZonaAplicavelDevolveNull(): void
    {
        // Nenhuma zona cadastrada — deve devolver null (fallback do
        // chamador para o cálculo global de sempre).
        $resultado = ZonePricingService::calcularPreco(5.0, 5.0, 1, 'carro', 10.0);
        $this->assertNull($resultado);
    }

    public function testCalcularPrecoComRegraDeTipoDeServico(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $serviceTypeId = ServiceType::criar([
            'category_id' => 1, 'code' => 'ELECTRICAL_DIAGNOSIS', 'name' => 'Diagnóstico Elétrico',
            'attendance_mode' => 'ON_SITE', 'requires_diagnostic' => true, 'allows_conversion_to_towing' => true,
        ]);

        ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, [
            'base_customer_price' => 108.00,
            'minimum_customer_price' => 108.00,
            'included_distance_km' => 0,
            'extra_distance_price' => 0,
        ], 'carro');

        $resultado = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 3.0);
        $this->assertNotNull($resultado);
        $this->assertEqualsWithDelta(108.00, $resultado['valor'], 0.01);
        $this->assertSame($zonaId, $resultado['zona_id']);
    }

    public function testCalcularPrecoAplicaDistanciaExcedenteEMinimo(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $serviceTypeId = ServiceType::criar([
            'category_id' => 1, 'code' => 'TOW_CAR', 'name' => 'Reboque de Automóvel', 'attendance_mode' => 'TOWING',
        ]);

        ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, [
            'base_customer_price' => 129.00,
            'minimum_customer_price' => 189.00,
            'included_distance_km' => 0,
            'extra_distance_price' => 8.00,
        ], 'carro');

        // 5km: 129 + 5*8 = 169, abaixo do mínimo (189) -> aplica mínimo.
        $curto = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 5.0);
        $this->assertEqualsWithDelta(189.00, $curto['valor'], 0.01);

        // 20km: 129 + 20*8 = 289, acima do mínimo -> usa o valor calculado.
        $longo = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 20.0);
        $this->assertEqualsWithDelta(289.00, $longo['valor'], 0.01);
    }

    public function testCalcularPrecoDiferenciaMotoDeCarroNaMesmaZona(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $serviceTypeId = ServiceType::criar([
            'category_id' => 1, 'code' => 'ELECTRICAL_DIAGNOSIS', 'name' => 'Diagnóstico Elétrico', 'attendance_mode' => 'ON_SITE',
        ]);

        ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, ['base_customer_price' => 108.00, 'minimum_customer_price' => 108.00], 'carro');
        ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, ['base_customer_price' => 74.00, 'minimum_customer_price' => 74.00], 'moto');

        $carro = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 1.0);
        $moto = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'moto', 1.0);

        $this->assertEqualsWithDelta(108.00, $carro['valor'], 0.01);
        $this->assertEqualsWithDelta(74.00, $moto['valor'], 0.01);
    }

    public function testCalcularPrecoCaiParaRegraCoringaQuandoCategoriaNaoTemRegraPropria(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $serviceTypeId = ServiceType::criar([
            'category_id' => 1, 'code' => 'AUTOMOTIVE_LOCKSMITH', 'name' => 'Chaveiro', 'attendance_mode' => 'ON_SITE',
        ]);

        // Regra "coringa" (vehicle_category NULL) — vale pra qualquer tipo.
        ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, ['base_customer_price' => 90.00, 'minimum_customer_price' => 90.00], null);

        $resultado = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'van', 1.0);
        $this->assertNotNull($resultado, 'Deveria cair na regra coringa quando não há regra específica para "van".');
        $this->assertEqualsWithDelta(90.00, $resultado['valor'], 0.01);
    }

    public function testCalcularPrecoRespeitaMaximo(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $serviceTypeId = ServiceType::criar([
            'category_id' => 1, 'code' => 'TOW_CAR', 'name' => 'Reboque de Automóvel', 'attendance_mode' => 'TOWING',
        ]);

        ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, [
            'base_customer_price' => 129.00,
            'minimum_customer_price' => 189.00,
            'maximum_customer_price' => 300.00,
            'included_distance_km' => 0,
            'extra_distance_price' => 8.00,
        ], 'carro');

        // 100km: 129 + 100*8 = 929, muito acima do teto (300) -> aplica teto.
        $resultado = ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 100.0);
        $this->assertEqualsWithDelta(300.00, $resultado['valor'], 0.01);
    }

    public function testRegraDesativadaParaDeSerVigente(): void
    {
        $zonaId = PricingZone::criar('QUAD', 'Quadrado Teste', null, $this->poligonoQuadrado());
        $serviceTypeId = ServiceType::criar([
            'category_id' => 1, 'code' => 'TOW_CAR', 'name' => 'Reboque de Automóvel', 'attendance_mode' => 'TOWING',
        ]);

        $regraId = ServicePriceRule::criarNovaVersao($zonaId, $serviceTypeId, ['base_customer_price' => 129.00, 'minimum_customer_price' => 189.00], 'carro');
        $this->assertNotNull(ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 5.0));

        ServicePriceRule::desativar($regraId);
        $this->assertNull(ZonePricingService::calcularPreco(5.0, 5.0, $serviceTypeId, 'carro', 5.0), 'Regra desativada não deve mais ser vigente.');
    }
}
