<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Provider/ProviderMember.php
// ROADMAP socorro automotivo — Etapa 12 (camada aditiva de prestador).
// Ver Provider.php para contexto. Vincula usuários (técnicos, operadores,
// donos) a um provider — uma oficina pode ter vários; um autônomo
// normalmente é o único membro do seu próprio provider.

final class ProviderMember
{
    private const TBL = 'provider_members';

    public const ROLE_OWNER_OPERATOR = 'OWNER_OPERATOR';
    public const ROLE_TECHNICIAN = 'TECHNICIAN';
    public const ROLE_DISPATCHER = 'DISPATCHER';
    public const ROLE_MANAGER = 'MANAGER';

    public static function listarPorProvider(int $providerId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT pm.*, u.nome, u.email
             FROM " . self::TBL . " pm
             JOIN usuarios u ON u.id = pm.user_id
             WHERE pm.provider_id = ? ORDER BY pm.id ASC"
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Idempotente: reenvio não duplica o vínculo provider+usuário (UNIQUE). */
    public static function adicionar(int $providerId, int $userId, string $roleCode = self::ROLE_OWNER_OPERATOR): void
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (provider_id, user_id, role_code, approval_status, can_accept_orders, can_execute_services, can_manage_inventory, created_at, updated_at)
             VALUES (?,?,?, 'APPROVED', 1, 1, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE role_code = VALUES(role_code), updated_at = NOW()"
        );
        $stmt->execute([$providerId, $userId, $roleCode]);
    }

    public static function buscarPorProviderEUsuario(int $providerId, int $userId): ?array
    {
        $stmt = getPDO()->prepare("SELECT * FROM " . self::TBL . " WHERE provider_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$providerId, $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
