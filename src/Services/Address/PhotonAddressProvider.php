<?php
declare(strict_types=1);

require_once __DIR__ . '/AddressProviderInterface.php';

final class PhotonAddressProvider implements AddressProviderInterface
{
    private const UF_MAP = [
        'acre'=>'AC','alagoas'=>'AL','amapa'=>'AP','amazonas'=>'AM','bahia'=>'BA',
        'ceara'=>'CE','distrito federal'=>'DF','espirito santo'=>'ES','goias'=>'GO',
        'maranhao'=>'MA','mato grosso'=>'MT','mato grosso do sul'=>'MS','minas gerais'=>'MG',
        'para'=>'PA','paraiba'=>'PB','parana'=>'PR','pernambuco'=>'PE','piaui'=>'PI',
        'rio de janeiro'=>'RJ','rio grande do norte'=>'RN','rio grande do sul'=>'RS',
        'rondonia'=>'RO','roraima'=>'RR','santa catarina'=>'SC','sao paulo'=>'SP',
        'sergipe'=>'SE','tocantins'=>'TO',
    ];

    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $url = (string)(getenv('ADDRESS_PROVIDER_URL') ?: 'https://photon.komoot.io');
        $this->baseUrl = rtrim($url, '/');
        $this->timeout = (int)(getenv('ADDRESS_PROVIDER_TIMEOUT') ?: 6);
    }

    public function suggest(string $q, ?float $biasLat = null, ?float $biasLng = null, ?array $bbox = null, int $limit = 8): array
    {
        // Photon NAO aceita lang=pt. Valores: default, de, en, fr.
        $params = ['q' => $q, 'limit' => min(20, max(1, $limit))];
        if ($biasLat !== null && $biasLng !== null) {
            $params['lat'] = $biasLat;
            $params['lon'] = $biasLng;
        }
        if (is_array($bbox) && count($bbox) === 4) {
            $params['bbox'] = implode(',', $bbox);
        }
        $json = $this->httpGet('/api/?' . http_build_query($params));
        if (!is_array($json) || empty($json['features'])) return [];
        $out = [];
        foreach ($json['features'] as $f) {
            $item = $this->normalize($f);
            if ($item) $out[] = $item;
        }
        return $out;
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $json = $this->httpGet('/reverse?' . http_build_query(['lat' => $lat, 'lon' => $lng]));
        if (!is_array($json) || empty($json['features'][0])) return null;
        return $this->normalize($json['features'][0]);
    }

    private function normalize(array $f): ?array
    {
        $coords = $f['geometry']['coordinates'] ?? null;
        $props = $f['properties'] ?? [];
        if (!is_array($coords) || count($coords) < 2) return null;
        $lng = (float)$coords[0];
        $lat = (float)$coords[1];
        if ($lat < -34 || $lat > 5 || $lng < -74 || $lng > -28) return null;

        $pais = strtolower((string)($props['country'] ?? ''));
        if ($pais !== '' && $pais !== 'brasil' && $pais !== 'brazil') return null;

        $numero = trim((string)($props['housenumber'] ?? ''));
        $rua = trim((string)($props['street'] ?? $props['name'] ?? ''));
        $bairro = trim((string)($props['district'] ?? $props['locality'] ?? $props['suburb'] ?? ''));
        $cidade = trim((string)($props['city'] ?? $props['town'] ?? $props['village'] ?? $props['county'] ?? ''));
        $uf = $this->ufFromState((string)($props['state'] ?? ''));

        $principal = $rua;
        if ($numero !== '') $principal .= ', ' . $numero;
        if ($principal === '') $principal = 'Local aproximado';
        $secundario = implode(' - ', array_values(array_filter([$bairro, $cidade, $uf])));

        return [
            'id' => 'ph-' . ($props['osm_id'] ?? '') . '-' . $lat . ',' . $lng,
            'principal' => $principal,
            'secundario' => $secundario,
            'lat' => $lat,
            'lng' => $lng,
            'cidade' => $cidade,
            'uf' => $uf,
            'numero' => $numero !== '' ? $numero : null,
            'precisa_numero' => $numero === '',
        ];
    }

    private function ufFromState(string $state): string
    {
        $k = mb_strtolower(trim($state), 'UTF-8');
        $k = strtr($k, [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a',
            'é'=>'e','ê'=>'e','í'=>'i',
            'ó'=>'o','ô'=>'o','õ'=>'o',
            'ú'=>'u','ç'=>'c',
        ]);
        return self::UF_MAP[$k] ?? '';
    }

    private function httpGet(string $path): ?array
    {
        // FIX SSL: cacert.pem do XAMPP está desatualizado para Let's Encrypt (usado pelo Photon).
        // file_get_contents com verify_peer=false bypassa — dev only.
        $url = $this->baseUrl . $path;
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout'    => $this->timeout,
                'user_agent' => 'GuinchaFacil/1.0 (+https://guinchafacil.com.br)',
                'header'     => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') {
            error_log('[PhotonAddressProvider] httpGet falhou: ' . $url);
            return null;
        }

        $j = json_decode($raw, true);
        return is_array($j) ? $j : null;
    }
}
