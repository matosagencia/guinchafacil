<?php
// File: guinchafacil/src/Models/Pedido.php
// Alinhado com schema real: tipo_problema, custo_estimado, expiracao_aceite
// pedidos.guincho_id → guinchos.id (FK está errada no SQL mas usamos guinchos.id)

class Pedido {

    /** Cria pedido (cliente) */
    public static function criar(array $dados): int|false
    {
        try {
            $stmt = getPDO()->prepare(
                "INSERT INTO pedidos (
                    cliente_id, veiculo_id, tipo_problema, descricao_problema,
                    lat_origem, lng_origem, endereco_origem,
                    lat_destino, lng_destino, endereco_destino,
                    distancia_km, custo_estimado,
                    status, raio_atual_km, score_minimo_atual, expiracao_aceite, criado_em
                 ) VALUES (
                    :cliente_id, :veiculo_id, :tipo_problema, :descricao_problema,
                    :lat_origem, :lng_origem, :endereco_origem,
                    :lat_destino, :lng_destino, :endereco_destino,
                    :distancia_km, :custo_estimado,
                    'aguardando_guincho', :raio_atual_km, :score_minimo_atual,
                    DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW()
                 )"
            );
            $stmt->execute([
                ':cliente_id'         => (int)$dados['cliente_id'],
                ':veiculo_id'         => (int)$dados['veiculo_id'],
                ':tipo_problema'      => $dados['tipo_problema'],
                ':descricao_problema' => trim($dados['descricao_problema'] ?? ''),
                ':lat_origem'         => (float)$dados['lat_origem'],
                ':lng_origem'         => (float)$dados['lng_origem'],
                ':endereco_origem'    => trim($dados['endereco_origem']),
                ':lat_destino'        => (float)($dados['lat_destino']  ?? $dados['lat_origem']),
                ':lng_destino'        => (float)($dados['lng_destino']  ?? $dados['lng_origem']),
                ':endereco_destino'   => trim($dados['endereco_destino'] ?? ''),
                ':distancia_km'       => (float)($dados['distancia_km'] ?? 5),
                ':custo_estimado'     => (float)$dados['custo_estimado'],
                ':raio_atual_km'      => (int)($dados['raio_atual_km']      ?? 10),
                ':score_minimo_atual' => (float)($dados['score_minimo_atual'] ?? 0.5),
            ]);
            return (int)getPDO()->lastInsertId();
        } catch (PDOException $e) {
            error_log("Pedido::criar: " . $e->getMessage()); return false;
        }
    }

    /** Busca pedido completo por ID */
    public static function buscarPorId(int $id): ?array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT p.*,
                        c.nome        AS cliente_nome,
                        c.telefone    AS cliente_telefone,
                        c.email       AS cliente_email,
                        v.placa, v.marca, v.modelo, v.cor,
                        g.placa_guincho AS guincho_placa,
                        go.nome         AS guincho_operador,
                        go.telefone     AS guincho_telefone,
                        go.email        AS guincho_email,
                        g.lat_atual     AS lat_guincho,
                        g.lng_atual     AS lng_guincho,
                        g.chave_pix     AS guincho_pix,
                        g.reputacao     AS guincho_reputacao
                 FROM pedidos p
                 JOIN  usuarios c  ON c.id  = p.cliente_id
                 JOIN  veiculos v  ON v.id  = p.veiculo_id
                 LEFT JOIN guinchos  g  ON g.id  = p.guincho_id
                 LEFT JOIN usuarios  go ON go.id = g.usuario_id
                 WHERE p.id = ?"
            );
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Pedido::buscarPorId: " . $e->getMessage()); return null;
        }
    }

    /** Lista pedidos de um cliente */
    public static function listarPorCliente(int $cliente_id, int $pagina = 1, int $por_pagina = 20): array
    {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            $stmt = getPDO()->prepare(
                "SELECT p.*,
                        v.placa, v.marca, v.modelo,
                        g.placa_guincho AS guincho_placa,
                        go.nome         AS guincho_operador
                 FROM pedidos p
                 JOIN  veiculos  v  ON v.id  = p.veiculo_id
                 LEFT JOIN guinchos  g  ON g.id  = p.guincho_id
                 LEFT JOIN usuarios  go ON go.id = g.usuario_id
                 WHERE p.cliente_id = ?
                 ORDER BY p.criado_em DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$cliente_id, $por_pagina, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Pedido::listarPorCliente: " . $e->getMessage()); return [];
        }
    }

    /** Lista pedidos de um guincho (guinchos.id) */
    public static function listarPorGuincho(int $guincho_id, int $pagina = 1, int $por_pagina = 20): array
    {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            $stmt = getPDO()->prepare(
                "SELECT p.*,
                        c.nome    AS cliente_nome,
                        c.telefone AS cliente_telefone,
                        v.placa, v.marca, v.modelo, v.cor
                 FROM pedidos p
                 JOIN usuarios c ON c.id = p.cliente_id
                 JOIN veiculos  v ON v.id = p.veiculo_id
                 WHERE p.guincho_id = ?
                 ORDER BY p.criado_em DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$guincho_id, $por_pagina, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Pedido::listarPorGuincho: " . $e->getMessage()); return [];
        }
    }

    /** Lista pedidos aguardando guincho (para exibir aos guinchos disponíveis) */
    public static function listarAguardandoGuincho(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT p.*,
                        c.nome  AS cliente_nome,
                        c.telefone AS cliente_telefone,
                        v.placa, v.marca, v.modelo, v.cor
                 FROM pedidos p
                 JOIN usuarios c ON c.id = p.cliente_id
                 JOIN veiculos  v ON v.id = p.veiculo_id
                 WHERE p.status = 'aguardando_guincho'
                   AND p.expiracao_aceite > NOW()
                 ORDER BY p.criado_em ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Pedido::listarAguardandoGuincho: " . $e->getMessage()); return [];
        }
    }

    /** Lista paginada para admin com filtros */
    public static function listarPorStatus(string $status = '', int $pagina = 1, array $filtros = [], int $por_pagina = 50): array
    {
        try {
            $offset = ($pagina - 1) * $por_pagina;
            $where  = '1=1';
            $params = [];
            if ($status !== '') {
                $where   .= ' AND p.status = ?'; $params[] = $status;
            }
            if (!empty($filtros['busca'])) {
                $like     = '%' . $filtros['busca'] . '%';
                $where   .= ' AND (c.nome LIKE ? OR c.email LIKE ? OR v.placa LIKE ?)';
                $params[] = $like; $params[] = $like; $params[] = $like;
            }
            if (!empty($filtros['data'])) {
                $where   .= ' AND DATE(p.criado_em) = ?'; $params[] = $filtros['data'];
            }
            $params[] = $por_pagina;
            $params[] = $offset;
            $stmt = getPDO()->prepare(
                "SELECT p.*,
                        c.nome  AS cliente_nome, c.telefone AS cliente_telefone,
                        v.placa, v.marca, v.modelo,
                        g.placa_guincho AS guincho_placa,
                        go.nome         AS guincho_operador
                 FROM pedidos p
                 JOIN  usuarios  c  ON c.id  = p.cliente_id
                 JOIN  veiculos  v  ON v.id  = p.veiculo_id
                 LEFT JOIN guinchos  g  ON g.id  = p.guincho_id
                 LEFT JOIN usuarios  go ON go.id = g.usuario_id
                 WHERE $where
                 ORDER BY p.criado_em DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Pedido::listarPorStatus: " . $e->getMessage()); return [];
        }
    }

    /** Atribui guincho (guinchos.id) ao pedido */
    public static function atribuirGuincho(int $pedido_id, int $guincho_id): bool
    {
        try {
            $stmt = getPDO()->prepare(
                "UPDATE pedidos SET guincho_id = ?, status = 'a_caminho' WHERE id = ?"
            );
            return $stmt->execute([$guincho_id, $pedido_id]);
        } catch (PDOException $e) {
            error_log("Pedido::atribuirGuincho: " . $e->getMessage()); return false;
        }
    }

    public static function atualizarStatus(int $id, string $status): bool
    {
        try {
            $stmt = getPDO()->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (PDOException $e) {
            error_log("Pedido::atualizarStatus: " . $e->getMessage()); return false;
        }
    }

    public static function cancelar(int $id): bool
    {
        return self::atualizarStatus($id, 'cancelado');
    }

    public static function totaisDoDia(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT
                    COUNT(*) AS total,
                    COUNT(CASE WHEN status='concluido'  THEN 1 END) AS concluidos,
                    COUNT(CASE WHEN status='cancelado'  THEN 1 END) AS cancelados,
                    COALESCE(SUM(CASE WHEN status='concluido' THEN COALESCE(custo_final, custo_estimado, 0) END), 0) AS faturamento
                 FROM pedidos WHERE DATE(criado_em) = CURDATE()"
            );
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) { return []; }
    }

    public static function listarRecentes(int $limite = 10): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email
                 FROM pedidos p JOIN usuarios u ON u.id = p.cliente_id
                 ORDER BY p.criado_em DESC LIMIT ?"
            );
            $stmt->execute([$limite]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { return []; }
    }

    public static function contar(array $filtros = []): int
    {
        try {
            $where  = '1=1';
            $params = [];
            if (!empty($filtros['status'])) {
                $where   .= ' AND status = ?'; $params[] = $filtros['status'];
            }
            if (!empty($filtros['busca'])) {
                // simple count — no joins for performance
            }
            $stmt = getPDO()->prepare("SELECT COUNT(*) FROM pedidos WHERE $where");
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) { return 0; }
    }

    public static function contarPorCliente(int $cliente_id): int
    {
        try {
            $stmt = getPDO()->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ?");
            $stmt->execute([$cliente_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Pedido::contarPorCliente: " . $e->getMessage()); return 0;
        }
    }

    public static function contarPorGuincho(int $guincho_id): int
    {
        try {
            $stmt = getPDO()->prepare("SELECT COUNT(*) FROM pedidos WHERE guincho_id = ? AND status = 'concluido'");
            $stmt->execute([$guincho_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) { return 0; }
    }

    public static function definirExpiracao(int $id, string $expiracao, ?int $raioAtualKm = null): bool
    {
        try {
            if ($raioAtualKm !== null) {
                $stmt = getPDO()->prepare("UPDATE pedidos SET expiracao_aceite = ?, raio_atual_km = ? WHERE id = ?");
                return $stmt->execute([$expiracao, $raioAtualKm, $id]);
            }
            $stmt = getPDO()->prepare("UPDATE pedidos SET expiracao_aceite = ? WHERE id = ?");
            return $stmt->execute([$expiracao, $id]);
        } catch (PDOException $e) { return false; }
    }

    /** Busca pedido ativo (em andamento) de um guincho (guinchos.id) */
    public static function buscarAtivoDoGuincho(int $guincho_id): ?array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT p.*,
                        c.nome     AS cliente_nome,
                        c.telefone AS cliente_telefone,
                        c.email    AS cliente_email,
                        v.placa, v.marca, v.modelo, v.cor
                 FROM pedidos p
                 JOIN usuarios c ON c.id = p.cliente_id
                 JOIN veiculos  v ON v.id = p.veiculo_id
                 WHERE p.guincho_id = ?
                   AND p.status IN ('a_caminho','no_local','em_reboque')
                 ORDER BY p.criado_em DESC LIMIT 1"
            );
            $stmt->execute([$guincho_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) { return null; }
    }
}
