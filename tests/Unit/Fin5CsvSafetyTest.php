<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Controllers/AdminController.php';

final class Fin5CsvSafetyTest extends TestCase
{
    public function testCellsStartingWithSpreadsheetFormulaAreNeutralized(): void
    {
        foreach (['=1+1', '+cmd', '-cmd', '@SUM(A1)'] as $value) {
            self::assertSame("'{$value}", AdminController::csvSafeCell($value));
        }
    }

    public function testOrdinaryCellsRemainStable(): void
    {
        self::assertSame('approved', AdminController::csvSafeCell('approved'));
        self::assertSame('100,50', AdminController::csvSafeCell('100,50'));
    }
}
