<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/ProviderWorkshopService.php';
require_once __DIR__ . '/../../src/Services/Financial/ChargePolicyService.php';
require_once __DIR__ . '/../../src/Services/Financial/WorkshopSettlementService.php';

final class ProviderWorkshopServiceTest extends TestCase
{
    public function testNormalizesCommercialRulesAndKeepsSnapshotStable(): void
    {
        $rules = ProviderWorkshopService::normalizeRules([
            'taxa_indicacao_fixa' => '42.5',
            'raio_checkin_m' => '220',
            'regra_versao' => 'v2',
            'grace_period_minutes' => '45',
        ]);

        self::assertSame(42.5, $rules['fee_amount']);
        self::assertSame(220, $rules['checkin_radius_meters']);
        self::assertSame(45, $rules['grace_period_minutes']);
        self::assertSame('v2', $rules['rule_version']);

        $snapshot = ProviderWorkshopService::snapshot(
            ['id' => 17, 'provider_type' => 'WORKSHOP'],
            $rules
        );

        self::assertSame(17, $snapshot['provider_id']);
        self::assertSame('WORKSHOP', $snapshot['provider_type']);
        self::assertSame(42.5, $snapshot['fee_amount']);
    }

    public function testRejectsInvalidCommercialRules(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProviderWorkshopService::normalizeRules(['fee_amount' => -1]);
    }

    public function testEligibilityRequiresApprovedActiveProviderAndActivePartnership(): void
    {
        self::assertTrue(ProviderWorkshopService::isEligible(
            ['provider_type' => 'WORKSHOP', 'approval_status' => 'APPROVED', 'active' => 1],
            ['status_parceria' => 'ATIVO']
        ));
        self::assertFalse(ProviderWorkshopService::isEligible(
            ['provider_type' => 'WORKSHOP', 'approval_status' => 'PENDING', 'active' => 1],
            ['status_parceria' => 'ATIVO']
        ));
        self::assertTrue(ProviderWorkshopService::isEligible(
            ['provider_type' => 'INDIVIDUAL', 'approval_status' => 'APPROVED', 'active' => 1],
            ['status_parceria' => 'ATIVO']
        ));
        self::assertFalse(ProviderWorkshopService::isEligible(
            ['provider_type' => 'INDIVIDUAL', 'approval_status' => 'APPROVED', 'active' => 1],
            ['status_parceria' => 'ATIVO', 'faz_resgate_direto' => 0]
        ));
    }

    public function testWorkshopChargePolicyContainsSnapshotAndIdempotencyInputs(): void
    {
        $item = ChargePolicyService::itemIndicacaoOficina(30.00, [
            'provider_id' => 9,
            'rule_version' => 'v1',
            'fee_amount' => 30.00,
        ]);

        self::assertSame('WORKSHOP_REFERRAL', $item['phase_code']);
        self::assertSame('REFERRAL_FEE', $item['charge_type']);
        self::assertSame(30.0, $item['gross_amount']);
        self::assertSame('v1', $item['calculation_version']);
        self::assertTrue($item['evidence_required']);
        self::assertSame(9, $item['calculation_context']['provider_id']);
    }

    public function testSettlementAmountsAreDerivedFromImmutableChargeSnapshot(): void
    {
        $values = WorkshopSettlementService::calcularValores([
            'gross_amount' => 30,
            'platform_fee_amount' => 30,
            'provider_net_amount' => 0,
        ]);

        self::assertSame(30.0, $values['gross_amount']);
        self::assertSame(30.0, $values['platform_fee_amount']);
        self::assertSame(0.0, $values['net_amount']);
        self::assertSame('PAID', $values['settlement_status']);
        self::assertSame('WORKSHOP_REFERRAL_ZERO_NET', $values['eligibility_reason_code']);
    }
}
