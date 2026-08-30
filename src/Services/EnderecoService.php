<?php
// File: guinchafacil/src/Services/EnderecoService.php

class EnderecoService
{
    public function __construct()
    {
        // noop
    }

    /**
     * Resolve os nomes das colunas de coordenadas no schema atual.
     * O schema canônico usa latitude/longitude, mas mantemos fallback
     * para lat/lng caso exista algum banco legado.
     *
     * @return array{0:string,1:string}
     */
    private static function coordinateColumns(PDO $pdo): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $stmt = $pdo->query(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'enderecos'
               AND COLUMN_NAME IN ('latitude', 'longitude', 'lat', 'lng')"
        );
        $columns = array_map('strval', $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []);

        if (in_array('latitude', $columns, true) || in_array('longitude', $columns, true)) {
            return $cache = ['latitude', 'longitude'];
        }

        return $cache = ['lat', 'lng'];
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
        [$latCol, $lngCol] = self::coordinateColumns($pdo);
        $sql = "INSERT INTO enderecos
                (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, {$latCol}, {$lngCol}, principal)
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

        $pdo = getPDO();
        [$latCol, $lngCol] = self::coordinateColumns($pdo);

        $stmt = $pdo->prepare(
            'SELECT id, usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, ' .
            $latCol . ', ' . $lngCol . ', principal
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
        [$latCol, $lngCol] = self::coordinateColumns($pdo);
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
                 SET cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, ' .
                $latCol . ' = COALESCE(?, ' . $latCol . '), ' .
                $lngCol . ' = COALESCE(?, ' . $lngCol . '), principal = 1
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
            'INSERT INTO enderecos (usuario_id, cep, logradouro, numero, complemento, bairro, cidade, estado, ' .
            $latCol . ', ' . $lngCol . ', principal)
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