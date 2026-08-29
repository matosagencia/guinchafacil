<?php
declare(strict_types=1);

/** Agrega demanda em células de ~1 km; nunca persiste endereço ou usuário. */
final class PreQuoteDemandService
{
    public static function registrar(array $quote, string $evento = 'quote'): void
    {
        $lat = isset($quote['lat_origem']) ? (float)$quote['lat_origem'] : null;
        $lng = isset($quote['lng_origem']) ? (float)$quote['lng_origem'] : null;
        if ($lat === null || $lng === null || $lat < -34 || $lat > 5 || $lng < -74 || $lng > -28) return;
        $service = substr((string)($quote['tipo_problema'] ?? 'outro'), 0, 40) ?: 'outro';
        $vehicle = substr((string)($quote['categoria'] ?? 'popular'), 0, 30) ?: 'popular';
        $cellLat = round($lat, 2);
        $cellLng = round($lng, 2);
        $quoteDelta = $evento === 'quote' ? 1 : 0;
        $acceptedDelta = $evento === 'accepted' ? 1 : 0;
        $convertedDelta = $evento === 'converted' ? 1 : 0;
        try {
            $pdo = getPDO();
            $sql = "INSERT INTO pre_quote_demand_cells
                (cell_lat,cell_lng,period_date,service_key,vehicle_category,quote_count,accepted_count,converted_count,updated_at)
                VALUES (?,?,CURRENT_DATE,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE quote_count=quote_count+VALUES(quote_count),
                accepted_count=accepted_count+VALUES(accepted_count), converted_count=converted_count+VALUES(converted_count), updated_at=NOW()";
            $pdo->prepare($sql)->execute([$cellLat, $cellLng, $service, $vehicle, $quoteDelta, $acceptedDelta, $convertedDelta]);
        } catch (Throwable $e) {
            error_log('[PreQuoteDemand] registro agregado indisponivel: ' . $e->getMessage());
        }
    }

    public static function listarPrioridades(int $dias = 30, int $limite = 100): array
    {
        $dias = max(1, min(90, $dias)); $limite = max(1, min(500, $limite));
        $stmt = getPDO()->query("SELECT cell_lat,cell_lng,SUM(quote_count) quote_count,SUM(accepted_count) accepted_count,SUM(converted_count) converted_count
            FROM pre_quote_demand_cells WHERE period_date >= DATE_SUB(CURRENT_DATE, INTERVAL {$dias} DAY)
            GROUP BY cell_lat,cell_lng HAVING SUM(quote_count) >= 5 ORDER BY quote_count DESC LIMIT {$limite}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function resumoPorServico(int $dias = 30): array
    {
        $dias = max(1, min(90, $dias));
        try {
            $stmt = getPDO()->query("SELECT service_key, SUM(quote_count) total
                FROM pre_quote_demand_cells WHERE period_date >= DATE_SUB(CURRENT_DATE, INTERVAL {$dias} DAY)
                GROUP BY service_key ORDER BY total DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}
