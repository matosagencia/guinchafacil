<?php
declare(strict_types=1);

final class PartnerReferralCommissionService
{
    public static function currentPercent(string $profile): float
    {
        $allowed = ['guincho', 'oficina', 'distribuicao'];
        if (!in_array($profile, $allowed, true)) return 0.0;
        try {
            $stmt = getPDO()->prepare("SELECT commission_percent FROM partner_referral_commission_rules WHERE partner_profile = ? AND status = 'ativo' AND effective_from <= NOW() AND (effective_until IS NULL OR effective_until > NOW()) ORDER BY effective_from DESC, id DESC LIMIT 1");
            $stmt->execute([$profile]);
            return max(0.0, min(100.0, (float)($stmt->fetchColumn() ?: 0.0)));
        } catch (Throwable $e) {
            error_log('[partner_commission] ' . $e->getMessage());
            return 0.0;
        }
    }
}
