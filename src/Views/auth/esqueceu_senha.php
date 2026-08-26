<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/public/assets/css/style.css" rel="stylesheet">
    <title>Esqueceu a Senha — GuinchaFácil</title>
</head>
<body>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']); ?> alert-dismissible position-fixed top-0 w-100 rounded-0" role="alert" style="z-index:9999">
    <?php echo htmlspecialchars($flash['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
    <div class="card p-4 shadow" style="width:100%;max-width:420px;">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Guincha<span style="color:var(--secondary,#ff7f00)">Fácil</span></h2>
            <p class="text-muted">Recuperação de senha</p>
        </div>

        <p class="text-muted small text-center mb-4">
            Informe seu email cadastrado e enviaremos um link para redefinir sua senha.
        </p>

        <form method="POST" action="/senha/esqueceu">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="seu@email.com" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Enviar link de recuperação</button>
        </form>

        <hr>
        <div class="text-center small">
            <a href="/login">← Voltar ao login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
