<?php

declare(strict_types=1);

class PedidoPercursoResumo
{
    public static function buscar(int $pedidoId, string $fase): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_percurso_resumos WHERE pedido_id = ? AND fase = ? LIMIT 1");
        $stmt->execute([$pedidoId, $fase]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function upsert(array $data): void
    {
        $pdo = getPDO();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // "ON DUPLICATE KEY UPDATE" é sintaxe exclusiva do MySQL; o stub SQLite
        // usado pelos testes de integração (tests/bootstrap.php) precisa do
        // equivalente padrão SQL "ON CONFLICT ... DO UPDATE" (SQLite >= 3.24,
        // disponível na versão empacotada com o PHP do XAMPP/CI). A chave de
        // conflito é a PRIMARY KEY (pedido_id, fase) das duas tabelas.
        if ($driver === 'sqlite') {
            $sql = "INSERT INTO pedido_percurso_resumos (
                        pedido_id, fase, total_points, valid_points, rejected_points,
                        started_at, last_point_at, duration_seconds, distance_raw_m,
                        distance_validated_m, max_gap_seconds, max_speed_kmh,
                        tracking_quality, last_street, last_latitude, last_longitude, updated_at
                    ) VALUES (
                        :pedido_id, :fase, :total_points, :valid_points, :rejected_points,
                        :started_at, :last_point_at, :duration_seconds, :distance_raw_m,
                        :distance_validated_m, :max_gap_seconds, :max_speed_kmh,
                        :tracking_quality, :last_street, :last_latitude, :last_longitude, NOW()
                    )
                    ON CONFLICT(pedido_id, fase) DO UPDATE SET
                        total_points = excluded.total_points,
                        valid_points = excluded.valid_points,
                        rejected_points = excluded.rejected_points,
                        started_at = excluded.started_at,
                        last_point_at = excluded.last_point_at,
                        duration_seconds = excluded.duration_seconds,
                        distance_raw_m = excluded.distance_raw_m,
                        distance_validated_m = excluded.distance_validated_m,
                        max_gap_seconds = excluded.max_gap_seconds,
                        max_speed_kmh = excluded.max_speed_kmh,
                        tracking_quality = excluded.tracking_quality,
                        last_street = excluded.last_street,
                        last_latitude = excluded.last_latitude,
                        last_longitude = excluded.last_longitude,
                        updated_at = excluded.updated_at";
        } else {
            $sql = "INSERT INTO pedido_percurso_resumos (
                        pedido_id, fase, total_points, valid_points, rejected_points,
                        started_at, last_point_at, duration_seconds, distance_raw_m,
                        distance_validated_m, max_gap_seconds, max_speed_kmh,
                        tracking_quality, last_street, last_latitude, last_longitude, updated_at
                    ) VALUES (
                        :pedido_id, :fase, :total_points, :valid_points, :rejected_points,
                        :started_at, :last_point_at, :duration_seconds, :distance_raw_m,
                        :distance_validated_m, :max_gap_seconds, :max_speed_kmh,
                        :tracking_quality, :last_street, :last_latitude, :last_longitude, NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        total_points = VALUES(total_points),
                        valid_points = VALUES(valid_points),
                        rejected_points = VALUES(rejected_points),
                        started_at = VALUES(started_at),
                        last_point_at = VALUES(last_point_at),
                        duration_seconds = VALUES(duration_seconds),
                        distance_raw_m = VALUES(distance_raw_m),
                        distance_validated_m = VALUES(distance_validated_m),
                        max_gap_seconds = VALUES(max_gap_seconds),
                        max_speed_kmh = VALUES(max_speed_kmh),
                        tracking_quality = VALUES(tracking_quality),
                        last_street = VALUES(last_street),
                        last_latitude = VALUES(last_latitude),
                        last_longitude = VALUES(last_longitude),
                        updated_at = NOW()";
        }

        $pdo->prepare($sql)->execute([
            ':pedido_id' => (int)$data['pedido_id'],
            ':fase' => (string)$data['fase'],
            ':total_points' => (int)$data['total_points'],
            ':valid_points' => (int)$data['valid_points'],
            ':rejected_points' => (int)$data['rejected_points'],
            ':started_at' => $data['started_at'],
            ':last_point_at' => $data['last_point_at'],
            ':duration_seconds' => (int)$data['duration_seconds'],
            ':distance_raw_m' => (float)$data['distance_raw_m'],
            ':distance_validated_m' => (float)$data['distance_validated_m'],
            ':max_gap_seconds' => (int)$data['max_gap_seconds'],
            ':max_speed_kmh' => (float)$data['max_speed_kmh'],
            ':tracking_quality' => (string)$data['tracking_quality'],
            ':last_street' => $data['last_street'] ?? null,
            ':last_latitude' => $data['last_latitude'] !== null ? (float)$data['last_latitude'] : null,
            ':last_longitude' => $data['last_longitude'] !== null ? (float)$data['last_longitude'] : null,
        ]);
    }
}
