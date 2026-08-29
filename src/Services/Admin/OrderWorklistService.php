<?php

declare(strict_types=1);

/**
 * Consultas da fila operacional do admin.
 * Somente leitura: nenhuma regra de transição ou POR é executada aqui.
 */
final class OrderWorklistService
{
    private const STATUS_LABELS = [
        'aguardando_pagamento' => ['label' => 'Aguardando pagamento', 'css' => 'new'],
        'aguardando_guincho' => ['label' => 'Buscando prestador', 'css' => 'searching'],
        'a_caminho' => ['label' => 'A caminho', 'css' => 'route'],
        'no_local' => ['label' => 'No local', 'css' => 'route'],
        'em_reboque' => ['label' => 'Em atendimento', 'css' => 'service'],
        'teste_final' => ['label' => 'Teste final', 'css' => 'service'],
        'concluido' => ['label' => 'Concluído', 'css' => 'done'],
        'cancelado' => ['label' => 'Cancelado', 'css' => 'cancelled'],
    ];

    private const SERVICE_LABELS = [
        'mecanico' => 'Mecânico', 'eletrico' => 'Elétrico', 'pneu' => 'Pneu',
        'bateria' => 'Bateria', 'combustivel' => 'Combustível',
        'acidente' => 'Acidente', 'colisao' => 'Colisão', 'outro' => 'Outro',
    ];

    /**
     * Acima deste número de linhas (após aplicar todos os filtros SQL, antes
     * do filtro de prioridade), a paginação por priority[] deixa de ser
     * exata — ver nota em list() abaixo. Na prática a fila operacional real
     * (pedidos ativos + filtros de status/serviço/região/data) fica muito
     * abaixo disso; o cap existe só como salvaguarda de memória/tempo.
     */
    private const PRIORITY_FILTER_SCAN_CAP = 2000;

    public static function list(array $filters = []): array
    {
        [$where, $params] = self::where($filters);
        $pdo = getPDO();
        $priorityValues = self::arrayParam($filters['priority'] ?? []);

        $baseSelect = "SELECT p.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone,
                       c.email AS cliente_email, v.placa, v.marca, v.modelo, v.cor,
                       g.placa_guincho AS guincho_placa, go.nome AS provider_name,
                       go.telefone AS provider_phone,
                       st.name AS service_type_name,
                       (SELECT pl.server_timestamp FROM pedido_localizacoes pl
                         WHERE pl.pedido_id = p.id ORDER BY pl.id DESC LIMIT 1) AS last_gps_at,
                       (SELECT pl.latitude FROM pedido_localizacoes pl
                         WHERE pl.pedido_id = p.id ORDER BY pl.id DESC LIMIT 1) AS last_latitude,
                       (SELECT pl.longitude FROM pedido_localizacoes pl
                         WHERE pl.pedido_id = p.id ORDER BY pl.id DESC LIMIT 1) AS last_longitude,
                       (SELECT pl.accuracy_m FROM pedido_localizacoes pl
                         WHERE pl.pedido_id = p.id ORDER BY pl.id DESC LIMIT 1) AS last_accuracy_m,
                       (SELECT COUNT(*) FROM chat_mensagens cm
                         WHERE cm.pedido_id = p.id AND cm.lida = 0) AS unread_messages
                  FROM pedidos p
                  JOIN usuarios c ON c.id = p.cliente_id
                  JOIN veiculos v ON v.id = p.veiculo_id
                  LEFT JOIN guinchos g ON g.id = p.guincho_id
                  LEFT JOIN usuarios go ON go.id = g.usuario_id
                  LEFT JOIN service_types st ON st.id = p.service_type_id
                 WHERE {$where}";

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int)($filters['per_page'] ?? 25)));
        $order = self::sort((string)($filters['sort'] ?? 'created_desc'));

        if (empty($priorityValues)) {
            // Caminho normal (sem filtro de prioridade): COUNT + LIMIT/OFFSET
            // direto no SQL, como antes — paginação exata.
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*)
                   FROM pedidos p
                   JOIN usuarios c ON c.id = p.cliente_id
                   JOIN veiculos v ON v.id = p.veiculo_id
                   LEFT JOIN guinchos g ON g.id = p.guincho_id
                   LEFT JOIN usuarios go ON go.id = g.usuario_id
                   LEFT JOIN service_types st ON st.id = p.service_type_id
                  WHERE {$where}"
            );
            self::bind($countStmt, $params);
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();

            $offset = ($page - 1) * $perPage;
            $stmt = $pdo->prepare($baseSelect . " ORDER BY {$order} LIMIT ? OFFSET ?");
            self::bind($stmt, array_merge($params, [$perPage, $offset]));
            $stmt->execute();

            $items = array_map([self::class, 'present'], $stmt->fetchAll(PDO::FETCH_ASSOC));

            return [
                'items' => $items,
                'meta' => [
                    'page' => $page, 'per_page' => $perPage, 'total' => $total,
                    'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
                ],
            ];
        }

        // priority[] é derivado em PHP (GPS/tempo de espera — ver alert()),
        // não existe como coluna no banco. Não dá pra fazer WHERE nem
        // LIMIT/OFFSET corretos em SQL sem duplicar essa lógica de negócio
        // como expressão SQL (frágil, dessincroniza fácil de alert()).
        // Solução: escaneia até PRIORITY_FILTER_SCAN_CAP linhas já filtradas
        // pelos demais critérios (status/serviço/região/data/etc.), calcula
        // a prioridade real de cada uma, filtra, e só DEPOIS pagina em PHP —
        // total/total_pages passam a refletir o filtro de prioridade de
        // verdade, ao custo de não ser 100% exato se houver mais linhas que
        // o cap batendo nos outros filtros (caso raro nesta tela).
        $stmt = $pdo->prepare($baseSelect . " ORDER BY {$order} LIMIT " . self::PRIORITY_FILTER_SCAN_CAP);
        self::bind($stmt, $params);
        $stmt->execute();

        $matched = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $item = self::present($row);
            if (in_array($item['priority'], $priorityValues, true)) {
                $matched[] = $item;
            }
        }

        $total = count($matched);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($matched, $offset, $perPage);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page, 'per_page' => $perPage, 'total' => $total,
                'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
            ],
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT p.*, c.nome AS cliente_nome, c.email AS cliente_email,
                    c.telefone AS cliente_telefone, v.placa, v.marca, v.modelo, v.cor,
                    v.ano, v.tipo AS veiculo_tipo, g.id AS provider_id,
                    g.placa_guincho, g.lat_atual AS provider_latitude,
                    g.lng_atual AS provider_longitude, go.nome AS provider_name,
                    go.email AS provider_email, go.telefone AS provider_phone,
                    st.name AS service_type_name
               FROM pedidos p
               JOIN usuarios c ON c.id = p.cliente_id
               JOIN veiculos v ON v.id = p.veiculo_id
               LEFT JOIN guinchos g ON g.id = p.guincho_id
               LEFT JOIN usuarios go ON go.id = g.usuario_id
               LEFT JOIN service_types st ON st.id = p.service_type_id
              WHERE p.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $payment = getPDO()->prepare(
            "SELECT id, metodo, valor_total, valor_guincho, valor_plataforma,
                    status, id_externo, data_pagamento, criado_em
               FROM pagamentos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1"
        );
        $payment->execute([$id]);

        $latest = getPDO()->prepare(
            "SELECT * FROM pedido_localizacoes WHERE pedido_id = ? ORDER BY id DESC LIMIT 1"
        );
        $latest->execute([$id]);
        $latestLocation = $latest->fetch(PDO::FETCH_ASSOC) ?: null;

        $distance = getPDO()->prepare(
            "SELECT COALESCE(MAX(distance_accumulated_m), 0) FROM pedido_localizacoes
              WHERE pedido_id = ? AND is_valid = 1"
        );
        $distance->execute([$id]);

        $created = strtotime((string)$row['criado_em']);
        $updated = strtotime((string)($row['atualizado_em'] ?? $row['criado_em']));
        $row['status_info'] = self::status((string)$row['status']);
        $row['service'] = self::service($row);
        $row['sla'] = [
            'created_at' => $row['criado_em'],
            'updated_at' => $row['atualizado_em'] ?? null,
            'elapsed_seconds' => max(0, time() - $created),
            'status_elapsed_seconds' => max(0, time() - $updated),
        ];
        $row['payment'] = $payment->fetch(PDO::FETCH_ASSOC) ?: null;
        $row['location'] = $latestLocation ? [
            'latitude' => (float)$latestLocation['latitude'],
            'longitude' => (float)$latestLocation['longitude'],
            'accuracy_m' => $latestLocation['accuracy_m'] !== null ? (float)$latestLocation['accuracy_m'] : null,
            'street_name' => $latestLocation['street_name'],
            'server_timestamp' => $latestLocation['server_timestamp'],
            'is_valid' => (bool)$latestLocation['is_valid'],
        ] : null;
        $row['distance'] = ['validated_m' => (float)$distance->fetchColumn()];
        $row['eta'] = null;
        return $row;
    }

    public static function tracking(int $id): array
    {
        $stmt = getPDO()->prepare(
            "SELECT id, fase, sequence_number, client_point_id, latitude, longitude,
                    accuracy_m, speed_mps, heading_deg, device_timestamp,
                    server_timestamp, distance_validated_m, distance_accumulated_m,
                    calculated_speed_kmh, street_name, street_source, match_confidence,
                    is_valid, rejection_code, request_id, run_id
               FROM pedido_localizacoes WHERE pedido_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$id]);
        $points = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['points' => $points, 'planned' => null];
    }

    public static function timeline(int $id): array
    {
        $stmt = getPDO()->prepare(
            "SELECT id, criado_em, level, cls, func, `system`, code, msg, ctx_json
               FROM app_logs WHERE pedido_id = ?
              ORDER BY criado_em ASC, id ASC LIMIT 500"
        );
        $stmt->execute([$id]);
        $events = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ctx = json_decode((string)($row['ctx_json'] ?? ''), true);
            $events[] = [
                'id' => (int)$row['id'], 'at' => $row['criado_em'],
                'type' => $row['code'] ?: $row['system'],
                'label' => $row['msg'], 'level' => $row['level'],
                'context' => is_array($ctx) ? $ctx : null,
            ];
        }
        return ['events' => $events];
    }

    public static function messages(int $id): array
    {
        $stmt = getPDO()->prepare(
            "SELECT c.id, c.pedido_id, c.usuario_id, c.mensagem, c.lida,
                    c.criado_em, u.nome AS usuario_nome, u.tipo AS usuario_tipo
               FROM chat_mensagens c JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.pedido_id = ? ORDER BY c.id ASC"
        );
        $stmt->execute([$id]);
        return ['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    public static function sendMessage(int $id, int $userId, string $message, ?string $idempotencyKey = null): array
    {
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 2000) {
            throw new InvalidArgumentException('Mensagem vazia ou maior que 2000 caracteres.');
        }
        $exists = getPDO()->prepare('SELECT id FROM pedidos WHERE id = ? LIMIT 1');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Pedido não encontrado.');
        require_once __DIR__ . '/../../Models/Chat.php';
        $msgId = Chat::enviar($id, $userId, $message, $idempotencyKey);
        return ['message_id' => (int)$msgId, 'message' => $message];
    }

    private static function where(array $filters): array
    {
        $where = ['1=1']; $params = [];
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%'; $id = ltrim($q, '#');
            if (ctype_digit($id)) { $where[] = '(p.id = ? OR c.nome LIKE ? OR c.telefone LIKE ? OR v.placa LIKE ?)'; $params[] = (int)$id; }
            else { $where[] = '(c.nome LIKE ? OR c.telefone LIKE ? OR v.placa LIKE ? OR p.endereco_origem LIKE ? OR p.endereco_destino LIKE ?)'; }
            $params = array_merge($params, [$like, $like, $like]);
            if (!ctype_digit($id)) $params = array_merge($params, [$like, $like]);
        }
        // priority[] NÃO entra aqui: é derivado em PHP (ver alert()/list()),
        // não existe como coluna no banco — filtrado separadamente em list().
        $statusValues = self::arrayParam($filters['status'] ?? []);
        if ($statusValues) {
            $where[] = 'p.status IN (' . implode(',', array_fill(0, count($statusValues), '?')) . ')';
            $params = array_merge($params, $statusValues);
        }
        $service = trim((string)($filters['service_id'] ?? ''));
        if ($service !== '') {
            if (ctype_digit($service)) {
                $where[] = '(p.service_type_id = ? OR p.tipo_problema = ?)';
                $params[] = (int)$service; $params[] = $service;
            } else {
                $where[] = 'p.tipo_problema = ?'; $params[] = $service;
            }
        }
        if (($provider = (int)($filters['provider_id'] ?? 0)) > 0) { $where[] = 'p.guincho_id = ?'; $params[] = $provider; }
        if (($region = trim((string)($filters['region'] ?? ''))) !== '') { $where[] = '(p.endereco_origem LIKE ? OR p.endereco_destino LIKE ?)'; $params[] = '%' . $region . '%'; $params[] = '%' . $region . '%'; }
        if (($from = self::dateParam($filters['created_from'] ?? null)) !== null) { $where[] = 'p.criado_em >= ?'; $params[] = $from . ' 00:00:00'; }
        if (($to = self::dateParam($filters['created_to'] ?? null)) !== null) { $where[] = 'p.criado_em < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $to; }
        if (!empty($filters['has_alert'])) {
            $where[] = "(p.status IN ('a_caminho','no_local','em_reboque') AND (NOT EXISTS (SELECT 1 FROM pedido_localizacoes ax WHERE ax.pedido_id = p.id AND ax.server_timestamp >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)) OR EXISTS (SELECT 1 FROM pedido_localizacoes ay WHERE ay.pedido_id = p.id AND ay.accuracy_m > 75 ORDER BY ay.id DESC LIMIT 1)) OR (p.status = 'aguardando_guincho' AND p.guincho_id IS NULL AND p.criado_em < DATE_SUB(NOW(), INTERVAL 15 MINUTE)))";
        }
        return [implode(' AND ', $where), $params];
    }

    private static function present(array $row): array
    {
        $status = self::status((string)$row['status']);
        $elapsed = max(0, time() - strtotime((string)($row['atualizado_em'] ?? $row['criado_em'])));
        $alert = self::alert($row, $elapsed);
        return [
            'id' => (int)$row['id'], 'code' => 'GF-' . (int)$row['id'],
            'status' => ['raw' => (string)$row['status'], 'label' => $status['label'], 'css' => $status['css']],
            'priority' => $alert['priority'], 'priority_label' => $alert['priority_label'],
            'client' => ['id' => (int)$row['cliente_id'], 'name' => $row['cliente_nome'], 'phone' => $row['cliente_telefone'], 'email' => $row['cliente_email']],
            'vehicle' => ['plate' => $row['placa'], 'make' => $row['marca'], 'model' => $row['modelo'], 'color' => $row['cor']],
            'service' => self::service($row),
            'provider' => $row['guincho_id'] ? ['id' => (int)$row['guincho_id'], 'name' => $row['provider_name'], 'plate' => $row['guincho_placa']] : null,
            'created_at' => $row['criado_em'], 'updated_at' => $row['atualizado_em'],
            'elapsed_status_seconds' => $elapsed, 'alert_summary' => $alert['summary'],
            'unread_messages' => (int)$row['unread_messages'],
            'origin' => ['address' => $row['endereco_origem'], 'lat' => (float)$row['lat_origem'], 'lng' => (float)$row['lng_origem']],
            'destination' => ['address' => $row['endereco_destino'], 'lat' => (float)$row['lat_destino'], 'lng' => (float)$row['lng_destino']],
            'estimated_cost' => (float)$row['custo_estimado'], 'final_cost' => $row['custo_final'] !== null ? (float)$row['custo_final'] : null,
        ];
    }

    private static function alert(array $row, int $elapsed): array
    {
        $active = in_array((string)$row['status'], ['a_caminho', 'no_local', 'em_reboque'], true);
        $gpsAge = $row['last_gps_at'] ? max(0, time() - strtotime((string)$row['last_gps_at'])) : null;
        if ($active && ($gpsAge === null || $gpsAge >= 1200)) return ['priority' => 'critical', 'priority_label' => 'Crítica', 'summary' => 'Sem GPS há ' . ($gpsAge === null ? 'tempo indeterminado' : (int)floor($gpsAge / 60) . ' min')];
        if ($active && (($gpsAge !== null && $gpsAge >= 600) || (float)($row['last_accuracy_m'] ?? 0) > 75)) return ['priority' => 'warning', 'priority_label' => 'Atenção', 'summary' => $gpsAge !== null ? 'Sem GPS há ' . (int)floor($gpsAge / 60) . ' min' : 'GPS degradado'];
        if ((string)$row['status'] === 'aguardando_guincho' && !$row['guincho_id'] && $elapsed >= 900) return ['priority' => 'warning', 'priority_label' => 'Atenção', 'summary' => 'Sem prestador há ' . (int)floor($elapsed / 60) . ' min'];
        return ['priority' => 'normal', 'priority_label' => 'Normal', 'summary' => ''];
    }

    private static function service(array $row): array
    {
        $id = (string)($row['service_type_id'] ?? '');
        $name = trim((string)($row['service_type_name'] ?? ''));
        if ($name === '') {
            $id = (string)($row['tipo_problema'] ?? '');
            $name = self::SERVICE_LABELS[$id] ?? ($id !== '' ? ucfirst($id) : '');
        }
        return ['id' => $id, 'name' => $name];
    }

    private static function status(string $status): array { return self::STATUS_LABELS[$status] ?? ['label' => ucfirst(str_replace('_', ' ', $status)), 'css' => 'new']; }
    private static function arrayParam(mixed $value): array { if (!is_array($value)) $value = $value === '' ? [] : explode(',', (string)$value); return array_values(array_filter(array_map('trim', $value), static fn($v) => $v !== '')); }
    private static function dateParam(mixed $value): ?string { $value = trim((string)$value); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null; }
    private static function sort(string $sort): string { return ['created_asc' => 'p.criado_em ASC', 'updated_desc' => 'p.atualizado_em DESC', 'priority' => 'p.criado_em ASC', 'created_desc' => 'p.criado_em DESC'][$sort] ?? 'p.criado_em DESC'; }
    private static function bind(PDOStatement $stmt, array $params): void { foreach (array_values($params) as $i => $value) $stmt->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR); }
}
