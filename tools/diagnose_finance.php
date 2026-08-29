<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese, mesmo que o
// bloqueio de tools/ no .htaccess falhe (AllowOverride, Nginx, etc.).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}


require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Pagamento.php';
require_once dirname(__DIR__) . '/src/Services/PaymentJobService.php';

$bounds = Pagamento::periodBounds();
$filters = [
    'data_inicio' => $bounds['min_date'] ?: date('Y-m-01'),
    'data_fim' => $bounds['max_date'] ?: date('Y-m-d'),
    'status' => '',
    'metodo' => '',
];
$jobFilters = [
    'data_inicio' => $filters['data_inicio'],
    'data_fim' => $filters['data_fim'],
    'status' => '',
    'job_type' => '',
    'pedido_id' => '',
    'worker_id' => '',
];

$payments = Pagamento::listar($filters, 1, 5);
$jobs = PaymentJobService::list($jobFilters, 5);
$rawQuery = [
    'ok' => true,
    'rows' => [],
];

try {
    $pdo = getPDO();
    $sql = "SELECT p.*,
                   uc.nome AS cliente_nome,
                   ug.nome AS guincho_nome
            FROM pagamentos p
            JOIN pedidos pe ON p.pedido_id = pe.id
            JOIN usuarios uc ON pe.cliente_id = uc.id
            LEFT JOIN guinchos g ON pe.guincho_id = g.id
            LEFT JOIN usuarios ug ON g.usuario_id = ug.id
            WHERE DATE(COALESCE(p.data_pagamento, p.criado_em)) >= :data_inicio
              AND DATE(COALESCE(p.data_pagamento, p.criado_em)) <= :data_fim
            ORDER BY p.criado_em DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':data_inicio', (string)$filters['data_inicio'], PDO::PARAM_STR);
    $stmt->bindValue(':data_fim', (string)$filters['data_fim'], PDO::PARAM_STR);
    $stmt->bindValue(':limit', 5, PDO::PARAM_INT);
    $stmt->bindValue(':offset', 0, PDO::PARAM_INT);
    $stmt->execute();
    $rawQuery['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rawQuery = [
        'ok' => false,
        'error' => $e->getMessage(),
    ];
}

echo json_encode([
    'bounds' => $bounds,
    'payments' => [
        'count' => Pagamento::contar($filters),
        'list_count' => count($payments),
        'sample' => $payments,
    ],
    'raw_query' => $rawQuery,
    'jobs' => [
        'summary' => PaymentJobService::summarize($jobFilters),
        'list_count' => count($jobs),
        'sample' => $jobs,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
