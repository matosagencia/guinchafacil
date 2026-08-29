<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// §COBERTURA-RAIO-01 (06/08/2026): snapshot direto do banco pro saldo de
// estoque de um produto de um prestador — mesmo padrão dos outros
// qa_get_*_snapshot.php (qa_get_pedido_snapshot.php, qa_get_orcamento_snapshot.php
// etc.), necessário pra Fase 4 (E2E de socorro com bateria/estoque) conseguir
// afirmar que uma baixa realmente aconteceu sem reconstruir a query manualmente
// em cada spec.
//
// Uso: php qa_get_estoque_snapshot.php <provider_id> <produto_id>

function saida(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if ($argc < 3 || !ctype_digit($argv[1]) || !ctype_digit($argv[2])) {
    saida(['ok' => false, 'erro' => 'Uso: php qa_get_estoque_snapshot.php <provider_id> <produto_id>']);
    exit(1);
}

$providerId = (int)$argv[1];
$produtoId = (int)$argv[2];

try {
    $pdo = getPDO();

    $stmtLinha = $pdo->prepare(
        "SELECT e.quantidade, e.preco_venda, e.active, p.sku, p.nome, p.preco_referencia, p.unidade
           FROM provider_produtos_estoque e
           JOIN produtos p ON p.id = e.produto_id
          WHERE e.provider_id = ? AND e.produto_id = ?
          LIMIT 1"
    );
    $stmtLinha->execute([$providerId, $produtoId]);
    $linha = $stmtLinha->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmtMov = $pdo->prepare(
        "SELECT id, pedido_id, tipo, quantidade, saldo_apos, descricao, criado_em
           FROM estoque_movimentos
          WHERE provider_id = ? AND produto_id = ?
          ORDER BY id DESC
          LIMIT 20"
    );
    $stmtMov->execute([$providerId, $produtoId]);
    $movimentos = $stmtMov->fetchAll(PDO::FETCH_ASSOC) ?: [];

    saida([
        'ok' => true,
        'provider_id' => $providerId,
        'produto_id' => $produtoId,
        'existe' => $linha !== null,
        'saldo' => $linha !== null ? (int)$linha['quantidade'] : 0,
        'produto_nome' => $linha['nome'] ?? null,
        'produto_sku' => $linha['sku'] ?? null,
        'active' => $linha !== null ? (bool)$linha['active'] : null,
        'movimentos' => array_map(static function (array $m): array {
            return [
                'id' => (int)$m['id'],
                'pedido_id' => $m['pedido_id'] !== null ? (int)$m['pedido_id'] : null,
                'tipo' => $m['tipo'],
                'quantidade' => (int)$m['quantidade'],
                'saldo_apos' => (int)$m['saldo_apos'],
                'descricao' => $m['descricao'],
                'criado_em' => $m['criado_em'],
            ];
        }, $movimentos),
    ]);
} catch (Throwable $e) {
    saida(['ok' => false, 'erro' => $e->getMessage()]);
    exit(1);
}
