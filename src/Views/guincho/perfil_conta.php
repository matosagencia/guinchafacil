<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$u   = $dadosUsuario ?? [];
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-user-pen me-2 text-primary-custom"></i>Meu Perfil</div>
            <div class="page-subtitle">Dados pessoais e acesso da conta</div>
        </div>
    </div>
    <?php foreach (($flashMsg ?? []) as $flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible">
        <?php echo htmlspecialchars($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endforeach; ?>
    <div class="card mb-4"><div class="card-body d-flex gap-2 flex-wrap">
        <a class="btn btn-primary" href="<?php echo $bp; ?>/guincho/perfil">Conta</a>
        <a class="btn btn-outline-primary" href="<?php echo $bp; ?>/guincho/operacao">Dados do guincho</a>
        <a class="btn btn-outline-success" href="<?php echo $bp; ?>/guincho/bancario">Dados bancários</a>
    </div></div>
    <form method="POST" action="<?php echo $bp; ?>/guincho/perfil/salvar">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <div class="card">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Conta do guincheiro</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nome completo</label>
                    <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>" required minlength="3">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Telefone / WhatsApp</label>
                    <input type="tel" name="telefone" class="form-control" value="<?php echo htmlspecialchars($u['telefone'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">E-mail</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">CPF</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($u['cpf'] ?? ''); ?>" disabled>
                </div>
                <div class="col-12"><hr></div>
                <div class="col-md-4">
                    <label class="form-label">Senha atual</label>
                    <input type="password" name="senha_atual" class="form-control" autocomplete="current-password">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="nova_senha" class="form-control" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirmar nova senha</label>
                    <input type="password" name="confirmar_senha" class="form-control" autocomplete="new-password">
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar dados da conta</button>
            </div>
        </div>
    </form>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
