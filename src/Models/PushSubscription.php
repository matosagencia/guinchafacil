<?php

class PushSubscription
{
    public static function listarPorUsuario(int $usuarioId, string $tipoUsuario): array
    {
        $pdo = getPDO();
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, usuario_id, tipo_usuario, endpoint, endpoint_hash, p256dh, auth, user_agent, created_at, updated_at, last_seen_at
               FROM push_subscriptions
              WHERE usuario_id = ? AND tipo_usuario = ?
              ORDER BY id DESC'
        );
        $stmt->execute([$usuarioId, $tipoUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function upsertForUser(int $usuarioId, string $tipoUsuario, array $subscription, ?string $userAgent = null): array
    {
        $endpoint = trim((string)($subscription['endpoint'] ?? ''));
        $keys = is_array($subscription['keys'] ?? null) ? $subscription['keys'] : [];
        $p256dh = trim((string)($keys['p256dh'] ?? ''));
        $auth = trim((string)($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new InvalidArgumentException('Subscription Web Push invalida.');
        }

        $pdo = getPDO();
        self::ensureSchema($pdo);

        $endpointHash = hash('sha256', $endpoint);
        $sql = <<<SQL
INSERT INTO push_subscriptions (
    usuario_id, tipo_usuario, endpoint, endpoint_hash, p256dh, auth, user_agent, created_at, updated_at, last_seen_at
) VALUES (
    :usuario_id, :tipo_usuario, :endpoint, :endpoint_hash, :p256dh, :auth, :user_agent, NOW(), NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    usuario_id = VALUES(usuario_id),
    tipo_usuario = VALUES(tipo_usuario),
    endpoint = VALUES(endpoint),
    p256dh = VALUES(p256dh),
    auth = VALUES(auth),
    user_agent = VALUES(user_agent),
    updated_at = NOW(),
    last_seen_at = NOW()
SQL;

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':tipo_usuario' => $tipoUsuario,
            ':endpoint' => $endpoint,
            ':endpoint_hash' => $endpointHash,
            ':p256dh' => $p256dh,
            ':auth' => $auth,
            ':user_agent' => $userAgent,
        ]);

        return [
            'usuario_id' => $usuarioId,
            'tipo_usuario' => $tipoUsuario,
            'endpoint' => $endpoint,
            'endpoint_hash' => $endpointHash,
        ];
    }

    public static function deleteForUserEndpoint(int $usuarioId, string $tipoUsuario, string $endpoint): bool
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return false;
        }

        $pdo = getPDO();
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            'DELETE FROM push_subscriptions WHERE usuario_id = ? AND tipo_usuario = ? AND endpoint_hash = ?'
        );
        $stmt->execute([$usuarioId, $tipoUsuario, hash('sha256', $endpoint)]);
        return $stmt->rowCount() > 0;
    }

    public static function deleteByEndpointHash(string $endpointHash): bool
    {
        $endpointHash = trim($endpointHash);
        if ($endpointHash === '') {
            return false;
        }

        $pdo = getPDO();
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?');
        $stmt->execute([$endpointHash]);
        return $stmt->rowCount() > 0;
    }

    private static function ensureSchema(PDO $pdo): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'push_subscriptions'"
        );
        if ((int)$stmt->fetchColumn() < 1) {
            throw new RuntimeException('Schema ausente: tabela push_subscriptions nao existe. Rode install/migration_push_subscriptions_v1.sql.');
        }

        $checked = true;
    }
}
