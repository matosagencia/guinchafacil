<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/PedidoDiagnostico.php
// ROADMAP socorro automotivo — Etapa 5 (diagnóstico e orçamento complementar).

class PedidoDiagnostico
{
    private const TBL = 'pedido_diagnosticos';

    public const RESOLVIDO_SEM_ORCAMENTO = 'RESOLVIDO_SEM_ORCAMENTO';
    public const REQUER_ORCAMENTO = 'REQUER_ORCAMENTO';
    public const REQUER_REBOQUE = 'REQUER_REBOQUE';

    public const RESULTADOS = [
        self::RESOLVIDO_SEM_ORCAMENTO,
        self::REQUER_ORCAMENTO,
        self::REQUER_REBOQUE,
    ];

    public static function buscarPorPedido(int $pedidoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE pedido_id = ? LIMIT 1");
        $stmt->execute([$pedidoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Idempotente via UNIQUE(pedido_id): reenvio do mesmo formulário
     * atualiza o diagnóstico existente em vez de duplicar (um prestador
     * pode refinar o diagnóstico antes de decidir concluir/orçar).
     */
    public static function registrar(int $pedidoId, int $guinchoId, string $resultado, ?string $descricao): int
    {
        if (!in_array($resultado, self::RESULTADOS, true)) {
            throw new \InvalidArgumentException("resultado de diagnóstico inválido: {$resultado}");
        }

        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (pedido_id, guincho_id, resultado, descricao, criado_em, atualizado_em)
             VALUES (?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE resultado = VALUES(resultado), descricao = VALUES(descricao),
                 guincho_id = VALUES(guincho_id), atualizado_em = NOW()"
        );
        $stmt->execute([$pedidoId, $guinchoId, $resultado, $descricao]);

        $existente = self::buscarPorPedido($pedidoId);
        return (int)($existente['id'] ?? 0);
    }
}
