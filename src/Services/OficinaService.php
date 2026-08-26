<?php
// File: guinchafacil/src/Services/OficinaService.php

class OficinaService
{
    private $oficinaModel;

    public function __construct()
    {
        $this->oficinaModel = new Oficina();
    }

    public function create(array $dados): int
    {
        return (int)$this->oficinaModel->criar($dados);
    }

    public function update(int $id, array $dados): bool
    {
        return (bool)$this->oficinaModel->atualizar($id, $dados);
    }

    public function delete(int $id, int $clienteId): bool
    {
        $o = $this->getByIdAndCliente($id, $clienteId);
        if (!$o) return false;
        return (bool)$this->oficinaModel->deletar($id);
    }

    public function getByIdAndCliente(int $id, int $clienteId)
    {
        $o = $this->oficinaModel->buscarPorId($id);
        if (!$o) return null;
        if ((int)($o['usuario_id'] ?? 0) !== $clienteId) return null;
        return $o;
    }

    public function getByClientePaginated(int $clienteId, int $page, int $perPage): array
    {
        $all = $this->oficinaModel->listarPorUsuario($clienteId);
        $offset = max(0, ($page - 1) * $perPage);
        return array_slice($all, $offset, $perPage);
    }

    public function countByCliente(int $clienteId): int
    {
        $all = $this->oficinaModel->listarPorUsuario($clienteId);
        return count($all);
    }
}
