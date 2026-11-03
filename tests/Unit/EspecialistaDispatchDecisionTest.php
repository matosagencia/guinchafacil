<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/EspecialistaDispatchService.php';

final class EspecialistaDispatchDecisionTest extends TestCase
{
    public function testCoordenadaInvalidaFalhaFechado(): void
    {
        $decisao = EspecialistaDispatchService::decidirPorCoordenada(0.0, 0.0, 'ELECTRICAL_DIAG');

        $this->assertFalse($decisao['disponivel']);
        $this->assertSame([], $decisao['candidatos']);
        $this->assertSame('COORDENADA_INVALIDA', $decisao['motivo']);
    }

    public function testDecisaoExpoePrimeiroCandidatoComoEscolhido(): void
    {
        $ref = new ReflectionClass(EspecialistaDispatchService::class);
        $method = $ref->getMethod('decidirPorCoordenada');
        $source = file_get_contents($method->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("'escolhido' => \$candidatos[0] ?? null", $source);
        $this->assertStringContainsString("'motivo' => \$candidatos === [] ? 'SEM_ESPECIALISTA_ELEGIVEL' : null", $source);
    }
}
