<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/OrderFlow/FlowDefinitionInterface.php
// ROADMAP socorro automotivo — Fundamento 4 (novas máquinas de estado).
//
// Generaliza o que já existia em PedidoStateMachine (mapa NEXT declarativo)
// para múltiplos fluxos, um por attendance_mode (TOWING/ON_SITE/HYBRID).
// TowingFlowDefinition é literalmente o mapa antigo, sem alteração de
// comportamento — o fluxo de reboque continua idêntico.

interface FlowDefinitionInterface
{
    /** @return string[] estados possíveis a partir de $from (exclui 'cancelado' quando houver alternativa, ver nextStatus()). */
    public function proximosEstados(string $from): array;

    public function podeTransitar(string $from, string $to): bool;

    /** Primeiro próximo estado "de avanço" (não-cancelamento) — usado por quem precisa de um default. */
    public function proximoStatusPadrao(string $from): ?string;
}
