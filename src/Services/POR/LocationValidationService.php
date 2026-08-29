<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Services/GeoService.php';
require_once __DIR__ . '/../DebugMode.php';
require_once __DIR__ . '/PorThresholds.php';
require_once __DIR__ . '/GeofenceService.php';
require_once __DIR__ . '/RoadNetworkMatchService.php';

class LocationValidationService
{
    public static function validatePoint(array $pedido, array $payload, ?array $lastPoint): array
    {
        $lat = (float)$payload['latitude'];
        $lng = (float)$payload['longitude'];
        $accuracy = isset($payload['accuracy_m']) ? (float)$payload['accuracy_m'] : null;
        $sequence = (int)$payload['sequence'];
        $deviceTimestampMs = isset($payload['device_timestamp']) ? (int)$payload['device_timestamp'] : 0;

        $result = [
            'accepted' => true,
            'is_valid' => true,
            'rejection_code' => null,
            'distance_raw_m' => 0.0,
            'distance_validated_m' => 0.0,
            'elapsed_ms' => null,
            'calculated_speed_kmh' => null,
        ];

        if ($lat < -34 || $lat > 5 || $lng < -74 || $lng > -28) {
            return self::reject($result, 'POR-VAL-001');
        }

        $maxAccuracy = PorThresholds::maxAccuracyM();
        if ($accuracy !== null && $accuracy > 0 && $accuracy > $maxAccuracy) {
            return self::reject($result, 'POR-VAL-003');
        }

        if ($lastPoint && $sequence <= (int)$lastPoint['sequence_number']) {
            return self::reject($result, 'POR-VAL-004');
        }

        if ($deviceTimestampMs > 0) {
            $ageSeconds = abs((int)round((microtime(true) * 1000 - $deviceTimestampMs) / 1000));
            $maxGap = PorThresholds::maxGapSeconds();
            if ($ageSeconds > max(600, $maxGap * 4)) {
                return self::reject($result, 'POR-VAL-005');
            }
        }

        if ($lastPoint) {
            $prevTs = self::toTimestampMs($lastPoint['device_timestamp'] ?? null, (string)($lastPoint['server_timestamp'] ?? ''));
            if ($deviceTimestampMs > 0 && $deviceTimestampMs <= $prevTs) {
                return self::reject($result, 'POR-VAL-009');
            }

            $distanceM = GeoService::haversine(
                (float)$lastPoint['latitude'],
                (float)$lastPoint['longitude'],
                $lat,
                $lng
            ) * 1000;
            $result['distance_raw_m'] = $distanceM;

            $currTs = $deviceTimestampMs > 0 ? $deviceTimestampMs : (int)round(microtime(true) * 1000);
            $elapsedMs = max(1, $currTs - $prevTs);
            $speedKmh = ($distanceM / max(1, $elapsedMs / 1000)) * 3.6;

            $result['elapsed_ms'] = $elapsedMs;
            $result['calculated_speed_kmh'] = $speedKmh;

            $maxGap = PorThresholds::maxGapSeconds();
            if ($elapsedMs > ($maxGap * 1000)) {
                return self::reject($result, 'POR-VAL-007');
            }

            if ($distanceM <= 3.0 && $elapsedMs <= 15000) {
                return self::reject($result, 'POR-VAL-008');
            }

            $maxSpeed = PorThresholds::maxSpeedKmh();
            if ($speedKmh > $maxSpeed) {
                return self::reject($result, 'POR-VAL-006');
            }
        }

        // Aderência à malha viária real (POR-VAL-010) — DESLIGADO por padrão
        // (PorThresholds::roadMatchEnabled()). Gap real de auditoria: nenhuma
        // validação acima confere se o ponto está de fato sobre uma via; um
        // ponto poderia estar plausível em velocidade/distância e ainda assim
        // estar numa rua errada. Só se aplica a pontos "em trânsito" (fora
        // das geofences de origem/destino) — perto da origem/destino o
        // veículo pode legitimamente estar fora de qualquer via roteável
        // (estacionamento, acostamento, garagem), então checar ali geraria
        // falso positivo. Fail-open: se a checagem externa não responder,
        // não rejeita — só rejeita quando SABE que o ponto está longe
        // demais de qualquer via (ver RoadNetworkMatchService).
        if (PorThresholds::roadMatchEnabled()
            && !GeofenceService::isNearOrigin($pedido, $lat, $lng, PorThresholds::arrivalRadiusM())
            && !GeofenceService::isNearDestination($pedido, $lat, $lng, PorThresholds::destinationRadiusM())
        ) {
            $distanciaVia = RoadNetworkMatchService::distanceToNearestRoadM($lat, $lng);
            if ($distanciaVia !== null && $distanciaVia > PorThresholds::roadMatchMaxDistanceM()) {
                return self::reject($result, 'POR-VAL-010');
            }
        }

        $result['distance_validated_m'] = $result['distance_raw_m'];
        return $result;
    }

    private static function toTimestampMs(mixed $deviceTimestamp, string $serverTimestamp): int
    {
        if ($deviceTimestamp !== null && $deviceTimestamp !== '') {
            return (int)$deviceTimestamp;
        }
        $ts = strtotime($serverTimestamp);
        return $ts > 0 ? ($ts * 1000) : (int)round(microtime(true) * 1000);
    }

    private static function reject(array $result, string $code): array
    {
        $result['is_valid'] = false;
        $result['rejection_code'] = $code;
        DebugMode::trace('LocationValidationService', 'validatePoint', 'por', 'ponto GPS rejeitado', [
            'rejection_code' => $code,
            'distance_raw_m' => $result['distance_raw_m'] ?? null,
            'elapsed_ms' => $result['elapsed_ms'] ?? null,
            'calculated_speed_kmh' => $result['calculated_speed_kmh'] ?? null,
        ]);
        return $result;
    }
}
