<?php

declare(strict_types=1);

/**
 * src/Models/Finance/PayoutLedgerEntry.php
 * Pacote L1.7 — persistência do ledger append-only de repasse (payout_ledger_entries).
 * Nunca faz UPDATE/DELETE: só INSERT. Reconciliação é feita por soma de linhas.
 */
class PayoutLedgerEntry
{
    public static function registrar(PDO $pdo, array $data): int
    {
        $stmt = $pdo->prepare(
            "INSERT INTO payout_ledger_entries (
                pagamento_id, pedido_id, entry_type, valor, referencia_externa, metadata_json, criado_em
            ) VALUES (
                :pagamento_id, :pedido_id, :entry_type, :valor, :referencia_externa, :metadata_json, NOW()
            )"
        );
        $stmt->execute([
            ':pagamento_id' => (int)$data['pagamento_id'],
            ':pedido_id' => (int)$data['pedido_id'],
            ':entry_type' => (string)$data['entry_type'],
            ':valor' => (float)$data['valor'],
            ':referencia_externa' => $data['referencia_externa'] ?? null,
            ':metadata_json' => json_encode($data['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function listarPorPedido(int $pedidoId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM payout_ledger_entries WHERE pedido_id = ? ORDER BY id ASC");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarPorPagamento(int $pagamentoId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM payout_ledger_entries WHERE pagamento_id = ? ORDER BY id ASC");
        $stmt->execute([$pagamentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Soma por tipo de lançamento — usado para reconciliação/relatórios.
     * @return array<string,float>
     */
    public static function somaPorTipo(?string $desde = null): array
    {
        $sql = "SELECT entry_type, SUM(valor) AS total FROM payout_ledger_entries";
        $params = [];
        if ($desde !== null) {
            $sql .= " WHERE criado_em >= ?";
            $params[] = $desde;
        }
        $sql .= " GROUP BY entry_type";
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['entry_type']] = (float)$row['total'];
        }
        return $out;
    }
}
