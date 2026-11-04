<?php
declare(strict_types=1);

final class MarketingEventService
{
    public const FUNNEL_EVENTS = [
        'landing_view','service_view','quote_started','quote_completed','quote_unavailable',
        'whatsapp_fallback','registration_started','registration_completed','vehicle_completed',
        'checkout_started','payment_selected','order_created','partner_page_view',
        'partner_interest_submitted','partner_approved','partner_first_order','referral_converted',
    ];

    public static function recordFunnel(string $event, array $metadata = [], ?string $label = null): bool
    {
        if (!in_array($event, self::FUNNEL_EVENTS, true)) return false;
        return self::record([
            'event_name' => $event,
            'event_label' => $label,
            'page_path' => $_SERVER['REQUEST_URI'] ?? null,
            'metadata' => $metadata,
            'consent_status' => $_SESSION['marketing_consent'] ?? 'unknown',
        ]);
    }

    public static function recordConsent(string $type, string $value, ?string $source = null): bool
    {
        if (!in_array($value, ['granted', 'denied', 'withdrawn'], true)) return false;
        try {
            getPDO()->prepare('INSERT INTO marketing_consents (session_hash,user_id,consent_type,consent_value,policy_version,source,ip_hash) VALUES (?,?,?,?,?,?,?)')->execute([
                self::sessionHash(), !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null,
                mb_substr($type, 0, 50), $value, 'v1', self::text($source, 120), hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
            ]);
            $_SESSION['marketing_consent'] = $value === 'granted' ? 'yes' : 'no';
            return true;
        } catch (Throwable $e) { error_log('[MarketingEventService] consent not stored: ' . $e->getMessage()); return false; }
    }

    public static function record(array $input): bool
    {
        $event = preg_replace('/[^a-z0-9_\-]/i', '_', trim((string)($input['event_name'] ?? '')));
        if ($event === '' || strlen($event) > 80) return false;

        $user = $_SESSION['user'] ?? [];
        $attribution = class_exists('MarketingAttributionService')
            ? MarketingAttributionService::current()
            : [];
        $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
        $metadata = array_slice($metadata, 0, 20, true);

        try {
            $sql = 'INSERT INTO marketing_events
                (event_name,event_label,page_path,destination,user_type,consent_status,session_hash,user_id,
                 utm_source,utm_medium,utm_campaign,utm_content,utm_term,canal_aquisicao,metadata_json)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            getPDO()->prepare($sql)->execute([
                $event,
                self::text($input['event_label'] ?? null, 120),
                self::text($input['page_path'] ?? null, 500),
                self::text($input['destination'] ?? null, 500),
                self::text($input['user_type'] ?? 'visitante', 30) ?: 'visitante',
                self::text($input['consent_status'] ?? 'unknown', 10) ?: 'unknown',
                self::sessionHash(),
                !empty($user['id']) ? (int)$user['id'] : null,
                $attribution['utm_source'] ?? null,
                $attribution['utm_medium'] ?? null,
                $attribution['utm_campaign'] ?? null,
                $attribution['utm_content'] ?? null,
                $attribution['utm_term'] ?? null,
                $attribution['canal_aquisicao'] ?? 'organico',
                $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('[MarketingEventService] event not stored: ' . $e->getMessage());
            return false;
        }
    }

    private static function text(mixed $value, int $limit): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private static function sessionHash(): string
    {
        return hash('sha256', session_id() . '|' . (defined('APP_KEY') ? APP_KEY : 'guinchafacil'));
    }
}
