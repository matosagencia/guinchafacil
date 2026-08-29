<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../src/Services/Dispatch/CompatibilityResult.php';
require_once __DIR__ . '/../../../src/Services/Dispatch/CompatibilityRequest.php';
require_once __DIR__ . '/../../../src/Services/Dispatch/ProviderVehicleCompatibilityService.php';

/**
 * ROADMAP socorro automotivo — Etapa 15.
 * Cobre o motor de decisão PURO (decide()), sem banco. As regras de
 * compatibilidade não podem depender de I/O — todo o julgamento é aqui.
 */
final class ProviderVehicleCompatibilityServiceTest extends TestCase
{
    private function cap(array $over = []): array
    {
        return array_merge([
            'approval_status' => 'APPROVED',
            'enabled' => 1,
            'supports_electric' => 1,
            'supports_hybrid' => 1,
            'supports_locked_wheels' => 0,
            'supports_damaged_vehicle' => 0,
            'supports_subsoil_access' => 0,
            'requires_manual_confirmation' => 0,
            'max_vehicle_weight_kg' => null,
        ], $over);
    }

    private function snap(array $over = []): array
    {
        return array_merge([
            'vehicle_category' => 'automovel_passeio',
            'declared_vehicle_type' => 'automovel_passeio',
            'electric_vehicle' => 0,
            'hybrid_vehicle' => 0,
            'damaged_vehicle' => 0,
            'wheels_locked' => 0,
            'underground_location' => 0,
            'verification_status' => 'CUSTOMER_CONFIRMED',
            'manual_review_required' => 0,
        ], $over);
    }

    /** Fallback conservador: sem config veicular = legado = ELIGIBLE (reboque não muda). */
    public function testSemConfigEhElegivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => false,
            'capability' => null,
            'requirements' => null,
            'snapshot' => $this->snap(),
        ]);
        $this->assertSame(CompatibilityResult::ELIGIBLE, $r->getStatus());
    }

    public function testGuinchoLeveComCarroLeveElegivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(),
            'requirements' => null,
            'snapshot' => $this->snap(),
        ]);
        $this->assertSame(CompatibilityResult::ELIGIBLE, $r->getStatus());
    }

    /** Config existe mas não há linha para a categoria (pickup pesada) = INELIGIBLE. */
    public function testCategoriaNaoAtendidaIneligivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => null, // nenhuma linha para a categoria do snapshot
            'requirements' => null,
            'snapshot' => $this->snap(['vehicle_category' => 'pickup_pesada', 'declared_vehicle_type' => 'pickup_pesada']),
        ]);
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-005', $r->getPrimaryReasonCode());
    }

    public function testCapacidadeSuspensaIneligivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(['approval_status' => 'SUSPENDED', 'enabled' => 0]),
            'requirements' => null,
            'snapshot' => $this->snap(),
        ]);
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-004', $r->getPrimaryReasonCode());
    }

    public function testEletricoSemSuporteIneligivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(['supports_electric' => 0]),
            'requirements' => null,
            'snapshot' => $this->snap(['electric_vehicle' => 1]),
        ]);
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-011', $r->getPrimaryReasonCode());
    }

    public function testRodasTravadasSemSuporteIneligivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(['supports_locked_wheels' => 0]),
            'requirements' => null,
            'snapshot' => $this->snap(['wheels_locked' => 1]),
        ]);
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-013', $r->getPrimaryReasonCode());
    }

    public function testVeiculoBatidoSemSuporteIneligivel(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(['supports_damaged_vehicle' => 0]),
            'requirements' => null,
            'snapshot' => $this->snap(['damaged_vehicle' => 1]),
        ]);
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-014', $r->getPrimaryReasonCode());
    }

    public function testSubsoloComSuporteMasExigeConfirmacao(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(['supports_subsoil_access' => 1]),
            'requirements' => null,
            'snapshot' => $this->snap(['underground_location' => 1]),
        ]);
        $this->assertSame(CompatibilityResult::REQUIRES_CONFIRMATION, $r->getStatus());
    }

    public function testDadosIncompletosExigemConfirmacao(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(),
            'requirements' => null,
            'snapshot' => $this->snap(['verification_status' => 'DECLARED', 'wheels_locked' => null]),
        ]);
        $this->assertSame(CompatibilityResult::REQUIRES_CONFIRMATION, $r->getStatus());
    }

    /** Categoria desconhecida (pedido sem snapshot) nunca é escondido: vira confirmação. */
    public function testCategoriaDesconhecidaExigeConfirmacao(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => null,
            'requirements' => null,
            'snapshot' => ['vehicle_category' => '', 'declared_vehicle_type' => ''],
        ]);
        $this->assertSame(CompatibilityResult::REQUIRES_CONFIRMATION, $r->getStatus());
        $this->assertSame('DSP-CMP-018', $r->getPrimaryReasonCode());
    }

    public function testConfirmacaoManualNaCapacidade(): void
    {
        $r = ProviderVehicleCompatibilityService::decide([
            'has_vehicle_config' => true,
            'capability' => $this->cap(['requires_manual_confirmation' => 1]),
            'requirements' => null,
            'snapshot' => $this->snap(),
        ]);
        $this->assertSame(CompatibilityResult::REQUIRES_CONFIRMATION, $r->getStatus());
        $this->assertSame('DSP-CMP-019', $r->getPrimaryReasonCode());
    }
}
