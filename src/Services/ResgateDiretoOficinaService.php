<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Provider/Provider.php';
require_once __DIR__ . '/../Models/PedidoIndicacaoOficina.php';
require_once __DIR__ . '/../Models/Financial/ChargeCodes.php';
require_once __DIR__ . '/ProviderWorkshopService.php';
require_once __DIR__ . '/Financial/ChargePolicyService.php';
require_once __DIR__ . '/AuditTrailService.php';

final class ResgateDiretoOficinaService
{
    public const MODALIDADE = 'RESGATE_DIRETO_OFICINA';

    public static function validarElegibilidade(array $pedido, array $provider, array $settings): void
    {
        if (($pedido['modalidade_socorro'] ?? null) !== self::MODALIDADE) {
            throw new InvalidArgumentException('O pedido não está configurado para resgate direto.');
        }
        if (!ProviderWorkshopService::isEligible($provider, $settings)) {
            throw new InvalidArgumentException('A oficina não está elegível para o resgate direto.');
        }
        if (empty($settings['permite_resgate_direto'])) {
            throw new InvalidArgumentException('A oficina não realiza resgate direto.');
        }
        foreach (['veiculo_esta_batido', 'rodas_travadas', 'local_dificil_acesso', 'em_garagem_subsolo'] as $riskField) {
            if (!empty($pedido[$riskField])) {
                throw new InvalidArgumentException('A modalidade direta é bloqueada para esta condição de risco.');
            }
        }
        if ($pedido['local_resgate_lat'] === null || $pedido['local_resgate_lng'] === null) {
            throw new InvalidArgumentException('O local exato do resgate direto é obrigatório.');
        }
    }

    public static function qualificar(int $pedidoId, int $providerId, array $snapshot = []): array
    {
        $pedido = Pedido::buscarPorId($pedidoId);
        $provider = Provider::buscarPorId($providerId);
        $settings = ProviderWorkshopService::obterRegras($providerId);
        if (!$pedido || !$provider || !$settings) {
            throw new InvalidArgumentException('Pedido ou oficina não encontrados.');
        }
        self::validarElegibilidade($pedido, $provider, $settings);
        $pdo = getPDO();
        $pdo->beginTransaction();
        try {
            $rules = ProviderWorkshopService::normalizeRules($snapshot ?: $settings);
            $chargeSnapshot = array_merge($rules, [
                'provider_id' => $providerId,
                'pedido_id' => $pedidoId,
                'modalidade_socorro' => self::MODALIDADE,
            ]);
            $charges = ChargePolicyService::criarCobrancasResgateDireto(
                $pedidoId,
                $providerId,
                $rules['fee_amount'],
                $rules['direct_rescue_fee'],
                $chargeSnapshot
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        AuditTrailService::evento('resgate_direto_oficina_qualificado', __CLASS__, __FUNCTION__, [
            'pedido_id' => $pedidoId, 'provider_id' => $providerId,
            'charge_item_ids' => [(int)$charges['referral']['id'], (int)$charges['direct_rescue']['id']],
        ]);
        return $charges;
    }
}
