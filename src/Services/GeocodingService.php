<?php
// File: guinchafacil/src/Services/GeocodingService.php

class GeocodingService
{
    /**
     * Geocoding via Nominatim (OpenStreetMap).
     * Retorna ['lat'=>..., 'lng'=>..., 'display_name'=>...] ou null.
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') return null;

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($address);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 10,
                'header'  => "User-Agent: GuinchaFacil/1.0
"
            ]
        ]);

        $json = @file_get_contents($url, false, $ctx);
        if (!$json) return null;

        $arr = json_decode($json, true);
        if (!is_array($arr) || empty($arr[0])) return null;

        $item = $arr[0];
        return [
            'lat' => isset($item['lat']) ? (float)$item['lat'] : null,
            'lng' => isset($item['lon']) ? (float)$item['lon'] : null,
            'display_name' => $item['display_name'] ?? null,
        ];
    }
}
