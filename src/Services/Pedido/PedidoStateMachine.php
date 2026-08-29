<?php
declare(strict_types=1);

// ROADMAP socorro automotivo — Fundamento 4: o mapa de transições fixo que
// existia aqui virou TowingFlowDefinition (idêntico, sem mudança de
// comportamento). Esta classe agora delega para OrderFlowResolver, que
// escolhe o fluxo certo por attendance_mode — TOWING é sempre o default
// quando $attendanceMode não é passado, preservando 100% a assinatura e o
// comportamento anteriores para quem já chamava canTransition($from, $to).

require_once __DIR__ . '/../OrderFlow/OrderFlowResolver.php';

final class PedidoStateMachine
{
    public static function canTransition(string $from, string $to, ?string $attendanceMode = null): bool
    {
        return OrderFlowResolver::forAttendanceMode($attendanceMode ?? 'TOWING')->podeTransitar($from, $to);
    }

    public static function nextStatus(string $from, ?string $attendanceMode = null): ?string
    {
        return OrderFlowResolver::forAttendanceMode($attendanceMode ?? 'TOWING')->proximoStatusPadrao($from);
    }
}
