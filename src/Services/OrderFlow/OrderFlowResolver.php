<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/OrderFlow/OrderFlowResolver.php
// ROADMAP socorro automotivo — Fundamento 4, §5.5.

require_once __DIR__ . '/FlowDefinitionInterface.php';
require_once __DIR__ . '/TowingFlowDefinition.php';
require_once __DIR__ . '/OnSiteFlowDefinition.php';
require_once __DIR__ . '/HybridFlowDefinition.php';

final class OrderFlowResolver
{
    /** @var array<string, FlowDefinitionInterface> cache por attendance_mode — os 3 fluxos são stateless. */
    private static array $instances = [];

    /**
     * Resolve a máquina de estados certa a partir do pedido. Default
     * TOWING quando `attendance_mode` está ausente/nulo — cobre tanto
     * pedidos antigos (coluna não existia antes da Etapa 1) quanto
     * qualquer pedido criado sem passar pela triagem.
     */
    public static function forPedido(array $pedido): FlowDefinitionInterface
    {
        return self::forAttendanceMode((string)($pedido['attendance_mode'] ?? 'TOWING'));
    }

    public static function forAttendanceMode(string $attendanceMode): FlowDefinitionInterface
    {
        $modo = strtoupper(trim($attendanceMode)) ?: 'TOWING';
        if (!in_array($modo, ['TOWING', 'ON_SITE', 'HYBRID', 'SPECIALIST'], true)) {
            $modo = 'TOWING';
        }

        if (!isset(self::$instances[$modo])) {
            self::$instances[$modo] = match ($modo) {
                'ON_SITE', 'SPECIALIST' => new OnSiteFlowDefinition(),
                'HYBRID' => new HybridFlowDefinition(),
                default => new TowingFlowDefinition(),
            };
        }

        return self::$instances[$modo];
    }
}
