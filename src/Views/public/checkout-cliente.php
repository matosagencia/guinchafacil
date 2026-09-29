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
        :root { --gf-blue:#1769e0; --gf-ink:#10233f; --gf-soft:#f4f8ff; }
        body { min-height:100vh; background:linear-gradient(135deg,#eef5ff 0%,#fff 58%,#eefbf8 100%); color:var(--gf-ink); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
        .shell { max-width:560px; margin:0 auto; padding:40px 18px 56px; }
        .brand { display:flex; align-items:center; justify-content:center; gap:12px; font-weight:800; font-size:1.15rem; text-decoration:none; color:var(--gf-ink); margin-bottom:28px; }
        .brand img { width:42px; height:42px; border-radius:12px; }
        .hero { text-align:center; padding:0 0 24px; }
        .hero .eyebrow { color:var(--gf-blue); font-size:.78rem; letter-spacing:.12em; font-weight:800; text-transform:uppercase; }
        .hero h1 { font-size:clamp(1.6rem,4vw,2.2rem); font-weight:800; letter-spacing:-.03em; margin:8px 0; }
        .hero p { color:#5d6b80; font-size:1rem; margin:0; }
        .card-panel { background:rgba(255,255,255,.96); border:1px solid #e3eaf4; border-radius:20px; box-shadow:0 16px 48px rgba(22,65,120,.1); padding:28px; }
        .resumo { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px dashed #e3eaf4; margin-bottom:22px; }
        .resumo strong { color:var(--gf-blue); font-size:1.25rem; }
        label { font-weight:600; font-size:.9rem; color:#334155; margin-bottom:6px; display:block; }
        .form-control { border-radius:10px; padding:.78rem .95rem; border-color:#dce4ef; font-size:.95rem; }
        .form-control:focus { border-color:var(--gf-blue); box-shadow:0 0 0 3px rgba(23,105,224,.15); }
        .btn-primary { background:var(--gf-blue); border:0; border-radius:12px; padding:.9rem 1.4rem; font-weight:700; width:100%; font-size:1rem; margin-top:8px; }
        .btn-primary:hover { background:#1558c0; }
        .rodape { text-align:center; color:#64748b; font-size:.85rem; margin-top:16px; }
        .rodape a { color:var(--gf-blue); text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
<div class="shell">
    <a class="brand" href="<?= $esc($bp) ?>/"><img src="<?= $esc($bp) ?>/public/assets/img/logo-128.png" alt=""> GuinchaFacil</a>

    <section class="hero">
        <div class="eyebrow">Falta pouco</div>
        <h1>Como podemos te chamar?</h1>
        <p>So precisamos de 3 dados. CPF e endereco ficam pro pagamento.</p>
    </section>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= $esc($flash['message']) ?></div>
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

        <button type="submit" class="btn-primary">Continuar</button>

        <p class="rodape">
            Ja tem conta? <a href="<?= $esc($bp) ?>/login?retorno=<?= urlencode('/checkout/veiculo') ?>">Entrar</a>
        </p>
    </form>
</div>

<script>
(function () {
    'use strict';
    var tel = document.getElementById('telefone');
    if (!tel) return;
    tel.addEventListener('input', function () {
        var v = tel.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) {
            tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
        } else if (v.length > 6) {
            tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2,6) + '-' + v.slice(6);
        } else if (v.length > 2) {
            tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2);
        } else if (v.length > 0) {
            tel.value = '(' + v;
        }
    });
})();
</script>
</body>
</html>