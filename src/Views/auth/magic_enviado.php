<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifique seu acesso | GuinchaFacil</title>
    <link href="<?= $esc($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height:100vh; background:#0b1220; color:#fff; margin:0; }
        .wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        .card-magic {
            width:100%; max-width:480px; background:#fff; color:#10233f;
            border-radius:16px; padding:36px 28px; text-align:center;
            box-shadow:0 12px 40px rgba(0,0,0,.35);
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
        }
        .card-magic img { display:block; margin:0 auto 16px; }
        .card-magic h2 { font-size:1.5rem; font-weight:700; margin:0 0 12px; color:#10233f; }
        .card-magic p { color:#4b5563; margin:0 0 12px; line-height:1.5; }
        .card-magic p.small { font-size:.85rem; color:#6b7280; }
        .card-magic .btn-voltar {
            display:inline-block; margin-top:20px; padding:10px 24px;
            border:1px solid #d1d5db; border-radius:8px; color:#374151;
            text-decoration:none; font-weight:500; transition:background .15s;
        }
        .card-magic .btn-voltar:hover { background:#f3f4f6; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card-magic">
        <img src="<?= $esc($bp) ?>/public/assets/img/logo-128.png" width="72" height="72" alt="Guinchafacil">
        <h2>Verifique seu acesso</h2>
        <?php if ($canal === 'whatsapp'): ?>
            <p>Enviamos um link para o seu WhatsApp.</p>
            <p>Clique nele para entrar automaticamente.</p>
        <?php elseif ($canal === 'email'): ?>
            <p>Enviamos um link para o seu email.</p>
            <p>Confira a caixa de entrada (e o spam).</p>
        <?php elseif ($canal === 'sms'): ?>
            <p>Enviamos um SMS com o link.</p>
            <p>Abra a mensagem para entrar.</p>
        <?php else: ?>
            <p>Se o identificador estiver cadastrado, voce recebera um link em instantes.</p>
        <?php endif; ?>
        <p class="small">O link expira em 15 minutos.</p>
        <a href="<?= $esc($bp) ?>/login" class="btn-voltar">Voltar</a>
    </div>
</div>
</body>
</html>