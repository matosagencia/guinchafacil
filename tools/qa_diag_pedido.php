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

// Diagnóstico pontual (temporário) pra investigar por que um pedido recém
// criado não aparece na lista de ofertas do guincho em onboarding-completo.spec.ts.
if ($argc < 2 || !ctype_digit($argv[1])) {
    fwrite(STDERR, 'Uso: php qa_diag_pedido.php <pedido_id>' . PHP_EOL);
    exit(1);
}

$pedidoId = (int)$argv[1];
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT id, status, guincho_id, lat_origem, lng_origem, expiracao_aceite, criado_em, score_minimo_atual, NOW() AS agora FROM pedidos WHERE id = ?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);
echo "PEDIDO:\n";
print_r($pedido);

if ($pedido) {
    $stmt2 = $pdo->prepare("SELECT g.id, g.aprovado, g.disponivel, g.lat_atual, g.lng_atual, g.lat_operacao, g.lng_operacao, g.raio_cobertura_km, u.email FROM guinchos g JOIN usuarios u ON u.id = g.usuario_id ORDER BY g.id DESC LIMIT 5");
    $stmt2->execute();
    echo "\nULTIMOS GUINCHOS:\n";
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

    $cfg = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('raio_maximo_km','tempo_expiracao_min','raio_inicial_km')")->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCONFIG:\n";
    print_r($cfg);
}
