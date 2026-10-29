<?php
declare(strict_types=1);

/** §23: projeção pública, somente-leitura, da cobertura operacional.
 * Não equivale a cotação, disponibilidade garantida ou despacho.
 */
final class MapaCoberturaService
{
    public static function consultar(PDO $pdo, float $lat, float $lng): array
    {
        if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('Coordenadas inválidas.');
        }
        // Limita dispersão espacial e cardinalidade do cache público.
        $lat = round($lat, 2);
        $lng = round($lng, 2);
        $key = hash('sha256', sprintf('mapa-v1|%.2f|%.2f', $lat, $lng));
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gf-mapa-23-' . $key . '.json';
        if (is_file($cacheFile) && (time() - (int)filemtime($cacheFile)) < 60) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['zonas'], $cached['guinchos'], $cached['centro'])) {
                return $cached;
            }
        }
        $zonas = [];
        $stmt = $pdo->query("SELECT id, name, status_expansao, polygon_geojson FROM pricing_zones WHERE active = 1 AND status_expansao IN ('pedra_viva', 'pedra_morta') AND polygon_geojson IS NOT NULL ORDER BY id LIMIT 200");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $z) {
            $geo = json_decode((string)$z['polygon_geojson'], true);
            if (!is_array($geo) || ($geo['type'] ?? '') !== 'Polygon' || !is_array($geo['coordinates'][0] ?? null)) {
                continue;
            }
            $zonas[] = [
                'id' => (int)$z['id'],
                'name' => (string)$z['name'],
                'status_expansao' => (string)$z['status_expansao'],
                'polygon_geojson' => $geo,
            ];
        }
        // Até ~50 km; usa bounding box apenas como pré-filtro de banco. Distância final via Haversine.
        $latSpan = 50 / 111.32;
        $lngSpan = 50 / (111.32 * max(0.1, cos(deg2rad($lat))));
        $sql = "SELECT g.id, g.lat_atual, g.lng_atual, g.placa_guincho
                FROM guinchos g INNER JOIN usuarios u ON u.id = g.usuario_id
                WHERE g.aprovado = 1 AND g.disponivel = 1 AND COALESCE(g.reboque_aprovado, 0) = 1
                  AND u.ativo = 1
                  AND g.lat_atual BETWEEN ? AND ? AND g.lng_atual BETWEEN ? AND ?
                  AND NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.guincho_id = g.id AND p.status IN ('a_caminho','no_local','em_reboque'))
                ORDER BY g.id LIMIT 200";
        $q = $pdo->prepare($sql);
        $q->execute([$lat-$latSpan, $lat+$latSpan, $lng-$lngSpan, $lng+$lngSpan]);
        require_once __DIR__ . '/../Finance/BloqueioFinanceiroService.php';
        $bloqueados = array_fill_keys(array_map('intval', BloqueioFinanceiroService::idsBloqueados()), true);
        $guinchos = [];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $g) {
            if (isset($bloqueados[(int)$g['id']])) continue;
            $glat = (float)$g['lat_atual'];
            $glng = (float)$g['lng_atual'];
            if (!is_finite($glat) || !is_finite($glng) || self::distanciaKm($lat,$lng,$glat,$glng) > 50) continue;
            $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$g['placa_guincho']));
            // Não vaza placas incompletas, nem a posição GPS precisa.
            $placaPublica = strlen($placa) === 7 ? substr($placa,0,4) . '*' . substr($placa,5,2) : null;
            $guinchos[] = ['id'=>(int)$g['id'], 'lat'=>round($glat, 2), 'lng'=>round($glng,2), 'placa_publica'=>$placaPublica];
            if (count($guinchos) >= 50) break;
        }
        $out = ['zonas'=>$zonas, 'guinchos'=>$guinchos, 'centro'=>['lat'=>$lat,'lng'=>$lng,'zoom_sugerido'=>12]];
        // Escrita somente em cache local, nunca no banco.
        $json = json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $tmp = $cacheFile . '.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $cacheFile);
        }
        return $out;
    }

    private static function distanciaKm(float $a, float $b, float $c, float $d): float
    {
        $dl = deg2rad($c-$a); $dn = deg2rad($d-$b);
        $h = sin($dl/2)**2 + cos(deg2rad($a))*cos(deg2rad($c))*sin($dn/2)**2;
        return 6371.0 * 2 * asin(min(1.0, sqrt($h)));
    }
}
