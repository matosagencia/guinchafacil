<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/PedidoOrcamentoPrevio.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Provider/Provider.php';
require_once __DIR__ . '/ProviderWorkshopService.php';
require_once __DIR__ . '/AuditTrailService.php';

final class OrcamentoPrevioService
{
    public static function resolverRegra(int $providerId, string $serviceCode = 'DEFAULT'): array
    {
        $serviceCode = trim($serviceCode) !== '' ? trim($serviceCode) : 'DEFAULT';
        $stmt = getPDO()->prepare(
            "SELECT * FROM provider_quote_rules
              WHERE provider_id = ? AND active = 1 AND service_code IN (?, 'DEFAULT')
              ORDER BY CASE WHEN service_code = ? THEN 0 ELSE 1 END, id DESC LIMIT 1"
        );
        $stmt->execute([$providerId, $serviceCode, $serviceCode]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rule) {
            throw new DomainException('A oficina ainda não possui uma faixa de orçamento configurada.');
        }
        return $rule;
    }

    public static function validarEstimativa(int $providerId, string $serviceCode, float $min, float $max, float $taxa): array
    {
        $rule = self::resolverRegra($providerId, $serviceCode);
        $ruleMin = (float)$rule['estimativa_minima'];
        $ruleMax = (float)$rule['estimativa_maxima'];
        $ruleTaxa = (float)$rule['taxa_diagnostico_local'];
        if ($min < $ruleMin || $max > $ruleMax || $max < $min) {
            throw new DomainException(sprintf('A estimativa deve ficar entre R$ %.2f e R$ %.2f.', $ruleMin, $ruleMax));
        }
        if (abs($taxa - $ruleTaxa) > 0.009) {
            throw new DomainException(sprintf('A taxa de diagnóstico definida para este serviço é R$ %.2f.', $ruleTaxa));
        }
        return ['rule' => $rule, 'estimativa_minima' => $min, 'estimativa_maxima' => $max, 'taxa_diagnostico_local' => $ruleTaxa];
    }

    public static function salvarRegra(int $providerId, string $serviceCode, float $min, float $max, float $taxa, int $actorId): array
    {
        $serviceCode = trim($serviceCode) !== '' ? trim($serviceCode) : 'DEFAULT';
        if ($min < 0 || $max < $min || $taxa < 0) {
            throw new InvalidArgumentException('A faixa de orçamento e a taxa devem ser valores válidos.');
        }
        $provider = Provider::buscarPorId($providerId);
        if (!$provider) {
            throw new InvalidArgumentException('Prestador não encontrado.');
        }
        getPDO()->prepare(
            "INSERT INTO provider_quote_rules
                (provider_id, service_code, estimativa_minima, estimativa_maxima, taxa_diagnostico_local, regra_versao, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'quote-v1', 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE estimativa_minima = VALUES(estimativa_minima),
                 estimativa_maxima = VALUES(estimativa_maxima), taxa_diagnostico_local = VALUES(taxa_diagnostico_local),
                 active = 1, updated_at = NOW()"
        )->execute([$providerId, $serviceCode, $min, $max, $taxa]);
        AuditTrailService::evento('regra_orcamento_atualizada', __CLASS__, __FUNCTION__, [
            'provider_id' => $providerId, 'service_code' => $serviceCode, 'actor_id' => $actorId,
            'estimativa_minima' => $min, 'estimativa_maxima' => $max, 'taxa_diagnostico_local' => $taxa,
        ]);
        return self::resolverRegra($providerId, $serviceCode);
    }

    public static function criarOrcamento(array $dados, int $actorId): array
    {
        $id = PedidoOrcamentoPrevio::criar($dados);
        AuditTrailService::evento('orcamento_previo_criado', __CLASS__, __FUNCTION__, [
            'orcamento_id' => $id, 'pedido_id' => (int)$dados['pedido_id'], 'actor_id' => $actorId,
        ]);
        return PedidoOrcamentoPrevio::buscarPorPedido((int)$dados['pedido_id']) ?? ['id' => $id];
    }

    public static function aprovarPeloCliente(int $pedidoId, int $clienteId): array
    {
        $orcamento = PedidoOrcamentoPrevio::buscarPorPedido($pedidoId);
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$orcamento || !$pedido || (int)($pedido['cliente_id'] ?? 0) !== $clienteId) {
            throw new InvalidArgumentException('Orçamento prévio não encontrado.');
        }
        if (!PedidoOrcamentoPrevio::aprovar((int)$orcamento['id'])) {
            throw new RuntimeException('Orçamento prévio não está pendente de aceite.');
        }
        AuditTrailService::evento('orcamento_previo_aprovado', __CLASS__, __FUNCTION__, [
            'orcamento_id' => (int)$orcamento['id'], 'pedido_id' => $pedidoId, 'cliente_id' => $clienteId,
        ]);
        return PedidoOrcamentoPrevio::buscarPorPedido($pedidoId) ?? $orcamento;
    }

    public static function calcularAbatimentoOS(int $pedidoId, float $valorOs): float
    {
        $orcamento = PedidoOrcamentoPrevio::buscarPorPedido($pedidoId);
        return $orcamento ? PedidoOrcamentoPrevio::calcularAbatimento($orcamento, $valorOs) : 0.0;
    }
}
