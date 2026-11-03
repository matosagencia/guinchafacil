<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/GuinchoDisponibilidade.php';

final class GuinchoDisponibilidadeDecisionTest extends TestCase
{
    public function testEscolheSempreOMenorAnelQueAtingeOMinimo(): void
    {
        $aneis = [
            ['idx' => 0, 'elegiveis' => 1, 'centro_km' => 1.0],
            ['idx' => 1, 'elegiveis' => 3, 'centro_km' => 3.5],
            ['idx' => 2, 'elegiveis' => 9, 'centro_km' => 7.5],
        ];

        $this->assertSame(1, GuinchoDisponibilidade::escolherAnel($aneis, 3)['idx']);
        $this->assertSame(2, GuinchoDisponibilidade::escolherAnel($aneis, 8)['idx']);
    }

    public function testSemAnelComMinimoRetornaNuloMesmoComCandidatosDistantes(): void
    {
        $aneis = [
            ['idx' => 0, 'elegiveis' => 1, 'centro_km' => 1.0],
            ['idx' => 1, 'elegiveis' => 2, 'centro_km' => 3.5],
        ];

        $this->assertNull(GuinchoDisponibilidade::escolherAnel($aneis, 3));
    }

    public function testDeadheadDaDecisaoEOCentroDoAnelEscolhido(): void
    {
        $anel = GuinchoDisponibilidade::escolherAnel([
            ['idx' => 3, 'elegiveis' => 3, 'centro_km' => 15.0],
        ], 3);
        $this->assertNotNull($anel);
        $this->assertSame(15.0, (float)$anel['centro_km']);
    }

    public function testCoordenadasInvalidasFalhamFechado(): void
    {
        $this->assertFalse(GuinchoDisponibilidade::temCandidatoElegivel(0.0, 0.0));
        $this->assertFalse(GuinchoDisponibilidade::temCandidatoElegivel(91.0, 0.0));
        $this->assertFalse(GuinchoDisponibilidade::temCandidatoElegivel(-91.0, 0.0));
    }

    public function testCompatibilidadeLegadaNaoPodeTerRegraDiferente(): void
    {
        $ref = new ReflectionClass(GuinchoDisponibilidade::class);
        $method = $ref->getMethod('existeNoRaio');
        $source = file_get_contents($method->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('self::temCandidatoElegivel($lat, $lng)', $source);
    }
}
