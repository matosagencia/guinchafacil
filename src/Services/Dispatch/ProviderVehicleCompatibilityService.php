<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Dispatch/ProviderVehicleCompatibilityService.php
// ROADMAP socorro automotivo — Etapa 15 (compatibilidade prestador × veículo).
//
// Dois pontos de uso:
//   1. filtro da fila (conveniência) — GuinchoController / SseController;
//   2. revalidação no aceite (segurança) — PedidoTransitionService, dentro
//      da MESMA transação que trava o pedido. A fila pode estar velha; o
//      aceite não confia nela.
//
// FALLBACK CONSERVADOR: se o prestador não tem NENHUMA linha de capacidade
// veicular para este serviço (tabela nasce vazia), decide() devolve ELIGIBLE.
// Assim o reboque em produção não muda em nada enquanto o admin não
// configurar compatibilidades — a Etapa 15 é aditiva, não um gate novo que
// derruba o fluxo atual.

require_once __DIR__ . '/CompatibilityRequest.php';
require_once __DIR__ . '/CompatibilityResult.php';
require_once __DIR__ . '/../Logger.php';
require_once __DIR__ . '/../../Models/Dispatch/ProviderServiceVehicleCapability.php';
require_once __DIR__ . '/../../Models/Dispatch/ServiceVehicleRequirement.php';
require_once __DIR__ . '/../../Models/Dispatch/OrderVehicleRequirement.php';

class ProviderVehicleCompatibilityService
{
    /**
     * Carrega o contexto do banco e delega para decide().
     */
    public static function evaluate(CompatibilityRequest $request): CompatibilityResult
    {
        $snapshot = OrderVehicleRequirement::buscarPorPedido($request->orderId) ?? [];
        $vehicleCategory = (string)($snapshot['vehicle_category'] ?? $snapshot['declared_vehicle_type'] ?? '');

        $hasVehicleConfig = ProviderServiceVehicleCapability::existeConfig($request->providerId, $request->serviceTypeId);
        $capability = $vehicleCategory !== ''
            ? ProviderServiceVehicleCapability::buscar($request->providerId, $request->serviceTypeId, $vehicleCategory)
            : null;
        $requirements = $vehicleCategory !== ''
            ? ServiceVehicleRequirement::resolver($request->serviceTypeId, $vehicleCategory)
            : ServiceVehicleRequirement::resolver($request->serviceTypeId, '');

        $result = self::decide([
            'has_vehicle_config' => $hasVehicleConfig,
            'capability' => $capability,
            'requirements' => $requirements,
            'snapshot' => $snapshot,
        ]);

        self::log($request, $result);
        return $result;
    }

    /**
     * MOTOR DE DECISÃO PURO — sem banco, sem I/O. Todo o teste unitário
     * bate aqui. Ordem: filtros eliminatórios antes de qualquer "confirmação".
     *
     * @param array{has_vehicle_config:bool, capability:?array, requirements:?array, snapshot:array} $ctx
     */
    public static function decide(array $ctx): CompatibilityResult
    {
        $hasConfig   = (bool)($ctx['has_vehicle_config'] ?? false);
        $cap         = $ctx['capability'] ?? null;
        $snap        = $ctx['snapshot'] ?? [];

        $categoria = (string)($snap['vehicle_category'] ?? $snap['declared_vehicle_type'] ?? '');
        $categoriaConhecida = $categoria !== '';

        // 0) Fallback conservador: sem config veicular => legado => ELIGIBLE.
        if (!$hasConfig) {
            return CompatibilityResult::eligible(['DSP-CMP-020']);
        }

        // Categoria desconhecida (pedido sem snapshot, ex.: anterior à Etapa 15)
        // com prestador que TEM config: não dá pra afirmar incompatibilidade —
        // aparece na fila como "revisar", nunca é escondido por falta de dado.
        if (!$categoriaConhecida) {
            return CompatibilityResult::requiresConfirmation(
                ['DSP-CMP-018'],
                ['Categoria do veículo não informada — confirme antes de aceitar.']
            );
        }

        // 5/7) Config existe mas não há linha para a categoria deste veículo.
        if ($cap === null) {
            return CompatibilityResult::ineligible(
                ['DSP-CMP-005'],
                ['Prestador não atende a categoria declarada do veículo.']
            );
        }

        // 4/6) Capacidade suspensa/rejeitada/desabilitada.
        $status = (string)($cap['approval_status'] ?? 'PENDING');
        if ((int)($cap['enabled'] ?? 0) !== 1 || $status !== 'APPROVED') {
            return CompatibilityResult::ineligible(
                ['DSP-CMP-004'],
                ['Capacidade do prestador não está homologada para este serviço/veículo.']
            );
        }

        $eletrico = (int)($snap['electric_vehicle'] ?? 0) === 1;
        $hibrido  = (int)($snap['hybrid_vehicle'] ?? 0) === 1;
        $batido   = array_key_exists('damaged_vehicle', $snap) ? $snap['damaged_vehicle'] : null;
        $rodas    = array_key_exists('wheels_locked', $snap) ? $snap['wheels_locked'] : null;
        $subsolo  = array_key_exists('underground_location', $snap) ? $snap['underground_location'] : null;
        $verif    = (string)($snap['verification_status'] ?? '');

        // 11/12) Elétrico/híbrido não suportado.
        if ($eletrico && (int)($cap['supports_electric'] ?? 0) !== 1) {
            return CompatibilityResult::ineligible(['DSP-CMP-011'], ['Prestador não atende veículo elétrico.']);
        }
        if ($hibrido && (int)($cap['supports_hybrid'] ?? 0) !== 1) {
            return CompatibilityResult::ineligible(['DSP-CMP-012'], ['Prestador não atende veículo híbrido.']);
        }

        // 12/condições) Rodas travadas / veículo batido não suportados.
        if ((int)$rodas === 1 && (int)($cap['supports_locked_wheels'] ?? 0) !== 1) {
            return CompatibilityResult::ineligible(['DSP-CMP-013'], ['Prestador não atende veículo com rodas travadas.']);
        }
        if ((int)$batido === 1 && (int)($cap['supports_damaged_vehicle'] ?? 0) !== 1) {
            return CompatibilityResult::ineligible(['DSP-CMP-014'], ['Prestador não atende veículo batido.']);
        }
        // Subsolo com prestador que não suporta acesso a subsolo = incompatível.
        if ((int)$subsolo === 1 && (int)($cap['supports_subsoil_access'] ?? 0) !== 1) {
            return CompatibilityResult::ineligible(['DSP-CMP-015'], ['Prestador não atende acesso em garagem/subsolo.']);
        }

        // --- A partir daqui, é compatível. Falta ver se precisa CONFIRMAR. ---
        $warnings = [];
        $codes = [];

        if ((int)($cap['requires_manual_confirmation'] ?? 0) === 1) {
            $codes[] = 'DSP-CMP-019';
            $warnings[] = 'Este atendimento exige confirmação manual do prestador antes do aceite.';
        }

        // Dados incompletos: veículo apenas declarado + condição relevante
        // desconhecida (rodas/subsolo/batido nulos), ou subsolo confirmado
        // (altura de acesso não é capturada no MVP -> revisar).
        $condicaoDesconhecida = ($rodas === null) || ($subsolo === null) || ($batido === null);
        if ($verif === 'DECLARED' && $condicaoDesconhecida) {
            $codes[] = 'DSP-CMP-018';
            $warnings[] = 'Dados do veículo/ocorrência incompletos — confirme antes de aceitar.';
        }
        if ((int)$subsolo === 1) {
            $codes[] = 'DSP-CMP-018';
            $warnings[] = 'Acesso em garagem/subsolo — confirme altura e condições no local.';
        }
        if ((int)($snap['manual_review_required'] ?? 0) === 1) {
            $codes[] = 'DSP-CMP-019';
            $warnings[] = 'Pedido marcado para revisão manual.';
        }

        if (!empty($codes)) {
            return CompatibilityResult::requiresConfirmation(array_values(array_unique($codes)), $warnings);
        }

        return CompatibilityResult::eligible(['DSP-CMP-020']);
    }

    private static function log(CompatibilityRequest $request, CompatibilityResult $result): void
    {
        $level = $result->getStatus() === CompatibilityResult::INELIGIBLE
            ? Logger::LEVEL_INFO
            : Logger::LEVEL_DEBUG;

        Logger::log($level, 'ProviderVehicleCompatibilityService', 'evaluate', 'DISPATCH',
            'Compatibilidade: ' . $result->getStatus() . ' (' . $result->getPrimaryReasonCode() . ')',
            [
                'phase' => 'vehicle_compatibility_check',
                'code' => $result->getPrimaryReasonCode(),
                'request_id' => $request->requestId,
                'pedido_id' => $request->orderId,
                'provider_id' => $request->providerId,
                'service_type_id' => $request->serviceTypeId,
                'operation' => $request->operation,
                'status' => $result->getStatus(),
                'reason_codes' => $result->getReasonCodes(),
            ]
        );
    }
}
