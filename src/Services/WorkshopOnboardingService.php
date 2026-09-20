<?php

declare(strict_types=1);

require_once __DIR__ . '/ProviderWorkshopService.php';
require_once __DIR__ . '/../Models/Provider/Provider.php';
require_once __DIR__ . '/AuditTrailService.php';

final class WorkshopOnboardingService
{
    public static function cadastrar(array $dados, int $actorId): array
    {
        return ProviderWorkshopService::cadastrar($dados, $actorId);
    }

    public static function aprovar(int $providerId, int $adminId): array
    {
        self::assertWorkshop($providerId);
        $stmt = getPDO()->prepare(
            "UPDATE providers
                SET approval_status = 'APPROVED', active = 1, updated_at = NOW()
              WHERE id = ?"
        );
        $stmt->execute([$providerId]);
        AuditTrailService::evento('oficina_parceira_aprovada', __CLASS__, __FUNCTION__, [
            'provider_id' => $providerId,
            'admin_id' => $adminId,
        ]);
        return Provider::buscarPorId($providerId) ?? [];
    }

    public static function suspender(int $providerId, int $adminId, string $motivo): array
    {
        self::assertWorkshop($providerId);
        $stmt = getPDO()->prepare(
            "UPDATE provider_workshop_settings ws
                JOIN providers p ON p.id = ws.provider_id
                SET ws.status_parceria = 'SUSPENSO', p.active = 0, p.updated_at = NOW()
              WHERE ws.provider_id = ?"
        );
        $stmt->execute([$providerId]);
        AuditTrailService::evento('oficina_parceira_suspensa', __CLASS__, __FUNCTION__, [
            'provider_id' => $providerId,
            'admin_id' => $adminId,
            'motivo' => trim($motivo),
        ]);
        return Provider::buscarPorId($providerId) ?? [];
    }

    private static function assertWorkshop(int $providerId): void
    {
        $provider = Provider::buscarPorId($providerId);
        if (!$provider || ($provider['provider_type'] ?? null) !== Provider::TYPE_WORKSHOP) {
            throw new InvalidArgumentException('Provider WORKSHOP não encontrado.');
        }
    }
}
