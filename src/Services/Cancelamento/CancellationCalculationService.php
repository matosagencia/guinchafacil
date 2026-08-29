<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/PedidoPercursoResumo.php';
require_once __DIR__ . '/../POR/GeofenceService.php';

class CancellationCalculationService
{
    /**
     * Pacote L1.6 — versão da fórmula gravada em cada snapshot. Incrementar
     * sempre que a lógica de cálculo mudar, para permitir auditoria histórica
     * (um snapshot antigo permanece rastreável à fórmula que o gerou).
     *
     * v2 (§A1, 21/07): distance_ratio deixou de normalizar a distância GPS
     * guincho→origem contra pedidos.distancia_km (que é a distância
     * origem→destino cotada ao cliente — conceito errado, sem relação com o
     * quanto o guincho efetivamente percorreu até a origem). Passa a usar
     * pedidos.distancia_guincho_origem_km, calculada no momento do aceite.
     */
    public const FORMULA_VERSION = 'v2';

    public static function calculate(array $pedido, array $cfg): array
    {
        $status = (string)($pedido['status'] ?? '');
        $summary = PedidoPercursoResumo::buscar((int)$pedido['id'], 'origem');
        $distanceM = (float)($summary['distance_validated_m'] ?? 0);
        $distanceKm = $distanceM / 1000;
        $travelSeconds = (int)($summary['duration_seconds'] ?? 0);
        $base = (float)($pedido['custo_final'] ?: ($pedido['custo_estimado'] ?? 0));
        $fixedFee = (float)($cfg['taxa_cancelamento_fixa'] ?? 15);
        $percent = (float)($cfg['taxa_cancelamento_percent'] ?? 20);
        $blockKm = (float)($cfg['km_bloqueio_cancelamento'] ?? 2);

        if ($status === 'aguardando_pagamento') {
            return self::result(true, 0.0, null, $summary, []);
        }

        if ($status === 'aguardando_guincho') {
            return self::result(true, 0.0, null, $summary, []);
        }

        if ($status === 'a_caminho') {
            $lastLat = isset($summary['last_latitude']) ? (float)$summary['last_latitude'] : null;
            $lastLng = isset($summary['last_longitude']) ? (float)$summary['last_longitude'] : null;
            if ($lastLat !== null && $lastLng !== null && GeofenceService::distanceToOrigin($pedido, $lastLat, $lastLng) <= ($blockKm * 1000)) {
                return self::result(false, 0.0, 'O guincho já está próximo demais da origem para cancelamento.', $summary, []);
            }

            // §A1 (v2): base do ratio é a distância guincho→origem no aceite
            // (distancia_guincho_origem_km), não a distância origem→destino
            // cotada ao cliente (distancia_km — conceito diferente). Pedidos
            // aceitos antes desta coluna existir (valor NULL) caem no fallback
            // legado pra não quebrar cancelamentos em andamento na migração.
            $distanciaBaseKm = $pedido['distancia_guincho_origem_km'] ?? null;
            if ($distanciaBaseKm === null) {
                $distanciaBaseKm = (float)($pedido['distancia_km'] ?? 1);
            } else {
                $distanciaBaseKm = (float)$distanciaBaseKm;
            }
            $distanceRatio = $base > 0 ? min(1.0, $distanceKm / max(1.0, $distanciaBaseKm)) : 0.0;
            $timeRatio = min(1.0, $travelSeconds / max(300.0, (float)($cfg['cancelamento_gratis_min'] ?? 5) * 60));
            $constitutionalFactor = max($distanceRatio, $timeRatio, 0.1);
            $fee = max($fixedFee, round($base * ($percent / 100) * $constitutionalFactor, 2));
            $factors = [
                'distance_ratio' => round($distanceRatio, 4),
                'distance_base_km' => round($distanciaBaseKm, 4),
                'time_ratio' => round($timeRatio, 4),
                'constitutional_factor' => round($constitutionalFactor, 4),
                'base' => $base,
                'fixed_fee' => $fixedFee,
                'percent' => $percent,
                'block_km' => $blockKm,
            ];
            return self::result(true, min($fee, $base), null, $summary, $factors);
        }

        if (in_array($status, ['no_local', 'em_reboque'], true)) {
            return self::result(false, 0.0, 'O atendimento já atingiu fase irreversível para cancelamento do cliente.', $summary, []);
        }

        return self::result(false, 0.0, 'Pedido não pode ser cancelado nesta fase.', $summary, []);
    }

    private static function result(bool $canCancel, float $fee, ?string $reason, ?array $summary, array $factors): array
    {
        return [
            'pode' => $canCancel,
            'taxa' => $fee,
            'motivo_bloqueio' => $reason,
            'proof_distance_m' => (float)($summary['distance_validated_m'] ?? 0),
            'proof_duration_s' => (int)($summary['duration_seconds'] ?? 0),
            'tracking_quality' => $summary['tracking_quality'] ?? 'unknown',
            'formula_version' => self::FORMULA_VERSION,
            'factors' => $factors,
        ];
    }
}
