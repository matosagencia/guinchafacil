<?php

declare(strict_types=1);

final class AdminWorkshopFinanceService
{
    public static function listarIndicacoes(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = getPDO()->query(
            "SELECT i.*, p.legal_name, p.trade_name, p.pix_key,
                    ci.charge_status, ci.payable_status, ci.gross_amount,
                    ci.platform_fee_amount, ci.provider_net_amount,
                    ci.id AS charge_item_id,
                    s.id AS settlement_id, s.settlement_status,
                    s.net_amount AS settlement_net_amount, s.paid_at
               FROM pedido_indicacoes_oficina i
               JOIN providers p ON p.id = i.provider_id
               LEFT JOIN order_charge_items ci ON ci.id = i.order_charge_item_id
               LEFT JOIN order_provider_settlements s
                      ON s.order_id = i.pedido_id AND s.provider_id = i.provider_id
              ORDER BY i.updated_at DESC, i.id DESC
              LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function resumo(): array
    {
        $stmt = getPDO()->query(
            "SELECT COUNT(*) AS total_indicacoes,
                    SUM(i.status = 'CHECKIN_PENDENTE') AS checkins_pendentes,
                    SUM(i.status IN ('COBRANCA_GERADA','LIQUIDADA')) AS qualificadas,
                    COALESCE(SUM(CASE WHEN ci.charge_status <> 'REFUNDED' THEN ci.gross_amount ELSE 0 END), 0) AS total_taxas,
                    COALESCE(SUM(CASE WHEN s.settlement_status = 'PAID' THEN s.net_amount ELSE 0 END), 0) AS total_liquidado
               FROM pedido_indicacoes_oficina i
               LEFT JOIN order_charge_items ci ON ci.id = i.order_charge_item_id
               LEFT JOIN order_provider_settlements s
                      ON s.order_id = i.pedido_id AND s.provider_id = i.provider_id"
        );
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
