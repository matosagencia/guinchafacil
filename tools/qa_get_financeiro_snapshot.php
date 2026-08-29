<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// IMPORTANTE: a "carteira do guincheiro" (saldo em compensação / liberado /
// reservado, ledger de movimentos) descrita na constituição do projeto
// AINDA NÃO EXISTE no código real (não há tabela guincheiro_carteira nem
// guincheiro_movimentos em install/*.sql, conferido em 29/07/2026). Este
// snapshot, portanto, é um agregado best-effort sobre a única fonte de
// verdade que existe hoje — a tabela `pagamentos` — e não deve ser lido
// como o ledger completo da constituição. Quando a carteira for
// implementada de verdade, este script precisa ser reescrito pra consultar
// as tabelas reais em vez de agregar pagamentos.
//
// Uso: php qa_get_financeiro_snapshot.php <guincho_id>

if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, '[ERRO] Uso: php qa_get_financeiro_snapshot.php <guincho_id>' . PHP_EOL);
    exit(1);
}

$guinchoId = (int)$argv[1];

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        "SELECT p.status, p.valor_guincho
           FROM pagamentos p
           JOIN pedidos ped ON ped.id = p.pedido_id
          WHERE ped.guincho_id = ?"
    );
    $stmt->execute([$guinchoId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $saldoTotal = 0.0;
    $movimentos = 0;
    foreach ($rows as $row) {
        if ($row['status'] === 'aprovado') {
            $saldoTotal += (float)$row['valor_guincho'];
            $movimentos++;
        }
    }

    echo json_encode([
        'ok' => true,
        // Sem distinção real de compensação/liberado no schema atual — tudo
        // aprovado é contado como "liberado" nesta aproximação.
        'saldo_total' => round($saldoTotal, 2),
        'saldo_em_compensacao' => 0.0,
        'saldo_liberado' => round($saldoTotal, 2),
        'movimentos' => $movimentos,
        'aviso' => 'Aproximação via agregação de pagamentos — carteira/ledger real ainda não existe no schema.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
