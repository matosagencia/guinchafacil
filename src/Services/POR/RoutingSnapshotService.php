<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/PedidoLocalizacao.php';
require_once __DIR__ . '/../../Models/PedidoPercursoResumo.php';
require_once __DIR__ . '/../../Services/GeoService.php';
require_once __DIR__ . '/GeofenceService.php';

class RoutingSnapshotService
{
    public static function buildForPedido(array $pedido): array
    {
        $pedidoId = (int)($pedido['id'] ?? 0);
        $trail = $pedidoId > 0 ? PedidoLocalizacao::listarTrail($pedidoId, 120) : [];
        $validTrail = array_values(array_filter($trail, static fn(array $point): bool => !empty($point['is_valid'])));
        $lastValidPoint = empty($validTrail) ? null : $validTrail[count($validTrail) - 1];
        $status = (string)($pedido['status'] ?? '');
        $summary = $pedidoId > 0 ? self::summaryForStatus($pedidoId, $status) : null;

        $mode = self::modeFromStatus($status);
        $target = self::targetForMode($pedido, $mode);
        $current = self::currentPosition($pedido, $lastValidPoint);
        $remainingDistanceM = self::distanceMeters($current['lat'], $current['lng'], $target['lat'], $target['lng']);
        $speedKmh = self::estimatedSpeedKmh($mode, $summary);
        $etaMinutes = self::estimateEtaMinutes($remainingDistanceM, $speedKmh);
        $plannedDistanceM = self::plannedDistance($pedido, $mode);
        $coveredDistanceM = self::coveredDistance($pedido, $lastValidPoint, $mode);
        $progressPercent = self::progressPercent($plannedDistanceM, $coveredDistanceM, $status);

        // Âncora para projeção client-side (dead reckoning) enquanto o GPS
        // real não manda um ponto novo: idade do último ponto VÁLIDO (tempo
        // de servidor, sem risco de desvio de relógio do dispositivo) + a
        // velocidade média já estimada para esta corrida. O cliente usa isso
        // para mover o ícone estimando a posição, sem nunca decidir sozinho
        // que o guincho "chegou" — isso continua exigindo GPS real.
        $anchorAgeS = null;
        if ($lastValidPoint) {
            $ref = $lastValidPoint['received_at'] ?? $lastValidPoint['device_timestamp'] ?? null;
            if ($ref) {
                $ts = strtotime((string)$ref);
                if ($ts !== false) {
                    $anchorAgeS = max(0, time() - $ts);
                }
            }
        }

        return [
            'mode' => $mode,
            'mode_label' => self::modeLabel($mode),
            'current_street' => self::currentStreet($pedido, $lastValidPoint, $mode),
            'target_label' => $target['label'],
            'target_address' => $target['address'],
            'remaining_distance_m' => round($remainingDistanceM, 1),
            'remaining_distance_label' => self::distanceLabel($remainingDistanceM),
            'eta_minutes' => $etaMinutes,
            'eta_label' => self::etaLabel($etaMinutes, $status),
            'progress_percent' => $progressPercent,
            'trail_points' => self::trailPoints($validTrail),
            'recent_streets' => self::recentStreets($validTrail),
            'tracking_quality' => (string)($summary['tracking_quality'] ?? 'unknown'),
            'valid_points' => (int)($summary['valid_points'] ?? 0),
            'rejected_points' => (int)($summary['rejected_points'] ?? 0),
            'distance_validated_m' => round((float)($summary['distance_validated_m'] ?? 0), 1),
            'last_point_at' => $summary['last_point_at'] ?? null,
            // Campos aditivos p/ projeção client-side (dead reckoning):
            'current_lat' => round($current['lat'], 6),
            'current_lng' => round($current['lng'], 6),
            'target_lat' => round($target['lat'], 6),
            'target_lng' => round($target['lng'], 6),
            'speed_kmh' => round($speedKmh, 1),
            'anchor_age_s' => $anchorAgeS,
        ];
    }

    private static function modeFromStatus(string $status): string
    {
        return match ($status) {
            'a_caminho' => 'guincho_origem',
            'no_local', 'em_reboque' => 'origem_destino',
            'concluido' => 'concluido',
            default => 'overview',
        };
    }

    private static function targetForMode(array $pedido, string $mode): array
    {
        if ($mode === 'guincho_origem') {
            return [
                'label' => 'Origem',
                'address' => (string)($pedido['endereco_origem'] ?? 'Origem'),
                'lat' => (float)($pedido['lat_origem'] ?? 0),
                'lng' => (float)($pedido['lng_origem'] ?? 0),
            ];
        }

        return [
            'label' => 'Destino',
            'address' => (string)($pedido['endereco_destino'] ?? 'Destino'),
            'lat' => (float)($pedido['lat_destino'] ?? 0),
            'lng' => (float)($pedido['lng_destino'] ?? 0),
        ];
    }

    private static function currentPosition(array $pedido, ?array $lastValidPoint): array
    {
        if ($lastValidPoint) {
            return [
                'lat' => (float)$lastValidPoint['latitude'],
                'lng' => (float)$lastValidPoint['longitude'],
            ];
        }

        return [
            'lat' => isset($pedido['lat_guincho']) ? (float)$pedido['lat_guincho'] : (float)($pedido['lat_origem'] ?? 0),
            'lng' => isset($pedido['lng_guincho']) ? (float)$pedido['lng_guincho'] : (float)($pedido['lng_origem'] ?? 0),
        ];
    }

    private static function currentStreet(array $pedido, ?array $lastValidPoint, string $mode): string
    {
        if ($lastValidPoint && !empty($lastValidPoint['street_name'])) {
            return (string)$lastValidPoint['street_name'];
        }

        if ($mode === 'guincho_origem') {
            return self::shortAddress((string)($pedido['endereco_origem'] ?? 'Origem'));
        }

        return self::shortAddress((string)($pedido['endereco_destino'] ?? 'Destino'));
    }

    private static function trailPoints(array $validTrail): array
    {
        $points = [];
        foreach ($validTrail as $point) {
            $points[] = [
                'lat' => (float)$point['latitude'],
                'lng' => (float)$point['longitude'],
            ];
        }

        return $points;
    }

    private static function recentStreets(array $validTrail): array
    {
        $unique = [];
        for ($i = count($validTrail) - 1; $i >= 0; $i--) {
            $street = trim((string)($validTrail[$i]['street_name'] ?? ''));
            if ($street === '' || isset($unique[$street])) {
                continue;
            }
            $unique[$street] = true;
            if (count($unique) >= 6) {
                break;
            }
        }

        return array_values(array_keys($unique));
    }

    private static function estimateEtaMinutes(float $remainingDistanceM, float $speedKmh): int
    {
        if ($remainingDistanceM <= 50) {
            return 0;
        }

        $hours = ($remainingDistanceM / 1000) / max($speedKmh, 0.1);
        return max(1, (int)ceil($hours * 60));
    }

    private static function estimatedSpeedKmh(string $mode, ?array $summary): float
    {
        $fallback = $mode === 'guincho_origem' ? 32.0 : 28.0;
        if (!$summary) {
            return $fallback;
        }

        $distanceM = (float)($summary['distance_validated_m'] ?? 0);
        $durationS = (int)($summary['duration_seconds'] ?? 0);
        if ($distanceM < 300 || $durationS < 120) {
            return $fallback;
        }

        $kmh = ($distanceM / 1000) / max($durationS / 3600, 0.01);
        return max(12.0, min(60.0, $kmh));
    }

    private static function summaryForStatus(int $pedidoId, string $status): ?array
    {
        $fase = in_array($status, ['a_caminho', 'no_local'], true) ? 'origem' : 'destino';
        return PedidoPercursoResumo::buscar($pedidoId, $fase);
    }

    private static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'guincho_origem' => 'Guincho até a origem',
            'origem_destino' => 'Origem até o destino',
            'concluido' => 'Atendimento concluído',
            default => 'Visão geral',
        };
    }

    private static function plannedDistance(array $pedido, string $mode): float
    {
        if ($mode === 'guincho_origem') {
            if (isset($pedido['lat_guincho'], $pedido['lng_guincho'])) {
                return self::distanceMeters(
                    (float)$pedido['lat_guincho'],
                    (float)$pedido['lng_guincho'],
                    (float)($pedido['lat_origem'] ?? 0),
                    (float)($pedido['lng_origem'] ?? 0)
                );
            }

            return 0.0;
        }

        if (!empty($pedido['distancia_km'])) {
            return (float)$pedido['distancia_km'] * 1000;
        }

        return self::distanceMeters(
            (float)($pedido['lat_origem'] ?? 0),
            (float)($pedido['lng_origem'] ?? 0),
            (float)($pedido['lat_destino'] ?? 0),
            (float)($pedido['lng_destino'] ?? 0)
        );
    }

    private static function coveredDistance(array $pedido, ?array $lastValidPoint, string $mode): float
    {
        if ($mode === 'guincho_origem') {
            if (!$lastValidPoint || !isset($pedido['lat_guincho'], $pedido['lng_guincho'])) {
                return 0.0;
            }

            $planned = self::plannedDistance($pedido, $mode);
            $remaining = self::distanceMeters(
                (float)$lastValidPoint['latitude'],
                (float)$lastValidPoint['longitude'],
                (float)($pedido['lat_origem'] ?? 0),
                (float)($pedido['lng_origem'] ?? 0)
            );
            return max(0.0, $planned - $remaining);
        }

        return (float)($lastValidPoint['distance_accumulated_m'] ?? 0);
    }

    private static function progressPercent(float $plannedDistanceM, float $coveredDistanceM, string $status): int
    {
        if ($status === 'concluido') {
            return 100;
        }

        if ($plannedDistanceM <= 0.0) {
            return 0;
        }

        return max(0, min(99, (int)round(($coveredDistanceM / $plannedDistanceM) * 100)));
    }

    private static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        if ($lat1 === 0.0 && $lng1 === 0.0) {
            return 0.0;
        }

        return GeoService::haversine($lat1, $lng1, $lat2, $lng2) * 1000;
    }

    private static function distanceLabel(float $distanceM): string
    {
        if ($distanceM < 1000) {
            return number_format($distanceM, 0, ',', '.') . ' m';
        }

        return number_format($distanceM / 1000, 1, ',', '.') . ' km';
    }

    private static function etaLabel(int $etaMinutes, string $status): string
    {
        if ($status === 'concluido') {
            return 'Atendimento concluído';
        }

        if ($etaMinutes <= 0) {
            return 'Chegada iminente';
        }

        if ($etaMinutes === 1) {
            return '1 minuto';
        }

        return $etaMinutes . ' minutos';
    }

    private static function shortAddress(string $address): string
    {
        $address = trim($address);
        if ($address === '') {
            return 'Sem rua informada';
        }

        $parts = preg_split('/\s*,\s*/', $address);
        return trim((string)($parts[0] ?? $address));
    }
}
