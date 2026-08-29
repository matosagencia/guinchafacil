<?php

declare(strict_types=1);

require_once __DIR__ . '/PorThresholds.php';

class DistanceAccumulatorService
{
    public static function accumulate(?array $summary, array $validation): array
    {
        $minPointDistance = PorThresholds::minPointDistanceM();
        $rawTotal = (float)($summary['distance_raw_m'] ?? 0) + (float)$validation['distance_raw_m'];

        $validatedIncrement = !empty($validation['is_valid']) && (float)$validation['distance_validated_m'] >= $minPointDistance
            ? (float)$validation['distance_validated_m']
            : 0.0;

        return [
            'raw_total_m' => $rawTotal,
            'validated_increment_m' => $validatedIncrement,
            'validated_total_m' => (float)($summary['distance_validated_m'] ?? 0) + $validatedIncrement,
        ];
    }
}
