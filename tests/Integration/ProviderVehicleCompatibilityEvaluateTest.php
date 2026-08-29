<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Dispatch/ProviderVehicleCompatibilityService.php';

/**
 * ROADMAP socorro automotivo — Etapa 15.
 * Integração de evaluate() com o banco (snapshot + capacidade + requisitos).
 * Complementa o teste unitário puro de decide(): aqui garante o wiring de
 * leitura, incluindo o FALLBACK CONSERVADOR (sem config veicular = ELIGIBLE),
 * que é o que preserva o fluxo de reboque em produção.
 */
final class ProviderVehicleCompatibilityEvaluateTest extends TestCase
{
    private int $providerId = 1;
    private int $serviceTypeId = 7;
    private int $pedidoId = 500;

    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec("DELETE FROM provider_service_vehicle_capabilities");
        $pdo->exec("DELETE FROM order_vehicle_requirements");
        $pdo->exec("DELETE FROM service_vehicle_requirements");

        // Snapshot do pedido: automóvel de passeio, não elétrico, confirmado.
        $pdo->prepare(
            "INSERT INTO order_vehicle_requirements
                (order_id, vehicle_id, vehicle_category, declared_vehicle_type, electric_vehicle, hybrid_vehicle,
                 damaged_vehicle, wheels_locked, underground_location, verification_status, snapshot_version)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'v1')"
        )->execute([$this->pedidoId, 99, 'automovel_passeio', 'automovel_passeio', 0, 0, 0, 0, 0, 'CUSTOMER_CONFIRMED']);
    }

    private function req(): CompatibilityRequest
    {
        return new CompatibilityRequest($this->pedidoId, $this->providerId, $this->serviceTypeId, CompatibilityRequest::OP_ORDER_ACCEPTANCE);
    }

    private function inserirCapacidade(string $status, int $enabled = 1, array $over = []): void
    {
        $d = array_merge([
            'supports_electric' => 1, 'supports_hybrid' => 1, 'supports_locked_wheels' => 0,
            'supports_damaged_vehicle' => 0, 'supports_subsoil_access' => 0, 'requires_manual_confirmation' => 0,
        ], $over);
        getPDO()->prepare(
            "INSERT INTO provider_service_vehicle_capabilities
                (provider_id, service_type_id, vehicle_category, approval_status, enabled,
                 supports_electric, supports_hybrid, supports_locked_wheels, supports_damaged_vehicle,
                 supports_subsoil_access, requires_manual_confirmation)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $this->providerId, $this->serviceTypeId, 'automovel_passeio', $status, $enabled,
            $d['supports_electric'], $d['supports_hybrid'], $d['supports_locked_wheels'],
            $d['supports_damaged_vehicle'], $d['supports_subsoil_access'], $d['requires_manual_confirmation'],
        ]);
    }

    public function testSemConfigVeicularEhElegivel(): void
    {
        // Nenhuma capacidade configurada => fallback conservador => ELIGIBLE.
        $r = ProviderVehicleCompatibilityService::evaluate($this->req());
        $this->assertSame(CompatibilityResult::ELIGIBLE, $r->getStatus());
    }

    public function testCapacidadeAprovadaParaCategoriaEhElegivel(): void
    {
        $this->inserirCapacidade('APPROVED');
        $r = ProviderVehicleCompatibilityService::evaluate($this->req());
        $this->assertSame(CompatibilityResult::ELIGIBLE, $r->getStatus());
    }

    public function testCapacidadeSuspensaEhIneligivel(): void
    {
        $this->inserirCapacidade('SUSPENDED', 0);
        $r = ProviderVehicleCompatibilityService::evaluate($this->req());
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-004', $r->getPrimaryReasonCode());
    }

    public function testConfigParaOutraCategoriaTornaVeiculoIneligivel(): void
    {
        // Config existe para o serviço (moto), mas não para a categoria do
        // pedido (automovel_passeio) => INELIGIBLE por categoria.
        getPDO()->prepare(
            "INSERT INTO provider_service_vehicle_capabilities
                (provider_id, service_type_id, vehicle_category, approval_status, enabled)
             VALUES (?,?,?, 'APPROVED', 1)"
        )->execute([$this->providerId, $this->serviceTypeId, 'moto']);

        $r = ProviderVehicleCompatibilityService::evaluate($this->req());
        $this->assertSame(CompatibilityResult::INELIGIBLE, $r->getStatus());
        $this->assertSame('DSP-CMP-005', $r->getPrimaryReasonCode());
    }
}
