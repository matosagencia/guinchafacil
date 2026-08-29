<?php

declare(strict_types=1);

require_once __DIR__ . '/GeofenceService.php';
require_once __DIR__ . '/PorThresholds.php';

class MapMatchingService
{
    public static function matchPoint(array $pedido, float $lat, float $lng, string $fase): array
    {
        // §A3 — antes hardcoded em 180m, divergindo dos 150m que
        // EvidenceService/PedidoTransitionService usam pro mesmo conceito de
        // "chegou na origem" (ver PorThresholds).
        $radiusOrigin = PorThresholds::arrivalRadiusM();
        $radiusDestination = PorThresholds::destinationRadiusM();

        if (GeofenceService::isNearOrigin($pedido, $lat, $lng, $radiusOrigin)) {
            return [
                'matched' => true,
                'street_hint' => self::extractStreet((string)($pedido['endereco_origem'] ?? 'Origem')),
                'source' => 'geofence_origin_match',
                'confidence' => 0.96,
            ];
        }

        if (GeofenceService::isNearDestination($pedido, $lat, $lng, $radiusDestination)) {
            return [
                'matched' => true,
                'street_hint' => self::extractStreet((string)($pedido['endereco_destino'] ?? 'Destino')),
                'source' => 'geofence_destination_match',
                'confidence' => 0.96,
            ];
        }

        return [
            'matched' => false,
            'street_hint' => null,
            'source' => 'fallback',
            'confidence' => 0.40,
        ];
    }

    private static function extractStreet(string $address): string
    {
        $parts = preg_split('/\s*,\s*/', trim($address));
        return trim((string)($parts[0] ?? $address));
    }
}
