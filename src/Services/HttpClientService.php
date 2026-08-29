<?php

declare(strict_types=1);

/**
 * Cliente HTTP mínimo via cURL para integrações externas.
 */
class HttpClientService
{
    public static function getJson(string $url, array $headers = [], int $timeout = 10): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $headers[] = 'Accept: application/json';

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (function_exists('ca_bundle_path') && ($ca = ca_bundle_path())) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        }

        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            error_log('[HttpClientService] GET falhou: ' . ($err ?: ('HTTP ' . $code)) . ' url=' . $url);
            return null;
        }

        $decoded = json_decode((string)$body, true);
        return is_array($decoded) ? $decoded : null;
    }
}
