<?php
// File: guinchafacil/src/Services/GeocodingService.php

class GeocodingService
{
    private const USER_AGENT = 'GuinchaFacil/1.0';
    private const DEFAULT_CELL_PRECISION = 4;

    /** @var array<string, ?array> */
    private static array $cache = [];

    /** @var array<string, ?array> */
    private static array $cepCache = [];

    /** @var array<string, ?array> */
    private static array $reverseCache = [];

    /**
     * Geocoding via Nominatim com normalizacao e fallback progressivo.
     * Retorna ['lat'=>..., 'lng'=>..., 'display_name'=>...] ou null.
     */
    public function geocode(string $address): ?array
    {
        $originalAddress = trim($address);
        $queries = self::buildQueries($address);
        foreach ($queries as $query) {
            if (array_key_exists($query, self::$cache)) {
                if (self::$cache[$query] !== null) {
                    return self::preserveHouseNumber($originalAddress, self::$cache[$query]);
                }
                continue;
            }

            // Cache persistente (tabela geocoding_cache, tipo='forward') —
            // antes só existia cache em memória estática (array), que morre
            // a cada request e nunca evita bater no Nominatim de novo pro
            // mesmo endereço em requisições diferentes. A tabela e o enum
            // 'forward' já existiam no schema (install/migrate.php) sem
            // nenhum código usando — só o reverse geocoding tinha isso.
            $cacheKey = self::forwardCacheKey($query);
            $cached = self::readForwardCache($cacheKey);
            if ($cached !== null) {
                self::$cache[$query] = $cached;
                return self::preserveHouseNumber($originalAddress, $cached);
            }

            $result = self::queryNominatim($query);
            self::$cache[$query] = $result;
            if ($result !== null) {
                self::writeForwardCache($cacheKey, $query, $result);
                return self::preserveHouseNumber($originalAddress, $result);
            }
        }

        return null;
    }

    /** Mantém o número digitado mesmo quando o provedor devolve apenas a rua. */
    private static function preserveHouseNumber(string $original, array $result): array
    {
        $number = self::extractHouseNumber($original);
        $display = trim((string)($result['display_name'] ?? ''));
        if ($number === null || $display === '') return $result;
        $first = trim((string)(preg_split('/\s*,\s*/', $display)[0] ?? ''));
        if (preg_match('/(?:^|\s)' . preg_quote($number, '/') . '(?:[A-Za-z]?)(?:\s|$)/u', $first)) return $result;
        $parts = preg_split('/\s*,\s*/', $display);
        $parts[0] = rtrim((string)$parts[0]) . ' ' . $number;
        $result['display_name'] = implode(', ', $parts);
        $result['house_number'] = $number;
        return $result;
    }

    private static function extractHouseNumber(string $address): ?string
    {
        $address = trim($address);
        if (preg_match('/(?:^|,|\s)(?:n[ºo°.]?\s*)?(\d+[A-Za-z]?)(?=\s*(?:,|$))/iu', $address, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function forwardCacheKey(string $query): string
    {
        return 'fwd:' . hash('sha256', mb_strtolower(trim($query)));
    }

    private static function readForwardCache(string $cacheKey): ?array
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT response_json
                   FROM geocoding_cache
                  WHERE cache_key = ?
                    AND tipo = 'forward'
                    AND (expires_at IS NULL OR expires_at >= NOW())
                  LIMIT 1"
            );
            $stmt->execute([$cacheKey]);
            $json = $stmt->fetchColumn();
            if (!$json) {
                return null;
            }

            $decoded = json_decode((string)$json, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function writeForwardCache(string $cacheKey, string $query, array $result): void
    {
        try {
            $ttlDays = defined('GEOCODING_CACHE_TTL_DAYS') ? max(1, (int)GEOCODING_CACHE_TTL_DAYS) : 30;
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO geocoding_cache (
                    cache_key, tipo, query_text, latitude, longitude, response_json, expires_at, created_at, updated_at
                ) VALUES (
                    ?, 'forward', ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL {$ttlDays} DAY), NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    response_json = VALUES(response_json),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()"
            );
            $stmt->execute([
                $cacheKey,
                mb_substr($query, 0, 255),
                $result['lat'] ?? null,
                $result['lng'] ?? null,
                json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            // Cache forward é best-effort — falha aqui nunca deve derrubar o geocode.
        }
    }

    private static function buildQueries(string $address): array
    {
        $full = self::normalizeAddress($address);
        if ($full === '') {
            return [];
        }

        $queries = [
            $full,
            self::stripPostalAndCountryNoise($full),
            self::extractStreetCityState($full),
            self::extractStreetPostalCode($full),
        ];

        return array_values(array_unique(array_filter(array_map('trim', $queries))));
    }

    private static function normalizeAddress(string $address): string
    {
        $address = trim($address);
        $address = preg_replace('/,\s*Brasil(\s*,\s*Brasil)+/iu', ', Brasil', $address) ?? $address;
        $address = preg_replace('/,\s*Regi[aã]o Geogr[aá]fica Imediata[^,]*/iu', '', $address) ?? $address;
        $address = preg_replace('/,\s*Regi[aã]o Metropolitana[^,]*/iu', '', $address) ?? $address;
        $address = preg_replace('/,\s*Regi[aã]o Geogr[aá]fica Intermedi[aá]ria[^,]*/iu', '', $address) ?? $address;
        $address = preg_replace('/\s+/', ' ', $address) ?? $address;
        return trim($address, " \t\n\r\0\x0B,");
    }

    private static function stripPostalAndCountryNoise(string $address): string
    {
        $clean = preg_replace('/\b\d{5}-?\d{3}\b/u', '', $address) ?? $address;
        $clean = preg_replace('/,\s*Brasil$/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*,\s*,+/', ',', $clean) ?? $clean;
        return trim($clean, " \t\n\r\0\x0B,");
    }

    private static function extractStreetCityState(string $address): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        if (count($parts) <= 3) {
            return $address;
        }

        return implode(', ', array_slice($parts, 0, 3));
    }

    private static function extractStreetPostalCode(string $address): string
    {
        if (!preg_match('/\b\d{5}-?\d{3}\b/u', $address, $match)) {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
        $street = $parts[0] ?? '';
        return trim($street . ', ' . $match[0], " \t\n\r\0\x0B,");
    }

    private static function queryNominatim(string $query): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?format=json&countrycodes=br&limit=1&q=' . urlencode($query);

        $json = self::httpGet($url, [
            'User-Agent: ' . self::USER_AGENT,
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
        ]);
        if (!$json) {
            return null;
        }

        $arr = json_decode($json, true);
        if (!is_array($arr) || empty($arr[0])) {
            return null;
        }

        $item = $arr[0];
        $lat = isset($item['lat']) ? (float)$item['lat'] : null;
        $lng = isset($item['lon']) ? (float)$item['lon'] : null;
        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'display_name' => $item['display_name'] ?? null,
        ];
    }

    public static function buscarCep(string $cep): ?array
    {
        $cep = preg_replace('/[^0-9]/', '', $cep) ?? '';
        if (strlen($cep) !== 8) {
            return null;
        }

        if (array_key_exists($cep, self::$cepCache)) {
            return self::$cepCache[$cep];
        }

        $cacheKey = 'cep:' . $cep;
        $cached = self::readCepCache($cacheKey);
        if ($cached !== null) {
            self::$cepCache[$cep] = $cached;
            return $cached;
        }

        $json = self::httpGet("https://viacep.com.br/ws/{$cep}/json/", [
            'User-Agent: ' . self::USER_AGENT,
            'Accept: application/json',
        ]);
        if (!$json) {
            self::$cepCache[$cep] = null;
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || isset($data['erro'])) {
            self::$cepCache[$cep] = null;
            return null;
        }

        self::$cepCache[$cep] = [
            'cep' => $data['cep'] ?? $cep,
            'logradouro' => $data['logradouro'] ?? '',
            'bairro' => $data['bairro'] ?? '',
            'localidade' => $data['localidade'] ?? '',
            'uf' => $data['uf'] ?? '',
        ];

        self::writeCepCache($cacheKey, $cep, self::$cepCache[$cep]);
        return self::$cepCache[$cep];
    }

    private static function readCepCache(string $cacheKey): ?array
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT response_json
                   FROM geocoding_cache
                  WHERE cache_key = ?
                    AND tipo = 'cep'
                    AND (expires_at IS NULL OR expires_at >= NOW())
                  LIMIT 1"
            );
            $stmt->execute([$cacheKey]);
            $json = $stmt->fetchColumn();
            if (!$json) {
                return null;
            }

            $decoded = json_decode((string)$json, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function writeCepCache(string $cacheKey, string $cep, array $result): void
    {
        try {
            // CEPs praticamente não mudam de endereço — TTL bem mais longo
            // que o geocoding livre (que pode ter endereços ambíguos).
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO geocoding_cache (
                    cache_key, tipo, query_text, response_json, expires_at, created_at, updated_at
                ) VALUES (
                    ?, 'cep', ?, ?, DATE_ADD(NOW(), INTERVAL 180 DAY), NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    response_json = VALUES(response_json),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()"
            );
            $stmt->execute([$cacheKey, $cep, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        } catch (Throwable $e) {
            // Cache CEP é best-effort.
        }
    }

    /**
     * Reverse geocoding com cache por célula geográfica.
     * Retorna ['street'=>..., 'display_name'=>..., 'source'=>...] ou null.
     */
    public function reverseGeocode(float $lat, float $lng, ?int $precision = null): ?array
    {
        $precision = $precision ?? self::DEFAULT_CELL_PRECISION;
        $cacheKey = self::reverseCacheKey($lat, $lng, $precision);

        if (array_key_exists($cacheKey, self::$reverseCache)) {
            return self::$reverseCache[$cacheKey];
        }

        $cached = self::readReverseCache($cacheKey);
        if ($cached !== null) {
            self::$reverseCache[$cacheKey] = $cached;
            return $cached;
        }

        $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&addressdetails=1&lat='
            . urlencode((string)$lat) . '&lon=' . urlencode((string)$lng);

        $json = self::httpGet($url, [
            'User-Agent: ' . self::USER_AGENT,
            'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
        ]);
        if (!$json) {
            self::$reverseCache[$cacheKey] = null;
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            self::$reverseCache[$cacheKey] = null;
            return null;
        }

        $resolved = self::normalizeReverseResult($data);
        self::$reverseCache[$cacheKey] = $resolved;
        if ($resolved !== null) {
            self::writeReverseCache($cacheKey, $lat, $lng, $resolved);
        }

        return $resolved;
    }

    private static function httpGet(string $url, array $headers): ?string
    {
        if (!function_exists('curl_init')) {
            error_log('[GeocodingService] cURL indisponivel para requisicao HTTP.');
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '' || $httpCode < 200 || $httpCode >= 300) {
            error_log("[GeocodingService] Falha HTTP {$httpCode}: {$error}");
            return null;
        }

        return (string)$response;
    }

    private static function reverseCacheKey(float $lat, float $lng, int $precision): string
    {
        return 'rev:' . number_format($lat, $precision, '.', '') . ':' . number_format($lng, $precision, '.', '');
    }

    private static function normalizeReverseResult(array $data): ?array
    {
        $address = is_array($data['address'] ?? null) ? $data['address'] : [];
        $displayName = trim((string)($data['display_name'] ?? ''));
        $street = self::pickStreetName($address, $displayName);
        if ($street === '') {
            return null;
        }

        return [
            'street' => $street,
            'display_name' => $displayName,
            'source' => 'reverse_geocode',
        ];
    }

    private static function pickStreetName(array $address, string $displayName): string
    {
        $candidates = [
            $address['road'] ?? null,
            $address['pedestrian'] ?? null,
            $address['residential'] ?? null,
            $address['footway'] ?? null,
            $address['cycleway'] ?? null,
            $address['path'] ?? null,
            $address['suburb'] ?? null,
            $address['neighbourhood'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if ($displayName !== '') {
            $parts = preg_split('/\s*,\s*/', $displayName);
            return trim((string)($parts[0] ?? ''));
        }

        return '';
    }

    private static function readReverseCache(string $cacheKey): ?array
    {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT response_json
                   FROM geocoding_cache
                  WHERE cache_key = ?
                    AND tipo = 'reverse'
                    AND (expires_at IS NULL OR expires_at >= NOW())
                  LIMIT 1"
            );
            $stmt->execute([$cacheKey]);
            $json = $stmt->fetchColumn();
            if (!$json) {
                return null;
            }

            $decoded = json_decode((string)$json, true);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function writeReverseCache(string $cacheKey, float $lat, float $lng, array $resolved): void
    {
        try {
            $ttlDays = defined('GEOCODING_CACHE_TTL_DAYS') ? max(1, (int)GEOCODING_CACHE_TTL_DAYS) : 30;
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "INSERT INTO geocoding_cache (
                    cache_key, tipo, latitude, longitude, response_json, expires_at, created_at, updated_at
                ) VALUES (
                    ?, 'reverse', ?, ?, ?, DATE_ADD(NOW(), INTERVAL {$ttlDays} DAY), NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    response_json = VALUES(response_json),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()"
            );
            $stmt->execute([
                $cacheKey,
                $lat,
                $lng,
                json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            // Cache reverse é best-effort.
        }
    }
}
