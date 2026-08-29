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

    public static function distanceToDestination(array $pedido, float $lat, float $lng): float
    {
        return GeoService::haversine($lat, $lng, (float)$pedido['lat_destino'], (float)$pedido['lng_destino']) * 1000;
    }
}
