<?php
// File: guinchafacil/src/Models/Pedido.php
// Alinhado com schema real: tipo_problema, custo_estimado, expiracao_aceite
// pedidos.guincho_id → guinchos.id (FK está errada no SQL mas usamos guinchos.id)

class Pedido {

    /** Cria pedido (cliente) */
    public static function criar(array $dados): int|false
    {
        try {
            $pdo = getPDO();
            // DATE_ADD/INTERVAL é sintaxe MySQL; sob o SQLite usado pelos testes
            // de integração (tests/bootstrap.php) isso quebrava silenciosamente
            // (capturado pelo catch abaixo como "Falha ao criar pedido no banco").
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $expiracaoExpr = $driver === 'sqlite'
                ? "datetime('now', '+30 minutes')"
                : 'DATE_ADD(NOW(), INTERVAL 30 MINUTE)';

            $stmt = $pdo->prepare(
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
                    :status, :raio_atual_km, :score_minimo_atual,
                    {$expiracaoExpr}, NOW()
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
                ':status'             => (string)($dados['status'] ?? 'aguardando_pagamento'),
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
                        v.placa, v.marca, v.modelo, v.cor, v.tipo AS veiculo_tipo,
                        g.placa_guincho AS guincho_placa,
                        go.nome         AS guincho_operador,
                        go.telefone     AS guincho_telefone,
                        go.email        AS guincho_email,
                        g.lat_atual     AS lat_guincho,
                        g.lng_atual     AS lng_guincho,
                        g.modelo_veiculo AS guincho_tipo,
                        g.foto_veiculo   AS guincho_foto,
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
                $buscaTexto = (string)$filtros['busca'];
                $like       = '%' . $buscaTexto . '%';
                // Busca por número do pedido: aceita tanto o valor puro
                // (ex: "1327") quanto com o prefixo "#" que aparece na UI
                // (ex: "#1327"). Antes só comparava nome/email do cliente e
                // placa do veículo — pedido "1327" nunca era encontrado.
                // O rótulo da tela promete busca por "cliente, guincho,
                // endereço", mas o WHERE só comparava nome/email do cliente e
                // placa do veículo — nem o operador do guincho nem o
                // endereço do pedido entravam na busca. Completado abaixo.
                $buscaId = ltrim($buscaTexto, '#');
                if (ctype_digit($buscaId)) {
                    $where   .= ' AND (p.id = ? OR c.nome LIKE ? OR c.email LIKE ? OR v.placa LIKE ? OR go.nome LIKE ? OR p.endereco_origem LIKE ? OR p.endereco_destino LIKE ?)';
                    $params[] = (int)$buscaId; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
                } else {
                    $where   .= ' AND (c.nome LIKE ? OR c.email LIKE ? OR v.placa LIKE ? OR go.nome LIKE ? OR p.endereco_origem LIKE ? OR p.endereco_destino LIKE ?)';
                    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
                }
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

    /**
     * @deprecated Pacote L1.3 — NÃO use em fluxo de produção. Este método
     * escreve guincho_id diretamente, sem transação, sem lock, sem checar
     * status/disponibilidade e sem gerar evento de auditoria — ou seja, sem
     * nenhuma das proteções de PedidoTransitionService::acceptByGuincho()/
     * assignByAdmin(). Os controllers de produção já migraram para o serviço
     * centralizado. Este método permanece apenas para scripts de seed/QA em
     * tools/ que montam cenários de teste fora do fluxo real de negócio.
     * Chamá-lo fora desse contexto é um bypass de segurança conhecido.
     */
    public static function atribuirGuincho(int $pedido_id, int $guincho_id): bool
    {
        trigger_error(
            'Pedido::atribuirGuincho() é legado/somente-seed. Use PedidoTransitionService::acceptByGuincho() ou assignByAdmin() em qualquer fluxo de produção.',
            E_USER_DEPRECATED
        );
        try {
            $stmt = getPDO()->prepare(
                "UPDATE pedidos SET guincho_id = ? WHERE id = ?"
            );
            return $stmt->execute([$guincho_id, $pedido_id]);
        } catch (PDOException $e) {
            error_log("Pedido::atribuirGuincho: " . $e->getMessage()); return false;
        }
    }

    public static function atualizarStatus(int $id, string $status): bool
    {
        throw new LogicException('Pedido::atualizarStatus() foi desabilitado. Use PedidoTransitionService.');
    }

    public static function cancelar(int $id): bool
    {
        throw new LogicException('Pedido::cancelar() foi desabilitado. Use PedidoTransitionService.');
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

    public static function seriePorDia(int $dias = 7): array
    {
        try {
            $dias = max(1, min(60, $dias));
            $inicio = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));
            $stmt = getPDO()->prepare(
                "SELECT DATE(criado_em) AS dia,
                        COUNT(*) AS total,
                        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) AS concluidos,
                        SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados
                   FROM pedidos
                  WHERE DATE(criado_em) >= ?
                  GROUP BY DATE(criado_em)
                  ORDER BY DATE(criado_em) ASC"
            );
            $stmt->execute([$inicio]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $byDay = [];
            foreach ($rows as $row) {
                $byDay[(string)$row['dia']] = $row;
            }

            $serie = [];
            for ($i = $dias - 1; $i >= 0; $i--) {
                $dia = date('Y-m-d', strtotime('-' . $i . ' days'));
                $row = $byDay[$dia] ?? [];
                $serie[] = [
                    'date' => $dia,
                    'label' => date('d/m', strtotime($dia)),
                    'total' => (int)($row['total'] ?? 0),
                    'concluidos' => (int)($row['concluidos'] ?? 0),
                    'cancelados' => (int)($row['cancelados'] ?? 0),
                ];
            }

            return $serie;
        } catch (PDOException $e) {
            error_log("Pedido::seriePorDia: " . $e->getMessage());
            return [];
        }
    }

    public static function statusBreakdown(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT status, COUNT(*) AS total
                   FROM pedidos
                  GROUP BY status
                  ORDER BY total DESC, status ASC"
            );
            return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (PDOException $e) {
            error_log("Pedido::statusBreakdown: " . $e->getMessage());
            return [];
        }
    }

    public static function operationalSnapshot(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT
                    SUM(CASE WHEN status = 'aguardando_pagamento' THEN 1 ELSE 0 END) AS aguardando_pagamento,
                    SUM(CASE WHEN status = 'aguardando_guincho' THEN 1 ELSE 0 END) AS aguardando_guincho,
                    SUM(CASE WHEN status = 'a_caminho' THEN 1 ELSE 0 END) AS a_caminho,
                    SUM(CASE WHEN status = 'no_local' THEN 1 ELSE 0 END) AS no_local,
                    SUM(CASE WHEN status = 'em_reboque' THEN 1 ELSE 0 END) AS em_reboque,
                    SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) AS concluidos,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelados
                 FROM pedidos"
            );
            $row = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            return [
                'aguardando_pagamento' => (int)($row['aguardando_pagamento'] ?? 0),
                'aguardando_guincho' => (int)($row['aguardando_guincho'] ?? 0),
                'a_caminho' => (int)($row['a_caminho'] ?? 0),
                'no_local' => (int)($row['no_local'] ?? 0),
                'em_reboque' => (int)($row['em_reboque'] ?? 0),
                'concluidos' => (int)($row['concluidos'] ?? 0),
                'cancelados' => (int)($row['cancelados'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log("Pedido::operationalSnapshot: " . $e->getMessage());
            return [
                'aguardando_pagamento' => 0,
                'aguardando_guincho' => 0,
                'a_caminho' => 0,
                'no_local' => 0,
                'em_reboque' => 0,
                'concluidos' => 0,
                'cancelados' => 0,
            ];
        }
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
            // Antes este método ignorava 'busca' e 'data' por completo (só
            // filtrava por status) — o "Total: N pedidos encontrados" da tela
            // de admin/pedidos sempre mostrava a contagem SEM filtro nenhum
            // de busca/data aplicado, descasado da lista realmente exibida
            // (que usa listarPorStatus(), com joins). Replica o mesmo WHERE
            // aqui, incluindo a busca por número do pedido.
            $where  = '1=1';
            $params = [];
            if (!empty($filtros['status'])) {
                $where   .= ' AND p.status = ?'; $params[] = $filtros['status'];
            }
            if (!empty($filtros['busca'])) {
                $buscaTexto = (string)$filtros['busca'];
                $like       = '%' . $buscaTexto . '%';
                $buscaId    = ltrim($buscaTexto, '#');
                if (ctype_digit($buscaId)) {
                    $where   .= ' AND (p.id = ? OR c.nome LIKE ? OR c.email LIKE ? OR v.placa LIKE ? OR go.nome LIKE ? OR p.endereco_origem LIKE ? OR p.endereco_destino LIKE ?)';
                    $params[] = (int)$buscaId; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
                } else {
                    $where   .= ' AND (c.nome LIKE ? OR c.email LIKE ? OR v.placa LIKE ? OR go.nome LIKE ? OR p.endereco_origem LIKE ? OR p.endereco_destino LIKE ?)';
                    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
                }
            }
            if (!empty($filtros['data'])) {
                $where   .= ' AND DATE(p.criado_em) = ?'; $params[] = $filtros['data'];
            }
            $stmt = getPDO()->prepare(
                "SELECT COUNT(*)
                   FROM pedidos p
                   JOIN usuarios c ON c.id = p.cliente_id
                   JOIN veiculos v ON v.id = p.veiculo_id
                   LEFT JOIN guinchos g ON g.id = p.guincho_id
                   LEFT JOIN usuarios go ON go.id = g.usuario_id
                  WHERE $where"
            );
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
