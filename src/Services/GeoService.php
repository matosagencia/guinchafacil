<?php

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
        $endereco_encoded = urlencode($endereco);
        $url = "https://nominatim.openstreetmap.org/search?q={$endereco_encoded}&format=json&limit=1";
        
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: GuinchaFacil/1.0\r\n",
                'timeout' => 10
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (empty($data)) {
            return false;
        }
        
        return [
            'lat' => (float)$data[0]['lat'],
            'lng' => (float)$data[0]['lon']
        ];
    }
    
    public static function geocodificarCep($cep) {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        
        if (strlen($cep) !== 8) {
            return false;
        }
        
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['erro'])) {
            return false;
        }
        
        return [
            'cep' => $data['cep'],
            'logradouro' => $data['logradouro'],
            'bairro' => $data['bairro'],
            'localidade' => $data['localidade'],
            'uf' => $data['uf']
        ];
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
