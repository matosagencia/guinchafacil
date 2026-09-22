<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Services/GeoService.php';

class GeofenceService
{
    public static function isNearOrigin(array $pedido, float $lat, float $lng, float $radiusM): bool
    {
        return self::distanceToOrigin($pedido, $lat, $lng) <= $radiusM;
    }

    public static function isNearDestination(array $pedido, float $lat, float $lng, float $radiusM): bool
    {
        return self::distanceToDestination($pedido, $lat, $lng) <= $radiusM;
    }

    public static function distanceToOrigin(array $pedido, float $lat, float $lng): float
    {
        return GeoService::haversine($lat, $lng, (float)$pedido['lat_origem'], (float)$pedido['lng_origem']) * 1000;
    }

    /**
     * Ponto operacional do resgate móvel.
     *
     * Pedidos antigos não possuem local_resgate_*; nesses casos a origem
     * continua sendo o fallback compatível.
     */
    public static function isNearRescueLocation(array $pedido, float $lat, float $lng, float $radiusM): bool
    {
        return self::distanceToRescueLocation($pedido, $lat, $lng) <= $radiusM;
    }

    public static function distanceToRescueLocation(array $pedido, float $lat, float $lng): float
    {
        $rescueLat = $pedido['local_resgate_lat'] ?? $pedido['lat_origem'] ?? null;
        $rescueLng = $pedido['local_resgate_lng'] ?? $pedido['lng_origem'] ?? null;
        if ($rescueLat === null || $rescueLng === null) {
            return INF;
        }

        return GeoService::haversine($lat, $lng, (float)$rescueLat, (float)$rescueLng) * 1000;
    }

    public static function distanceToDestination(array $pedido, float $lat, float $lng): float
    {
        return GeoService::haversine($lat, $lng, (float)$pedido['lat_destino'], (float)$pedido['lng_destino']) * 1000;
    }
}
