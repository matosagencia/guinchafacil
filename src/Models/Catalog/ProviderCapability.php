<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Catalog/ProviderCapability.php
// ROADMAP socorro automotivo — Fundamento 2 (prestador genérico e capacidades).
//
// §3.3 do roadmap: "Não basta o prestador clicar e se declarar apto." — por
// isso declarar() sempre nasce em PENDING; só aprovar()/suspender()/rejeitar()
// (ações administrativas) mudam approval_status.

require_once __DIR__ . '/../../Services/Logger.php';

class ProviderCapability
{
    private const TBL = 'provider_capabilities';

    public const STATUS_PENDING   = 'PENDING';
    public const STATUS_APPROVED  = 'APPROVED';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_REJECTED  = 'REJECTED';

    public static function listarPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT pc.*, st.code AS service_code, st.name AS service_name, st.attendance_mode
             FROM " . self::TBL . " pc
             JOIN service_types st ON st.id = pc.service_type_id
             WHERE pc.provider_id = ?
             ORDER BY st.name ASC"
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarAprovadasPorPrestador(int $providerId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT pc.*, st.code AS service_code, st.name AS service_name, st.attendance_mode
             FROM " . self::TBL . " pc
             JOIN service_types st ON st.id = pc.service_type_id
             WHERE pc.provider_id = ? AND pc.enabled = 1 AND pc.approval_status = ?
             ORDER BY st.name ASC"
        );
        $stmt->execute([$providerId, self::STATUS_APPROVED]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Todos os prestadores aprovados para um tipo de serviço — base do matching (Etapa 4). */
    public static function listarPrestadoresAprovados(int $serviceTypeId): array
    {
        $stmt = getPDO()->prepare(
            "SELECT pc.*, g.lat_atual, g.lng_atual, g.disponivel, g.aprovado AS guincho_aprovado, g.reputacao
             FROM " . self::TBL . " pc
             JOIN guinchos g ON g.id = pc.provider_id
             WHERE pc.service_type_id = ? AND pc.enabled = 1 AND pc.approval_status = ?
               AND g.aprovado = 1 AND g.disponivel = 1"
        );
        $stmt->execute([$serviceTypeId, self::STATUS_APPROVED]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Etapa 4 (matching) — o prestador pode atender ESTE tipo de serviço?
     * Usado tanto para filtrar a fila de ofertas quanto para revalidar no
     * momento do aceite (defesa em profundidade — nunca confiar só no
     * filtro de listagem).
     */
    public static function possuiCapacidadeAprovada(int $providerId, int $serviceTypeId): bool
    {
        $stmt = getPDO()->prepare(
            "SELECT 1 FROM " . self::TBL . "
             WHERE provider_id = ? AND service_type_id = ? AND enabled = 1 AND approval_status = ?
             LIMIT 1"
        );
        $stmt->execute([$providerId, $serviceTypeId, self::STATUS_APPROVED]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Etapa 7 (conversão de socorro local para reboque) — este prestador é
     * "híbrido" na prática: já tem capacidade de REBOQUE aprovada, além do
     * serviço local que estava executando? Se sim, a conversão pula a
     * disputa de matching e ele mesmo continua com o pedido
     * (HybridFlowDefinition/preparacao_veiculo). Se não, o pedido volta
     * para aguardando_guincho e outro prestador assume o reboque.
     */
    public static function possuiCapacidadeReboqueAprovada(int $providerId): bool
    {
        $stmt = getPDO()->prepare(
            "SELECT 1 FROM " . self::TBL . " pc
             JOIN service_types st ON st.id = pc.service_type_id
             WHERE pc.provider_id = ? AND pc.enabled = 1 AND pc.approval_status = ?
               AND st.attendance_mode = 'TOWING'
             LIMIT 1"
        );
        $stmt->execute([$providerId, self::STATUS_APPROVED]);
        return (bool)$stmt->fetchColumn();
    }

    public static function buscar(int $providerId, int $serviceTypeId): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT * FROM " . self::TBL . " WHERE provider_id = ? AND service_type_id = ? LIMIT 1"
        );
        $stmt->execute([$providerId, $serviceTypeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Prestador se declara apto a um serviço — sempre nasce PENDING
     * (nunca aprova a própria capacidade). Idempotente via UNIQUE
     * (provider_id, service_type_id): reenviar o mesmo formulário não
     * duplica linha, apenas atualiza preços/dados e força nova análise
     * quando já tinha sido rejeitada/suspensa.
     */
    public static function declarar(int $providerId, int $serviceTypeId, array $dados = []): int
    {
        $pdo = getPDO();
        $existente = self::buscar($providerId, $serviceTypeId);

        if ($existente) {
            $novoStatus = in_array($existente['approval_status'], [self::STATUS_REJECTED, self::STATUS_SUSPENDED], true)
                ? self::STATUS_PENDING
                : $existente['approval_status'];

            $stmt = $pdo->prepare(
                "UPDATE " . self::TBL . "
                 SET base_price = ?, price_per_km = ?, price_per_minute = ?, night_surcharge = ?,
                     holiday_surcharge = ?, coverage_radius_km = ?, estimated_duration_minutes = ?,
                     requires_inventory = ?, approval_status = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([
                $dados['base_price'] ?? $existente['base_price'],
                $dados['price_per_km'] ?? $existente['price_per_km'],
                $dados['price_per_minute'] ?? $existente['price_per_minute'],
                $dados['night_surcharge'] ?? $existente['night_surcharge'],
                $dados['holiday_surcharge'] ?? $existente['holiday_surcharge'],
                $dados['coverage_radius_km'] ?? $existente['coverage_radius_km'],
                $dados['estimated_duration_minutes'] ?? $existente['estimated_duration_minutes'],
                !empty($dados['requires_inventory']) ? 1 : (int)$existente['requires_inventory'],
                $novoStatus,
                (int)$existente['id'],
            ]);

            Logger::log(Logger::LEVEL_INFO, 'ProviderCapability', 'declarar', 'catalogo_prestador',
                "Capacidade atualizada: prestador #{$providerId} / service_type #{$serviceTypeId} -> {$novoStatus}",
                ['provider_id' => $providerId, 'service_type_id' => $serviceTypeId, 'status' => $novoStatus]);

            return (int)$existente['id'];
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO " . self::TBL . "
                    (provider_id, service_type_id, enabled, approval_status, base_price, price_per_km,
                     price_per_minute, night_surcharge, holiday_surcharge, coverage_radius_km,
                     estimated_duration_minutes, requires_inventory, created_at, updated_at)
                 VALUES (?,?,0,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
            );
            $stmt->execute([
                $providerId,
                $serviceTypeId,
                self::STATUS_PENDING,
                $dados['base_price'] ?? null,
                $dados['price_per_km'] ?? null,
                $dados['price_per_minute'] ?? null,
                $dados['night_surcharge'] ?? null,
                $dados['holiday_surcharge'] ?? null,
                $dados['coverage_radius_km'] ?? null,
                $dados['estimated_duration_minutes'] ?? null,
                !empty($dados['requires_inventory']) ? 1 : 0,
            ]);
            $id = (int)$pdo->lastInsertId();

            Logger::log(Logger::LEVEL_INFO, 'ProviderCapability', 'declarar', 'catalogo_prestador',
                "Nova capacidade declarada: prestador #{$providerId} / service_type #{$serviceTypeId} (PENDING)",
                ['provider_id' => $providerId, 'service_type_id' => $serviceTypeId]);

            return $id;
        } catch (\PDOException $e) {
            // Corrida com outra requisição concorrente criando a mesma linha
            // (mesma UNIQUE (provider_id, service_type_id)) — não é erro real,
            // só significa que já existe; recarrega e segue idempotente.
            Logger::exception('ProviderCapability', 'declarar', 'catalogo_prestador', $e,
                ['provider_id' => $providerId, 'service_type_id' => $serviceTypeId]);
            $existente = self::buscar($providerId, $serviceTypeId);
            return $existente ? (int)$existente['id'] : 0;
        }
    }

    /** Ação administrativa — só admin/gerente aprova, nunca o próprio prestador. */
    public static function aprovar(int $capabilityId, int $adminId): bool
    {
        return self::mudarStatus($capabilityId, self::STATUS_APPROVED, $adminId, enabled: true);
    }

    public static function suspender(int $capabilityId, int $adminId): bool
    {
        return self::mudarStatus($capabilityId, self::STATUS_SUSPENDED, $adminId, enabled: false);
    }

    public static function rejeitar(int $capabilityId, int $adminId): bool
    {
        return self::mudarStatus($capabilityId, self::STATUS_REJECTED, $adminId, enabled: false);
    }

    private static function mudarStatus(int $capabilityId, string $status, int $adminId, bool $enabled): bool
    {
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET approval_status = ?, enabled = ?, updated_at = NOW() WHERE id = ?"
        );
        $ok = $stmt->execute([$status, $enabled ? 1 : 0, $capabilityId]);

        Logger::log(Logger::LEVEL_INFO, 'ProviderCapability', 'mudarStatus', 'catalogo_prestador',
            "Capacidade #{$capabilityId} -> {$status} por admin #{$adminId}",
            ['capability_id' => $capabilityId, 'status' => $status, 'admin_id' => $adminId, 'enabled' => $enabled]);

        return $ok;
    }
}
