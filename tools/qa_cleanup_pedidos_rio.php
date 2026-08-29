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

// Utilitário pontual de limpeza: os pedidos de teste antigos com
// endereco_origem/endereco_destino no Rio de Janeiro (criados por
// tools/prepare_atendimento_rio_seed.php e tools/seed_bulk_accounts_and_scenarios.php
// em sessões manuais anteriores) estavam poluindo o "Mapa operacional ao
// vivo" do admin junto com os pedidos da suíte QA atual (concentrados em São
// Paulo), fazendo parecer que só 1-2 pedidos apareciam no mapa quando na
// verdade eram vários sobrepostos + 1 pedido antigo do Rio.
//
// Este script NÃO apaga pedidos — só cancela (status = 'cancelado') os que
// ainda estão em status ativo/não-terminal e têm endereço no Rio de Janeiro,
// preservando histórico/auditoria. Pedidos já concluídos ou cancelados não
// são tocados.

$pdo = getPDO();

$statusesAtivos = ['aguardando_pagamento', 'aguardando_guincho', 'a_caminho', 'no_local', 'em_reboque'];
$placeholders = implode(',', array_fill(0, count($statusesAtivos), '?'));

$stmt = $pdo->prepare(
    "SELECT id, status, endereco_origem, endereco_destino, cliente_id, guincho_id
       FROM pedidos
      WHERE status IN ($placeholders)
        AND (endereco_origem LIKE '%Rio de Janeiro%' OR endereco_destino LIKE '%Rio de Janeiro%')"
);
$stmt->execute($statusesAtivos);
$pedidos = $stmt->fetchAll();

if (!$pedidos) {
    echo json_encode(['ok' => true, 'cancelados' => [], 'mensagem' => 'Nenhum pedido ativo no Rio de Janeiro encontrado.']) . PHP_EOL;
    exit;
}

$cancelados = [];
foreach ($pedidos as $pedido) {
    $upd = $pdo->prepare(
        "UPDATE pedidos
            SET status = 'cancelado',
                motivo_cancelamento = ?,
                cancelado_em = NOW()
          WHERE id = ?"
    );
    try {
        $upd->execute(['[limpeza QA] pedido de teste antigo (Rio de Janeiro) cancelado para higienizar o mapa operacional.', (int)$pedido['id']]);
    } catch (Throwable $e) {
        // Alguma instalação pode não ter a coluna cancelado_em; tenta sem ela.
        $upd2 = $pdo->prepare("UPDATE pedidos SET status = 'cancelado', motivo_cancelamento = ? WHERE id = ?");
        $upd2->execute(['[limpeza QA] pedido de teste antigo (Rio de Janeiro) cancelado para higienizar o mapa operacional.', (int)$pedido['id']]);
    }
    $cancelados[] = [
        'id' => (int)$pedido['id'],
        'status_anterior' => $pedido['status'],
        'endereco_origem' => $pedido['endereco_origem'],
    ];
}

echo json_encode(['ok' => true, 'cancelados' => $cancelados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
