<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Models/Financial/OrderChargeItem.php';
require_once __DIR__ . '/../../Models/Financial/OrderProviderSettlement.php';
require_once __DIR__ . '/../../Models/Financial/ChargeCodes.php';
require_once __DIR__ . '/../../Models/Provider/Provider.php';
require_once __DIR__ . '/../AuditTrailService.php';

final class WorkshopSettlementService
{
    public const REASON_QUALIFIED_REFERRAL = 'WORKSHOP_REFERRAL_QUALIFIED';
    public const REASON_ZERO_NET = 'WORKSHOP_REFERRAL_ZERO_NET';

    public static function calcularValores(array $chargeItem): array
    {
        $gross = round((float)($chargeItem['gross_amount'] ?? 0), 2);
        $platformFee = round((float)($chargeItem['platform_fee_amount'] ?? 0), 2);
        $net = round((float)($chargeItem['provider_net_amount'] ?? ($gross - $platformFee)), 2);
        return [
            'gross_amount' => $gross,
            'platform_fee_amount' => $platformFee,
            'net_amount' => $net,
            'settlement_status' => $net <= 0 ? 'PAID' : 'PENDING',
            'eligibility_reason_code' => $net <= 0 ? self::REASON_ZERO_NET : self::REASON_QUALIFIED_REFERRAL,
        ];
    }

    public static function reconciliar(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $stmt = getPDO()->query(
            "SELECT ci.*
               FROM order_charge_items ci
               JOIN providers p ON p.id = ci.provider_id
              WHERE ci.phase_code = 'WORKSHOP_REFERRAL'
                AND ci.charge_type = 'REFERRAL_FEE'
                AND ci.payable_status IN ('ELIGIBLE', 'SCHEDULED')
                AND p.provider_type = 'WORKSHOP'
              ORDER BY ci.id ASC
              LIMIT {$limit}"
        );
        $processed = 0;
        $failed = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            try {
                self::liquidarItem($item);
                $processed++;
            } catch (Throwable $e) {
                $failed++;
                error_log('[WorkshopSettlement] item ' . (int)$item['id'] . ': ' . $e->getMessage());
            }
        }
        return ['processed' => $processed, 'failed' => $failed];
    }

    public static function liquidarItem(array $chargeItem): array
    {
        $providerId = (int)($chargeItem['provider_id'] ?? 0);
        $orderId = (int)($chargeItem['order_id'] ?? 0);
        if ($providerId <= 0 || $orderId <= 0) {
            throw new InvalidArgumentException('Item de cobrança sem pedido ou provider.');
        }
        $values = self::calcularValores($chargeItem);
        $settlement = OrderProviderSettlement::criar(array_merge($values, [
            'order_id' => $orderId,
            'provider_id' => $providerId,
            'idempotency_key' => 'workshop_settlement:charge:' . (int)$chargeItem['id'],
        ]));

        $payable = $values['net_amount'] <= 0 ? ChargeCodes::PAYABLE_PAID : ChargeCodes::PAYABLE_SCHEDULED;
        OrderChargeItem::atualizarPayableStatus((int)$chargeItem['id'], $payable, null);
        AuditTrailService::evento('workshop_settlement_reconciliado', __CLASS__, __FUNCTION__, [
            'charge_item_id' => (int)$chargeItem['id'],
            'settlement_id' => (int)($settlement['id'] ?? 0),
            'provider_id' => $providerId,
            'net_amount' => $values['net_amount'],
        ]);
        return $settlement;
    }

    public static function criarEstorno(int $chargeItemId, int $actorId, string $motivo): array
    {
        $original = OrderChargeItem::buscarPorId($chargeItemId);
        if (!$original) {
            throw new InvalidArgumentException('Item de cobrança original não encontrado.');
        }
        $reversal = OrderChargeItem::criar([
            'order_id' => (int)$original['order_id'],
            'provider_id' => $original['provider_id'] !== null ? (int)$original['provider_id'] : null,
            'phase_code' => (string)$original['phase_code'],
            'charge_type' => ChargeCodes::TYPE_REFUND,
            'description' => 'Estorno reverso do item de cobrança #' . (int)$original['id'],
            'quantity' => 1,
            'unit_amount' => -(float)$original['unit_amount'],
            'gross_amount' => -(float)$original['gross_amount'],
            'platform_fee_amount' => -(float)$original['platform_fee_amount'],
            'provider_net_amount' => -(float)$original['provider_net_amount'],
            'charge_status' => ChargeCodes::CHARGE_REFUNDED,
            'payable_status' => ChargeCodes::PAYABLE_REVERSED,
            'calculation_version' => 'reversal-v1',
            'calculation_context' => [
                'original_charge_item_id' => (int)$original['id'],
                'reason' => trim($motivo),
                'actor_id' => $actorId,
            ],
            'reverses_charge_item_id' => (int)$original['id'],
            'idempotency_key' => 'workshop_referral_reversal:' . (int)$original['id'],
        ]);
        AuditTrailService::evento('workshop_charge_reversed', __CLASS__, __FUNCTION__, [
            'charge_item_id' => (int)$original['id'],
            'reversal_charge_item_id' => (int)($reversal['id'] ?? 0),
            'actor_id' => $actorId,
        ]);
        return $reversal;
    }
}
