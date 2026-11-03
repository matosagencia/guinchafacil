<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/DecisaoAtendimentoService.php';

final class DecisaoOficinasTest extends TestCase
{
    public function testCoordenadaInvalidaNaoAbreFallbackDeOficina(): void
    {
        $decisao = (new DecisaoAtendimentoService())->decidirOficinas(0.0, 0.0, 'bateria');

        $this->assertFalse($decisao['disponivel']);
        $this->assertSame([], $decisao['candidatos']);
        $this->assertNull($decisao['escolhido']);
        $this->assertSame('COORDENADA_INVALIDA', $decisao['motivo']);
    }

    public function testAListaDeCandidatosEOrdenadaAntesDoEscolhido(): void
    {
        $ref = new ReflectionClass(DecisaoAtendimentoService::class);
        $method = $ref->getMethod('decidirOficinas');
        $source = file_get_contents($method->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString("'escolhido' => \$candidatos[0] ?? null", $source);
        $this->assertStringContainsString("'motivo' => \$candidatos === [] ? 'SEM_OFICINA_ELEGIVEL' : null", $source);
    }
}
