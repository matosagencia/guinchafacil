<?php

class PartnerProfileService
{
    public static function ensure(int $usuarioId): void
    {
        $stmt = getPDO()->prepare('INSERT IGNORE INTO partner_profiles (usuario_id) VALUES (?)');
        $stmt->execute([$usuarioId]);
    }

    public static function find(int $usuarioId): array
    {
        self::ensure($usuarioId);
        $stmt = getPDO()->prepare('SELECT * FROM partner_profiles WHERE usuario_id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public static function ensureReferralCode(int $usuarioId): string
    {
        $profile = self::find($usuarioId);
        if (!empty($profile['referral_code'])) return (string)$profile['referral_code'];
        $code = 'parceiro-' . $usuarioId;
        $stmt = getPDO()->prepare('UPDATE partner_profiles SET referral_code = ? WHERE usuario_id = ? AND (referral_code IS NULL OR referral_code = "")');
        $stmt->execute([$code, $usuarioId]);
        return $code;
    }

    public static function isComplete(int $usuarioId): bool
    {
        $profile = self::find($usuarioId);
        $address = getPDO()->prepare('SELECT cep, logradouro, numero, bairro, cidade, estado FROM enderecos WHERE usuario_id = ? AND principal = 1 LIMIT 1');
        $address->execute([$usuarioId]);
        $endereco = $address->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['cep','logradouro','numero','bairro','cidade','estado'] as $key) {
            if (trim((string)($endereco[$key] ?? '')) === '') return false;
        }
        return trim((string)($profile['chave_pix'] ?? '')) !== ''
            && in_array((string)($profile['chave_pix_tipo'] ?? ''), ['cpf','cnpj','email','telefone','aleatoria'], true);
    }

    public static function savePix(int $usuarioId, string $chave, string $tipo): void
    {
        self::ensure($usuarioId);
        $stmt = getPDO()->prepare('UPDATE partner_profiles SET chave_pix = ?, chave_pix_tipo = ?, status = ? WHERE usuario_id = ?');
        $stmt->execute([$chave, $tipo, self::isAddressComplete($usuarioId) ? 'completo' : 'pendente', $usuarioId]);
    }

    public static function isAddressComplete(int $usuarioId): bool
    {
        $stmt = getPDO()->prepare('SELECT cep, logradouro, numero, bairro, cidade, estado FROM enderecos WHERE usuario_id = ? AND principal = 1 LIMIT 1');
        $stmt->execute([$usuarioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['cep','logradouro','numero','bairro','cidade','estado'] as $key) {
            if (trim((string)($row[$key] ?? '')) === '') return false;
        }
        return true;
    }
}
