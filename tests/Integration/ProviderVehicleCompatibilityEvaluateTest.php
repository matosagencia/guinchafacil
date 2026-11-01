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
        $pdo->exec("INSERT IGNORE INTO service_categories (id, code, name) VALUES (1, 'QA', 'QA')");
        $pdo->exec("INSERT IGNORE INTO service_types (id, category_id, code, name, attendance_mode, active) VALUES (7, 1, 'QA_TOW', 'Reboque QA', 'TOWING', 1)");
        $pdo->exec("INSERT IGNORE INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo, ativo) VALUES (1, 'Cliente QA', 'compat@test.local', 'hash', '11999990002', '90000000002', 'cliente', 1)");
        $pdo->exec("INSERT IGNORE INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES (99, 1, 'QAA0001', 'QA', 'Teste', 2020, 'Prata', 'carro')");
        $pdo->exec("INSERT IGNORE INTO pedidos (id, cliente_id, veiculo_id, status, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino, distancia_km, custo_estimado, valor_estimado) VALUES (500, 1, 99, 'aguardando_guincho', -23.55, -46.63, 'Origem QA', -23.56, -46.64, 'Destino QA', 1, 100, 100)");

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
