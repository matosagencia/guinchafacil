<?php

require_once __DIR__ . '/GeocodingService.php';

class GeoService {
    
    public static function haversine($lat1, $lng1, $lat2, $lng2) {
        $raio_terra = 6371; // km
        
        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);
        
        $a = sin($dlat/2) * sin($dlat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dlng/2) * sin($dlng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $raio_terra * $c;
    }
    
    public static function geocodificarEndereco($endereco) {
        $resultado = (new GeocodingService())->geocode((string)$endereco);
        if ($resultado === null) {
            return false;
        }

        return [
            'lat' => (float)$resultado['lat'],
            'lng' => (float)$resultado['lng']
        ];
    }
    
    public static function geocodificarCep($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        
        if (strlen($cep) !== 8) {
            return false;
        }
        
        return GeocodingService::buscarCep($cep) ?: false;
    }
    
    public static function calcularCusto($distancia_km, $tarifa_km, $taxa_fixa) {
        $custo = $taxa_fixa + ($distancia_km * $tarifa_km);
        return round($custo, 2);
    }
    
    public static function coordenadasValidas($lat, $lng) {
        // Limites aproximados do Brasil
        $lat_min = -34;
        $lat_max = 5;
        $lng_min = -74;
        $lng_max = -28;
        
        return ($lat >= $lat_min && $lat <= $lat_max && 
                $lng >= $lng_min && $lng <= $lng_max);
    }
}
