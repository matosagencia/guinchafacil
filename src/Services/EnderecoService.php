<?php
// File: guinchafacil/src/Services/EnderecoService.php

class EnderecoService
{
    public function __construct()
    {
        // noop
    }

    /**
     * Cria endereco (tabela enderecos exige usuario_id).
     * Controllers antigos nao passam usuario_id, entao usamos:
     * - $dados['usuario_id'] ou $dados['user_id'] se existir
     * - fallback: $GLOBALS['__GF_LAST_USER_ID'] se setado pelo UserService
     */
    public function create(array $dados): int
    {
        $usuarioId = $dados['usuario_id'] ?? ($dados['user_id'] ?? ($GLOBALS['__GF_LAST_USER_ID'] ?? null));
        if (!$usuarioId) {
            throw new RuntimeException("EnderecoService::create precisa de usuario_id (nao encontrado).");
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

    public static function buscarPrincipalPorUsuarioId(int $usuarioId): ?array
    {
        if ($usuarioId <= 0) {
            return null;
        }

        $stmt = getPDO()->prepare(
            'SELECT id, usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, lat, lng, principal
             FROM enderecos
             WHERE usuario_id = ?
             ORDER BY principal DESC, id ASC
             LIMIT 1'
        );
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function salvarPrincipal(array $dados): int
    {
        $usuarioId = (int)($dados['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('usuario_id e obrigatorio para salvar endereco.');
        }

        $pdo = getPDO();
        $existente = self::buscarPrincipalPorUsuarioId($usuarioId);
        $payload = [
            'cep' => trim((string)($dados['cep'] ?? '')),
            'logradouro' => trim((string)($dados['logradouro'] ?? '')),
            'numero' => trim((string)($dados['numero'] ?? '')),
            'complemento' => trim((string)($dados['complemento'] ?? '')),
            'bairro' => trim((string)($dados['bairro'] ?? '')),
            'cidade' => trim((string)($dados['cidade'] ?? '')),
            'estado' => strtoupper(trim((string)($dados['estado'] ?? ''))),
            'lat' => array_key_exists('lat', $dados) && $dados['lat'] !== '' ? (float)$dados['lat'] : null,
            'lng' => array_key_exists('lng', $dados) && $dados['lng'] !== '' ? (float)$dados['lng'] : null,
        ];

        if ($payload['cep'] === '' || $payload['logradouro'] === '' || $payload['numero'] === '' || $payload['bairro'] === '' || $payload['cidade'] === '' || $payload['estado'] === '') {
            throw new InvalidArgumentException('Preencha o endereco base completo.');
        }

        if ($existente) {
            $pdo->prepare(
                'UPDATE enderecos
                 SET cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, lat = COALESCE(?, lat), lng = COALESCE(?, lng), principal = 1
                 WHERE id = ? AND usuario_id = ?'
            )->execute([
                $payload['cep'],
                $payload['logradouro'],
                $payload['numero'],
                $payload['complemento'] !== '' ? $payload['complemento'] : null,
                $payload['bairro'],
                $payload['cidade'],
                $payload['estado'],
                $payload['lat'],
                $payload['lng'],
                (int)$existente['id'],
                $usuarioId,
            ]);
            return (int)$existente['id'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, lat, lng, principal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $usuarioId,
            $payload['cep'],
            $payload['logradouro'],
            $payload['numero'],
            $payload['complemento'] !== '' ? $payload['complemento'] : null,
            $payload['bairro'],
            $payload['cidade'],
            $payload['estado'],
            $payload['lat'],
            $payload['lng'],
        ]);

        return (int)$pdo->lastInsertId();
    }
}