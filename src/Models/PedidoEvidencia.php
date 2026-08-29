<?php

declare(strict_types=1);

class PedidoEvidencia
{
    public static function criar(array $data): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO pedido_evidencias (
                pedido_id, guincho_id, tipo, status, nonce_token, nonce_expires_at,
                point_id, latitude, longitude, accuracy_m, device_timestamp, server_timestamp,
                original_name, stored_name, mime_type, size_bytes, sha256, metadata_json, created_at
            ) VALUES (
                :pedido_id, :guincho_id, :tipo, :status, :nonce_token, :nonce_expires_at,
                :point_id, :latitude, :longitude, :accuracy_m, :device_timestamp, NOW(),
                :original_name, :stored_name, :mime_type, :size_bytes, :sha256, :metadata_json, NOW()
            )"
        );
        $stmt->execute([
            ':pedido_id' => (int)$data['pedido_id'],
            ':guincho_id' => (int)$data['guincho_id'],
            ':tipo' => (string)$data['tipo'],
            ':status' => (string)$data['status'],
            ':nonce_token' => (string)$data['nonce_token'],
            ':nonce_expires_at' => (string)$data['nonce_expires_at'],
            ':point_id' => (int)$data['point_id'],
            ':latitude' => (float)$data['latitude'],
            ':longitude' => (float)$data['longitude'],
            ':accuracy_m' => $data['accuracy_m'] !== null ? (float)$data['accuracy_m'] : null,
            ':device_timestamp' => $data['device_timestamp'] ?? null,
            ':original_name' => (string)$data['original_name'],
            ':stored_name' => (string)$data['stored_name'],
            ':mime_type' => (string)$data['mime_type'],
            ':size_bytes' => (int)$data['size_bytes'],
            ':sha256' => (string)$data['sha256'],
            ':metadata_json' => json_encode($data['metadata_json'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int)getPDO()->lastInsertId();
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_evidencias WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function buscarUltimaPorTipo(int $pedidoId, string $tipo): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_evidencias WHERE pedido_id = ? AND tipo = ? AND status = 'accepted' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$pedidoId, $tipo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function listarPorPedido(int $pedidoId): array
    {
        $stmt = getPDO()->prepare("SELECT * FROM pedido_evidencias WHERE pedido_id = ? ORDER BY id ASC");
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
