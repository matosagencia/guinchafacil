<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$u = $cliente ?? ($_SESSION['user'] ?? []);
$nome = trim((string)($u['nome'] ?? 'Cliente'));
$iniciais = mb_strtoupper(mb_substr($nome, 0, 1) . mb_substr(strstr($nome, ' ') ?: '', 1, 1));
if (trim($iniciais) === '') {
    $iniciais = 'CL';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-perfil.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <div class="profile-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Conta</span>
                <h1><i class="fas fa-user-pen me-2 text-primary-custom"></i>Meu Perfil</h1>
                <p>Novo layout visual mantendo seus dados, segurança e fluxo atual intactos.</p>
            </div>
            <a href="<?php echo $bp; ?>/cliente/dashboard" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </header>

        <?php foreach ($flashMsg ?? [] as $flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'triangle-exclamation'; ?> me-2"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <form method="POST" action="<?php echo $bp; ?>/cliente/perfil/salvar" class="profile-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <section class="profile-hero position-relative p-4 p-lg-5 mb-4">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                            <div class="profile-avatar"><?php echo htmlspecialchars($iniciais); ?></div>
                            <div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="profile-chip"><i class="fas fa-circle-check"></i>Conta ativa</span>
                                    <span class="profile-chip"><i class="fas fa-envelope-lock"></i>E-mail protegido</span>
                                    <span class="profile-chip"><i class="fas fa-shield-heart"></i>Segurança centralizada</span>
                                </div>
                                <h2 class="mb-1 profile-hero-title"><?php echo htmlspecialchars($nome); ?></h2>
                                <p class="mb-0 text-muted">Gerencie seus dados cadastrais e mantenha sua conta pronta para abrir novos chamados com agilidade.</p>
                            </div>
                        </div>

                        <div class="profile-meta mt-4">
                            <div class="profile-meta-card">
                                <span class="label">Telefone principal</span>
                                <span class="value"><?php echo htmlspecialchars($u['telefone'] ?? 'Não informado'); ?></span>
                            </div>
                            <div class="profile-meta-card">
                                <span class="label">E-mail vinculado</span>
                                <span class="value"><?php echo htmlspecialchars($u['email'] ?? 'Não informado'); ?></span>
                            </div>
                            <div class="profile-meta-card">
                                <span class="label">Documento</span>
                                <span class="value"><?php echo htmlspecialchars($u['cpf'] ?? 'CPF não informado'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="profile-pane h-100">
                            <div class="profile-card-header">
                                <div>
                                    <h3 class="profile-card-title"><i class="fas fa-sparkles me-2 text-warning"></i>Resumo da Conta</h3>
                                    <p class="profile-card-subtitle">Estrutura inspirada nas referências mobile da pasta `screenshot`.</p>
                                </div>
                            </div>
                            <div class="d-grid gap-3">
                                <div class="d-flex justify-content-between align-items-center rounded-4 px-3 py-3 profile-summary-row">
                                    <div>
                                        <div class="small text-muted">Canal de contato</div>
                                        <strong>WhatsApp e telefone</strong>
                                    </div>
                                    <span class="badge text-bg-success">Pronto</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center rounded-4 px-3 py-3 profile-summary-row">
                                    <div>
                                        <div class="small text-muted">Login</div>
                                        <strong>E-mail fixo da conta</strong>
                                    </div>
                                    <span class="badge text-bg-secondary">Bloqueado</span>
                                </div>
                                <div class="profile-note">
                                    <div class="fw-semibold mb-1"><i class="fas fa-lock me-2 text-primary-custom"></i>Boas práticas</div>
                                    <div class="small">Altere a senha apenas quando necessário. Se os campos de senha ficarem em branco, a credencial atual será preservada.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4">
                <div class="col-xl-7">
                    <section class="profile-card p-4 p-lg-5 h-100">
                        <div class="profile-card-header">
                            <div>
                                <h3 class="profile-card-title"><i class="fas fa-id-card me-2 text-primary-custom"></i>Dados Cadastrais</h3>
                                <p class="profile-card-subtitle">Campos principais do cliente preservados, agora em módulos mais legíveis.</p>
                            </div>
                        </div>

                        <div class="profile-pane">
                            <div class="mb-3">
                                <label class="form-label">Nome completo <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control" minlength="3" required
                                       value="<?php echo htmlspecialchars($u['nome'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control" disabled
                                       value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>">
                                <div class="form-text">O e-mail continua sendo o identificador principal da conta e não é alterado nesta tela.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Telefone / WhatsApp <span class="text-danger">*</span></label>
                                    <input type="tel" name="telefone" class="form-control" required
                                           value="<?php echo htmlspecialchars($u['telefone'] ?? ''); ?>"
                                           placeholder="(11) 99999-9999">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">CPF</label>
                                    <input type="text" class="form-control" disabled
                                           value="<?php echo htmlspecialchars($u['cpf'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-xl-5">
                    <section class="profile-card p-4 p-lg-5 h-100">
                        <div class="profile-card-header">
                            <div>
                                <h3 class="profile-card-title"><i class="fas fa-lock me-2 text-warning"></i>Segurança</h3>
                                <p class="profile-card-subtitle">Mesma regra atual: campos vazios mantêm a senha existente.</p>
                            </div>
                        </div>

                        <div class="profile-pane">
                            <div class="profile-note mb-3">
                                <div class="fw-semibold mb-1">Senha atual protegida</div>
                                <div class="small">Para trocar a credencial, informe primeiro a senha vigente e depois a nova combinação.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Senha atual</label>
                                <input type="password" name="senha_atual" class="form-control" autocomplete="current-password">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nova senha</label>
                                <input type="password" name="nova_senha" class="form-control" minlength="8"
                                       autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                            </div>

                            <div class="mb-0">
                                <label class="form-label">Confirmar nova senha</label>
                                <input type="password" name="confirmar_senha" class="form-control"
                                       autocomplete="new-password" placeholder="Repita a nova senha">
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="profile-actions mb-4">
                <a href="<?php echo $bp; ?>/cliente/dashboard" class="btn btn-outline-secondary px-4">
                    <i class="fas fa-xmark me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary px-5">
                    <i class="fas fa-floppy-disk me-2"></i>Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
