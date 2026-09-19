<?php

namespace App\Services\Prospeccao;

/**
 * Cliente fino para o engine=google_maps da SerpApi.
 *
 * Requer a variável de ambiente SERPAPI_KEY (adicionar ao .env e,
 * se o projeto já tiver a tela de governança do .env, expor lá
 * junto das chaves de gateway/SMTP).
 */
class SerpApiMapsClient
{
    private const ENDPOINT = 'https://serpapi.com/search.json';

    public function __construct(private readonly string $apiKey)
    {
    }

    /**
     * Busca locais no Google Maps para uma categoria, ao redor de
     * um ponto (lat/lng), dentro de um raio aproximado (via zoom).
     *
     * @return array<int, array<string, mixed>> lista normalizada de resultados
     */
    public function buscar(string $categoria, float $lat, float $lng, int $paginas = 1): array
    {
        $resultados = [];

        for ($pagina = 0; $pagina < max(1, $paginas); $pagina++) {
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
                break; // sem mais páginas, evita gastar crédito à toa
            }

            foreach ($locais as $local) {
                $resultados[] = $this->normalizar($local, $categoria);
            }
        }

        return $resultados;
    }

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
            throw new \RuntimeException("SerpApi: falha de rede ({$erro})");
        }
        if ($status !== 200) {
            throw new \RuntimeException("SerpApi: HTTP {$status} — {$body}");
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('SerpApi: resposta não é JSON válido');
        }

        return $json;
    }

    private function normalizar(array $local, string $categoria): array
    {
        return [
            'place_id' => $local['data_id'] ?? $local['data_cid'] ?? null,
            'nome_negocio' => $local['title'] ?? '',
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
