<?php

declare(strict_types=1);

class Avaliacao
{
    private static function logErr(string $method, string $phase, string $message, array $ctx = []): void
    {
        $line = '[Avaliacao][' . $method . '][' . $phase . '] ' . $message;
        if (!empty($ctx)) {
            $line .= ' | ctx=' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
    }

    public static function criar(int $pedido_id, int $cliente_id, int $guincho_id, int $estrelas, string $comentario): int|false
    {
        try {
            $sql = "INSERT INTO avaliacoes (pedido_id, cliente_id, guincho_id, estrelas, comentario, criado_em)
                    VALUES (:pedido_id, :cliente_id, :guincho_id, :estrelas, :comentario, NOW())";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':pedido_id' => $pedido_id,
                ':cliente_id' => $cliente_id,
                ':guincho_id' => $guincho_id,
                ':estrelas' => max(1, min(5, $estrelas)),
                ':comentario' => trim($comentario),
            ]);

            return (int)getPDO()->lastInsertId();
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'insert', $e->getMessage(), [
                'pedido_id' => $pedido_id,
                'cliente_id' => $cliente_id,
                'guincho_id' => $guincho_id,
            ]);
            return false;
        }
    }

    public static function buscarPorPedido(int $pedido_id): array|false
    {
        try {
            $sql = "SELECT a.*, u.nome as cliente_nome
                    FROM avaliacoes a
                    JOIN usuarios u ON a.cliente_id = u.id
                    WHERE a.pedido_id = :pedido_id
                    LIMIT 1";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([':pedido_id' => $pedido_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }

    public static function listarPorGuincho(int $guincho_id, int $pagina = 1): array
    {
        try {
            $offset = max(0, ($pagina - 1) * 20);
            $sql = "SELECT a.*, u.nome as cliente_nome
                    FROM avaliacoes a
                    JOIN usuarios u ON a.cliente_id = u.id
                    WHERE a.guincho_id = :guincho_id
                    ORDER BY a.criado_em DESC
                    LIMIT 20 OFFSET :offset";

            $stmt = getPDO()->prepare($sql);
            $stmt->bindValue(':guincho_id', $guincho_id, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['guincho_id' => $guincho_id, 'pagina' => $pagina]);
            return [];
        }
    }

    public static function mediaGuincho(int $guincho_id): float
    {
        try {
            $stmt = getPDO()->prepare('SELECT AVG(estrelas) as media FROM avaliacoes WHERE guincho_id = :guincho_id');
            $stmt->execute([':guincho_id' => $guincho_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($result['media']) ? (float)$result['media'] : 0.0;
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['guincho_id' => $guincho_id]);
            return 0.0;
        }
    }

    public static function listarTodas(array $filtros = [], int $pagina = 1): array
    {
        try {
            $where = ['1=1'];
            $params = [];
            $guinchoId = (int)($filtros['guincho_id'] ?? 0);
            $nota = (int)($filtros['nota'] ?? 0);
            if ($guinchoId > 0) { $where[] = 'a.guincho_id = ?'; $params[] = $guinchoId; }
            if ($nota >= 1 && $nota <= 5) { $where[] = 'a.estrelas = ?'; $params[] = $nota; }
            $offset = max(0, ($pagina - 1) * 20);
            $stmt = getPDO()->prepare(
                'SELECT a.*, c.nome AS cliente_nome, g.nome AS guincho_nome
                   FROM avaliacoes a
                   JOIN usuarios c ON c.id = a.cliente_id
                   JOIN guinchos g0 ON g0.id = a.guincho_id
                   JOIN usuarios g ON g.id = g0.usuario_id
                  WHERE ' . implode(' AND ', $where) . '
                  ORDER BY a.criado_em DESC, a.id DESC LIMIT 20 OFFSET ?'
            );
            foreach ($params as $i => $value) $stmt->bindValue($i + 1, $value, PDO::PARAM_INT);
            $stmt->bindValue(count($params) + 1, $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), $filtros);
            return [];
        }
    }

    public static function resumo(array $filtros = []): array
    {
        try {
            $where = ['1=1']; $params = [];
            $guinchoId = (int)($filtros['guincho_id'] ?? 0);
            $nota = (int)($filtros['nota'] ?? 0);
            if ($guinchoId > 0) { $where[] = 'guincho_id = ?'; $params[] = $guinchoId; }
            if ($nota >= 1 && $nota <= 5) { $where[] = 'estrelas = ?'; $params[] = $nota; }
            $stmt = getPDO()->prepare('SELECT COUNT(*) total, COALESCE(AVG(estrelas),0) media FROM avaliacoes WHERE ' . implode(' AND ', $where));
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return ['total' => (int)($row['total'] ?? 0), 'media' => (float)($row['media'] ?? 0)];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), $filtros);
            return ['total' => 0, 'media' => 0.0];
        }
    }

    public static function jaAvaliou(int $pedido_id, int $cliente_id): bool
    {
        try {
            $stmt = getPDO()->prepare('SELECT COUNT(*) as total FROM avaliacoes WHERE pedido_id = :pedido_id AND cliente_id = :cliente_id');
            $stmt->execute([
                ':pedido_id' => $pedido_id,
                ':cliente_id' => $cliente_id,
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0) > 0;
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['pedido_id' => $pedido_id, 'cliente_id' => $cliente_id]);
            return false;
        }
    }

    public static function criarEspecialista(int $pedidoId, int $clienteId, int $especialistaId, int $estrelas, string $comentario): int|false
    {
        try {
            $st = getPDO()->prepare('INSERT INTO avaliacoes (pedido_id, cliente_id, guincho_id, especialista_id, estrelas, comentario, criado_em) VALUES (?, ?, NULL, ?, ?, ?, NOW())');
            $st->execute([$pedidoId, $clienteId, $especialistaId, max(1, min(5, $estrelas)), trim($comentario)]);
            $id=(int)getPDO()->lastInsertId();
            getPDO()->prepare('UPDATE especialistas SET reputacao=(SELECT COALESCE(AVG(estrelas),0) FROM avaliacoes WHERE especialista_id=?), total_avaliacoes=(SELECT COUNT(*) FROM avaliacoes WHERE especialista_id=?) WHERE id=?')->execute([$especialistaId,$especialistaId,$especialistaId]);
            return $id;
        } catch (Throwable $e) { self::logErr(__FUNCTION__, 'insert', $e->getMessage(), ['pedido_id'=>$pedidoId,'especialista_id'=>$especialistaId]); return false; }
    }

    public static function mediaEspecialista(int $especialistaId): float
    {
        $st=getPDO()->prepare('SELECT COALESCE(AVG(estrelas),0) FROM avaliacoes WHERE especialista_id=?'); $st->execute([$especialistaId]); return (float)$st->fetchColumn();
    }
}
