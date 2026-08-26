<?php
// File: guinchafacil/src/Services/PedidoService.php

class PedidoService
{
    private $pedidoModel;

    public function __construct()
    {
        $this->pedidoModel = new Pedido();
    }

    public function create(array $dados): int
    {
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
        // Usa query direta para performance
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

    public function cancel(int $pedidoId, int $clienteId): bool
    {
        $p = $this->getByIdAndCliente($pedidoId, $clienteId);
        if (!$p) return false;
        // §3: cliente só pode cancelar em aguardando_pagamento ou aguardando_guincho
        if (!in_array($p['status'], ['aguardando_pagamento', 'aguardando_guincho'], true)) return false;

        $cancelado = (bool)$this->pedidoModel->cancelar($pedidoId);
        if (!$cancelado) return false;

        // §4.4: estorno automático para cancelamentos nestes dois status
        require_once __DIR__ . '/EstornoService.php';
        EstornoService::estornar($pedidoId);

        return true;
    }
}
