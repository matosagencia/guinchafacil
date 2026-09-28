<?php
declare(strict_types=1);

require_once __DIR__ . '/AddressProviderInterface.php';

final class AddressAutocompleteService
{
    private AddressProviderInterface $provider;
    private ?AddressProviderInterface $fallbackProvider = null;

    public function __construct(?AddressProviderInterface $provider = null)
    {
        if ($provider === null) {
            require_once __DIR__ . '/PhotonAddressProvider.php';
            $provider = new PhotonAddressProvider();
        }
        $this->provider = $provider;

        // Fallback: Nominatim quando Photon nao achar ou retornar baixa qualidade
        $nomFile = __DIR__ . '/NominatimAddressProvider.php';
        if (is_file($nomFile)) {
            require_once $nomFile;
            if (class_exists('NominatimAddressProvider')) {
                $this->fallbackProvider = new NominatimAddressProvider();
            }
        }
    }

    public function suggest(string $q, ?float $biasLat = null, ?float $biasLng = null, int $limit = 8): array
    {
        $q = trim($q);
        if (mb_strlen($q, 'UTF-8') < 3) return [];

        // CEP exato
        $digits = preg_replace('/\D/', '', $q);
        if (strlen($digits) === 8 && strlen($q) <= 9) {
            $cep = $this->lookupCep($digits);
            if ($cep) return [$cep];
        }

        // 1) Photon
        $resultado = $this->provider->suggest($q, $biasLat, $biasLng, null, $limit);

        // 2) Decide se precisa fallback
        $precisa = false;
        if (count($resultado) < 2) {
            $precisa = true;
        } elseif (!$this->verificarQualidade($q, $resultado)) {
            $precisa = true;
        }

        if ($precisa && $this->fallbackProvider !== null) {
            try {
                $alt = $this->fallbackProvider->suggest($q, $biasLat, $biasLng, null, $limit);
                if (count($alt) > 0 && ($this->verificarQualidade($q, $alt) || count($alt) > count($resultado))) {
                    return $alt;
                }
            } catch (Throwable $e) {
                error_log('[AddressAutocompleteService] fallback Nominatim: ' . $e->getMessage());
            }
        }

        return $resultado;
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $r = $this->provider->reverse($lat, $lng);
        if ($r !== null) return $r;
        if ($this->fallbackProvider !== null) {
            try { return $this->fallbackProvider->reverse($lat, $lng); } catch (Throwable $e) {}
        }
        return null;
    }

    /** Verifica se o TOPO do resultado bate com a query. */
    private function verificarQualidade(string $q, array $resultado): bool
    {
        if (empty($resultado)) return false;
        $lower = mb_strtolower($q, 'UTF-8');
        $words = preg_split('/[\s,]+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopwords = ['rua', 'av', 'avenida', 'de', 'da', 'do', 'das', 'dos', 'e', 'para', 'com', 'rio', 'janeiro', 'sao', 'paulo'];
        $words = array_filter($words, function ($w) use ($stopwords) {
            return mb_strlen($w, 'UTF-8') >= 4 && !in_array($w, $stopwords, true);
        });
        if (empty($words)) return true;

        $p = $resultado[0] ?? null;
        if (!$p) return false;
        $hay = mb_strtolower((string)($p['principal'] ?? '') . ' ' . (string)($p['secundario'] ?? ''), 'UTF-8');
        foreach ($words as $w) {
            if (mb_strpos($hay, $w) !== false) return true;
        }
        return false;
    }

    private function lookupCep(string $cep): ?array
    {
        $file = __DIR__ . '/../GeocodingService.php';
        if (!is_file($file)) return null;
        require_once $file;
        if (!class_exists('GeocodingService') || !method_exists('GeocodingService', 'buscarCep')) return null;
        try {
            $r = (new GeocodingService())->buscarCep($cep);
            if (!is_array($r) || empty($r['lat']) || empty($r['lng'])) return null;
            $bairro = (string)($r['bairro'] ?? '');
            $cidade = (string)($r['cidade'] ?? $r['localidade'] ?? '');
            $uf = (string)($r['uf'] ?? '');
            return [
                'id' => 'cep-' . $cep,
                'principal' => (string)($r['logradouro'] ?? '') ?: 'CEP ' . $cep,
                'secundario' => implode(' - ', array_values(array_filter([$bairro, $cidade, $uf]))),
                'lat' => (float)$r['lat'],
                'lng' => (float)$r['lng'],
                'cidade' => $cidade,
                'uf' => $uf,
                'numero' => null,
                'precisa_numero' => true,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }
}