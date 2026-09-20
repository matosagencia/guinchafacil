<?php

declare(strict_types=1);

final class PedidoBypassCase
{
    public const SUSPEITO = 'SUSPEITO';
    public const CONFIRMADO = 'AUDITADO_CONFIRMADO';
    public const ISENTO = 'ISENTO_ESTORNADO';

    public static function encontrar(int $pedidoId, int $providerId, ?PDO $pdo = null): ?array
    {
        $pdo ??= getPDO();
        $stmt = $pdo->prepare('SELECT * FROM pedido_bypass_cases WHERE pedido_id = ? AND provider_id = ? LIMIT 1');
        $stmt->execute([$pedidoId, $providerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function criarOuAtualizar(array $dados, ?PDO $pdo = null): array
    {
        $pdo ??= getPDO();
        $sql = 'INSERT INTO pedido_bypass_cases
            (pedido_id, provider_id, amostras_validas_count, permanencia_minutos, precisao_media_m, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE amostras_validas_count = VALUES(amostras_validas_count), permanencia_minutos = VALUES(permanencia_minutos), precisao_media_m = VALUES(precisao_media_m), updated_at = NOW()';
        $pdo->prepare($sql)->execute([
            (int)$dados['pedido_id'], (int)$dados['provider_id'], (int)$dados['amostras_validas_count'],
            (int)$dados['permanencia_minutos'], (float)$dados['precisao_media_m'], self::SUSPEITO,
        ]);
        return self::encontrar((int)$dados['pedido_id'], (int)$dados['provider_id'], $pdo) ?? [];
    }

    public static function listar(string $status = self::SUSPEITO): array
    {
        $stmt = getPDO()->prepare('SELECT c.*, p.status AS pedido_status, COALESCE(p.cancelado_em, p.criado_em) AS cancelado_at,
                pr.trade_name, pr.legal_name FROM pedido_bypass_cases c
            JOIN pedidos p ON p.id = c.pedido_id JOIN providers pr ON pr.id = c.provider_id
            WHERE c.status = ? ORDER BY c.created_at ASC');
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function decidir(int $id, int $adminId, string $status, string $nota): bool
    {
        if (!in_array($status, [self::CONFIRMADO, self::ISENTO], true)) {
            throw new InvalidArgumentException('Decisão de bypass inválida.');
        }
        $stmt = getPDO()->prepare('UPDATE pedido_bypass_cases SET status = ?, decisao_admin_id = ?, decisao_nota = ?, updated_at = NOW() WHERE id = ? AND status = ?');
        $stmt->execute([$status, $adminId, mb_substr($nota, 0, 255), $id, self::SUSPEITO]);
        return $stmt->rowCount() > 0;
    }
}
