<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$cotacao = $_SESSION['pre_cotacao'] ?? [];
$valorCotado = (float)($cotacao['valor'] ?? 0);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seus dados | GuinchaFacil</title>
    <link href="<?= $esc($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --gf-green:#2fb34a; --gf-green-dark:#1f8a36; --gf-bg:#0a1a0d; --gf-ink:#10233f; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; background:var(--gf-bg); color:#fff; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid rgba(47,179,74,.15); }
        .topbar .brand { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; font-weight:700; }
        .topbar .brand img { width:34px; height:34px; border-radius:10px; }
        .topbar .brand span { color:var(--gf-green); }
        .topbar .btn-outline { border:1px solid rgba(47,179,74,.3); color:#7dff96; text-decoration:none; font-size:.85rem; padding:.4rem .9rem; border-radius:8px; transition:.15s; }
        .topbar .btn-outline:hover { background:rgba(47,179,74,.1); color:#fff; }
        .wrap { max-width:520px; margin:0 auto; padding:40px 20px 60px; }
        .hero { text-align:center; margin-bottom:28px; }
        .hero .eyebrow { color:var(--gf-green); font-size:.75rem; letter-spacing:.15em; font-weight:800; text-transform:uppercase; }
        .hero h1 { font-size:clamp(1.6rem,4vw,2rem); font-weight:800; letter-spacing:-.02em; margin:8px 0 6px; color:#fff; }
        .hero p { color:rgba(255,255,255,.55); font-size:.95rem; margin:0; }
        .card-panel { background:#fff; color:var(--gf-ink); border-radius:18px; padding:28px; box-shadow:0 20px 60px rgba(0,0,0,.4); }
        .resumo { display:flex; justify-content:space-between; align-items:center; padding:12px 0 16px; border-bottom:1px dashed #e3eaf4; margin-bottom:22px; color:#334155; font-size:.9rem; }
        .resumo strong { color:var(--gf-green-dark); font-size:1.25rem; }
        label { font-weight:600; font-size:.88rem; color:#334155; margin-bottom:6px; display:block; }
        .form-control { border-radius:10px; padding:.75rem .95rem; border:1.5px solid #dce4ef; font-size:.95rem; }
        .form-control:focus { border-color:var(--gf-green); box-shadow:0 0 0 3px rgba(47,179,74,.15); outline:none; }
        .btn-primary { background:linear-gradient(135deg,var(--gf-green),var(--gf-green-dark)); border:0; border-radius:10px; padding:.85rem 1.2rem; font-weight:700; width:100%; font-size:1rem; color:#fff; box-shadow:0 6px 20px rgba(47,179,74,.35); transition:.15s; }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 26px rgba(47,179,74,.45); }
        .rodape { text-align:center; color:#64748b; font-size:.85rem; margin-top:18px; }
        .rodape a { color:var(--gf-green-dark); text-decoration:none; font-weight:600; }
        .alerta { background:#fff5f5; border:1px solid #fed7d7; color:#c53030; border-radius:10px; padding:12px 14px; font-size:.9rem; margin-bottom:16px; }
    </style>
</head>
<body>
<div class="topbar">
    <a class="brand" href="<?= $esc($bp) ?>/"><img src="<?= $esc($bp) ?>/public/assets/img/logo-48.png" alt=""> Guincha<span>Facil</span></a>
    <a class="btn-outline" href="<?= $esc($bp) ?>/login?retorno=<?= urlencode('/checkout/veiculo') ?>">Ja tenho conta</a>
</div>

<div class="wrap">
    <section class="hero">
        <div class="eyebrow">Falta pouco</div>
        <h1>Como podemos te chamar?</h1>
        <p>So precisamos de 3 dados. CPF e endereco ficam pro pagamento.</p>
    </section>

    <?php if (!empty($flash)): ?>
        <div class="alerta"><?= $esc($flash['message']) ?></div>
    <?php endif; ?>

    <form class="card-panel" method="post" action="<?= $esc($bp) ?>/checkout/cliente">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">

        <div class="resumo">
            <span>Total a pagar</span>
            <strong>R$ <?= number_format($valorCotado, 2, ',', '.') ?></strong>
        </div>

        <div class="mb-3">
            <label for="nome">Nome completo</label>
            <input class="form-control" id="nome" name="nome" placeholder="Maria Silva" autocomplete="name" required autofocus>
        </div>

        <div class="mb-3">
            <label for="telefone">WhatsApp</label>
            <input class="form-control" id="telefone" name="telefone" inputmode="numeric" placeholder="(21) 99999-9999" autocomplete="tel" required>
        </div>

        <div class="mb-3">
            <label for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" placeholder="voce@email.com" autocomplete="email" required>
        </div>

        <button type="submit" class="btn-primary" id="btn-continuar">Continuar</button>

        <p class="rodape">
            Ja tem conta? <a href="<?= $esc($bp) ?>/login?retorno=<?= urlencode('/checkout/veiculo') ?>">Entrar</a>
        </p>
    </form>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
    'use strict';
    var tel = document.getElementById('telefone');
    if (!tel) return;
    tel.addEventListener('input', function () {
        var v = tel.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
        else if (v.length > 6) tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2,6) + '-' + v.slice(6);
        else if (v.length > 2) tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2);
        else if (v.length > 0) tel.value = '(' + v;
    });
})();
document.querySelector('form').addEventListener('submit', function (e) {
    var btn = document.getElementById('btn-continuar');
    if (btn && btn.disabled) { e.preventDefault(); return; }
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
});
</script>
</body>
</html>