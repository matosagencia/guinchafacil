<?php

declare(strict_types=1);

class PedidoLocalizacao
{
    public static function criar(array $data): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO pedido_localizacoes (
                pedido_id, guincho_id, usuario_id, fase, sequence_number, client_point_id,
                latitude, longitude, accuracy_m, speed_mps, heading_deg,
                device_timestamp, server_timestamp, received_at, previous_point_id,
                distance_raw_m, distance_validated_m, distance_accumulated_m,
                elapsed_ms, calculated_speed_kmh, street_name, street_source,
                match_confidence, is_valid, rejection_code, hash_previous, hash_current,
                request_id, run_id
            ) VALUES (
                :pedido_id, :guincho_id, :usuario_id, :fase, :sequence_number, :client_point_id,
                :latitude, :longitude, :accuracy_m, :speed_mps, :heading_deg,
                :device_timestamp, NOW(), NOW(), :previous_point_id,
                :distance_raw_m, :distance_validated_m, :distance_accumulated_m,
                :elapsed_ms, :calculated_speed_kmh, :street_name, :street_source,
                :match_confidence, :is_valid, :rejection_code, :hash_previous, :hash_current,
                :request_id, :run_id
            )"
        );

        $stmt->execute([
            ':pedido_id' => (int)$data['pedido_id'],
            ':guincho_id' => (int)$data['guincho_id'],
            ':usuario_id' => (int)$data['usuario_id'],
            ':fase' => (string)$data['fase'],
            ':sequence_number' => (int)$data['sequence_number'],
            ':client_point_id' => (string)$data['client_point_id'],
            ':latitude' => (float)$data['latitude'],
            ':longitude' => (float)$data['longitude'],
            ':accuracy_m' => $data['accuracy_m'] !== null ? (float)$data['accuracy_m'] : null,
            ':speed_mps' => $data['speed_mps'] !== null ? (float)$data['speed_mps'] : null,
            ':heading_deg' => $data['heading_deg'] !== null ? (float)$data['heading_deg'] : null,
            ':device_timestamp' => $data['device_timestamp'] !== null ? (string)$data['device_timestamp'] : null,
            ':previous_point_id' => $data['previous_point_id'] !== null ? (int)$data['previous_point_id'] : null,
            ':distance_raw_m' => (float)$data['distance_raw_m'],
            ':distance_validated_m' => (float)$data['distance_validated_m'],
            ':distance_accumulated_m' => (float)$data['distance_accumulated_m'],
            ':elapsed_ms' => $data['elapsed_ms'] !== null ? (int)$data['elapsed_ms'] : null,
            ':calculated_speed_kmh' => $data['calculated_speed_kmh'] !== null ? (float)$data['calculated_speed_kmh'] : null,
            ':street_name' => $data['street_name'] ?? null,
            ':street_source' => $data['street_source'] ?? null,
            ':match_confidence' => $data['match_confidence'] !== null ? (float)$data['match_confidence'] : null,
            ':is_valid' => !empty($data['is_valid']) ? 1 : 0,
            ':rejection_code' => $data['rejection_code'] ?? null,
            ':hash_previous' => $data['hash_previous'] ?? null,
            ':hash_current' => $data['hash_current'] ?? null,
            ':request_id' => $data['request_id'] ?? null,
            ':run_id' => $data['run_id'] ?? null,
        ]);

        return (int)getPDO()->lastInsertId();
    }

    public static function buscarUltimoPorPedido(int $pedidoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_localizacoes WHERE pedido_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$pedidoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarUltimoValidoPorPedido(int $pedidoId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_localizacoes WHERE pedido_id = ? AND is_valid = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$pedidoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorClientPointId(int $pedidoId, string $clientPointId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_localizacoes WHERE pedido_id = ? AND client_point_id = ? LIMIT 1");
        $stmt->execute([$pedidoId, $clientPointId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Bug real (STRESS-POR-001, 31/07/2026): a corrida de duplicidade tratada
     * em ProofOfRoadService::ingestPoint só sabia achar o "vencedor" de um
     * SQLSTATE 23000 via client_point_id. Duas requisições concorrentes com
     * o MESMO sequence_number (ex.: reenvio automático do app do guincheiro)
     * também violam `uk_pedido_sequence`, e nesse caso o client_point_id de
     * cada uma é DIFERENTE — a busca por client_point_id não encontra nada,
     * caindo no erro genérico em vez da resposta idempotente. Este fallback
     * cobre esse segundo caso real de colisão.
     */
    public static function buscarPorPedidoSequence(int $pedidoId, int $sequence): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_localizacoes WHERE pedido_id = ? AND sequence_number = ? LIMIT 1");
        $stmt->execute([$pedidoId, $sequence]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_localizacoes WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarTrail(int $pedidoId, int $limit = 200): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_localizacoes WHERE pedido_id = ? ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, $pedidoId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * §A3 — $sincePointId (opcional) faz o polling do admin trazer só os
     * pontos novos desde a última leitura (id > sincePointId, ordem
     * cronológica), em vez de sempre reenviar a janela inteira dos últimos
     * $limit pontos a cada chamada. Sem esse parâmetro o comportamento é
     * idêntico ao anterior — nenhum consumidor existente quebra.
     */
    public static function listarTrailFiltrado(int $pedidoId, array $filters = [], int $limit = 400, ?int $sincePointId = null): array
    {
        $where = ['pedido_id = ?'];
        $params = [$pedidoId];

        if (!empty($filters['fase']) && in_array($filters['fase'], ['origem', 'destino'], true)) {
            $where[] = 'fase = ?';
            $params[] = (string)$filters['fase'];
        }

        if (array_key_exists('valid_only', $filters) && $filters['valid_only'] !== '') {
            if ($filters['valid_only'] === '1') {
                $where[] = 'is_valid = 1';
            } elseif ($filters['valid_only'] === '0') {
                $where[] = 'is_valid = 0';
            }
        }

        $usaCursor = $sincePointId !== null && $sincePointId > 0;
        if ($usaCursor) {
            $where[] = 'id > ?';
            $params[] = $sincePointId;
        }

        $ordem = $usaCursor ? 'ASC' : 'DESC';
        $sql = 'SELECT * FROM pedido_localizacoes WHERE ' . implode(' AND ', $where) . " ORDER BY id {$ordem} LIMIT ?";
        $stmt = getPDO()->prepare($sql);
        $position = 1;
        foreach ($params as $param) {
            $type = is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($position++, $param, $type);
        }
        $stmt->bindValue($position, max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $usaCursor ? $rows : array_reverse($rows);
    }

    public static function listarRejeitados(int $pedidoId, int $limit = 20): array
    {
        $stmt = getPDO()->prepare(
            "SELECT *
               FROM pedido_localizacoes
              WHERE pedido_id = ? AND is_valid = 0
              ORDER BY id DESC
              LIMIT ?"
        );
        $stmt->bindValue(1, $pedidoId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function resumirRejeicoes(int $pedidoId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT rejection_code, COUNT(*) AS total
               FROM pedido_localizacoes
              WHERE pedido_id = ? AND is_valid = 0
              GROUP BY rejection_code
              ORDER BY total DESC, rejection_code ASC"
        );
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
