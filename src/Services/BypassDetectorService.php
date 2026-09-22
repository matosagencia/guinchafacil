<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/PedidoBypassCase.php';
require_once __DIR__ . '/../Services/GeoService.php';
require_once __DIR__ . '/AuditTrailService.php';

final class BypassDetectorService
{
    public const MINUTES_REQUIRED = 45;
    public const MIN_SAMPLES = 3;
    public const MAX_ACCURACY_METERS = 30.0;

    public static function analisarPermanenciaPosCancelamento(int $pedidoId, ?PDO $pdo = null): ?array
    {
        $pdo ??= getPDO();
        $stmt = $pdo->prepare("SELECT p.id, COALESCE(p.cancelado_em, p.criado_em) AS cancelado_at, p.usuario_id, i.provider_id,
                p.modalidade_socorro, p.local_resgate_lat, p.local_resgate_lng,
                ws.latitude, ws.longitude
            FROM pedidos p
            JOIN pedido_indicacoes_oficina i ON i.pedido_id = p.id
            JOIN provider_workshop_settings ws ON ws.provider_id = i.provider_id
            WHERE p.id = ? AND p.status = 'cancelado' LIMIT 1");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido) {
            return null;
        }

        $isDirectRescue = ($pedido['modalidade_socorro'] ?? '') === 'RESGATE_DIRETO_OFICINA';
        $targetLat = $isDirectRescue ? $pedido['local_resgate_lat'] : $pedido['latitude'];
        $targetLng = $isDirectRescue ? $pedido['local_resgate_lng'] : $pedido['longitude'];
        if ($targetLat === null || $targetLng === null) {
            return null;
        }

        $points = $pdo->prepare("SELECT latitude, longitude, precisao_metros, captured_at
            FROM pedido_presenca_localizacoes WHERE pedido_id = ? AND precisao_metros <= ?
            AND captured_at >= ? ORDER BY captured_at ASC");
        $points->execute([$pedidoId, self::MAX_ACCURACY_METERS, $pedido['cancelado_at']]);
        $valid = [];
        foreach ($points->fetchAll(PDO::FETCH_ASSOC) as $point) {
            $distance = GeoService::haversine((float)$point['latitude'], (float)$point['longitude'], (float)$targetLat, (float)$targetLng) * 1000;
            if ($distance <= 150.0) {
                $point['distance_meters'] = $distance;
                $valid[] = $point;
            }
        }
        if (count($valid) < self::MIN_SAMPLES) {
            return null;
        }
        $start = strtotime((string)$valid[0]['captured_at']);
        $end = strtotime((string)$valid[count($valid) - 1]['captured_at']);
        $minutes = (int)floor(max(0, $end - $start) / 60);
        if ($minutes < self::MINUTES_REQUIRED) {
            return null;
        }
        $averageAccuracy = array_sum(array_map(static fn(array $p): float => (float)$p['precisao_metros'], $valid)) / count($valid);
        $case = PedidoBypassCase::criarOuAtualizar([
            'pedido_id' => $pedidoId,
            'provider_id' => (int)$pedido['provider_id'],
            'amostras_validas_count' => count($valid),
            'permanencia_minutos' => $minutes,
            'precisao_media_m' => $averageAccuracy,
        ], $pdo);
        AuditTrailService::evento('OFICINA_BYPASS_SUSPEITO', __CLASS__, __FUNCTION__, [
            'pedido_id' => $pedidoId, 'provider_id' => (int)$pedido['provider_id'],
            'amostras' => count($valid), 'permanencia_minutos' => $minutes,
        ]);
        return $case;
    }
}
