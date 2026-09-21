<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Provider/Provider.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/AuditTrailService.php';

final class ProviderWorkshopService
{
    public const DEFAULT_FEE_AMOUNT = 30.00;
    public const DEFAULT_GRACE_PERIOD_MINUTES = 30;
    public const DEFAULT_CHECKIN_RADIUS_METERS = 150;
    public const DEFAULT_RULE_VERSION = 'v1';
    public const DEFAULT_DIRECT_RESCUE_FEE = 20.00;

    public static function isEligible(array $provider, array $settings = []): bool
    {
        return in_array(($provider['provider_type'] ?? null), [Provider::TYPE_WORKSHOP, Provider::TYPE_INDIVIDUAL], true)
            && ($provider['approval_status'] ?? null) === 'APPROVED'
            && (int)($provider['active'] ?? 0) === 1
            && ($settings['status_parceria'] ?? 'ATIVO') === 'ATIVO'
            && (int)($settings['faz_resgate_direto'] ?? $settings['permite_resgate_direto'] ?? 1) === 1;
    }

    public static function normalizeRules(array $rules): array
    {
        $fee = (float)($rules['fee_amount'] ?? $rules['taxa_indicacao_fixa'] ?? self::DEFAULT_FEE_AMOUNT);
        if ($fee < 0) {
            throw new InvalidArgumentException('A taxa de indicação não pode ser negativa.');
        }

        $grace = (int)($rules['grace_period_minutes'] ?? self::DEFAULT_GRACE_PERIOD_MINUTES);
        if ($grace < 0) {
            throw new InvalidArgumentException('O período de tolerância não pode ser negativo.');
        }

        $radius = (int)($rules['checkin_radius_meters'] ?? $rules['raio_checkin_m'] ?? self::DEFAULT_CHECKIN_RADIUS_METERS);
        if ($radius <= 0) {
            throw new InvalidArgumentException('O raio de check-in deve ser maior que zero.');
        }

        $directFee = (float)($rules['direct_rescue_fee'] ?? $rules['taxa_resgate_direto'] ?? self::DEFAULT_DIRECT_RESCUE_FEE);
        if ($directFee < 0) {
            throw new InvalidArgumentException('A taxa de resgate direto não pode ser negativa.');
        }

        return [
            'fee_amount' => round($fee, 2),
            'grace_period_minutes' => $grace,
            'checkin_radius_meters' => $radius,
            'rule_version' => trim((string)($rules['rule_version'] ?? $rules['regra_versao'] ?? self::DEFAULT_RULE_VERSION)) ?: self::DEFAULT_RULE_VERSION,
            'direct_rescue_fee' => round($directFee, 2),
            'allows_direct_rescue' => !array_key_exists('allows_direct_rescue', $rules) && !array_key_exists('permite_resgate_direto', $rules)
                ? true
                : (bool)($rules['allows_direct_rescue'] ?? $rules['permite_resgate_direto']),
            'receives_vehicle_at_yard' => !array_key_exists('receives_vehicle_at_yard', $rules) && !array_key_exists('recebe_veiculo_patio', $rules)
                ? true
                : (bool)($rules['receives_vehicle_at_yard'] ?? $rules['recebe_veiculo_patio']),
            'does_direct_rescue' => !array_key_exists('does_direct_rescue', $rules) && !array_key_exists('faz_resgate_direto', $rules)
                ? true
                : (bool)($rules['does_direct_rescue'] ?? $rules['faz_resgate_direto']),
        ];
    }

    public static function snapshot(array $provider, array $settings): array
    {
        $rules = self::normalizeRules($settings);
        return [
            'provider_id' => (int)($provider['id'] ?? 0),
            'provider_type' => (string)($provider['provider_type'] ?? ''),
            'fee_amount' => $rules['fee_amount'],
            'grace_period_minutes' => $rules['grace_period_minutes'],
            'checkin_radius_meters' => $rules['checkin_radius_meters'],
            'rule_version' => $rules['rule_version'],
            'direct_rescue_fee' => $rules['direct_rescue_fee'],
            'allows_direct_rescue' => $rules['allows_direct_rescue'],
            'receives_vehicle_at_yard' => $rules['receives_vehicle_at_yard'],
            'does_direct_rescue' => $rules['does_direct_rescue'],
        ];
    }

    public static function listarElegiveis(): array
    {
        $stmt = getPDO()->query(
            "SELECT p.*, ws.taxa_indicacao_fixa, ws.taxa_resgate_direto, ws.permite_resgate_direto,
                    ws.recebe_veiculo_patio, ws.faz_resgate_direto, ws.regra_versao,
                    ws.status_parceria, ws.raio_checkin_m,
                    ws.address, ws.latitude, ws.longitude
               FROM providers p
               JOIN provider_workshop_settings ws ON ws.provider_id = p.id
              WHERE p.provider_type = 'WORKSHOP'
                AND p.approval_status = 'APPROVED'
                AND p.active = 1
                AND ws.status_parceria = 'ATIVO'
              ORDER BY COALESCE(p.trade_name, p.legal_name), p.id"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarOficinasElegiveis(): array
    {
        return self::listarElegiveis();
    }

    public static function listarPrestadoresMoveisElegiveis(): array
    {
        $stmt = getPDO()->query(
            "SELECT p.*, ws.taxa_indicacao_fixa, ws.taxa_resgate_direto, ws.permite_resgate_direto,
                    ws.recebe_veiculo_patio, ws.faz_resgate_direto, ws.regra_versao,
                    ws.status_parceria, ws.raio_checkin_m
               FROM providers p
               JOIN provider_workshop_settings ws ON ws.provider_id = p.id
              WHERE p.provider_type IN ('INDIVIDUAL', 'WORKSHOP')
                AND p.approval_status = 'APPROVED'
                AND p.active = 1
                AND ws.status_parceria = 'ATIVO'
                AND ws.faz_resgate_direto = 1
                AND ws.recebe_veiculo_patio = 0
              ORDER BY COALESCE(p.trade_name, p.legal_name), p.id"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarParaAdmin(): array
    {
        $stmt = getPDO()->query(
            "SELECT p.*, ws.taxa_indicacao_fixa, ws.taxa_resgate_direto, ws.permite_resgate_direto,
                    ws.recebe_veiculo_patio, ws.faz_resgate_direto, ws.regra_versao,
                    ws.status_parceria, ws.raio_checkin_m, ws.address,
                    ws.latitude, ws.longitude
               FROM providers p
               LEFT JOIN provider_workshop_settings ws ON ws.provider_id = p.id
              WHERE p.provider_type = 'WORKSHOP'
              ORDER BY COALESCE(p.trade_name, p.legal_name), p.id"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obterRegras(int $providerId): ?array
    {
        $stmt = getPDO()->prepare('SELECT * FROM provider_workshop_settings WHERE provider_id = ? LIMIT 1');
        $stmt->execute([$providerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function cadastrar(array $dados, int $actorId): array
    {
        $legalName = trim((string)($dados['legal_name'] ?? ''));
        if ($legalName === '') {
            throw new InvalidArgumentException('A razão social da oficina é obrigatória.');
        }

        $rules = self::normalizeRules($dados);
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            $providerId = Provider::criar([
                'provider_type' => Provider::TYPE_WORKSHOP,
                'legal_name' => $legalName,
                'trade_name' => trim((string)($dados['trade_name'] ?? '')) ?: null,
                'document_type' => $dados['document_type'] ?? 'CNPJ',
                'document_number' => preg_replace('/\D/', '', (string)($dados['document_number'] ?? '')) ?: null,
                'payment_recipient_type' => 'WORKSHOP',
                'pix_key' => trim((string)($dados['pix_key'] ?? '')) ?: null,
            ]);

            $stmt = $pdo->prepare(
                'INSERT INTO provider_workshop_settings
                    (provider_id, taxa_indicacao_fixa, taxa_resgate_direto, permite_resgate_direto, recebe_veiculo_patio, faz_resgate_direto, regra_versao, status_parceria, raio_checkin_m,
                     address, latitude, longitude, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'ATIVO\', ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $providerId,
                $rules['fee_amount'],
                $rules['direct_rescue_fee'],
                $rules['allows_direct_rescue'] ? 1 : 0,
                $rules['receives_vehicle_at_yard'] ? 1 : 0,
                $rules['does_direct_rescue'] ? 1 : 0,
                $rules['rule_version'],
                $rules['checkin_radius_meters'],
                trim((string)($dados['address'] ?? '')) ?: null,
                $dados['latitude'] ?? null,
                $dados['longitude'] ?? null,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        AuditTrailService::evento('oficina_parceira_cadastrada', __CLASS__, __FUNCTION__, [
            'provider_id' => $providerId,
            'actor_id' => $actorId,
        ]);
        return Provider::buscarPorId($providerId) ?? ['id' => $providerId];
    }

    public static function atualizarRegras(int $providerId, array $rules, int $actorId): array
    {
        $provider = Provider::buscarPorId($providerId);
        if (!$provider || ($provider['provider_type'] ?? null) !== Provider::TYPE_WORKSHOP) {
            throw new InvalidArgumentException('Provider WORKSHOP não encontrado.');
        }

        $normalized = self::normalizeRules($rules);
        $stmt = getPDO()->prepare(
            'UPDATE provider_workshop_settings
                SET taxa_indicacao_fixa = ?, taxa_resgate_direto = ?, permite_resgate_direto = ?, recebe_veiculo_patio = ?, faz_resgate_direto = ?, regra_versao = ?, raio_checkin_m = ?, updated_at = NOW()
              WHERE provider_id = ?'
        );
        $stmt->execute([$normalized['fee_amount'], $normalized['direct_rescue_fee'], $normalized['allows_direct_rescue'] ? 1 : 0, $normalized['receives_vehicle_at_yard'] ? 1 : 0, $normalized['does_direct_rescue'] ? 1 : 0, $normalized['rule_version'], $normalized['checkin_radius_meters'], $providerId]);
        if ($stmt->rowCount() === 0 && self::obterRegras($providerId) === null) {
            throw new RuntimeException('Configuração comercial da oficina não encontrada.');
        }

        AuditTrailService::evento('oficina_parceira_regras_atualizadas', __CLASS__, __FUNCTION__, [
            'provider_id' => $providerId,
            'actor_id' => $actorId,
            'rule_version' => $normalized['rule_version'],
        ]);
        return self::obterRegras($providerId) ?? [];
    }
}
