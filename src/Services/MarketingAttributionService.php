<?php
declare(strict_types=1);

final class MarketingAttributionService
{
    private const SESSION_KEY = 'first_touch_utm';
    private const COOKIE_KEY = 'gf_first_touch';
    private const FIELDS = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','canal_aquisicao','referrer_url','landing_page'];

    public static function capture(): void
    {
        $current = $_SESSION[self::SESSION_KEY] ?? [];
        $raw = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string)($_GET[$field] ?? ''));
            if ($value !== '') $raw[$field] = mb_substr($value, 0, $field === 'referrer_url' || $field === 'landing_page' ? 1000 : 180);
        }
        if (!$current) {
            $cookie = json_decode((string)($_COOKIE[self::COOKIE_KEY] ?? ''), true);
            if (is_array($cookie)) $current = $cookie;
        }
        if (!$current) {
            $current = [
                'utm_source' => $raw['utm_source'] ?? null,
                'utm_medium' => $raw['utm_medium'] ?? null,
                'utm_campaign' => $raw['utm_campaign'] ?? null,
                'utm_content' => $raw['utm_content'] ?? null,
                'utm_term' => $raw['utm_term'] ?? null,
                'canal_aquisicao' => self::normalizarCanal($raw['canal_aquisicao'] ?? ($raw['utm_source'] ?? 'organico')),
                'referrer_url' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
                'landing_page' => (string)($_SERVER['REQUEST_URI'] ?? '/'),
                'captured_at' => date('c'),
            ];
            $_SESSION[self::SESSION_KEY] = $current;
            setcookie(self::COOKIE_KEY, json_encode($current, JSON_UNESCAPED_SLASHES), [
                'expires' => time() + 60 * 60 * 24 * 90, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax'
            ]);
        }
    }

    public static function current(): array
    {
        self::capture();
        $a = $_SESSION[self::SESSION_KEY] ?? [];
        return array_merge(['canal_aquisicao' => 'organico'], array_intersect_key($a, array_flip(self::FIELDS)));
    }

    public static function forPedido(): array { return self::current(); }

    public static function normalizarCanal(string $canal): string
    {
        $canal = strtolower(trim($canal));
        $map = ['google' => 'google_ads', 'googleads' => 'google_ads', 'meta' => 'meta_ads', 'facebook' => 'meta_ads', 'instagram' => 'meta_ads', 'referral' => 'indicacao', 'referrer' => 'indicacao', 'direct' => 'organico'];
        return $map[$canal] ?? ($canal !== '' ? preg_replace('/[^a-z0-9_\-]/', '_', $canal) : 'organico');
    }
}
