<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Dispatch/OrderVehicleRequirementService.php
// ROADMAP socorro automotivo — Etapa 15.
//
// Congela o cenário avaliado no momento do pedido: junta a declaração
// permanente do veículo (Etapa 14) com as condições situacionais informadas
// na abertura do pedido. É este snapshot — não o cadastro atual do veículo —
// que a compatibilidade lê no aceite (o carro pode mudar de estado depois).

require_once __DIR__ . '/../../Models/Dispatch/OrderVehicleRequirement.php';

class OrderVehicleRequirementService
{
    /**
     * @param array $veiculo  linha de veiculos (com campos da Etapa 14)
     * @param array $ocorrencia  ['batido'=>?bool,'rodas_travadas'=>?bool,'dificil_acesso'=>?bool,'garagem_subsolo'=>?bool]
     */
    public static function registrar(int $pedidoId, array $veiculo, array $ocorrencia): int
    {
        $electricType = (string)($veiculo['electric_type'] ?? 'nao_eletrico');
        $categoria = (string)($veiculo['operational_category'] ?? $veiculo['vehicle_type'] ?? '');

        $requiresPlatform = ((int)($ocorrencia['rodas_travadas'] ?? 0) === 1)
            || ((int)($ocorrencia['batido'] ?? 0) === 1);

        return OrderVehicleRequirement::salvar($pedidoId, [
            'vehicle_id' => (int)($veiculo['id'] ?? 0),
            'vehicle_category' => $categoria !== '' ? $categoria : null,
            'declared_vehicle_type' => $veiculo['vehicle_type'] ?? ($veiculo['tipo'] ?? null),
            'fuel_type' => $veiculo['fuel_type'] ?? null,
            'electric_vehicle' => $electricType === 'eletrico_puro' ? 1 : 0,
            'hybrid_vehicle' => $electricType === 'hibrido' ? 1 : 0,
            'damaged_vehicle' => $ocorrencia['batido'] ?? null,
            'wheels_locked' => $ocorrencia['rodas_travadas'] ?? null,
            'underground_location' => $ocorrencia['garagem_subsolo'] ?? null,
            'difficult_access' => $ocorrencia['dificil_acesso'] ?? null,
            'spare_tire_available' => array_key_exists('has_spare_tire', $veiculo) ? $veiculo['has_spare_tire'] : null,
            'locking_bolt_present' => array_key_exists('has_locking_bolt', $veiculo) ? $veiculo['has_locking_bolt'] : null,
            'requires_platform' => $requiresPlatform ? 1 : 0,
            'manual_review_required' => 0,
            'verification_status' => $veiculo['verification_status'] ?? null,
            'snapshot_version' => 'v1',
        ]);
    }
}
