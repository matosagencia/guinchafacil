ALTER TABLE partner_profiles
    ADD COLUMN referral_code VARCHAR(80) NULL AFTER usuario_id,
    ADD UNIQUE KEY uq_partner_profiles_referral_code (referral_code);
