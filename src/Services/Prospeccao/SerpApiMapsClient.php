<?php

declare(strict_types=1);

/**
 * Cliente fino para o engine google_maps da SerpApi.
 */
final class SerpApiMapsClient
{
    private const ENDPOINT = 'https://serpapi.com/search.json';
    /** @var string */
    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buscar(string $categoria, float $lat, float $lng, int $paginas = 1): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('SERPAPI_KEY nao configurada no .env.');
        }

        $resultados = [];
        $limitePaginas = max(1, min(3, $paginas));

        for ($pagina = 0; $pagina < $limitePaginas; $pagina++) {
            $params = [
                'engine' => 'google_maps',
                'type' => 'search',
                'q' => $categoria,
                'll' => sprintf('@%F,%F,13z', $lat, $lng),
                'start' => $pagina * 20,
                'hl' => 'pt',
                'gl' => 'br',
                'api_key' => $this->apiKey,
            ];

            $resposta = $this->executar($params);
            $locais = $resposta['local_results'] ?? [];

            if (empty($locais)) {
                break;
            }

            foreach ($locais as $local) {
                $resultados[] = $this->normalizar((array)$local, $categoria);
            }
        }

        return $resultados;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function executar(array $params): array
    {
        $url = self::ENDPOINT . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CAINFO => (function_exists('ca_bundle_path') ? ca_bundle_path() : null) ?: null,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_error($ch);
        curl_close($ch);

        if ($body === false || $erro) {
            throw new RuntimeException("SerpApi: falha de rede ({$erro})");
        }

        if ($status !== 200) {
            throw new RuntimeException("SerpApi: HTTP {$status} - {$body}");
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new RuntimeException('SerpApi: resposta nao e JSON valido');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $local
     * @return array<string, mixed>
     */
    private function normalizar(array $local, string $categoria): array
    {
        return [
            'place_id' => $local['data_id'] ?? $local['data_cid'] ?? null,
            'nome_negocio' => (string)($local['title'] ?? ''),
            'categoria' => $categoria,
            'telefone' => $local['phone'] ?? null,
            'endereco' => $local['address'] ?? null,
            'website' => $local['links']['website'] ?? $local['website'] ?? null,
            'rating' => $local['rating'] ?? null,
            'reviews_count' => $local['reviews'] ?? null,
            'lat' => $local['gps_coordinates']['latitude'] ?? null,
            'lng' => $local['gps_coordinates']['longitude'] ?? null,
        ];
    }
}
