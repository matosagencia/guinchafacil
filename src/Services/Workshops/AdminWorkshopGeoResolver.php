<?php
declare(strict_types=1);
/** Resolver de coordenadas: só aceita coordenadas legadas com vínculo explícito. */
final class AdminWorkshopGeoResolver
{
    public static function legacyCoordinatesByProvider(PDO $pdo): array
    {
        // Uma associação não equivale à aprovação: a elegibilidade é verificada antes, pelo ProviderWorkshopService.
        $sql = 'SELECT l.provider_id, o.latitude, o.longitude
                FROM oficina_provider_links l
                INNER JOIN oficinas o ON o.id = l.oficina_id';
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['provider_id'];
            if ($id <= 0 || !self::validCoordinatePair($row['latitude'], $row['longitude'])) continue;
            $out[$id] = [(float)$row['latitude'], (float)$row['longitude']];
        }
        return $out;
    }

    public static function coordinatesFor(array $provider, array $linkedCoords): ?array
    {
        if (self::validCoordinatePair($provider['latitude'] ?? null, $provider['longitude'] ?? null)) {
            return [(float)$provider['latitude'], (float)$provider['longitude']];
        }
        $id = (int)($provider['id'] ?? 0);
        return $linkedCoords[$id] ?? null;
    }

    private static function validCoordinatePair($lat, $lng): bool
    {
        if (!is_numeric($lat) || !is_numeric($lng)) return false;
        $a = (float)$lat; $b = (float)$lng;
        return is_finite($a) && is_finite($b) && abs($a) <= 90 && abs($b) <= 180 && !($a == 0.0 && $b == 0.0);
    }

    public static function countWithinRadius(array $providers, array $links, float $lat, float $lng, float $radiusKm): int
    {
        $ids = [];
        foreach ($providers as $provider) {
            // A lista de entrada DEVE ser a lista aprovada de ProviderWorkshopService::listarOficinasElegiveis().
            if ((int)($provider['recebe_veiculo_patio'] ?? 0) !== 1) continue;
            $id = (int)($provider['id'] ?? 0);
            if ($id <= 0 || isset($ids[$id])) continue;
            $point = self::coordinatesFor($provider, $links);
            if ($point === null) continue;
            [$a, $b] = $point;
            $dLat = deg2rad($a - $lat);
            $dLng = deg2rad($b - $lng);
            $h = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($a)) * sin($dLng / 2) ** 2;
            $km = 2 * 6371.0088 * asin(min(1.0, sqrt(max(0.0, $h))));
            if ($km <= $radiusKm) $ids[$id] = true;
        }
        return count($ids);
    }
}
