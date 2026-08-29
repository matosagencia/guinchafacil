<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Financial/OrderProviderSettlement.php
// ROADMAP socorro automotivo — Etapa 11 (financeiro de duas fases).
// order_charge_items explica O QUE foi cobrado; esta tabela consolida
// QUANTO cada prestador deve receber, por pedido+prestador (§ desenho
// confirmado pelo usuário em 22/07/2026). Ainda não é escrita por nenhum
// fluxo de produção.

class OrderProviderSettlement
{
    private const TBL = 'order_provider_settlements';

    /** Idempotente via idempotency_key — retry não duplica consolidação. */
    public static function criar(array $dados): array
    {
        $pdo = getPDO();

        $existente = self::buscarPorIdempotencyKey($dados['idempotency_key']);
        if ($existente !== null) {
            return $existente;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO " . self::TBL . "
                (order_id, provider_id, service_execution_id, gross_amount, platform_fee_amount, net_amount,
                 settlement_status, eligibility_reason_code, idempotency_key, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->execute([
            $dados['order_id'],
            $dados['provider_id'],
            $dados['service_execution_id'] ?? null,
            $dados['gross_amount'] ?? 0.0,
            $dados['platform_fee_amount'] ?? 0.0,
            $dados['net_amount'] ?? 0.0,
            $dados['settlement_status'] ?? 'PENDING',
            $dados['eligibility_reason_code'] ?? null,
            $dados['idempotency_key'],
        ]);

        $id = (int)$pdo->lastInsertId();
        return self::buscarPorId($id);
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorIdempotencyKey(string $key): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE idempotency_key = ? LIMIT 1");
        $stmt->execute([$key]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarPorPedido(int $orderId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE order_id = ? ORDER BY id ASC");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function listarPorPrestador(int $providerId, ?string $status = null): array
    {
        if ($status !== null) {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? AND settlement_status = ? ORDER BY id DESC");
            $stmt->execute([$providerId, $status]);
        } else {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? ORDER BY id DESC");
            $stmt->execute([$providerId]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function atualizarStatus(int $id, string $status, ?string $eligibilityReasonCode = null): bool
    {
        $campoData = match ($status) {
            'APPROVED' => 'approved_at',
            'SCHEDULED' => 'scheduled_at',
            'PAID' => 'paid_at',
            default => null,
        };

        $sql = "UPDATE " . self::TBL . " SET settlement_status = ?, eligibility_reason_code = ?, updated_at = NOW()";
        if ($campoData !== null) {
            $sql .= ", {$campoData} = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = getPDO()->prepare($sql);
        return $stmt->execute([$status, $eligibilityReasonCode, $id]);
    }
}
