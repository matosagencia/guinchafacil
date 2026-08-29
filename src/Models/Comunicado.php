<?php
require_once __DIR__ . '/../../config.php';

class Comunicado
{
    private static function pdo(): PDO
    {
        return getPDO();
    }

    public static function findById(int $id): ?array
    {
        try {
            $stmt = self::pdo()->prepare('SELECT * FROM comunicados WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function listAdmin(array $filters = [], int $page = 1, int $limit = 20): array
    {
        try {
            $where = [];
            $params = [];
            foreach (['status', 'publico', 'placement'] as $key) {
                if (!empty($filters[$key])) {
                    $where[] = "{$key} = ?";
                    $params[] = $filters[$key];
                }
            }
            $sql = 'SELECT * FROM comunicados' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY prioridade ASC, id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, ($page - 1) * $limit);
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function listActive(string $profile, string $placement, int $limit = 5): array
    {
        try {
            $sql = "SELECT * FROM comunicados
                     WHERE status = 'publicado'
                       AND (publico = ? OR publico = 'ambos')
                       AND placement = ?
                       AND (inicio_em IS NULL OR inicio_em <= NOW())
                       AND (fim_em IS NULL OR fim_em > NOW())
                     ORDER BY prioridade ASC, id DESC
                     LIMIT " . max(1, $limit);
            $stmt = self::pdo()->prepare($sql);
            $stmt->execute([$profile, $placement]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function save(array $data): int
    {
        $pdo = self::pdo();
        $id = (int)($data['id'] ?? 0);
        unset($data['id']);
        $cols = array_keys($data);
        if ($id > 0) {
            $set = implode(', ', array_map(fn($c) => "{$c} = ?", $cols));
            $stmt = $pdo->prepare("UPDATE comunicados SET {$set} WHERE id = ?");
            $params = array_values($data);
            $params[] = $id;
            $stmt->execute($params);
            return $id;
        }
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $stmt = $pdo->prepare('INSERT INTO comunicados (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')');
        $stmt->execute(array_values($data));
        return (int)$pdo->lastInsertId();
    }

    public static function setStatus(int $id, string $status, int $adminId): bool
    {
        try {
            $stmt = self::pdo()->prepare('UPDATE comunicados SET status = ?, atualizado_por = ? WHERE id = ?');
            return $stmt->execute([$status, $adminId, $id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function metricsById(int $id): array
    {
        try {
            $stmt = self::pdo()->prepare('SELECT * FROM comunicado_metricas_diarias WHERE comunicado_id = ? ORDER BY data DESC LIMIT 30');
            $stmt->execute([$id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function statsAdmin(): array
    {
        try {
            $stmt = self::pdo()->query("SELECT
                SUM(status='publicado') AS publicados,
                SUM(status='rascunho') AS rascunhos,
                SUM(status='pausado') AS pausados,
                SUM(status='arquivado') AS arquivados
                FROM comunicados");
            return $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
