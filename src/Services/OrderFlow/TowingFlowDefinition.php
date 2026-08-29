<?php
declare(strict_types=1);

// File: guinchafacil/src/Services/OrderFlow/TowingFlowDefinition.php
// ROADMAP socorro automotivo — Fundamento 4. Fluxo de reboque — IDÊNTICO ao
// mapa que já existia em PedidoStateMachine::NEXT antes desta etapa. Não
// altera o comportamento do fluxo de reboque em produção.

require_once __DIR__ . '/FlowDefinitionInterface.php';

final class TowingFlowDefinition implements FlowDefinitionInterface
{
    private const NEXT = [
        'aguardando_pagamento' => ['aguardando_guincho', 'cancelado'],
        'aguardando_guincho' => ['a_caminho', 'cancelado'],
        'a_caminho' => ['no_local', 'cancelado'],
        'no_local' => ['em_reboque', 'cancelado'],
        'em_reboque' => ['concluido', 'cancelado'],
        'concluido' => [],
        'cancelado' => [],
    ];

    public function proximosEstados(string $from): array
    {
        return self::NEXT[$from] ?? [];
    }

    public function podeTransitar(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        return in_array($to, self::NEXT[$from] ?? [], true);
    }

    public function proximoStatusPadrao(string $from): ?string
    {
        $opcoes = array_values(array_filter(
            self::NEXT[$from] ?? [],
            static fn(string $status): bool => $status !== 'cancelado'
        ));
        return $opcoes[0] ?? null;
    }
}
