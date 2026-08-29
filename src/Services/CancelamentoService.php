<?php
// File: guinchafacil/src/Services/CancelamentoService.php

require_once __DIR__ . '/GeoService.php';
require_once __DIR__ . '/EstornoService.php';
require_once __DIR__ . '/NotificacaoService.php';
require_once __DIR__ . '/AuditTrailService.php';
require_once __DIR__ . '/Cancelamento/CancellationCalculationService.php';
require_once __DIR__ . '/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../Models/CancellationSnapshot.php';

/**
 * Serviço central de cancelamento com penalidade de tarifa.
 *
 * Regras (todas configuráveis via tabela configuracoes):
 *  - aguardando_pagamento .... cliente cancela grátis (nada foi pago);
 *  - aguardando_guincho ...... cliente cancela grátis dentro de `cancelamento_gratis_min`;
 *                              depois disso aplica-se apenas a taxa fixa se configurada > 0;
 *  - a_caminho ............... cliente paga max(taxa_fixa, custo_estimado * percent/100);
 *                              bloqueado se o guincho estiver a menos de `km_bloqueio_cancelamento`;
 *  - no_local / em_reboque ... cliente não pode cancelar (apenas admin);
 *  - guincho ................. só pode cancelar em `a_caminho`; o pedido volta para a fila
 *                              (aguardando_guincho) e o guincho sofre penalidade de reputação.
 *
 * Toda operação é feita em transação com SELECT ... FOR UPDATE (idempotente sob concorrência)
 * e gera trilha de auditoria.
 */
class CancelamentoService
{
    /** "FOR UPDATE" é MySQL/PostgreSQL; SQLite (usado nos testes de integração) não suporta. */
    private static function lockClause(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    // ─── Consulta de penalidade (usada pelas telas para exibir preview) ──────
    /**
     * @return array{pode:bool, taxa:float, motivo_bloqueio:?string, isento_ate:?string}
     */
    public static function previewCliente(array $pedido): array
    {
        $status = (string)($pedido['status'] ?? '');
        $cfg    = Configuracao::getAll();

        if (in_array($status, ['concluido', 'cancelado'], true)) {
            return ['pode' => false, 'taxa' => 0.0,
                    'motivo_bloqueio' => 'Este pedido já foi encerrado.',
                    'isento_ate' => null];
        }
        if (in_array($status, ['no_local', 'em_reboque'], true)) {
            return ['pode' => false, 'taxa' => 0.0,
                    'motivo_bloqueio' => 'O atendimento já está em andamento no local e não pode mais ser cancelado.',
                    'isento_ate' => null];
        }

        if ($status === 'aguardando_pagamento') {
            return ['pode' => true, 'taxa' => 0.0, 'motivo_bloqueio' => null, 'isento_ate' => null];
        }

        $gratisMin = (int)($cfg['cancelamento_gratis_min'] ?? 5);
        $criadoEm  = strtotime((string)($pedido['criado_em'] ?? 'now'));
        $isento    = (time() - $criadoEm) <= ($gratisMin * 60);
        $isentoAte = date('H:i', $criadoEm + $gratisMin * 60);

        if ($status === 'aguardando_guincho') {
            // Nenhum guincho envolvido ainda: sem penalidade
            return ['pode' => true, 'taxa' => 0.0, 'motivo_bloqueio' => null,
                    'isento_ate' => $isento ? $isentoAte : null];
        }
        $constitutional = CancellationCalculationService::calculate($pedido, $cfg);
        $taxa = $isento ? 0.0 : (float)$constitutional['taxa'];
        return [
            'pode' => (bool)$constitutional['pode'],
            'taxa' => $taxa,
            'motivo_bloqueio' => $constitutional['motivo_bloqueio'],
            'isento_ate' => $isento ? $isentoAte : null,
            'proof_distance_m' => (float)($constitutional['proof_distance_m'] ?? 0),
            'proof_duration_s' => (int)($constitutional['proof_duration_s'] ?? 0),
            'tracking_quality' => $constitutional['tracking_quality'] ?? 'unknown',
        ];
    }

    // ─── Preview versionado e persistido (L1.6) ───────────────────────────────
    /**
     * Calcula o preview igual a previewCliente(), mas GRAVA o resultado como
     * cancelamento_snapshots antes de devolver. A confirmação (cancelarPorCliente)
     * só aceita esse snapshot_id/hash enquanto ele não expirar nem for usado,
     * eliminando a janela de corrida entre "ver o preço" e "confirmar".
     *
     * @return array{pode:bool, taxa:float, motivo_bloqueio:?string, snapshot_id:?int,
     *               snapshot_hash:?string, expires_at:?string, formula_version:string}
     */
    public static function previewClienteComSnapshot(array $pedido, int $clienteId): array
    {
        $cfg = Configuracao::getAll();
        $preview = self::previewCliente($pedido);

        if (!$preview['pode']) {
            return $preview + ['snapshot_id' => null, 'snapshot_hash' => null, 'expires_at' => null,
                'formula_version' => CancellationCalculationService::FORMULA_VERSION];
        }

        $ttlMin = (int)($cfg['cancelamento_preview_ttl_min'] ?? 3);
        $expiresAt = date('Y-m-d H:i:s', time() + max(1, $ttlMin) * 60);
        $formulaVersion = CancellationCalculationService::FORMULA_VERSION;
        $factors = $preview['factors'] ?? [];
        $porQuality = $preview['tracking_quality'] ?? 'unknown';
        $valorPago = (float)($pedido['custo_final'] ?: ($pedido['custo_estimado'] ?? 0));
        $refund = max(0.0, round($valorPago - (float)$preview['taxa'], 2));

        $hash = hash('sha256', implode('|', [
            (int)$pedido['id'], $clienteId, number_format((float)$preview['taxa'], 2, '.', ''),
            $formulaVersion, $expiresAt, bin2hex(random_bytes(8)),
        ]));

        $snapshotId = CancellationSnapshot::criar([
            'pedido_id' => (int)$pedido['id'],
            'actor_type' => 'cliente',
            'actor_id' => $clienteId,
            'formula_version' => $formulaVersion,
            'factors' => $factors,
            'por_quality' => $porQuality,
            'fee_amount' => (float)$preview['taxa'],
            'refund_amount' => $refund,
            'penalty_amount' => 0.0,
            'snapshot_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        return $preview + [
            'snapshot_id' => $snapshotId,
            'snapshot_hash' => $hash,
            'expires_at' => $expiresAt,
            'formula_version' => $formulaVersion,
            'refund_previsto' => $refund,
        ];
    }

    private static function calcularTaxaCliente(array $pedido, array $cfg): float
    {
        $percent = (float)($cfg['taxa_cancelamento_percent'] ?? 20);
        $fixa    = (float)($cfg['taxa_cancelamento_fixa'] ?? 15.00);
        $base    = (float)($pedido['custo_final'] ?: ($pedido['custo_estimado'] ?? 0));
        $taxa    = max($fixa, round($base * $percent / 100, 2));
        // Nunca cobrar mais do que o valor do próprio serviço
        return min($taxa, $base);
    }

    private static function distanciaGuinchoOrigem(array $pedido): ?float
    {
        if (empty($pedido['guincho_id'])) return null;
        $lat = $pedido['lat_guincho'] ?? null;
        $lng = $pedido['lng_guincho'] ?? null;
        if ($lat === null || $lng === null) return null;
        return GeoService::haversine(
            (float)$lat, (float)$lng,
            (float)$pedido['lat_origem'], (float)$pedido['lng_origem']
        );
    }

    // ─── Cancelamento pelo CLIENTE ───────────────────────────────────────────
    /**
     * Pacote L1.6: exige o snapshot_id devolvido por previewClienteComSnapshot().
     * A taxa aplicada é a que está gravada no snapshot (não recalculada aqui),
     * o que fecha a janela de corrida entre "mostrar preço" e "confirmar".
     *
     * @return array{ok:bool, erro:?string, taxa:float, estorno:?array, snapshot_id:?int}
     */
    public static function cancelarPorCliente(int $pedidoId, int $clienteId, string $motivo = '', ?int $snapshotId = null): array
    {
        if ($snapshotId === null) {
            return ['ok' => false, 'erro' => 'Preview de cancelamento ausente. Solicite o preview antes de confirmar.', 'taxa' => 0.0, 'estorno' => null, 'snapshot_id' => null];
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $snapshot = CancellationSnapshot::buscarParaConfirmar($pdo, $snapshotId);
            if (!$snapshot || !CancellationSnapshot::isValido($snapshot, $pedidoId, $clienteId) || $snapshot['actor_type'] !== 'cliente') {
                $pdo->rollBack();
                $motivoErro = $snapshot && strtotime((string)($snapshot['expires_at'] ?? '')) < time()
                    ? 'O preview de cancelamento expirou. Solicite um novo preview antes de confirmar.'
                    : 'Preview de cancelamento inválido ou já utilizado.';
                return ['ok' => false, 'erro' => $motivoErro, 'taxa' => 0.0, 'estorno' => null, 'snapshot_id' => $snapshotId];
            }

            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int)$row['cliente_id'] !== $clienteId) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Pedido não encontrado.', 'taxa' => 0.0, 'estorno' => null, 'snapshot_id' => $snapshotId];
            }
            if (in_array((string)$row['status'], ['cancelado', 'concluido', 'no_local', 'em_reboque'], true)) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'O pedido mudou de fase desde o preview. Solicite um novo preview.', 'taxa' => 0.0, 'estorno' => null, 'snapshot_id' => $snapshotId];
            }

            $taxa = (float)$snapshot['fee_amount'];
            CancellationSnapshot::marcarConfirmado($pdo, $snapshotId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[CancelamentoService::cancelarPorCliente] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Erro interno ao cancelar.', 'taxa' => 0.0, 'estorno' => null, 'snapshot_id' => $snapshotId];
        }

        $transition = PedidoTransitionService::cancelByCliente($pedidoId, $clienteId, $motivo, $taxa);
        if (!$transition->ok) {
            return ['ok' => false, 'erro' => $transition->error, 'taxa' => 0.0, 'estorno' => null, 'snapshot_id' => $snapshotId];
        }

        // §COBERTURA-RAIO-01 (06/08/2026): gap encontrado em auditoria —
        // um pedido em em_execucao_servico/autorizacao_servico_pendente
        // (orçamento complementar já APROVADO, estoque já baixado por
        // DiagnosticoService::decidirOrcamento()) PODE ser cancelado pelo
        // cliente (não está na lista de bloqueio da linha acima), mas até
        // agora nada revertia o estoque consumido. Reverte aqui, fora da
        // transação de cancelamento (mesmo padrão do estorno financeiro
        // logo abaixo). Método central em EstoqueService — o mesmo é
        // chamado por PedidoTransitionService::cancelByAdmin(), que tem o
        // mesmo gap pelo caminho administrativo (AdminController/DemandaService).
        require_once __DIR__ . '/Estoque/EstoqueService.php';
        EstoqueService::estornarEstoqueDeOrcamentoAprovado($pedidoId);

        // Fora da transação: estorno (parcial se houver taxa) + notificações
        $valorPago = (float)($row['custo_final'] ?: $row['custo_estimado']);
        $valorEstorno = max(0.0, round($valorPago - $taxa, 2));
        $estorno = EstornoService::estornar($pedidoId, $taxa > 0 ? $valorEstorno : null);

        AuditTrailService::evento('pedido_cancelado_cliente', 'CancelamentoService', 'cancelarPorCliente', [
            'pedido_id' => $pedidoId, 'cliente_id' => $clienteId,
            'taxa' => $taxa, 'estorno_ok' => (bool)($estorno['sucesso'] ?? false),
            'status_anterior' => $row['status'],
        ]);

        try {
            $completo = Pedido::buscarPorId($pedidoId);
            if ($completo) {
                $motivoTxt = $taxa > 0
                    ? sprintf('Cancelamento solicitado pelo cliente. Taxa de cancelamento aplicada: R$ %s.', number_format($taxa, 2, ',', '.'))
                    : 'Cancelamento solicitado pelo cliente, sem taxa.';
                NotificacaoService::pedidoCancelado(
                    $completo,
                    ['nome' => $completo['cliente_nome'] ?? '', 'email' => $completo['cliente_email'] ?? ''],
                    $motivoTxt,
                    (bool)($estorno['sucesso'] ?? false)
                );
            }
        } catch (Throwable $e) {
            error_log('[CancelamentoService] Falha notificando cancelamento cliente: ' . $e->getMessage());
        }

        return ['ok' => true, 'erro' => null, 'taxa' => $taxa, 'estorno' => $estorno, 'snapshot_id' => $snapshotId];
    }

    // ─── Cancelamento pelo GUINCHO ───────────────────────────────────────────
    /**
     * O pedido volta para a fila (aguardando_guincho) e o guincheiro é penalizado.
     * @return array{ok:bool, erro:?string, penalidade_reputacao:float}
     */
    public static function cancelarPorGuincho(int $pedidoId, int $guinchoId, string $motivo = ''): array
    {
        $pdo = getPDO();
        $cfg = Configuracao::getAll();
        $penalidade = (float)($cfg['penalidade_reputacao_cancelamento'] ?? 0.25);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?" . self::lockClause($pdo));
            $stmt->execute([$pedidoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int)$row['guincho_id'] !== $guinchoId) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Pedido não encontrado ou não pertence a você.', 'penalidade_reputacao' => 0.0];
            }
            if ($row['status'] !== 'a_caminho') {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Só é possível cancelar antes de chegar ao local. Fale com o suporte.', 'penalidade_reputacao' => 0.0];
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[CancelamentoService::cancelarPorGuincho] ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Erro interno ao cancelar.', 'penalidade_reputacao' => 0.0];
        }

        $tempoExp = (int)($cfg['tempo_expiracao_min'] ?? 5);
        // Pacote L1.6: a penalidade de reputação agora é aplicada DENTRO da mesma
        // transação do reenfileiramento (ver PedidoTransitionService::requeueByGuincho),
        // não mais como uma segunda operação separada após o commit.
        $transition = PedidoTransitionService::requeueByGuincho($pedidoId, $guinchoId, $guinchoId, $motivo, $tempoExp, $penalidade);
        if (!$transition->ok) {
            return ['ok' => false, 'erro' => $transition->error, 'penalidade_reputacao' => 0.0];
        }

        AuditTrailService::evento('atendimento_cancelado_guincho', 'CancelamentoService', 'cancelarPorGuincho', [
            'pedido_id' => $pedidoId, 'guincho_id' => $guinchoId,
            'penalidade_reputacao' => $penalidade, 'motivo' => $motivo,
        ]);

        // Notificação best-effort ao cliente (o realtime da tela dele detecta a volta à fila)
        try {
            $completo = Pedido::buscarPorId($pedidoId);
            if ($completo) {
                NotificacaoService::pedidoCancelado(
                    $completo,
                    ['nome' => $completo['cliente_nome'] ?? '', 'email' => $completo['cliente_email'] ?? ''],
                    'O guincheiro precisou cancelar o atendimento. Seu pedido voltou para a fila e já estamos buscando outro guincho — sem custo adicional para você.',
                    false
                );
            }
        } catch (Throwable $e) {
            error_log('[CancelamentoService] Falha notificando cliente sobre cancelamento do guincho: ' . $e->getMessage());
        }

        return ['ok' => true, 'erro' => null, 'penalidade_reputacao' => $penalidade];
    }
}
