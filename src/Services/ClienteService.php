<?php
// File: guinchafacil/src/Services/ClienteService.php

class ClienteService
{
    public function __construct()
    {
        // noop
    }

    /**
     * No schema existe "usuarios" + "enderecos".
     * Este método mantém o contrato do controller:
     * - recebe user_id, cpf e endereco_id
     * - define endereço como principal (se existir)
     */
    public function create(array $dados): bool
    {
        $userId = (int)($dados['user_id'] ?? ($dados['usuario_id'] ?? 0));
        $enderecoId = (int)($dados['endereco_id'] ?? 0);

        if ($userId && $enderecoId) {
            $pdo = getPDO();
            $stmt = $pdo->prepare("UPDATE enderecos SET principal = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$enderecoId, $userId]);
        }

        return true;
    }

    public function cpfExists(string $cpf): bool
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
        $stmt->execute([$cpf]);
        return (bool)$stmt->fetch();
    }
}
