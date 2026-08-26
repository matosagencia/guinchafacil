<?php

declare(strict_types=1);

class Pagamento
{
    private static function logErr(string $method, string $phase, string $message, array $ctx = []): void
    {
        $line = '[Pagamento][' . $method . '][' . $phase . '] ' . $message;
        if (!empty($ctx)) {
            $line .= ' | ctx=' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        error_log($line);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = getPDO()->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        $cache[$key] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        return $cache[$key];
    }

    private static function paymentDateExpression(): string
    {
        if (self::hasColumn('pagamentos', 'data_pagamento')) {
            return 'COALESCE(data_pagamento, criado_em)';
        }
        return 'criado_em';
    }

    public static function criar(int $pedido_id, string $metodo, float $valor_total, float $valor_guincho, float $valor_plataforma): int|false
    {
        try {
            $sql = "INSERT INTO pagamentos (
                        pedido_id, metodo, valor_total, valor_guincho, valor_plataforma, status, criado_em
                    ) VALUES (
                        :pedido_id, :metodo, :valor_total, :valor_guincho, :valor_plataforma, 'pendente', NOW()
                    )";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':pedido_id' => $pedido_id,
                ':metodo' => $metodo,
                ':valor_total' => $valor_total,
                ':valor_guincho' => $valor_guincho,
                ':valor_plataforma' => $valor_plataforma,
            ]);

            return (int)getPDO()->lastInsertId();
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'insert', $e->getMessage(), ['pedido_id' => $pedido_id, 'metodo' => $metodo]);
            return false;
        }
    }

    public static function buscarPorPedido(int $pedido_id): array|false
    {
        try {
            $sql = "SELECT p.*, pe.tipo_problema, pe.endereco_origem
                    FROM pagamentos p
                    JOIN pedidos pe ON p.pedido_id = pe.id
                    WHERE p.pedido_id = :pedido_id
                    ORDER BY p.id DESC
                    LIMIT 1";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([':pedido_id' => $pedido_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }

    public static function buscarPorIdExterno(string $id_externo): array|false
    {
        try {
            if (!self::hasColumn('pagamentos', 'id_externo')) {
                return false;
            }

            $stmt = getPDO()->prepare('SELECT * FROM pagamentos WHERE id_externo = :id_externo LIMIT 1');
            $stmt->execute([':id_externo' => $id_externo]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['id_externo' => $id_externo]);
            return false;
        }
    }

    public static function aprovar(int $id, string $id_externo, string $webhook_payload = ''): bool
    {
        try {
            $sets = ["status = 'aprovado'"];
            $params = [':id' => $id];

            if (self::hasColumn('pagamentos', 'id_externo')) {
                $sets[] = 'id_externo = :id_externo';
                $params[':id_externo'] = $id_externo;
            }
            if (self::hasColumn('pagamentos', 'webhook_payload')) {
                $sets[] = 'webhook_payload = :webhook_payload';
                $params[':webhook_payload'] = $webhook_payload;
            }
            if (self::hasColumn('pagamentos', 'data_pagamento')) {
                $sets[] = 'data_pagamento = NOW()';
            }

            $sql = 'UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $stmt = getPDO()->prepare($sql);
            return $stmt->execute($params);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['id' => $id, 'id_externo' => $id_externo]);
            return false;
        }
    }

    public static function atualizarSplit(int $id, float $valor_guincho, float $valor_plataforma): bool
    {
        try {
            $stmt = getPDO()->prepare(
                'UPDATE pagamentos SET valor_guincho = :valor_guincho, valor_plataforma = :valor_plataforma WHERE id = :id'
            );
            return $stmt->execute([
                ':id' => $id,
                ':valor_guincho' => $valor_guincho,
                ':valor_plataforma' => $valor_plataforma,
            ]);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }

    public static function marcarGuinhoPago(int $id): bool
    {
        try {
            $sets = ['pago_guincho = 1'];
            if (self::hasColumn('pagamentos', 'data_pagamento_guincho')) {
                $sets[] = 'data_pagamento_guincho = NOW()';
            }
            $stmt = getPDO()->prepare('UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['id' => $id]);
            return false;
        }
    }

    public static function listar(array $filtros = [], int $pagina = 1): array
    {
        try {
            $offset = max(0, ($pagina - 1) * 20);
            $where = [];
            $valores = [];

            if (!empty($filtros['status'])) {
                $where[] = 'p.status = :status';
                $valores[':status'] = $filtros['status'];
            }

            if (!empty($filtros['metodo'])) {
                $where[] = 'p.metodo = :metodo';
                $valores[':metodo'] = $filtros['metodo'];
            }

            if (!empty($filtros['data_inicio'])) {
                $where[] = 'DATE(' . self::paymentDateExpression() . ') >= :data_inicio';
                $valores[':data_inicio'] = $filtros['data_inicio'];
            }

            if (!empty($filtros['data_fim'])) {
                $where[] = 'DATE(' . self::paymentDateExpression() . ') <= :data_fim';
                $valores[':data_fim'] = $filtros['data_fim'];
            }

            $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

            $sql = "SELECT p.*, 
                           uc.nome AS cliente_nome,
                           ug.nome AS guincho_nome
                    FROM pagamentos p
                    JOIN pedidos pe ON p.pedido_id = pe.id
                    JOIN usuarios uc ON pe.cliente_id = uc.id
                    LEFT JOIN guinchos g ON pe.guincho_id = g.id
                    LEFT JOIN usuarios ug ON g.usuario_id = ug.id
                    {$whereClause}
                    ORDER BY p.criado_em DESC
                    LIMIT 20 OFFSET :offset";

            $valores[':offset'] = $offset;
            $stmt = getPDO()->prepare($sql);
            foreach ($valores as $k => $v) {
                $type = $k === ':offset' ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($k, $v, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['filtros' => $filtros, 'pagina' => $pagina]);
            return [];
        }
    }

    public static function totalPorPeriodo(string $data_inicio, string $data_fim): array
    {
        try {
            $dateExpr = self::paymentDateExpression();
            $sql = "SELECT 
                        COALESCE(SUM(valor_total), 0) AS total_valor_total,
                        COALESCE(SUM(valor_guincho), 0) AS total_valor_guincho,
                        COALESCE(SUM(valor_plataforma), 0) AS total_valor_plataforma
                    FROM pagamentos
                    WHERE status = 'aprovado'
                      AND DATE({$dateExpr}) BETWEEN :data_inicio AND :data_fim";

            $stmt = getPDO()->prepare($sql);
            $stmt->execute([
                ':data_inicio' => $data_inicio,
                ':data_fim' => $data_fim,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'valor_total' => (float)($result['total_valor_total'] ?? 0),
                'valor_guincho' => (float)($result['total_valor_guincho'] ?? 0),
                'valor_plataforma' => (float)($result['total_valor_plataforma'] ?? 0),
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['data_inicio' => $data_inicio, 'data_fim' => $data_fim]);
            return [
                'valor_total' => 0.0,
                'valor_guincho' => 0.0,
                'valor_plataforma' => 0.0,
            ];
        }
    }

    public static function listarPorGuincho(int $guincho_id, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.guincho_id = ?', "pg.status = 'aprovado'"];
            $params = [$guincho_id];

            if ($mes > 0) {
                $where[] = 'MONTH(pg.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(pg.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                'SELECT pg.*, p.endereco_origem, p.endereco_destino, p.criado_em AS pedido_em
'
                . 'FROM pagamentos pg
'
                . 'JOIN pedidos p ON p.id = pg.pedido_id
'
                . 'WHERE ' . implode(' AND ', $where) . '
'
                . 'ORDER BY pg.criado_em DESC'
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'select', $e->getMessage(), ['guincho_id' => $guincho_id, 'mes' => $mes, 'ano' => $ano]);
            return [];
        }
    }

    public static function totaisPorGuincho(int $guincho_id, int $mes = 0, int $ano = 0): array
    {
        try {
            $where = ['p.guincho_id = ?', "pg.status = 'aprovado'"];
            $params = [$guincho_id];

            if ($mes > 0) {
                $where[] = 'MONTH(pg.criado_em) = ?';
                $params[] = $mes;
            }
            if ($ano > 0) {
                $where[] = 'YEAR(pg.criado_em) = ?';
                $params[] = $ano;
            }

            $stmt = getPDO()->prepare(
                'SELECT
'
                . '    COUNT(*) AS total_corridas,
'
                . '    COALESCE(SUM(pg.valor_guincho), 0) AS total_recebido,
'
                . '    COALESCE(SUM(CASE WHEN MONTH(pg.criado_em) = MONTH(NOW()) AND YEAR(pg.criado_em) = YEAR(NOW()) THEN pg.valor_guincho END), 0) AS recebido_mes
'
                . 'FROM pagamentos pg
'
                . 'JOIN pedidos p ON p.id = pg.pedido_id
'
                . 'WHERE ' . implode(' AND ', $where)
            );
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_corridas' => 0,
                'total_recebido' => 0,
                'recebido_mes' => 0,
            ];
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'aggregate', $e->getMessage(), ['guincho_id' => $guincho_id, 'mes' => $mes, 'ano' => $ano]);
            return [
                'total_corridas' => 0,
                'total_recebido' => 0,
                'recebido_mes' => 0,
            ];
        }
    }

    public static function marcarGuinhoPagoPorPedido(int $pedido_id): bool
    {
        try {
            $sets = ['pago_guincho = 1'];
            if (self::hasColumn('pagamentos', 'data_pagamento_guincho')) {
                $sets[] = 'data_pagamento_guincho = NOW()';
            }
            $stmt = getPDO()->prepare('UPDATE pagamentos SET ' . implode(', ', $sets) . ' WHERE pedido_id = ?');
            return $stmt->execute([$pedido_id]);
        } catch (Throwable $e) {
            self::logErr(__FUNCTION__, 'update', $e->getMessage(), ['pedido_id' => $pedido_id]);
            return false;
        }
    }
}
