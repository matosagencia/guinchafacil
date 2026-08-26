<?php
// File: guinchafacil/src/Services/EnderecoService.php

class EnderecoService
{
    public function __construct()
    {
        // noop
    }

    /**
     * Cria endereço (tabela enderecos exige usuario_id).
     * Controllers antigos não passam usuario_id, então usamos:
     * - $dados['usuario_id'] ou $dados['user_id'] se existir
     * - fallback: $GLOBALS['__GF_LAST_USER_ID'] se setado pelo UserService
     */
    public function create(array $dados): int
    {
        $usuarioId = $dados['usuario_id'] ?? ($dados['user_id'] ?? ($GLOBALS['__GF_LAST_USER_ID'] ?? null));
        if (!$usuarioId) {
            throw new RuntimeException("EnderecoService::create precisa de usuario_id (não encontrado).");
        }

        $pdo = getPDO();
        $sql = "INSERT INTO enderecos
                (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, lat, lng, principal)
                VALUES
                (:usuario_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :lat, :lng, :principal)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':usuario_id'  => (int)$usuarioId,
            ':cep'         => $dados['cep'] ?? '',
            ':logradouro'  => $dados['logradouro'] ?? '',
            ':numero'      => $dados['numero'] ?? '',
            ':complemento' => $dados['complemento'] ?? null,
            ':bairro'      => $dados['bairro'] ?? '',
            ':cidade'      => $dados['cidade'] ?? '',
            ':estado'      => $dados['estado'] ?? '',
            ':lat'         => $dados['lat'] ?? null,
            ':lng'         => $dados['lng'] ?? null,
            ':principal'   => isset($dados['principal']) ? (int)$dados['principal'] : 1,
        ]);

        return (int)$pdo->lastInsertId();
    }
}
