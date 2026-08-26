<?php
// File: guinchafacil/src/Services/VeiculoService.php

class VeiculoService
{
    private $veiculoModel;

    public function __construct()
    {
        $this->veiculoModel = new Veiculo();
    }

    public function create(array $dados): int
    {
        return (int)$this->veiculoModel->criar($dados);
    }

    public function update(int $id, array $dados): bool
    {
        return (bool)$this->veiculoModel->atualizar($id, $dados);
    }

    public function delete(int $id, int $clienteId): bool
    {
        $v = $this->getByIdAndCliente($id, $clienteId);
        if (!$v) return false;
        return (bool)$this->veiculoModel->desativar($id);
    }

    public function getByIdAndCliente(int $id, int $clienteId)
    {
        $v = $this->veiculoModel->buscarPorId($id);
        if (!$v) return null;
        if ((int)($v['usuario_id'] ?? 0) !== $clienteId) return null;
        return $v;
    }

    public function getByClientePaginated(int $clienteId, int $page, int $perPage): array
    {
        $all = $this->veiculoModel->listarPorUsuario($clienteId);
        $offset = max(0, ($page - 1) * $perPage);
        return array_slice($all, $offset, $perPage);
    }

    public function countByCliente(int $clienteId): int
    {
        $all = $this->veiculoModel->listarPorUsuario($clienteId);
        return count($all);
    }

    public function placaExists(string $placa, int $clienteId): bool
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id FROM veiculos WHERE usuario_id = ? AND placa = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$clienteId, strtoupper(trim($placa))]);
        return (bool)$stmt->fetch();
    }
}
