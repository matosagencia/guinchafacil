<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; ?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>
    <?php require __DIR__ . '/../components/marketing_tracking.php'; ?>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">

    <title>Login - GuinchaFácil</title>

</head>

<body>

<?php if (!empty($flash)): ?>

<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']); ?> alert-dismissible position-fixed top-0 w-100 rounded-0 mb-0" role="alert" style="z-index:9999">

    <?php echo htmlspecialchars($flash['message']); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

</div>

<?php endif; ?>

<?php if (!empty($_GET['motivo'])): ?>
<div class="alert alert-warning text-center rounded-0 mb-0" role="alert">
    Sua sessão expirou. Entre novamente para continuar de onde parou.
</div>
<?php endif; ?>
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">

    <div class="card p-4 shadow" style="width:100%;max-width:420px;">

        <div class="text-center mb-4">

            <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-128.png" alt="GuinchaFácil" width="72" height="72" class="mb-2">

            <h2 class="fw-bold">Guincha<span style="color:var(--primary)">Fácil</span></h2>

            <p class="text-muted">Acesse sua conta</p>

        </div>

        <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/login">

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
            <input type="hidden" name="retorno" value="<?php echo htmlspecialchars($retorno ?? '/', ENT_QUOTES, 'UTF-8'); ?>">

            <div class="mb-3">

                <label for="email" class="form-label">Email</label>

                <input type="email" class="form-control" id="email" name="email"

                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>

            </div>

            <div class="mb-3">

                <label for="password" class="form-label">Senha</label>

                <input type="password" class="form-control" id="password" name="password" required>

            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>

        </form>

        <div class="text-center mt-2 small">

            <a href="<?php echo htmlspecialchars($bp); ?>/senha/esqueceu">Esqueceu sua senha?</a>

        </div>

        <hr>

        <div class="text-center small">

            <a href="<?php echo htmlspecialchars($bp); ?>/registro/cliente">Não tem conta? Cadastre-se como Cliente</a><br>

            <a href="<?php echo htmlspecialchars($bp); ?>/registro/guincho" class="mt-1 d-block">Cadastre-se como Guincheiro</a>

        </div>

    </div>

</div>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>

