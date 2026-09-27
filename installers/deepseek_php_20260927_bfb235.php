<?php
require_once __DIR__ . '/config.php';
$email = 'oficina@teste.com';
$senha = 'teste123';
$pdo = getPDO();

// Limpa rate limit
$pdo->exec("DELETE FROM rate_limit WHERE rota = 'login'");

// Busca exatamente como AuthController faz
$stmt = $pdo->prepare("SELECT id, nome, email, tipo, ativo, senha_hash, LENGTH(senha_hash) AS tam FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Diag</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#eee;line-height:1.6}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a}
h1{color:#22c55e}.ok{color:#22c55e}.erro{color:#ef4444}
.del{color:#ff6b6b;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px;font-weight:700}</style>
</head><body>
<h1>Diagnóstico de Login</h1>
<pre>Usuário existe? <?= $user ? '✅ SIM' : '❌ NÃO' ?>


<?php if ($user): ?>
ID:        <?= htmlspecialchars((string)$user['id']) ?>

Email:     [<?= htmlspecialchars((string)$user['email']) ?>]

Tipo:      <?= htmlspecialchars((string)$user['tipo']) ?>

Ativo:     <?= htmlspecialchars((string)$user['ativo']) ?>

Tam hash:  <?= htmlspecialchars((string)$user['tam']) ?> chars (esperado 60)

Hash:      <?= htmlspecialchars(substr((string)$user['senha_hash'], 0, 10)) ?>...

Verify "teste123": <?= password_verify($senha, (string)$user['senha_hash']) ? '✅ PASSOU' : '❌ FALHOU' ?>

<?php endif; ?>

── SIMULAÇÃO EXATA DO LOGIN ──
<?php
$stmt2 = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
$stmt2->execute([$email]);
$u = $stmt2->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    echo "❌ Query do AuthController não achou usuário (ativo=1)\n";
} elseif (!password_verify($senha, (string)$u['senha_hash'])) {
    echo "❌ password_verify retornou false\n";
} else {
    echo "✅ LOGIN VAI FUNCIONAR — pode entrar\n";
}
?>
</pre>
<p class="del">APAGUE: diag-login.php</p>
</body></html>