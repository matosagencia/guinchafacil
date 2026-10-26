<?php
declare(strict_types=1);

use App\Services\Finance\Fin2ApuracaoReadOnly;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Finance/CompensacaoBilateralCalculator.php';
require_once __DIR__ . '/../../src/Services/Finance/Fin2ReadOnlySource.php';
require_once __DIR__ . '/../../src/Services/Finance/Fin2ApuracaoReadOnly.php';

final class Fin2ApuracaoReadOnlyTest extends TestCase
{
    public function testContratoDaApuracaoEReadOnly(): void
    {
        self::assertTrue(method_exists(Fin2ApuracaoReadOnly::class, 'porGuincho'));
        self::assertFalse(method_exists(Fin2ApuracaoReadOnly::class, 'fechar'));
        self::assertFalse(method_exists(Fin2ApuracaoReadOnly::class, 'cobrar'));
    }
}
