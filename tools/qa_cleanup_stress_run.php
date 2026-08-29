<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';

// Remove usuários/pedidos criados por um run de stress (buildClienteBatch /
// buildGuinchoBatch / etc. com runTag, e-mails no padrão
// qa.<tipo>.<runTag>.<n>@guinchafacil.com). GUARDA DE SEGURANÇA: só apaga
// e-mails que baterem literalmente com esse padrão E contenham o runTag
// exato passado — nunca varre a tabela toda nem apaga por prefixo genérico
// "qa.", pra não arriscar apagar contas de outro teste/seed fixo (ex.:
// pw_socorro_*, pw_gamboa_*, que são fixas e reaproveitadas entre execuções,
// não devem ser tocadas por este script).
//
// Uso: php qa_cleanup_stress_run.php <runTag>

if ($argc < 2 || trim((string)$argv[1]) === '') {
    fwrite(STDERR, '[ERRO] Uso: php qa_cleanup_stress_run.php <runTag>' . PHP_EOL);
    exit(1);
}

$runTag = trim((string)$argv[1]);
// runTag real é sempre um timestamp numérico (String(Date.now()), possivelmente
// com sufixo "-multi"/"-especialista" — ver account-factories.ts). Validação
// mínima pra recusar entradas genéricas demais (ex.: "qa", "%", "").
if (!preg_match('/^\d{10,}/', $runTag)) {
    fwrite(STDERR, "[ERRO] runTag '{$runTag}' não parece um runTag real (esperado: timestamp numérico no início). Abortando por segurança." . PHP_EOL);
    exit(1);
}

$likePattern = '%.' . $runTag . '%@guinchafacil.com';

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        "SELECT id FROM usuarios WHERE email LIKE ? AND email LIKE 'qa.%@guinchafacil.com'"
    );
    $stmt->execute([$likePattern]);
    $usuarioIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));

    if (!$usuarioIds) {
        echo json_encode(['ok' => true, 'removidos' => 0]) . PHP_EOL;
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($usuarioIds), '?'));
    $removidos = 0;

    $pdo->beginTransaction();
    try {
        // guinchos vinculados a esses usuários
        $stmtG = $pdo->prepare("SELECT id FROM guinchos WHERE usuario_id IN ({$placeholders})");
        $stmtG->execute($usuarioIds);
        $guinchoIds = array_map('intval', array_column($stmtG->fetchAll(PDO::FETCH_ASSOC), 'id'));

        // pedidos como cliente OU atendidos por um desses guinchos
        $condPedido = "cliente_id IN ({$placeholders})";
        $paramsPedido = $usuarioIds;
        if ($guinchoIds) {
            $phG = implode(',', array_fill(0, count($guinchoIds), '?'));
            $condPedido .= " OR guincho_id IN ({$phG})";
            $paramsPedido = array_merge($paramsPedido, $guinchoIds);
        }
        $stmtPed = $pdo->prepare("SELECT id FROM pedidos WHERE {$condPedido}");
        $stmtPed->execute($paramsPedido);
        $pedidoIds = array_map('intval', array_column($stmtPed->fetchAll(PDO::FETCH_ASSOC), 'id'));

        if ($pedidoIds) {
            $phP = implode(',', array_fill(0, count($pedidoIds), '?'));
            foreach (['pedido_evidencias', 'pedido_localizacoes', 'pedido_percurso_resumos', 'chat_mensagens', 'pagamentos'] as $tabela) {
                $stmtChk = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
                $stmtChk->execute([DB_NAME, $tabela]);
                if ((int)$stmtChk->fetchColumn() === 0) continue;
                $pdo->prepare("DELETE FROM {$tabela} WHERE pedido_id IN ({$phP})")->execute($pedidoIds);
            }
            $pdo->prepare("DELETE FROM pedidos WHERE id IN ({$phP})")->execute($pedidoIds);
            $removidos += count($pedidoIds);
        }

        if ($guinchoIds) {
            $phG = implode(',', array_fill(0, count($guinchoIds), '?'));
            $pdo->prepare("DELETE FROM guinchos WHERE id IN ({$phG})")->execute($guinchoIds);
        }

        $stmtVei = $pdo->prepare("SELECT id FROM veiculos WHERE usuario_id IN ({$placeholders})");
        $stmtVei->execute($usuarioIds);
        $veiculoIds = array_map('intval', array_column($stmtVei->fetchAll(PDO::FETCH_ASSOC), 'id'));
        if ($veiculoIds) {
            $phV = implode(',', array_fill(0, count($veiculoIds), '?'));
            $pdo->prepare("DELETE FROM veiculos WHERE id IN ({$phV})")->execute($veiculoIds);
        }

        $pdo->prepare("DELETE FROM usuarios WHERE id IN ({$placeholders})")->execute($usuarioIds);
        $removidos += count($usuarioIds);

        $pdo->commit();
    } catch (Throwable $inner) {
        $pdo->rollBack();
        throw $inner;
    }

    echo json_encode(['ok' => true, 'removidos' => $removidos], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
