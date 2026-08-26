<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/public/assets/css/style.css" rel="stylesheet">
    <title>Redefinir Senha — GuinchaFácil</title>
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
            <p class="text-muted">Criar nova senha</p>
        </div>

        <form method="POST" action="/senha/redefinir" id="formRedefinir">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>">

            <div class="mb-3">
                <label for="senha" class="form-label">Nova senha</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="senha" name="senha"
                           minlength="8" placeholder="Mínimo 8 caracteres" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleSenha('senha')">
                        <i id="icon-senha" class="bi bi-eye">👁</i>
                    </button>
                </div>
                <div class="form-text">
                    <span id="forca-label" class="fw-bold"></span>
                    <div class="progress mt-1" style="height:4px;">
                        <div id="forca-bar" class="progress-bar" style="width:0%;transition:width .3s"></div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label for="confirmacao" class="form-label">Confirmar nova senha</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirmacao" name="confirmacao"
                           minlength="8" placeholder="Repita a senha" required>
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleSenha('confirmacao')">
                        <i id="icon-confirmacao" class="bi bi-eye">👁</i>
                    </button>
                </div>
                <div id="match-msg" class="form-text"></div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2" id="btnSalvar">Salvar nova senha</button>
        </form>

        <hr>
        <div class="text-center small">
            <a href="/login">← Voltar ao login</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSenha(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

const senhaInput = document.getElementById('senha');
const confirmInput = document.getElementById('confirmacao');
const forcaBar = document.getElementById('forca-bar');
const forcaLabel = document.getElementById('forca-label');
const matchMsg = document.getElementById('match-msg');
const btnSalvar = document.getElementById('btnSalvar');

function avaliarForca(s) {
    let score = 0;
    if (s.length >= 8)  score++;
    if (s.length >= 12) score++;
    if (/[A-Z]/.test(s)) score++;
    if (/[0-9]/.test(s)) score++;
    if (/[^A-Za-z0-9]/.test(s)) score++;
    return score;
}

senhaInput.addEventListener('input', function() {
    const v = this.value;
    const score = avaliarForca(v);
    const pct = Math.min(score * 20, 100);
    forcaBar.style.width = pct + '%';
    const cores = ['','#dc2626','#f59e0b','#eab308','#22c55e','#16a34a'];
    const labels = ['','Muito fraca','Fraca','Média','Forte','Muito forte'];
    forcaBar.style.backgroundColor = cores[score] || '#dc2626';
    forcaLabel.textContent = labels[score] || '';
    forcaLabel.style.color = cores[score] || '#dc2626';
    verificarMatch();
});

confirmInput.addEventListener('input', verificarMatch);

function verificarMatch() {
    if (!confirmInput.value) { matchMsg.textContent = ''; return; }
    if (senhaInput.value === confirmInput.value) {
        matchMsg.textContent = '✅ Senhas coincidem';
        matchMsg.style.color = '#16a34a';
        btnSalvar.disabled = false;
    } else {
        matchMsg.textContent = '❌ Senhas não coincidem';
        matchMsg.style.color = '#dc2626';
        btnSalvar.disabled = true;
    }
}

document.getElementById('formRedefinir').addEventListener('submit', function(e) {
    if (senhaInput.value !== confirmInput.value) {
        e.preventDefault();
        matchMsg.textContent = '❌ Senhas não coincidem';
        matchMsg.style.color = '#dc2626';
    }
});
</script>
</body>
</html>
