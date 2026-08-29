<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/Pedido.php';
require_once __DIR__ . '/../../Models/PedidoLocalizacao.php';
require_once __DIR__ . '/../../Models/PedidoPercursoResumo.php';
require_once __DIR__ . '/../../Models/Configuracao.php';
require_once __DIR__ . '/../../Services/Logger.php';
require_once __DIR__ . '/LocationValidationService.php';
require_once __DIR__ . '/DistanceAccumulatorService.php';
require_once __DIR__ . '/GeofenceService.php';
require_once __DIR__ . '/StreetResolutionService.php';

class ProofOfRoadService
{
    public static function ingestPoint(int $pedidoId, int $guinchoId, int $usuarioId, array $payload): array
    {
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (int)($pedido['guincho_id'] ?? 0) !== $guinchoId) {
            // §LOG-POR-01 (29/07/2026): antes esse early-return não deixava
            // nenhum rastro — um ponto de GPS recusado aqui some sem
            // explicação em qualquer log, mesmo sendo exatamente o tipo de
            // recusa silenciosa que trava o rastreamento em campo.
            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'POR', 'Ponto POR recusado: pedido inválido ou não pertence a este guincho', [
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'code' => 'POR-ING-003',
                'pedido_guincho_id' => $pedido['guincho_id'] ?? null,
                'pedido_encontrado' => (bool)$pedido,
            ]);
            return ['ok' => false, 'accepted' => false, 'erro' => 'Pedido inválido para rastreamento.'];
        }

        if (!in_array((string)$pedido['status'], ['a_caminho', 'no_local', 'em_reboque'], true)) {
            Logger::log(Logger::LEVEL_WARN, __CLASS__, __FUNCTION__, 'POR', 'Ponto POR recusado: pedido fora de fase rastreável', [
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'code' => 'POR-ING-004',
                'status_atual' => $pedido['status'],
            ]);
            return ['ok' => false, 'accepted' => false, 'erro' => 'Pedido fora de fase rastreável.'];
        }

        $clientPointId = trim((string)($payload['client_point_id'] ?? ''));
        if ($clientPointId === '') {
            $clientPointId = bin2hex(random_bytes(8));
        }

        $existing = PedidoLocalizacao::buscarPorClientPointId($pedidoId, $clientPointId);
        if ($existing) {
            return [
                'ok' => true,
                'accepted' => (bool)$existing['is_valid'],
                'point_id' => (int)$existing['id'],
                'sequence' => (int)$existing['sequence_number'],
                'distance_accumulated_m' => (float)$existing['distance_accumulated_m'],
                'tracking_quality' => self::trackingQualityFromPoint($existing),
                'current_street' => $existing['street_name'],
                'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
            ];
        }

        $lastPoint = PedidoLocalizacao::buscarUltimoPorPedido($pedidoId);
        $summary = PedidoPercursoResumo::buscar($pedidoId, self::faseFromStatus((string)$pedido['status']));

        $normalized = [
            'latitude' => (float)($payload['latitude'] ?? $payload['lat'] ?? 0),
            'longitude' => (float)($payload['longitude'] ?? $payload['lng'] ?? 0),
            'accuracy_m' => isset($payload['accuracy_m']) ? (float)$payload['accuracy_m'] : null,
            'speed_mps' => isset($payload['speed_mps']) ? (float)$payload['speed_mps'] : null,
            'heading_deg' => isset($payload['heading_deg']) ? (float)$payload['heading_deg'] : null,
            'device_timestamp' => isset($payload['device_timestamp']) ? (string)$payload['device_timestamp'] : null,
            'sequence' => (int)($payload['sequence'] ?? (($lastPoint ? ((int)$lastPoint['sequence_number'] + 1) : 1))),
            'client_point_id' => $clientPointId,
        ];

        $validation = LocationValidationService::validatePoint($pedido, $normalized, $lastPoint);
        $accumulated = DistanceAccumulatorService::accumulate($summary, $validation);
        $resolvedStreet = StreetResolutionService::resolve(
            $pedido,
            $normalized['latitude'],
            $normalized['longitude'],
            self::faseFromStatus((string)$pedido['status'])
        );

        $hashPrevious = $lastPoint['hash_current'] ?? null;
        $hashCurrent = hash('sha256', json_encode([
            'pedido_id' => $pedidoId,
            'sequence' => $normalized['sequence'],
            'lat' => $normalized['latitude'],
            'lng' => $normalized['longitude'],
            'ts' => $normalized['device_timestamp'],
            'prev' => $hashPrevious,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $quality = self::trackingQuality($summary, $validation);
        $pdo = getPDO();

        // Pacote L1.4 — ponto + resumo passam a ser gravados numa única transação.
        // Antes, PedidoLocalizacao::criar() e PedidoPercursoResumo::upsert() eram
        // duas operações independentes: se a segunda falhasse, o ponto ficava
        // gravado com o resumo (distância acumulada, qualidade) desatualizado.
        try {
            $pdo->beginTransaction();

            $pointId = PedidoLocalizacao::criar([
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'usuario_id' => $usuarioId,
                'fase' => self::faseFromStatus((string)$pedido['status']),
                'sequence_number' => $normalized['sequence'],
                'client_point_id' => $normalized['client_point_id'],
                'latitude' => $normalized['latitude'],
                'longitude' => $normalized['longitude'],
                'accuracy_m' => $normalized['accuracy_m'],
                'speed_mps' => $normalized['speed_mps'],
                'heading_deg' => $normalized['heading_deg'],
                'device_timestamp' => $normalized['device_timestamp'],
                'previous_point_id' => $lastPoint ? (int)$lastPoint['id'] : null,
                'distance_raw_m' => $validation['distance_raw_m'],
                'distance_validated_m' => $accumulated['validated_increment_m'],
                'distance_accumulated_m' => $accumulated['validated_total_m'],
                'elapsed_ms' => $validation['elapsed_ms'],
                'calculated_speed_kmh' => $validation['calculated_speed_kmh'],
                'street_name' => $resolvedStreet['street_name'],
                'street_source' => $resolvedStreet['street_source'],
                'match_confidence' => $resolvedStreet['match_confidence'],
                'is_valid' => $validation['is_valid'],
                'rejection_code' => $validation['rejection_code'],
                'hash_previous' => $hashPrevious,
                'hash_current' => $hashCurrent,
                'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
                'run_id' => $_SERVER['HTTP_X_RUN_ID'] ?? null,
            ]);

            PedidoPercursoResumo::upsert([
                'pedido_id' => $pedidoId,
                'fase' => self::faseFromStatus((string)$pedido['status']),
                'total_points' => (int)($summary['total_points'] ?? 0) + 1,
                'valid_points' => (int)($summary['valid_points'] ?? 0) + (!empty($validation['is_valid']) ? 1 : 0),
                'rejected_points' => (int)($summary['rejected_points'] ?? 0) + (empty($validation['is_valid']) ? 1 : 0),
                'started_at' => $summary['started_at'] ?? date('Y-m-d H:i:s'),
                'last_point_at' => date('Y-m-d H:i:s'),
                'duration_seconds' => self::durationSeconds($summary['started_at'] ?? null),
                'distance_raw_m' => $accumulated['raw_total_m'],
                'distance_validated_m' => $accumulated['validated_total_m'],
                'max_gap_seconds' => max((int)($summary['max_gap_seconds'] ?? 0), (int)round(((float)($validation['elapsed_ms'] ?? 0)) / 1000)),
                'max_speed_kmh' => max((float)($summary['max_speed_kmh'] ?? 0), (float)($validation['calculated_speed_kmh'] ?? 0)),
                'tracking_quality' => $quality,
                'last_street' => $resolvedStreet['street_name'],
                'last_latitude' => $normalized['latitude'],
                'last_longitude' => $normalized['longitude'],
            ]);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Pacote L1.4 — corrida de duplicidade: o app do guincheiro reenvia
            // pontos automaticamente ao reconectar depois de queda de rede, e o
            // check-then-act acima (buscarPorClientPointId) não elimina a janela
            // de corrida entre dois requests concorrentes. SQLSTATE 23000 =
            // violação de integridade (inclui UNIQUE KEY em client_point_id ou
            // sequence_number). Em vez de deixar vazar um 500 pro app em campo,
            // devolvemos o ponto que efetivamente foi gravado pela requisição
            // concorrente vencedora — resposta idempotente, não erro.
            if ($e->getCode() === '23000') {
                $winner = PedidoLocalizacao::buscarPorClientPointId($pedidoId, $normalized['client_point_id']);
                // Bug real (STRESS-POR-001, 31/07/2026): client_point_id
                // diferente + mesmo sequence_number também viola UNIQUE KEY
                // (uk_pedido_sequence) — cenário real de reenvio automático
                // do app do guincheiro concorrendo com outra requisição pro
                // mesmo ponto da rota. Sem este fallback, o vencedor nunca
                // era encontrado e a resposta idempotente virava erro
                // genérico (POR-ING-002) mesmo com o ponto já gravado com
                // sucesso pela requisição concorrente.
                if (!$winner) {
                    $winner = PedidoLocalizacao::buscarPorPedidoSequence($pedidoId, (int)$normalized['sequence']);
                }
                if ($winner) {
                    return [
                        'ok' => true,
                        'accepted' => (bool)$winner['is_valid'],
                        'point_id' => (int)$winner['id'],
                        'sequence' => (int)$winner['sequence_number'],
                        'distance_accumulated_m' => (float)$winner['distance_accumulated_m'],
                        'tracking_quality' => self::trackingQualityFromPoint($winner),
                        'current_street' => $winner['street_name'],
                        'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
                        'idempotent_retry' => true,
                    ];
                }
            }

            Logger::exception(__CLASS__, __FUNCTION__, 'POR', $e, [
                'pedido_id' => $pedidoId,
                'guincho_id' => $guinchoId,
                'code' => 'POR-ING-002',
            ]);

            return ['ok' => false, 'accepted' => false, 'erro' => 'Falha ao registrar ponto de rastreamento.'];
        }

        Logger::log(Logger::LEVEL_INFO, __CLASS__, __FUNCTION__, 'POR', 'Ponto POR ingerido', [
            'pedido_id' => $pedidoId,
            'guincho_id' => $guinchoId,
            'code' => 'POR-ING-001',
            'accepted' => $validation['is_valid'],
            'rejection_code' => $validation['rejection_code'],
        ]);

        return [
            'ok' => true,
            'accepted' => !empty($validation['is_valid']),
            'point_id' => $pointId,
            'sequence' => $normalized['sequence'],
            'distance_accumulated_m' => $accumulated['validated_total_m'],
            'tracking_quality' => $quality,
            'current_street' => $resolvedStreet['street_name'],
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
            'rejection_code' => $validation['rejection_code'],
        ];
    }

    public static function getCurrentSnapshot(int $pedidoId): array
    {
        $summaryOrigem = PedidoPercursoResumo::buscar($pedidoId, 'origem');
        $summaryDestino = PedidoPercursoResumo::buscar($pedidoId, 'destino');

        $totalPoints = (int)($summaryOrigem['total_points'] ?? 0) + (int)($summaryDestino['total_points'] ?? 0);
        $validPoints = (int)($summaryOrigem['valid_points'] ?? 0) + (int)($summaryDestino['valid_points'] ?? 0);
        $qualidadePct = $totalPoints > 0 ? (int)round(($validPoints / $totalPoints) * 100) : null;

        $integridade = self::verificarIntegridadeRota($pedidoId);

        return [
            'last_point' => PedidoLocalizacao::buscarUltimoPorPedido($pedidoId),
            'last_valid_point' => PedidoLocalizacao::buscarUltimoValidoPorPedido($pedidoId),
            'summary_origem' => $summaryOrigem,
            'summary_destino' => $summaryDestino,
            'qualidade_pct' => $qualidadePct,
            'rota_integra' => $integridade['integro'],
            'rota_pontos_verificados' => $integridade['pontos_verificados'],
        ];
    }

    /**
     * Verifica a continuidade do encadeamento de hashes dos pontos POR do
     * pedido (hash_previous de cada ponto deve bater com o hash_current do
     * ponto anterior, na ordem de sequence_number). Detecta lacunas, remoção
     * ou reordenação de pontos.
     *
     * Não recomputa hash_current a partir de latitude/longitude armazenados:
     * lat/lng são gravados em colunas DECIMAL(10,8)/DECIMAL(11,8), então o
     * valor lido de volta do banco pode ter menos casas decimais que o float
     * original usado para gerar o hash em ProofOfRoadService::ingestPoint()
     * — recalcular o hash a partir do dado já truncado produziria falsos
     * "rota corrompida" para pontos legítimos. Verificar só o encadeamento
     * (hash_previous ↔ hash_current) é a checagem que dá pra fazer de forma
     * confiável com o que é persistido hoje.
     */
    public static function verificarIntegridadeRota(int $pedidoId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT id, sequence_number, hash_previous, hash_current
               FROM pedido_localizacoes
              WHERE pedido_id = ?
              ORDER BY sequence_number ASC, id ASC"
        );
        $stmt->execute([$pedidoId]);
        $pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pontos)) {
            return ['integro' => true, 'pontos_verificados' => 0];
        }

        $hashAnterior = null;
        foreach ($pontos as $i => $ponto) {
            if ($i > 0 && (string)($ponto['hash_previous'] ?? '') !== (string)($hashAnterior ?? '')) {
                return ['integro' => false, 'pontos_verificados' => $i];
            }
            $hashAnterior = $ponto['hash_current'];
        }

        return ['integro' => true, 'pontos_verificados' => count($pontos)];
    }

    public static function getTrail(int $pedidoId, int $limit = 200): array
    {
        return PedidoLocalizacao::listarTrail($pedidoId, $limit);
    }

    public static function getSummary(int $pedidoId): array
    {
        return [
            'origem' => PedidoPercursoResumo::buscar($pedidoId, 'origem'),
            'destino' => PedidoPercursoResumo::buscar($pedidoId, 'destino'),
        ];
    }

    private static function faseFromStatus(string $status): string
    {
        return in_array($status, ['a_caminho', 'no_local'], true) ? 'origem' : 'destino';
    }

    private static function trackingQuality(?array $summary, array $validation): string
    {
        if (empty($validation['is_valid'])) {
            return 'poor';
        }

        $totalPoints = max(1, (int)($summary['total_points'] ?? 0) + 1);
        $rejected = (int)($summary['rejected_points'] ?? 0);
        $maxGapSeconds = (int)($summary['max_gap_seconds'] ?? 0);
        $rejectionRate = $rejected / $totalPoints;

        if ($rejectionRate >= 0.35 || $maxGapSeconds >= 300) {
            return 'poor';
        }

        if ($rejected > 3 || $rejectionRate >= 0.15 || $maxGapSeconds >= 180) {
            return 'fair';
        }

        return 'good';
    }

    private static function trackingQualityFromPoint(array $point): string
    {
        return !empty($point['is_valid']) ? 'good' : 'poor';
    }

    private static function durationSeconds(?string $startedAt): int
    {
        if (!$startedAt) {
            return 0;
        }
        $ts = strtotime($startedAt);
        return $ts > 0 ? max(0, time() - $ts) : 0;
    }
}
