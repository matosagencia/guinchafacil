<?php
declare(strict_types=1);

final class PromotionResolutionService
{
    public static function resolve(string $code, array $context = []): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return ['ok' => false, 'message' => 'Informe um código de desconto.'];
        try {
            $stmt = getPDO()->prepare("SELECT * FROM marketing_promotion_rules WHERE code = ? AND status = 'ativo' AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) ORDER BY priority DESC, id DESC LIMIT 1");
            $stmt->execute([$code]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rule) return ['ok' => false, 'message' => 'Código inválido ou fora da validade.'];
            if ($rule['max_uses'] !== null && (int)$rule['used_count'] >= (int)$rule['max_uses']) return ['ok' => false, 'message' => 'Este código atingiu o limite de utilizações.'];
            foreach (['partner_profile','vehicle_type','vehicle_make','region','service_type_id'] as $field) {
                if ($rule[$field] !== null && $rule[$field] !== '' && (string)$rule[$field] !== (string)($context[$field] ?? '')) {
                    return ['ok' => false, 'message' => 'Este código não é válido para este atendimento.'];
                }
            }
            return ['ok' => true, 'rule' => $rule, 'message' => 'Código aplicado.'];
        } catch (Throwable $e) {
            error_log('[promotion_resolve] ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Cupom indisponível no momento.'];
        }
    }

    public static function consume(int $id): void
    {
        if ($id <= 0) return;
        try { getPDO()->prepare("UPDATE marketing_promotion_rules SET used_count = used_count + 1 WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)")->execute([$id]); } catch (Throwable $e) { error_log('[promotion_consume] ' . $e->getMessage()); }
    }

    public static function apply(float $gross, array $resolved): array
    {
        $rule = $resolved['rule'] ?? [];
        $value = (float)($rule['discount_value'] ?? 0);
        $discount = (($rule['discount_type'] ?? 'percent') === 'fixed') ? $value : $gross * $value / 100;
        $discount = round(min(max(0, $discount), max(0, $gross)), 2);
        return ['discount' => $discount, 'net' => round(max(0, $gross - $discount), 2)];
    }
}
