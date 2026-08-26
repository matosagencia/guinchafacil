<?php
// File: guinchafacil/src/Models/Oficina.php
// Tabela real (phpMyAdmin): oficinas_favoritas(id, usuario_id, nome, telefone, endereco, latitude, longitude, criado_em, atualizado_em)

class Oficina
{
    private const TBL = 'oficinas_favoritas';

    private static function logError(string $func, string $system, string $msg, array $ctx = []): void
    {
        $line = "Oficina - {$func} - {$system} - {$msg}";
        if (!empty($ctx)) {
            $line .= " | ctx=" . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
    }

    private static function normTelefone(?string $tel): string
    {
        return preg_replace('/\D/', '', $tel ?? '');
    }

    /**
     * Aceita tanto chaves antigas (lat/lng) quanto novas (latitude/longitude).
     */
    private static function pickLatLng(array $dados): array
    {
        $lat = $dados['latitude'] ?? $dados['lat'] ?? null;
        $lng = $dados['longitude'] ?? $dados['lng'] ?? null;

        $lat = (is_numeric($lat) ? (float)$lat : null);
        $lng = (is_numeric($lng) ? (float)$lng : null);

        // Normaliza 0 => null (evita salvar lixo)
        if ($lat === 0.0) $lat = null;
        if ($lng === 0.0) $lng = null;

        return [$lat, $lng];
    }

    public static function criar(array $dados): int|false
    {
        $pdo = getPDO();

        try {
            [$lat, $lng] = self::pickLatLng($dados);

            $stmt = $pdo->prepare(
                "INSERT INTO " . self::TBL . " 
                (usuario_id, nome, telefone, endereco, latitude, longitude, criado_em, atualizado_em)
                 VALUES (:usuario_id, :nome, :telefone, :endereco, :latitude, :longitude, NOW(), NOW())"
            );

            $stmt->execute([
                ':usuario_id' => (int)($dados['usuario_id'] ?? 0),
                ':nome'       => trim((string)($dados['nome'] ?? '')),
                ':telefone'   => self::normTelefone($dados['telefone'] ?? ''),
                ':endereco'   => trim((string)($dados['endereco'] ?? '')),
                ':latitude'   => $lat,
                ':longitude'  => $lng,
            ]);

            return (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            self::logError(
                'criar',
                'db/pdo/insert',
                $e->getMessage(),
                [
                    'usuario_id' => (int)($dados['usuario_id'] ?? 0),
                    'nome' => $dados['nome'] ?? null,
                    'telefone' => $dados['telefone'] ?? null,
                    'endereco' => $dados['endereco'] ?? null,
                    'latitude' => $dados['latitude'] ?? ($dados['lat'] ?? null),
                    'longitude' => $dados['longitude'] ?? ($dados['lng'] ?? null),
                ]
            );
            return false;
        }
    }

    public static function listarPorUsuario(int $usuario_id): array
    {
        $pdo = getPDO();

        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM " . self::TBL . " 
                 WHERE usuario_id = :uid
                 ORDER BY atualizado_em DESC, criado_em DESC, id DESC"
            );
            $stmt->execute([':uid' => (int)$usuario_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            self::logError('listarPorUsuario', 'db/pdo/select', $e->getMessage(), ['usuario_id' => $usuario_id]);
            return [];
        }
    }

    public static function buscarPorId(int $id): ?array
    {
        $pdo = getPDO();

        try {
            $stmt = $pdo->prepare("SELECT * FROM " . self::TBL . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => (int)$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            self::logError('buscarPorId', 'db/pdo/select', $e->getMessage(), ['id' => $id]);
            return null;
        }
    }

    public static function atualizar(int $id, array $dados): bool
    {
        $pdo = getPDO();

        try {
            [$lat, $lng] = self::pickLatLng($dados);

            $stmt = $pdo->prepare(
                "UPDATE " . self::TBL . "
                 SET nome = :nome,
                     telefone = :telefone,
                     endereco = :endereco,
                     latitude = :latitude,
                     longitude = :longitude,
                     atualizado_em = NOW()
                 WHERE id = :id"
            );

            return (bool)$stmt->execute([
                ':nome'      => trim((string)($dados['nome'] ?? '')),
                ':telefone'  => self::normTelefone($dados['telefone'] ?? ''),
                ':endereco'  => trim((string)($dados['endereco'] ?? '')),
                ':latitude'  => $lat,
                ':longitude' => $lng,
                ':id'        => (int)$id,
            ]);
        } catch (PDOException $e) {
            self::logError('atualizar', 'db/pdo/update', $e->getMessage(), ['id' => $id, 'dados' => $dados]);
            return false;
        }
    }

    public static function deletar(int $id): bool
    {
        $pdo = getPDO();

        try {
            $stmt = $pdo->prepare("DELETE FROM " . self::TBL . " WHERE id = :id");
            return (bool)$stmt->execute([':id' => (int)$id]);
        } catch (PDOException $e) {
            self::logError('deletar', 'db/pdo/delete', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }
}