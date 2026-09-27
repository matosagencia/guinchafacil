<?php
require_once __DIR__ . '/config.php';
$email = 'oficina@teste.com';
$senha = 'teste123';
$hash = password_hash($senha, PASSWORD_BCRYPT);
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$id = $stmt->fetchColumn();

if ($id) {
    $pdo->prepare("UPDATE usuarios SET senha_hash = ?, ativo = 1, tipo = 'oficina' WHERE id = ?")->execute([$hash, $id]);
    $msg = "ATUALIZADO id=$id";
} else {
    $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo, criado_em) VALUES ('Oficina Barão Car', ?, ?, 'oficina', 1, NOW())")->execute([$email, $hash]);
    $id = $pdo->lastInsertId();
    $msg = "CRIADO id=$id";
}
$pdo->prepare("UPDATE oficinas SET usuario_id = ? WHERE id = 1")->execute([$id]);

$row = $pdo->query("SELECT o.id, o.nome, o.usuario_id, u.email, u.tipo, u.ativo FROM oficinas o LEFT JOIN usuarios u ON u.id = o.usuario_id WHERE o.id = 1")->fetch(PDO::FETCH_ASSOC);
$verify = password_verify($senha, $hash) ? '✅ OK' : '❌ FALHOU';

?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Reset</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#eee;line-height:1.6}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}
h1{color:#22c55e}.ok{color:#22c55e}.del{color:#ff6b6b;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px;font-weight:700}</style>
</head><body>
<h1>Reset Oficina — <?= $msg ?></h1>
<pre>
Email: <?= htmlspecialchars($email) ?>
Senha: <?= htmlspecialchars($senha) ?>
Verify: <?= $verify ?>

Oficina no banco:
<?= htmlspecialchars(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>


── LOGIN ──
http://localhost:8080/login
</pre>
<p class="del">APAGUE: reset-oficina.php</p>
</body></html>