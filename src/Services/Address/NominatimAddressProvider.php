<?php
declare(strict_types=1);

require_once __DIR__ . '/AddressProviderInterface.php';

final class NominatimAddressProvider implements AddressProviderInterface
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
        $url = (string)(getenv('NOMINATIM_URL') ?: 'https://nominatim.openstreetmap.org');
        $this->baseUrl = rtrim($url, '/');
        $this->timeout = (int)(getenv('NOMINATIM_TIMEOUT') ?: 8);
    }

    public function suggest(string $q, ?float $biasLat = null, ?float $biasLng = null, ?array $bbox = null, int $limit = 8): array
    {
        $params = [
            'q' => $q,
            'format' => 'json',
            'limit' => min(15, max(1, $limit)),
            'addressdetails' => 1,
            'accept-language' => 'pt-BR',
            'countrycodes' => 'br',
        ];
        // Sem bias geografico: o Nominatim e o fallback GLOBAL.
        // O Photon (provider principal) ja aplica bias local.
        // Removemos o viewbox pra priorizar resultados de maior relevancia nacional
        // (ex: "av paulista" deve trazer a de SP, nao uma homonima em outra cidade).

        $json = $this->httpGet('/search?' . http_build_query($params));
        if (!is_array($json) || empty($json)) return [];
        $out = [];
        foreach ($json as $row) {
            $item = $this->normalize($row);
            if ($item) $out[] = $item;
        }
        return $out;
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $json = $this->httpGet('/reverse?' . http_build_query([
            'lat' => $lat, 'lon' => $lng, 'format' => 'json', 'addressdetails' => 1, 'accept-language' => 'pt-BR',
        ]));
        if (!is_array($json) || empty($json['display_name'])) return null;
        return $this->normalize($json);
    }

    private function normalize(array $row): ?array
    {
        $lat = (float)($row['lat'] ?? 0);
        $lng = (float)($row['lon'] ?? 0);
        if ($lat === 0.0 || $lng === 0.0) return null;
        if ($lat < -34 || $lat > 5 || $lng < -74 || $lng > -28) return null;

        $addr = $row['address'] ?? [];
        $numero = trim((string)($addr['house_number'] ?? ''));
        $rua = trim((string)($addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? ''));
        $bairro = trim((string)($addr['suburb'] ?? $addr['neighbourhood'] ?? ''));
        $cidade = trim((string)($addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? ''));
        $uf = $this->ufFromState((string)($addr['state'] ?? ''));

        $principal = $rua ?: trim((string)($row['display_name'] ?? ''));
        if ($numero !== '' && $rua !== '') $principal = $rua . ', ' . $numero;
        if ($principal === '') $principal = 'Local aproximado';
        $secundario = implode(' - ', array_values(array_filter([$bairro, $cidade, $uf])));

        return [
            'id' => 'nm-' . ($row['place_id'] ?? ''),
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
        $k = strtr($k, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        return self::UF_MAP[$k] ?? '';
    }

    private function httpGet(string $path): ?array
    {
        $url = $this->baseUrl . $path;
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => 'GuinchaFacil/1.0 (+https://guinchafacil.com.br)',
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || $raw === '') return null;
        $j = json_decode($raw, true);
        return is_array($j) ? $j : null;
    }
}