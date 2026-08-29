<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Financial/OrderChargeItem.php
// ROADMAP socorro automotivo — Etapa 11 (financeiro de duas fases).
// CRUD idempotente da tabela order_charge_items. Nenhum controller de
// produção grava aqui ainda — ver ChargePolicyService para a política que
// decide QUAIS itens gerar, e doc/ROADMAP_SOCORRO_AUTOMOTIVO_PROGRESSO.md
// (Etapa 11) para o porquê da geração automática ainda estar desligada.

class OrderChargeItem
{
    private const TBL = 'order_charge_items';

    /**
     * Cria um item de cobrança. Idempotente via idempotency_key (UNIQUE) —
     * reenvio/retry com a mesma chave não duplica, apenas retorna o
     * registro existente.
     */
    public static function criar(array $dados): array
    {
        $pdo = getPDO();

        $existente = self::buscarPorIdempotencyKey($dados['idempotency_key']);
        if ($existente !== null) {
            return $existente;
        }

        $driver = (string)$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql =
            "INSERT INTO " . self::TBL . "
                (order_id, provider_id, service_execution_id, phase_code, charge_type, description,
                 quantity, unit_amount, gross_amount, discount_amount, platform_fee_amount, provider_net_amount,
                 charge_status, payable_status, calculation_version, calculation_context_json,
                 evidence_required, idempotency_key, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)";
        if ($driver !== 'sqlite') {
            $sql .= " ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $dados['order_id'],
            $dados['provider_id'] ?? null,
            $dados['service_execution_id'] ?? null,
            $dados['phase_code'],
            $dados['charge_type'],
            $dados['description'],
            $dados['quantity'] ?? 1.0,
            $dados['unit_amount'] ?? 0.0,
            $dados['gross_amount'] ?? 0.0,
            $dados['discount_amount'] ?? 0.0,
            $dados['platform_fee_amount'] ?? 0.0,
            $dados['provider_net_amount'] ?? 0.0,
            $dados['charge_status'] ?? ChargeCodes::CHARGE_PENDING,
            $dados['payable_status'] ?? ChargeCodes::PAYABLE_NOT_ELIGIBLE,
            $dados['calculation_version'],
            isset($dados['calculation_context']) ? json_encode($dados['calculation_context'], JSON_UNESCAPED_UNICODE) : null,
            !empty($dados['evidence_required']) ? 1 : 0,
            $dados['idempotency_key'],
        ]);

        if ($driver === 'sqlite' && $stmt->rowCount() === 0) {
            $existente = self::buscarPorIdempotencyKey($dados['idempotency_key']);
            if ($existente !== null) {
                return $existente;
            }
        }

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

    public static function listarPorPrestador(int $providerId, ?string $payableStatus = null): array
    {
        if ($payableStatus !== null) {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? AND payable_status = ? ORDER BY id DESC");
            $stmt->execute([$providerId, $payableStatus]);
        } else {
            $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? ORDER BY id DESC");
            $stmt->execute([$providerId]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Transição de charge_status — dimensão "o que aconteceu com a cobrança". */
    public static function atualizarChargeStatus(int $id, string $status): bool
    {
        if (!in_array($status, ChargeCodes::CHARGE_STATUSES, true)) {
            return false;
        }
        $stmt = getPDO()->prepare("UPDATE " . self::TBL . " SET charge_status = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    /**
     * Transição de payable_status — dimensão "o que acontece com o repasse".
     * Independente de charge_status (ver doc: cobrança aprovada pode ainda
     * assim estar com repasse pendente de evidência).
     */
    public static function atualizarPayableStatus(int $id, string $status, ?string $blockReasonCode = null): bool
    {
        if (!in_array($status, ChargeCodes::PAYABLE_STATUSES, true)) {
            return false;
        }

        $campoData = match ($status) {
            ChargeCodes::PAYABLE_ELIGIBLE => 'approved_at',
            ChargeCodes::PAYABLE_SCHEDULED => 'payable_at',
            ChargeCodes::PAYABLE_PAID => 'paid_at',
            ChargeCodes::PAYABLE_BLOCKED => 'blocked_at',
            default => null,
        };

        $sql = "UPDATE " . self::TBL . " SET payable_status = ?, block_reason_code = ?, updated_at = NOW()";
        if ($campoData !== null) {
            $sql .= ", {$campoData} = NOW()";
        }
        $sql .= " WHERE id = ?";

        $stmt = getPDO()->prepare($sql);
        return $stmt->execute([$status, $blockReasonCode, $id]);
    }

    public static function marcarEvidenciaValidada(int $id): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET evidence_validated_at = NOW(), updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }
}
