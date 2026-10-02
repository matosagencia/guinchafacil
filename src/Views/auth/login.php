<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">
    <style>
        .google-login-btn{display:flex;align-items:center;justify-content:center;gap:.7rem;background:#fff;border:1px solid #dadce0;border-radius:6px;color:#3c4043;font-weight:600;box-shadow:0 1px 2px rgba(60,64,67,.15);text-decoration:none;transition:background .15s,box-shadow .15s}
        .google-login-btn:hover{background:#f8fafd;color:#202124;box-shadow:0 1px 3px rgba(60,64,67,.25)}
        .google-login-icon{display:grid;place-items:center;width:20px;height:20px;font:700 18px Arial,sans-serif;color:#4285f4}
    </style>
    <title>Login - GuinchaFacil</title>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
    <div class="card p-4 shadow" style="width:100%;max-width:420px;">
        <div class="text-center mb-4">
            <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-128.png" alt="GuinchaFacil" width="72" height="72" class="mb-2">
            <h2 class="fw-bold">Guincha<span style="color:var(--primary)">Facil</span></h2>
            <p class="text-muted">Acesse sua conta</p>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php $veioDoCheckout = !empty($retorno) && strpos((string)$retorno, '/checkout/') === 0; ?>
        <?php if ($veioDoCheckout): ?>
        <div class="alert alert-success text-center mb-3" style="border-radius:12px;">
            <strong>Quer criar sua conta em 30 segundos?</strong><br>
            <small>So nome, WhatsApp e email. CPF e endereco ficam pro pagamento.</small>
            <a href="<?php echo htmlspecialchars($bp); ?>/checkout/cliente" class="btn btn-success w-100 mt-3 py-2 fw-bold">Criar conta rapida</a>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/login">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="retorno" value="<?= htmlspecialchars((string)($retorno ?? '/'), ENT_QUOTES, 'UTF-8') ?>">

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="" required autofocus>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Senha</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>
        </form>

        <div class="text-center text-muted small my-3">ou</div>
        <a class="google-login-btn w-100 py-2" href="<?php echo htmlspecialchars($bp); ?>/auth/google?retorno=<?= urlencode((string)($retorno ?? '/')) ?>"><span class="google-login-icon" aria-hidden="true">G</span><span>Continuar com Google</span></a>

        <div class="text-center mt-2 small">
            <a href="<?php echo htmlspecialchars($bp); ?>/senha/esqueceu">Esqueceu sua senha?</a>
        </div>

        <hr>

        <div class="text-center small">
            <?php if ($veioDoCheckout): ?>
                <a href="<?php echo htmlspecialchars($bp); ?>/checkout/cliente">Nao tem conta? Criar em 30 segundos</a><br>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($bp); ?>/registro/cliente">Nao tem conta? Cadastre-se como Cliente</a><br>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($bp); ?>/registro/guincho" class="mt-1 d-block">Cadastre-se como Guincheiro</a>
        </div>
    </div>
</div>
<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>