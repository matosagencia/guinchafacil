<?php
declare(strict_types=1);

final class EspecialistaAtendimentoStateMachine
{
    private const TRANSITIONS = [
        'procurando' => ['ofertado', 'cancelado'],
        'ofertado' => ['aceito', 'procurando', 'cancelado'],
        'aceito' => ['a_caminho', 'cancelado'],
        'a_caminho' => ['no_local', 'cancelado'],
        'no_local' => ['em_diagnostico', 'cancelado'],
        'em_diagnostico' => ['aguardando_aprovacao', 'em_execucao', 'necessita_reboque', 'resolvido', 'cancelado'],
        'aguardando_aprovacao' => ['em_execucao', 'cancelado', 'necessita_reboque'],
        'em_execucao' => ['resolvido', 'necessita_reboque', 'cancelado'],
        'necessita_reboque' => ['cancelado'],
        'resolvido' => [],
        'cancelado' => [],
        'aguardando_pagamento' => ['procurando', 'cancelado'],
    ];

    public static function podeTransicionar(string $de, string $para): bool
    {
        return in_array($para, self::TRANSITIONS[$de] ?? [], true);
    }

    public static function validar(string $de, string $para): void
    {
        if (!self::podeTransicionar($de, $para)) {
            throw new DomainException("Transição inválida: {$de} → {$para}");
        }
    }
}
