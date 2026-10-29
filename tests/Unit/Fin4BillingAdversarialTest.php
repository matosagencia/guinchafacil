<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Finance/GFInvoiceReadModel.php';
require_once __DIR__ . '/../../src/Services/Finance/GFExactMoney.php';
require_once __DIR__ . '/../../src/Services/Finance/GFPaymentReceipt.php';
require_once __DIR__ . '/../../src/Services/Finance/GFBillingPlan.php';

final class Fin4BillingAdversarialTest extends TestCase
{
    private const REF = '2026-10-27 12:00:00';

    private function invoice(int $id, string $status, string $due, string $amount = '100.00', ?string $outstanding = null): array
    {
        return [
            'invoice_id' => $id,
            'partner_id' => 7001,
            'due_at' => $due,
            'stored_status' => $status,
            'amount' => $amount,
            'outstanding' => $outstanding ?? ($status === 'paga' ? '0.00' : $amount),
        ];
    }

    public function testDividaVencidaPositivaEViraCandidataSemBloqueioAutomatico(): void
    {
        $report = GFBillingPlan::project([
            $this->invoice(101, 'aberta', '2026-10-10 12:00:00', '125.50'),
        ], self::REF, '1');

        $row = $report['invoices'][0];
        self::assertTrue($row['overdue']);
        self::assertSame('overdue_8_30', $row['bucket']);
        self::assertTrue($row['block_candidate']);
        self::assertTrue($row['would_block_if_applied']);
        self::assertFalse($row['notification_proposed']);
        self::assertSame('125.50', $report['totals']['candidate']);
    }

    public function testDividaAindaNaCarenciaNaoViraCandidata(): void
    {
        $report = GFBillingPlan::project([
            $this->invoice(102, 'aberta', '2026-10-25 12:00:00', '90.00'),
        ], self::REF, '1');

        self::assertTrue($report['invoices'][0]['overdue']);
        self::assertFalse($report['invoices'][0]['block_candidate']);
        self::assertFalse($report['invoices'][0]['would_block_if_applied']);
    }

    public function testFaturaPagaNaoMantemSaldoNemCandidatura(): void
    {
        $report = GFBillingPlan::project([
            $this->invoice(103, 'paga', '2026-10-01 12:00:00', '200.00'),
        ], self::REF, '1');

        self::assertSame('0.00', $report['invoices'][0]['outstanding']);
        self::assertFalse($report['invoices'][0]['block_candidate']);
        self::assertSame('0.00', $report['totals']['candidate']);
    }

    public function testDriftEntreValorESaldoRejeitado(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('FIN_BILLING_OUTSTANDING_DRIFT');
        GFBillingPlan::project([
            $this->invoice(104, 'aberta', '2026-10-01 12:00:00', '100.00', '99.99'),
        ], self::REF, '1');
    }

    public function testDuplicidadeDeFaturaNaoPodeInflarCobranca(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('FIN_BILLING_DUPLICATE_INVOICE');
        GFBillingPlan::project([
            $this->invoice(105, 'aberta', '2026-10-01 12:00:00', '75.00'),
            $this->invoice(105, 'aberta', '2026-10-01 12:00:00', '75.00'),
        ], self::REF, '1');
    }
}
