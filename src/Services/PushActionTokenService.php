<?php

final class PushActionTokenService
{
    public static function gerar(int $usuarioId, int $especialistaId, int $atendimentoId, string $acao, int $ttlSeconds = 7200): string
    {
        $acao = strtolower(trim($acao));
        if (!in_array($acao, ['aceitar', 'recusar'], true)) {
            throw new InvalidArgumentException('Acao invalida para token de push.');
        }

        $payload = [
            'uid' => $usuarioId,
            'esp_id' => $especialistaId,
            'aid' => $atendimentoId,
            'acao' => $acao,
            'exp' => time() + max(60, $ttlSeconds),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $json, self::secret(), true);
        return self::b64($json) . '.' . self::b64($sig);
    }

    public static function validar(string $token, string $acaoEsperada): array
    {
        $acaoEsperada = strtolower(trim($acaoEsperada));
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new RuntimeException('Token de push invalido.');
        }

        $payloadJson = self::unb64($parts[0]);
        $sig = self::unb64($parts[1]);
        $expected = hash_hmac('sha256', $payloadJson, self::secret(), true);
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('Token de push invalido.');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Token de push invalido.');
        }
        if (($payload['exp'] ?? 0) < time()) {
            throw new RuntimeException('Token de push expirado.');
        }
        if (($payload['acao'] ?? '') !== $acaoEsperada) {
            throw new RuntimeException('Token de push nao corresponde a acao esperada.');
        }

        return $payload;
    }

    private static function secret(): string
    {
        $secret = (string)(defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '');
        return $secret !== '' ? $secret : 'guinchafacil-push-secret';
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function unb64(string $value): string
    {
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($value, '-_', '+/'), true) ?: '';
    }
}
