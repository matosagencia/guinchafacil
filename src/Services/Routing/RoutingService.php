<?php
declare(strict_types=1);

require_once __DIR__ . '/../POR/PorThresholds.php';
require_once __DIR__ . '/../Logger.php';
require_once __DIR__ . '/../../Models/Configuracao.php';

/**
 * Cliente de roteamento server-side.
 *
 * Fonte primária: OpenRouteService (HeiGIT) — API pública com free tier
 * de 2000 rotas/dia. Endpoint oficial pós-abril/2026:
 *   https://api.heigit.org/openrouteservice/v2/directions/driving-car
 *
 * Fallback: OSRM (se configurado em PorThresholds::roadMatchBaseUrl())
 * Fallback final: null (o chamador decide se usa Haversine).
 *
 * Cache em memória por request. Fail-open. Nunca lança.
 */
final class RoutingService
{
    private static array $cache = [];
    private static array $fontes = [];
    private static ?string $ultimaFonte = null;
    private const TIMEOUT_MS = 2500;
    private const CONNECT_TIMEOUT_MS = 1200;
    private const ORS_DEFAULT_BASE = 'https://api.heigit.org/openrouteservice';

    public static function distanciaKm(float $latO, float $lngO, float $latD, float $lngD): ?float
    {
        if (defined('GF_ROUTING_QA_FIXTURE') && GF_ROUTING_QA_FIXTURE === true) {
            $fixtureKey = self::chaveCache($latO, $lngO, $latD, $lngD);
            self::$fontes[$fixtureKey] = 'qa_fixture:rua_do_proposito_36-rua_da_gamboa_247';
            self::$ultimaFonte = self::$fontes[$fixtureKey];
            self::$cache[$fixtureKey] = 1.25;
            return 1.25;
        }
        $key = self::chaveCache($latO, $lngO, $latD, $lngD);
        if (array_key_exists($key, self::$cache)) {
            self::$ultimaFonte = self::$fontes[$key] ?? null;
            return self::$cache[$key];
        }

        // 1. OpenRouteService (primário)
        $metros = self::chamarOrs($latO, $lngO, $latD, $lngD);

        $fonte = $metros !== null ? 'ors' : null;
        // 2. OSRM (fallback, se ORS falhar e OSRM estiver configurado)
        if ($metros === null) {
            $metros = self::chamarOsrm($latO, $lngO, $latD, $lngD);
            if ($metros !== null) $fonte = 'osrm';
        }

        // Bancada XAMPP: quando o DNS/rede externa está indisponível, permite
        // continuar testes locais com uma estimativa determinística. Nunca é
        // habilitado fora de APP_ENV=local e a fonte fica explícita no snapshot.
        if ($metros === null && self::fallbackLocalHabilitado()) {
            $metros = self::distanciaHaversineMetros($latO, $lngO, $latD, $lngD) * 1.25;
            $fonte = 'haversine_local';
        }

        self::$fontes[$key] = $fonte;
        self::$ultimaFonte = $fonte;
        self::$cache[$key] = $metros !== null ? round($metros / 1000, 2) : null;
        return self::$cache[$key];
    }

    public static function ultimaFonte(): ?string { return self::$ultimaFonte; }

    public static function limparCache(): void
    {
        self::$cache = [];
        self::$fontes = [];
        self::$ultimaFonte = null;
    }

    /**
     * OpenRouteService v2 directions.
     * POST JSON, API key no header Authorization.
     */
    private static function chamarOrs(float $latO, float $lngO, float $latD, float $lngD): ?float
    {
        if (!self::coordsValidas($latO, $lngO) || !self::coordsValidas($latD, $lngD)) {
            return null;
        }

        $cfg = Configuracao::getAll();
        $apiKey = trim((string)(
            ($_ENV['ORS_API_KEY'] ?? '')
            ?: ($_SERVER['ORS_API_KEY'] ?? '')
            ?: (function_exists('env') ? (string)env('ORS_API_KEY', '') : '')
            ?: (string)(getenv('ORS_API_KEY') ?: '')
            ?: (string)($cfg['ors_api_key'] ?? '')
        ));

        $base = trim((string)(getenv('ORS_BASE_URL') ?: ($cfg['ors_base_url'] ?? '')));
        if ($base === '') {
            $base = self::ORS_DEFAULT_BASE;
        }
        $url = rtrim($base, '/') . '/v2/directions/driving-car';

        $body = json_encode([
            'coordinates' => [
                [(float)$lngO, (float)$latO],
                [(float)$lngD, (float)$latD],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            CURLOPT_TIMEOUT_MS => self::TIMEOUT_MS,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: ' . $apiKey,
                'User-Agent: GuinchaFacil-Routing/2.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        self::aplicarCaBundle($ch);

        $resposta = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '' || $resposta === false || $httpCode >= 400) {
            Logger::event([
                'level' => Logger::LEVEL_WARN,
                'class' => __CLASS__,
                'function' => __FUNCTION__,
                'system' => 'ROUTING',
                'phase' => 'ors_call',
                'code' => 'ROUTING-ORS-FAIL',
                'message' => 'Falha ao consultar OpenRouteService.',
                'context' => [
                    'url'      => $url,
                    'http'     => $httpCode,
                    'curl_err' => $curlErr,
                    'trecho'   => substr((string)$resposta, 0, 200),
                ],
            ]);
            return null;
        }

        $decoded = json_decode((string)$resposta, true);
        if (!is_array($decoded)) {
            return null;
        }
        $dist = $decoded['routes'][0]['summary']['distance'] ?? null;
        if (!is_numeric($dist)) {
            return null;
        }
        return (float)$dist; // metros
    }

    /**
     * OSRM (fallback). Mantido para quando uma instância própria estiver ativa.
     */
    private static function chamarOsrm(float $latO, float $lngO, float $latD, float $lngD): ?float
    {
        if (!self::coordsValidas($latO, $lngO) || !self::coordsValidas($latD, $lngD)) {
            return null;
        }

        $base = rtrim(PorThresholds::roadMatchBaseUrl(), '/');
        $coords = sprintf('%.6F,%.6F;%.6F,%.6F', $lngO, $latO, $lngD, $latD);
        $url = $base . '/route/v1/driving/' . $coords . '?overview=false&alternatives=false&steps=false';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            CURLOPT_TIMEOUT_MS => self::TIMEOUT_MS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        self::aplicarCaBundle($ch);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '' || $body === false || $httpCode >= 400) {
            return null;
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded) || ($decoded['code'] ?? '') !== 'Ok') {
            return null;
        }
        $dist = $decoded['routes'][0]['distance'] ?? null;
        return is_numeric($dist) ? (float)$dist : null;
    }

    private static function chaveCache(float $a, float $b, float $c, float $d): string
    {
        return sprintf('%.4F,%.4F;%.4F,%.4F', $a, $b, $c, $d);
    }

    private static function coordsValidas(float $lat, float $lng): bool
    {
        if (!is_finite($lat) || !is_finite($lng)) return false;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
        return !($lat === 0.0 && $lng === 0.0);
    }

    private static function fallbackLocalHabilitado(): bool
    {
        $ambiente = strtolower(trim((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ''))));
        return in_array($ambiente, ['local', 'development', 'dev'], true);
    }

    private static function distanciaHaversineMetros(float $latO, float $lngO, float $latD, float $lngD): float
    {
        $r = 6371008.8;
        $dLat = deg2rad($latD - $latO);
        $dLng = deg2rad($lngD - $lngO);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latO)) * cos(deg2rad($latD)) * sin($dLng / 2) ** 2;
        return $r * 2 * asin(min(1.0, sqrt($a)));
    }
    
        /**
     * Aplica CA bundle no handle cURL, com fallback para os caminhos
     * padrão do XAMPP no Windows. Sem isso, o cURL do XAMPP falha com
     * "unable to get local issuer certificate" em qualquer HTTPS.
     */
    private static function aplicarCaBundle($ch): void
    {
        // 1. ca_bundle_path() do projeto (se existir)
        if (function_exists('ca_bundle_path')) {
            $ca = ca_bundle_path();
            if (is_string($ca) && $ca !== '' && is_file($ca)) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca);
                return;
            }
        }

        // 2. ini curl.cainfo
        $ini = (string)ini_get('curl.cainfo');
        if ($ini !== '' && is_file($ini)) {
            curl_setopt($ch, CURLOPT_CAINFO, $ini);
            return;
        }

        // 3. Fallbacks XAMPP Windows
        foreach ([
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            'C:/xampp/php/extras/ssl/cacert.pem',
            (string)ini_get('openssl.cafile'),
        ] as $candidato) {
            if ($candidato !== '' && is_file($candidato)) {
                curl_setopt($ch, CURLOPT_CAINFO, $candidato);
                return;
            }
        }
        // Nenhum achado: cURL cai no default do sistema (funciona em Linux/cPanel).
    }
}
