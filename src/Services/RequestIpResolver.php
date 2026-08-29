<?php

declare(strict_types=1);

/**
 * §IP-CANONICO-01: ponto único de resolução do "IP do cliente" usado em
 * decisões de segurança (rate limit) e trilha de auditoria (logs).
 *
 * Antes desta classe, 4 lugares diferentes (RateLimiter, AuthService,
 * DemandaService, Logger) confiavam direto em `X-Forwarded-For` sempre que
 * o header estava presente — e esse header é enviado pelo CLIENTE, não pelo
 * servidor: qualquer requisição pode incluir um `X-Forwarded-For` arbitrário
 * sem passar por proxy nenhum. Na prática isso permitia:
 *   1) furar o rate-limit de login/pagamento trocando o valor do header a
 *      cada tentativa (a chave (ip, rota) do RateLimiter nunca colidia);
 *   2) poluir logs/auditoria com IPs falsos.
 *
 * `REMOTE_ADDR` é preenchido pelo Apache a partir do socket TCP real da
 * conexão — não pode ser forjado pelo cliente. Por padrão, é a ÚNICA fonte
 * confiável. `X-Forwarded-For` só passa a ser considerado se a conexão
 * chegou de um proxy explicitamente marcado como confiável via
 * `TRUSTED_PROXIES` (lista de IPs/CIDRs no .env, vazia por padrão — este
 * projeto roda em XAMPP direto, sem reverse proxy na frente). Quando
 * confiável, usa o ÚLTIMO IP da cadeia (o mais próximo do nosso proxy,
 * portanto o que ele mesmo anexou), não o primeiro (controlado pelo
 * cliente).
 */
final class RequestIpResolver
{
    public static function resolve(): string
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr === '') {
            return '0.0.0.0';
        }

        if (!self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        $xff = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff === '') {
            return $remoteAddr;
        }

        $partes = array_map('trim', explode(',', $xff));
        $ultimo = end($partes);

        return $ultimo !== '' && filter_var($ultimo, FILTER_VALIDATE_IP) !== false
            ? $ultimo
            : $remoteAddr;
    }

    private static function isTrustedProxy(string $ip): bool
    {
        $lista = trim((string)(defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : ''));
        if ($lista === '') {
            return false;
        }

        foreach (array_map('trim', explode(',', $lista)) as $faixa) {
            if ($faixa === '') {
                continue;
            }
            if (str_contains($faixa, '/')) {
                if (self::ipEmCidr($ip, $faixa)) {
                    return true;
                }
            } elseif ($faixa === $ip) {
                return true;
            }
        }

        return false;
    }

    private static function ipEmCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, '32');
        $bits = (int)$bits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
