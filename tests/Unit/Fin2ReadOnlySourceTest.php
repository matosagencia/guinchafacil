<?php
declare(strict_types=1);

use App\Services\Finance\Fin2ReadOnlySource;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Finance/Fin2ReadOnlySource.php';

final class Fin2ReadOnlySourceTest extends TestCase
{
    public function testClasseExpõeSomenteLeituraEContratoDeMovimentos(): void
    {
        self::assertTrue(method_exists(Fin2ReadOnlySource::class, 'movimentosPlataforma'));
        self::assertFalse(method_exists(Fin2ReadOnlySource::class, 'fechar'));
    }
}
