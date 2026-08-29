<?php

declare(strict_types=1);

/**
 * src/Models/CancellationSnapshot.php
 * Pacote L1.6 — persistência do preview de cancelamento (plano seção 4.7).
 * Cada preview exibido ao ator vira uma linha aqui; a confirmação só é aceita
 * se referenciar o mesmo snapshot_id/hash e ele ainda não estiver expirado
 * nem confirmado (evita TOCTOU entre cálculo e efetivação).
 */
class CancellationSnapshot
{
    public static function criar(array $data): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO cancelamento_snapshots (
                pedido_id, actor_type, actor_id, formula_version, factors_json,
                por_quality, fee_amount, refund_amount, penalty_amount,
                snapshot_hash, status, expires_at, created_at
            ) VALUES (
                :pedido_id, :actor_type, :actor_id, :formula_version, :factors_json,
                :por_quality, :fee_amount, :refund_amount, :penalty_amount,
                :snapshot_hash, 'pending', :expires_at, NOW()
            )"
        );
        $stmt->execute([
            ':pedido_id' => (int)$data['pedido_id'],
            ':actor_type' => (string)$data['actor_type'],
            ':actor_id' => (int)$data['actor_id'],
            ':formula_version' => (string)$data['formula_version'],
            ':factors_json' => json_encode($data['factors'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':por_quality' => $data['por_quality'] ?? null,
            ':fee_amount' => (float)($data['fee_amount'] ?? 0),
            ':refund_amount' => (float)($data['refund_amount'] ?? 0),
            ':penalty_amount' => (float)($data['penalty_amount'] ?? 0),
            ':snapshot_hash' => (string)$data['snapshot_hash'],
            ':expires_at' => (string)$data['expires_at'],
        ]);

        return (int)getPDO()->lastInsertId();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM cancelamento_snapshots WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Busca o snapshot travando a linha (uso dentro de transação de confirmação).
     */
    public static function buscarParaConfirmar(PDO $pdo, int $id): ?array
    {
        // "FOR UPDATE" é MySQL/PostgreSQL; SQLite (testes de integração) não suporta.
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $pdo->prepare("SELECT * FROM cancelamento_snapshots WHERE id = ?" . $lock);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function marcarConfirmado(PDO $pdo, int $id): void
    {
        $pdo->prepare("UPDATE cancelamento_snapshots SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?")
            ->execute([$id]);
    }

    public static function marcarExpirado(PDO $pdo, int $id): void
    {
        $pdo->prepare("UPDATE cancelamento_snapshots SET status = 'expired' WHERE id = ?")
            ->execute([$id]);
    }

    public static function isValido(array $snapshot, int $pedidoId, int $actorId): bool
    {
        if ((int)$snapshot['pedido_id'] !== $pedidoId) return false;
        if ((int)$snapshot['actor_id'] !== $actorId) return false;
        if ($snapshot['status'] !== 'pending') return false;
        if (strtotime((string)$snapshot['expires_at']) < time()) return false;
        return true;
    }
}
