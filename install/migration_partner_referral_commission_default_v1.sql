-- Regra inicial do programa de indicacao: 5% da comissao da GuinchaFacil.
-- Migration aditiva: nao alterar partner_referral_commissions_v1 ja aplicada.
INSERT INTO partner_referral_commission_rules
    (partner_profile, commission_percent, discount_percent, status, effective_from)
SELECT 'distribuicao', 5.00, 0.00, 'ativo', NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM partner_referral_commission_rules
    WHERE partner_profile = 'distribuicao'
      AND status = 'ativo'
      AND effective_from <= NOW()
      AND (effective_until IS NULL OR effective_until > NOW())
);
