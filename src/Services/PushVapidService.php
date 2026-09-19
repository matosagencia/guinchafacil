<?php

final class PushVapidService
{
    public static function publicKey(): string
    {
        $configured = trim((string)(defined('PUSH_VAPID_PUBLIC_KEY') ? PUSH_VAPID_PUBLIC_KEY : ''));
        if ($configured !== '') {
            return $configured;
        }

        $pem = self::privateKeyPem();
        if ($pem === '') {
            return '';
        }

        $resource = openssl_pkey_get_private($pem);
        if (!$resource) {
            return '';
        }

        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || empty($details['ec']['x']) || empty($details['ec']['y'])) {
            return '';
        }

        return self::base64UrlEncode("\x04" . $details['ec']['x'] . $details['ec']['y']);
    }

    public static function privateKeyPem(): string
    {
        $value = trim((string)(defined('PUSH_VAPID_PRIVATE_KEY') ? PUSH_VAPID_PRIVATE_KEY : ''));
        if ($value === '') {
            return '';
        }

        return str_replace(["\r\n", "\r", "\\n"], "\n", $value);
    }

    public static function authorizationHeaders(string $endpoint): array
    {
        $publicKey = self::publicKey();
        $privateKey = self::privateKeyPem();
        if ($publicKey === '' || $privateKey === '') {
            throw new RuntimeException('Chaves VAPID nao configuradas.');
        }

        $audience = self::audienceFromEndpoint($endpoint);
        $jwt = self::jwt($audience, $privateKey);

        return [
            'Authorization: vapid t=' . $jwt . ', k=' . $publicKey,
            'Crypto-Key: p256ecdsa=' . $publicKey,
            'TTL: 60',
            'Content-Length: 0',
        ];
    }

    private static function jwt(string $audience, string $privateKeyPem): string
    {
        $header = self::base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'typ' => 'JWT',
        ], JSON_UNESCAPED_SLASHES));

        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => defined('PUSH_VAPID_SUBJECT') && PUSH_VAPID_SUBJECT !== '' ? PUSH_VAPID_SUBJECT : (defined('APP_URL') ? APP_URL : 'https://localhost'),
        ], JSON_UNESCAPED_SLASHES));

        $unsigned = $header . '.' . $payload;
        $signature = '';
        $key = openssl_pkey_get_private($privateKeyPem);
        if (!$key || !openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Falha ao assinar JWT VAPID.');
        }

        return $unsigned . '.' . self::base64UrlEncode(self::derToJose($signature, 64));
    }

    private static function audienceFromEndpoint(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Endpoint de push invalido.');
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function derToJose(string $der, int $length): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new RuntimeException('Assinatura DER invalida.');
        }
        self::readDerLength($der, $offset);

        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Assinatura DER invalida.');
        }
        $rLen = self::readDerLength($der, $offset);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Assinatura DER invalida.');
        }
        $sLen = self::readDerLength($der, $offset);
        $s = substr($der, $offset, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, $length / 2, "\x00", STR_PAD_LEFT) . str_pad($s, $length / 2, "\x00", STR_PAD_LEFT);
    }

    private static function readDerLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++]);
        if (($length & 0x80) === 0) {
            return $length;
        }

        $byteCount = $length & 0x7F;
        $length = 0;
        for ($i = 0; $i < $byteCount; $i++) {
            $length = ($length << 8) | ord($der[$offset++]);
        }
        return $length;
    }
}
