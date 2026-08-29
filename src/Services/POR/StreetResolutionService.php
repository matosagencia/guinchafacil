<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Services/GeoService.php';
require_once __DIR__ . '/../../Services/GeocodingService.php';
require_once __DIR__ . '/../../Services/RateLimiter.php';
require_once __DIR__ . '/MapMatchingService.php';
require_once __DIR__ . '/PorThresholds.php';

class StreetResolutionService
{
    public static function resolve(array $pedido, float $lat, float $lng, string $fase): array
    {
        $match = MapMatchingService::matchPoint($pedido, $lat, $lng, $fase);
        $origem = self::extractStreet((string)($pedido['endereco_origem'] ?? 'Origem'));
        $destino = self::extractStreet((string)($pedido['endereco_destino'] ?? 'Destino'));

        $street = $fase === 'origem' ? $origem : $destino;
        $source = 'pedido_address';
        $confidence = 0.55;

        if (!empty($match['matched']) && !empty($match['street_hint'])) {
            return [
                'street_name' => (string)$match['street_hint'],
                'street_source' => (string)$match['source'],
                'match_confidence' => (float)$match['confidence'],
            ];
        }

        $distOrigem = isset($pedido['lat_origem'], $pedido['lng_origem'])
            ? GeoService::haversine($lat, $lng, (float)$pedido['lat_origem'], (float)$pedido['lng_origem']) * 1000
            : null;
        $distDestino = isset($pedido['lat_destino'], $pedido['lng_destino'])
            ? GeoService::haversine($lat, $lng, (float)$pedido['lat_destino'], (float)$pedido['lng_destino']) * 1000
            : null;

        if ($distOrigem !== null && $distOrigem <= PorThresholds::arrivalRadiusM()) {
            $street = $origem;
            $source = 'origin_geofence';
            $confidence = 0.92;
        } elseif ($distDestino !== null && $distDestino <= PorThresholds::destinationRadiusM()) {
            $street = $destino;
            $source = 'destination_geofence';
            $confidence = 0.92;
        } else {
            // §A3 — cada ponto GPS fora das geofences de origem/destino cai
            // aqui, e o cache de GeocodingService (por célula ~11m) não
            // protege um veículo em movimento (cada ponto novo é uma célula
            // nova). Sem limite, isso é uma rajada de requisições ao
            // Nominatim proporcional à frequência de envio de GPS do app.
            // Reaproveita o RateLimiter já usado em PagamentoController
            // (chave por IP+rota), aqui com rota fixa dedicada ao POR — o IP
            // do dispositivo do guincho já é o identificador natural da
            // sessão de rastreamento.
            $rateLimiter = new RateLimiter();
            $rlRota = 'por_reverse_geocode';
            if (!$rateLimiter->checkLimit($rlRota, 6, 60)) {
                $source = 'rate_limited_fallback';
                $confidence = 0.45;
            } else {
                $rateLimiter->recordAttempt($rlRota, 6, 60);
                try {
                    $reverse = (new GeocodingService())->reverseGeocode($lat, $lng);
                    if ($reverse && !empty($reverse['street'])) {
                        $street = self::extractStreet((string)$reverse['street']);
                        $source = (string)($reverse['source'] ?? 'reverse_geocode');
                        $confidence = 0.84;
                    }
                } catch (Throwable) {
                    $source = 'pedido_address_fallback';
                    $confidence = 0.5;
                }
            }
        }

        return [
            'street_name' => $street,
            'street_source' => $source,
            'match_confidence' => $confidence,
        ];
    }

    private static function extractStreet(string $address): string
    {
        $address = trim($address);
        if ($address === '') {
            return 'Sem rua informada';
        }

        $parts = preg_split('/\s*,\s*/', $address);
        return trim((string)($parts[0] ?? $address));
    }
}
