<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Provider/Provider.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/PedidoIndicacaoOficina.php';
require_once __DIR__ . '/../Models/Financial/ChargeCodes.php';
require_once __DIR__ . '/../Models/Configuracao.php';
require_once __DIR__ . '/../Services/Financial/ChargePolicyService.php';
require_once __DIR__ . '/../Services/Financial/WorkshopSettlementService.php';
require_once __DIR__ . '/../Services/Evidence/EvidenceService.php';
require_once __DIR__ . '/ProviderWorkshopService.php';
require_once __DIR__ . '/AuditTrailService.php';

final class IndicacaoOficinaService
{
    public static function ativo(): bool
    {
        return (string)Configuracao::get('monetizacao_oficinas_ativo', '0') === '1';
    }

    private static function ensureAtivo(): void
    {
        if (!self::ativo()) {
            throw new RuntimeException('Monetização de oficinas desativada.');
        }
    }

    public static function registrarSelecao(int $pedidoId, int $providerId, int $actorId): array
    {
        self::ensureAtivo();
        $pedido = Pedido::buscarPorId($pedidoId);
        $provider = Provider::buscarPorId($providerId);
        if (!$pedido || (int)($pedido['cliente_id'] ?? 0) !== $actorId) {
            throw new InvalidArgumentException('IND-001: pedido inválido.');
        }

        $settings = ProviderWorkshopService::obterRegras($providerId);
        if (!$provider || !$settings || !ProviderWorkshopService::isEligible($provider, $settings)) {
            throw new InvalidArgumentException('IND-001: oficina parceira inválida.');
        }

        $existing = PedidoIndicacaoOficina::buscarPorPedido($pedidoId);
        if ($existing && (int)$existing['provider_id'] !== $providerId) {
            throw new InvalidArgumentException('IND-002: o pedido já possui uma oficina selecionada.');
        }

        $snapshot = ProviderWorkshopService::snapshot($provider, $settings);
        $row = PedidoIndicacaoOficina::criar([
            'pedido_id' => $pedidoId,
            'provider_id' => $providerId,
            'idempotency_key' => 'indicacao_oficina:' . $pedidoId,
            'regra_snapshot' => $snapshot,
        ]);
        AuditTrailService::evento('indicacao_oficina_selecionada', __CLASS__, __FUNCTION__, [
            'pedido_id' => $pedidoId,
            'provider_id' => $providerId,
            'actor_id' => $actorId,
            'idempotency_key' => 'indicacao_oficina:' . $pedidoId,
        ]);
        return $row;
    }

    public static function processarEntrega(int $pedidoId): array
    {
        $row = PedidoIndicacaoOficina::buscarPorPedido($pedidoId);
        if (!$row || in_array($row['status'], PedidoIndicacaoOficina::TERMINAIS, true)) {
            return $row ?? [];
        }

        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || !(float)($pedido['lat_destino'] ?? 0) || !(float)($pedido['lng_destino'] ?? 0)) {
            PedidoIndicacaoOficina::atualizar((int)$row['id'], [
                'status' => PedidoIndicacaoOficina::CHECKIN_PENDENTE,
                'checkin_geofence_ok' => 0,
            ]);
            AuditTrailService::evento('indicacao_oficina_checkin_pendente', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'motivo' => 'destino_sem_coordenadas',
            ]);
            return PedidoIndicacaoOficina::buscarPorId((int)$row['id']) ?? $row;
        }

        PedidoIndicacaoOficina::atualizar((int)$row['id'], ['status' => PedidoIndicacaoOficina::AGUARDANDO_CHECKIN]);
        return PedidoIndicacaoOficina::buscarPorId((int)$row['id']) ?? $row;
    }

    public static function confirmarCheckin(int $pedidoId, array $payload): array
    {
        self::ensureAtivo();
        $pdo = getPDO();
        $indicacaoId = 0;
        $pdo->beginTransaction();
        try {
            $row = PedidoIndicacaoOficina::buscarPorPedido($pedidoId, true);
            $pedido = Pedido::buscarPorId($pedidoId);
            $guinchoId = (int)($payload['guincho_id'] ?? 0);
            if (!$row || !$pedido) {
                throw new InvalidArgumentException('IND-001: indicação inexistente.');
            }
            $indicacaoId = (int)$row['id'];
            if ($guinchoId <= 0 || (int)($pedido['guincho_id'] ?? 0) !== $guinchoId) {
                throw new RuntimeException('IND-001: guincho não vinculado ao pedido.');
            }
            if (in_array($row['status'], [PedidoIndicacaoOficina::COBRANCA_GERADA, PedidoIndicacaoOficina::LIQUIDADA], true)) {
                $pdo->commit();
                return $row;
            }

            $evidencia = EvidenceService::storeUploadedEvidence(
                $pedido,
                $guinchoId,
                'CHECKIN_OFICINA',
                $payload['file'] ?? [],
                (string)($payload['qr_token'] ?? ''),
                (float)($row['raio_checkin_m'] ?? ProviderWorkshopService::DEFAULT_CHECKIN_RADIUS_METERS)
            );
            $snapshot = json_decode((string)($row['regra_comissao_snapshot_json'] ?? ''), true) ?: [];
            $fee = (float)($snapshot['fee_amount'] ?? $row['taxa_indicacao_fixa'] ?? 30);
            $item = ChargePolicyService::criarCobrancaIndicacaoOficina(
                $pedidoId,
                (int)$row['provider_id'],
                $fee,
                $snapshot,
                'referral_fee:' . $pedidoId
            );
            OrderChargeItem::marcarEvidenciaValidada((int)$item['id']);
            OrderChargeItem::atualizarPayableStatus((int)$item['id'], ChargeCodes::PAYABLE_ELIGIBLE, null);
            PedidoIndicacaoOficina::atualizar((int)$row['id'], [
                'status' => PedidoIndicacaoOficina::QUALIFICADA,
                'checkin_evidencia_id' => $evidencia['id'],
                'checkin_geofence_ok' => 1,
                'order_charge_item_id' => $item['id'],
            ]);
            PedidoIndicacaoOficina::atualizar((int)$row['id'], ['status' => PedidoIndicacaoOficina::COBRANCA_GERADA]);
            $pdo->commit();
            AuditTrailService::evento('indicacao_oficina_cobranca_gerada', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'order_charge_item_id' => $item['id'],
                'valor' => $fee,
            ]);
            return PedidoIndicacaoOficina::buscarPorId((int)$row['id']) ?? $row;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($indicacaoId > 0) {
                try {
                    PedidoIndicacaoOficina::atualizar($indicacaoId, [
                        'status' => PedidoIndicacaoOficina::CHECKIN_PENDENTE,
                        'checkin_geofence_ok' => 0,
                    ]);
                } catch (Throwable $stateError) {
                    error_log('[IndicacaoOficina] falha ao marcar CHECKIN_PENDENTE: ' . $stateError->getMessage());
                }
            }
            throw $e;
        }
    }

    public static function revisarManualmente(int $id, int $adminId, string $veredito, string $nota = ''): array
    {
        self::ensureAtivo();
        $row = PedidoIndicacaoOficina::buscarPorId($id, true);
        if (!$row) {
            throw new InvalidArgumentException('Indicação não encontrada.');
        }
        $veredito = strtoupper($veredito);
        if (!in_array($veredito, ['APROVAR', 'REJEITAR'], true)) {
            throw new InvalidArgumentException('Veredito inválido.');
        }
        $status = $veredito === 'APROVAR' ? PedidoIndicacaoOficina::QUALIFICADA : PedidoIndicacaoOficina::REJEITADA;
        $chargeItem = null;
        if ($veredito === 'APROVAR' && empty($row['order_charge_item_id'])) {
            $snapshot = json_decode((string)($row['regra_comissao_snapshot_json'] ?? ''), true) ?: [];
            $fee = (float)($snapshot['fee_amount'] ?? $row['taxa_indicacao_fixa'] ?? 30);
            $chargeItem = ChargePolicyService::criarCobrancaIndicacaoOficina(
                (int)$row['pedido_id'],
                (int)$row['provider_id'],
                $fee,
                $snapshot,
                'referral_fee:' . (int)$row['pedido_id']
            );
            OrderChargeItem::marcarEvidenciaValidada((int)$chargeItem['id']);
            OrderChargeItem::atualizarPayableStatus((int)$chargeItem['id'], ChargeCodes::PAYABLE_ELIGIBLE, null);
            $status = PedidoIndicacaoOficina::COBRANCA_GERADA;
        }
        PedidoIndicacaoOficina::atualizar($id, [
            'status' => $status,
            'revisao_admin_id' => $adminId,
            'revisao_admin_nota' => mb_substr($nota, 0, 255),
            ...($chargeItem ? ['order_charge_item_id' => (int)$chargeItem['id']] : []),
        ]);
        AuditTrailService::evento('indicacao_oficina_revisada', __CLASS__, __FUNCTION__, [
            'indicacao_id' => $id,
            'admin_id' => $adminId,
            'veredito' => $veredito,
        ]);
        return PedidoIndicacaoOficina::buscarPorId($id) ?? $row;
    }

    public static function estornar(int $id, int $adminId, string $motivo): array
    {
        self::ensureAtivo();
        $row = PedidoIndicacaoOficina::buscarPorId($id);
        if (!$row || empty($row['order_charge_item_id'])) {
            throw new InvalidArgumentException('Cobrança da indicação não encontrada.');
        }
        $rev = WorkshopSettlementService::criarEstorno(
            (int)$row['order_charge_item_id'],
            $adminId,
            $motivo
        );
        PedidoIndicacaoOficina::atualizar($id, ['status' => PedidoIndicacaoOficina::ESTORNADA]);
        AuditTrailService::evento('indicacao_oficina_estornada', __CLASS__, __FUNCTION__, [
            'indicacao_id' => $id,
            'motivo' => $motivo,
            'admin_id' => $adminId,
        ]);
        return $rev;
    }
}
