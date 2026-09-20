<?php

/** OAuth 2.0 server-side flow for Google accounts. */
class GoogleOAuthService
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';

    public static function enabled(): bool
    {
        return trim((string)(defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '')) !== ''
            && trim((string)(defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '')) !== '';
    }

    public static function redirectUri(): string
    {
        return rtrim((string)(defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : ''), '/')
            ?: rtrim((string)(defined('APP_URL') ? APP_URL : ''), '/') . '/auth/google/callback';
    }

    public static function createAuthorizationUrl(string $returnPath = '/'): string
    {
        if (!self::enabled()) {
            throw new RuntimeException('Login com Google ainda não foi configurado.');
        }

        $state = bin2hex(random_bytes(32));
        $_SESSION['google_oauth_state'] = [
            'value' => $state,
            'created_at' => time(),
            'return' => AuthService::sanitizeReturnPath($returnPath),
        ];

        return self::AUTH_ENDPOINT . '?' . http_build_query([
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => bin2hex(random_bytes(24)),
            'access_type' => 'online',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function consumeState(string $state): ?string
    {
        $saved = $_SESSION['google_oauth_state'] ?? null;
        unset($_SESSION['google_oauth_state']);
        if (!is_array($saved) || !isset($saved['value'], $saved['created_at'])
            || !is_string($saved['value']) || !hash_equals($saved['value'], $state)
            || (time() - (int)$saved['created_at']) > 600) {
            return null;
        }
        return AuthService::sanitizeReturnPath((string)($saved['return'] ?? '/'));
    }

    public static function fetchIdentity(string $code): array
    {
        $response = self::request(self::TOKEN_ENDPOINT, [
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        $accessToken = (string)($response['access_token'] ?? '');
        if ($accessToken === '') throw new RuntimeException('Google não retornou um token de acesso.');

        $identity = self::request(self::USERINFO_ENDPOINT, [], [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);
        $subject = trim((string)($identity['sub'] ?? ''));
        $email = strtolower(trim((string)($identity['email'] ?? '')));
        if ($subject === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($identity['email_verified'])) {
            throw new RuntimeException('A conta Google não possui email verificado.');
        }

        return [
            'subject' => $subject,
            'email' => $email,
            'name' => trim((string)($identity['name'] ?? '')) ?: $email,
            'picture' => trim((string)($identity['picture'] ?? '')),
        ];
    }

    private static function request(string $url, array $fields, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Não foi possível iniciar a conexão com o Google.');
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($fields !== []) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
            $options[CURLOPT_HTTPHEADER] = array_merge(['Content-Type: application/x-www-form-urlencoded'], $headers);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('Falha na comunicação com o Google.');
        }
        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) throw new RuntimeException('Resposta inválida do Google.');
        return $decoded;
    }
}
