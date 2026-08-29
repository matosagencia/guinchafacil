<?php
// File: guinchafacil/src/Services/PedidoService.php

class PedidoService
{
    private $pedidoModel;

    public function __construct()
    {
        $this->pedidoModel = new Pedido();
    }

    public function modoOperacao(): string
    {
        return (string)Configuracao::get('system_mode', 'production');
    }

    public function pagamentoObrigatorio(): bool
    {
        return Configuracao::get('payment_required', '1') == '1';
    }

    public function podeIniciarAtendimento(): bool
    {
        $systemMode = $this->modoOperacao();
        $paymentRequired = $this->pagamentoObrigatorio();

        if ($systemMode === 'freeflow') {
            return true;
        }

        if (!$paymentRequired) {
            return true;
        }

        return false;
    }

    public function statusInicialPedido(): string
    {
        return $this->podeIniciarAtendimento()
            ? 'aguardando_guincho'
            : 'aguardando_pagamento';
    }

    public function create(array $dados): int
    {
        if (!isset($dados['status']) || !is_string($dados['status']) || $dados['status'] === '') {
            $dados['status'] = $this->statusInicialPedido();
        }

        return (int)$this->pedidoModel->criar($dados);
    }

    public function getByIdAndCliente(int $pedidoId, int $clienteId)
    {
        $p = $this->pedidoModel->buscarPorId($pedidoId);
        if (!$p) return null;
        if ((int)($p['cliente_id'] ?? 0) !== $clienteId) return null;
        return $p;
    }

    public function getRecentByCliente(int $clienteId, int $limit = 5): array
    {
        $lista = $this->pedidoModel->listarPorCliente($clienteId);
        return array_slice($lista, 0, $limit);
    }

    public function getInProgressByCliente(int $clienteId): array
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE cliente_id = ? AND status IN ('aguardando_pagamento','aguardando_guincho','a_caminho','no_local','em_reboque') ORDER BY criado_em DESC");
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function hasPedidosInProgress(int $clienteId): bool
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id FROM pedidos WHERE cliente_id = ? AND status IN ('aguardando_pagamento','aguardando_guincho','a_caminho','no_local','em_reboque') LIMIT 1");
        $stmt->execute([$clienteId]);
        return (bool)$stmt->fetch();
    }

    public function getByClientePaginated(int $clienteId, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE cliente_id = ? ORDER BY criado_em DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $clienteId, PDO::PARAM_INT);
        $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByCliente(int $clienteId): int
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM pedidos WHERE cliente_id = ?");
        $stmt->execute([$clienteId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($r['c'] ?? 0);
    }

    public function countByClienteStatus(int $clienteId, string $status): int
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM pedidos WHERE cliente_id = ? AND status = ?");
        $stmt->execute([$clienteId, $status]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($r['c'] ?? 0);
    }

    public function getDistanciaGuinchoPedido(int $pedidoId): float
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            SELECT p.lat_origem, p.lng_origem, g.lat_atual, g.lng_atual 
            FROM pedidos p 
            JOIN guinchos g ON p.guincho_id = g.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$pedidoId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data || is_null($data['lat_atual'])) return 99999.0;
        
        require_once 'GeoService.php';
        return GeoService::haversine(
            (float)$data['lat_atual'], (float)$data['lng_atual'],
            (float)$data['lat_origem'], (float)$data['lng_origem']
        );
    }

    public function cancelarPorCliente(int $pedidoId, int $clienteId, string $motivo): array
    {
        require_once __DIR__ . '/CancelamentoService.php';
        return CancelamentoService::cancelarPorCliente($pedidoId, $clienteId, $motivo);
    }

    public function cancelarPorGuincho(int $pedidoId, int $guinchoId, string $motivo): array
    {
        require_once __DIR__ . '/CancelamentoService.php';
        return CancelamentoService::cancelarPorGuincho($pedidoId, $guinchoId, $motivo);
    }


    private function registrarAuditoriaCancelamento(PDO $pdo, int $pedidoId, string $atorTipo, int $atorId, string $motivo, string $statusAnterior, float $penalidade): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO pedido_cancelamentos (pedido_id, ator_tipo, ator_id, motivo, status_anterior, penalidade, ip, user_agent, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $pedidoId, $atorTipo, $atorId, mb_substr($motivo, 0, 1000), $statusAnterior, $penalidade,
                (string)($_SERVER['REMOTE_ADDR'] ?? ''), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            ]);
        } catch (Throwable $e) {
            // A auditoria é aditiva: a ausência da migration não pode impedir o cancelamento.
            Logger::exception(__CLASS__, __FUNCTION__, 'auditoria_cancelamento', $e, [
                'pedido_id' => $pedidoId, 'ator_tipo' => $atorTipo, 'ator_id' => $atorId, 'fase' => 'insert_best_effort'
            ]);
        }
    }

    public function cancel(int $pedidoId, int $clienteId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            require_once __DIR__ . '/Pedido/PedidoTransitionService.php';
            $result = PedidoTransitionService::cancelByAdmin($pedidoId, $clienteId, 'Cancelamento administrativo');
            return $result->ok;
        }
        $r = $this->cancelarPorCliente($pedidoId, $clienteId, 'Cancelamento solicitado pelo cliente');
        return (bool)($r['ok'] ?? false);
    }

}
