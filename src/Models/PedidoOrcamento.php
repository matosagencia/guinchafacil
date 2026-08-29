<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/PedidoOrcamento.php
// ROADMAP socorro automotivo — Etapa 5 (diagnóstico e orçamento complementar).
// Itens propostos pelo prestador para o cliente aprovar antes da execução.
// Não gera cobrança sozinho — vira `order_charge_items`/pagamento real só
// depois de aprovado (a integração de fato com Etapa 11 é trabalho futuro,
// mesma cautela de não automatizar dinheiro sem o próximo elo da corrente).

class PedidoOrcamento
{
    private const TBL = 'pedido_orcamentos';

    public const PENDENTE = 'PENDENTE';
    public const APROVADO = 'APROVADO';
    public const RECUSADO = 'RECUSADO';

    public static function buscarPorPedido(int $pedidoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE pedido_id = ? LIMIT 1");
        $stmt->execute([$pedidoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['itens'] = json_decode((string)$row['itens_json'], true) ?: [];
        }
        return $row ?: null;
    }

    /**
     * Idempotente via UNIQUE(pedido_id) — um orçamento ativo por pedido.
     * Reenvio (ex.: prestador corrige um item antes do cliente decidir)
     * sobrescreve os itens e reseta o status para PENDENTE.
     *
     * §COBERTURA-RAIO-01 (06/08/2026): produto_id/quantidade são OPCIONAIS
     * e nullable — antes desta mudança, este método descartava
     * silenciosamente esses campos mesmo se o chamador os enviasse (só
     * gravava descricao/valor), o que tornava estruturalmente impossível
     * ligar a aprovação do orçamento a uma baixa de estoque real (ver
     * DiagnosticoService::decidirOrcamento()). Item sem produto_id continua
     * sendo tratado como mão de obra/serviço, sem efeito em estoque.
     *
     * @param array<int, array{descricao:string, valor:float, produto_id?:int, quantidade?:int}> $itens
     */
    public static function criar(int $pedidoId, int $diagnosticoId, array $itens): int
    {
        $itensNormalizados = array_map(
            static function (array $item): array {
                $normalizado = [
                    'descricao' => (string)($item['descricao'] ?? ''),
                    'valor' => round((float)($item['valor'] ?? 0), 2),
                ];
                $produtoId = (int)($item['produto_id'] ?? 0);
                if ($produtoId > 0) {
                    $normalizado['produto_id'] = $produtoId;
                    $normalizado['quantidade'] = max(1, (int)($item['quantidade'] ?? 1));
                }
                return $normalizado;
            },
            $itens
        );
        $valorTotal = array_sum(array_column($itensNormalizados, 'valor'));

        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . " (pedido_id, diagnostico_id, itens_json, valor_total, status, criado_em, atualizado_em)
             VALUES (?,?,?,?, 'PENDENTE', NOW(), NOW())
             ON DUPLICATE KEY UPDATE diagnostico_id = VALUES(diagnostico_id), itens_json = VALUES(itens_json),
                 valor_total = VALUES(valor_total), status = 'PENDENTE', decidido_em = NULL, atualizado_em = NOW()"
        );
        $stmt->execute([
            $pedidoId,
            $diagnosticoId,
            json_encode($itensNormalizados, JSON_UNESCAPED_UNICODE),
            $valorTotal,
        ]);

        $existente = self::buscarPorPedido($pedidoId);
        return (int)($existente['id'] ?? 0);
    }

    /** Decisão do cliente — idempotente: decidir de novo com o mesmo veredito não é erro, só não-op. */
    public static function decidir(int $pedidoId, string $status): bool
    {
        if (!in_array($status, [self::APROVADO, self::RECUSADO], true)) {
            return false;
        }
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET status = ?, decidido_em = NOW(), atualizado_em = NOW()
             WHERE pedido_id = ? AND status = 'PENDENTE'"
        );
        $stmt->execute([$status, $pedidoId]);
        return $stmt->rowCount() > 0;
    }
}
