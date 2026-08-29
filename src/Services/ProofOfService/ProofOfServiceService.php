<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/ProofOfService/ProofOfServiceService.php
// ROADMAP socorro automotivo — Etapa 6 (Proof-of-Service).
//
// Avalia, a partir de fontes que já existem (ServiceType — o que é exigido;
// PedidoDiagnostico/PedidoEvidencia — o que de fato aconteceu), se o
// checklist de um atendimento local está completo. Não decide dinheiro —
// só registra o fato estruturado (ServiceExecution) que o financeiro de
// duas fases (Etapa 11) vai consumir quando for ligado.

require_once __DIR__ . '/../../Models/ServiceExecution.php';
require_once __DIR__ . '/../../Models/PedidoDiagnostico.php';
require_once __DIR__ . '/../../Models/PedidoEvidencia.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../../Models/Catalog/ServiceType.php';
require_once __DIR__ . '/../../Models/Financial/ChargeCodes.php';
require_once __DIR__ . '/../Logger.php';

final class ProofOfServiceService
{
    /**
     * Chamado quando um atendimento local termina (teste_final → concluido
     * com resolvido=true). Reavaliar mais de uma vez é seguro/idempotente
     * (ServiceExecution::registrar faz upsert).
     */
    public static function avaliarEFechar(int $pedidoId, int $providerId): ?array
    {
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido) {
            return null;
        }

        $serviceTypeId = (int)($pedido['service_type_id'] ?? 0);
        $serviceType = $serviceTypeId > 0 ? ServiceType::buscarPorId($serviceTypeId) : null;

        // Sem tipo de serviço estruturado (pedido legado/reboque puro) —
        // Proof-of-Service não se aplica; o reboque tem seu próprio
        // controle de evidência (foto_plataforma/foto_destino) desde antes.
        if (!$serviceType) {
            return null;
        }

        $requiresDiagnostic = ServiceType::requiresDiagnostic($serviceType);
        $requiresBefore = ServiceType::requiresBeforeEvidence($serviceType);
        $requiresAfter = ServiceType::requiresAfterEvidence($serviceType);

        $hasDiagnostic = PedidoDiagnostico::buscarPorPedido($pedidoId) !== null;
        $hasBefore = PedidoEvidencia::buscarUltimaPorTipo($pedidoId, 'coleta') !== null;
        $hasAfter = PedidoEvidencia::buscarUltimaPorTipo($pedidoId, 'entrega') !== null;

        $checklist = [
            'requires_diagnostic' => $requiresDiagnostic,
            'requires_before_evidence' => $requiresBefore,
            'requires_after_evidence' => $requiresAfter,
            'has_diagnostic' => $hasDiagnostic,
            'has_before_evidence' => $hasBefore,
            'has_after_evidence' => $hasAfter,
        ];

        ServiceExecution::registrar($pedidoId, $providerId, ChargeCodes::PHASE_ON_SITE_SERVICE, $checklist);
        $registro = ServiceExecution::buscarPorPedido($pedidoId);

        Logger::log(Logger::LEVEL_INFO, 'ProofOfServiceService', 'avaliarEFechar', 'proof_of_service',
            "Checklist avaliado — pedido #{$pedidoId}: " . ($registro['checklist_status'] ?? '?'),
            ['pedido_id' => $pedidoId, 'provider_id' => $providerId, 'checklist' => $checklist, 'status' => $registro['checklist_status'] ?? null]);

        return $registro;
    }
}
