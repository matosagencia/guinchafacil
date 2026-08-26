<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-user-edit me-2 text-primary-custom"></i>Editar Usuário</div>
            <div class="page-subtitle">
                <?php echo htmlspecialchars($usuario['nome'] ?? ''); ?> —
                <span class="badge-perfil <?php echo $usuario['tipo'] ?? ''; ?>"><?php echo ucfirst($usuario['tipo'] ?? ''); ?></span>
            </div>
        </div>
        <a href="<?php echo $bp; ?>/admin/usuario/<?php echo (int)$usuario['id']; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar ao Perfil
        </a>
    </div>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <?php
        $erros = [
            '1'               => 'Dados inválidos. Verifique os campos obrigatórios.',
            'email_duplicado' => 'E-mail ou CPF já está em uso por outro usuário.',
            'sem_permissao'   => 'Sem permissão para executar esta ação.',
        ];
        echo htmlspecialchars($erros[$_GET['erro']] ?? 'Erro desconhecido.');
        ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['salvo'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Dados salvos com sucesso!</div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <!-- Dados básicos -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-id-card me-2"></i>Dados Pessoais</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/atualizar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome"
                                       value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail *</label>
                                <input type="email" class="form-control" name="email"
                                       value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" id="telefoneInput"
                                       value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"
                                       placeholder="(11) 99999-9999">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control" name="cpf" id="cpfInput"
                                       value="<?php echo htmlspecialchars($usuario['cpf'] ?? ''); ?>"
                                       placeholder="000.000.000-00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status da Conta</label>
                            <select class="form-select" name="ativo">
                                <option value="1" <?php echo ($usuario['ativo'] ?? 1) ? 'selected' : ''; ?>>✅ Ativo</option>
                                <option value="0" <?php echo !($usuario['ativo'] ?? 1) ? 'selected' : ''; ?>>⛔ Suspenso</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Dados Pessoais
                        </button>
                    </form>
                </div>
            </div>

            <!-- Alterar senha -->
            <div class="card">
                <div class="card-header"><i class="fas fa-lock me-2"></i>Alterar Senha</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/senha">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nova Senha *</label>
                                <input type="password" class="form-control" name="senha"
                                       id="novaSenha" minlength="6" placeholder="Mínimo 6 caracteres">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar Senha *</label>
                                <input type="password" class="form-control" name="confirmar"
                                       id="confirmarSenha">
                                <div id="senhaFeedback" class="small mt-1" style="color:var(--theme-muted)"></div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 mt-3" id="btnSenha">
                            <i class="fas fa-key me-2"></i>Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Info do usuário -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informações do Sistema</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:40%">ID</td><td>#<?php echo (int)$usuario['id']; ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Tipo de Perfil</td>
                            <td><span class="badge-perfil <?php echo $usuario['tipo'] ?? ''; ?>"><?php echo ucfirst($usuario['tipo'] ?? ''); ?></span></td></tr>
                        <tr><td style="color:var(--theme-muted)">Cadastrado em</td>
                            <td><?php echo isset($usuario['criado_em']) ? date('d/m/Y H:i', strtotime($usuario['criado_em'])) : '—'; ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Último Login</td>
                            <td><?php echo !empty($usuario['ultimo_login']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?></td></tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($extra) && $usuario['tipo'] === 'guincho'): ?>
            <!-- Dados do guincho (se for guincho) -->
            <div class="card">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Guincho</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/guincho/atualizar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$extra['id']; ?>">

                        <div class="mb-3">
                            <label class="form-label">Placa do Reboque</label>
                            <input type="text" class="form-control" name="placa_guincho"
                                   value="<?php echo htmlspecialchars($extra['placa_guincho'] ?? ''); ?>"
                                   style="text-transform:uppercase">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CNH Número</label>
                            <input type="text" class="form-control" name="cnh_numero"
                                   value="<?php echo htmlspecialchars($extra['cnh_numero'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Validade CNH</label>
                            <input type="date" class="form-control" name="cnh_validade"
                                   value="<?php echo htmlspecialchars($extra['cnh_validade'] ?? ''); ?>">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label">Chave Pix</label>
                                <input type="text" class="form-control" name="chave_pix"
                                       value="<?php echo htmlspecialchars($extra['chave_pix'] ?? ''); ?>">
                            </div>
                            <div class="col-5">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="chave_pix_tipo">
                                    <?php foreach (['cpf','cnpj','email','telefone','aleatoria'] as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo ($extra['chave_pix_tipo'] ?? 'cpf') === $t ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($t); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacidade (ton)</label>
                            <input type="number" step="0.1" class="form-control" name="capacidade_ton"
                                   value="<?php echo htmlspecialchars($extra['capacidade_ton'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aprovado</label>
                            <select class="form-select" name="aprovado">
                                <option value="1" <?php echo ($extra['aprovado'] ?? 0) ? 'selected' : ''; ?>>✅ Aprovado</option>
                                <option value="0" <?php echo !($extra['aprovado'] ?? 0) ? 'selected' : ''; ?>>⏳ Pendente</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Salvar Dados do Guincho
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script>
// Validação senha
document.getElementById('confirmarSenha').addEventListener('input', function() {
    const s = document.getElementById('novaSenha').value;
    const c = this.value;
    const fb = document.getElementById('senhaFeedback');
    const btn = document.getElementById('btnSenha');
    if (!c) { fb.textContent = ''; return; }
    if (s === c) {
        fb.style.color = 'var(--primary)';
        fb.textContent = '✓ Senhas conferem';
        btn.disabled = false;
    } else {
        fb.style.color = '#f87171';
        fb.textContent = '✗ Senhas não conferem';
        btn.disabled = true;
    }
});

// Máscara CPF
document.getElementById('cpfInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0,11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/, '$1.$2');
    this.value = v;
});

// Máscara telefone
document.getElementById('telefoneInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').slice(0,11);
    if (v.length > 6) v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    this.value = v;
});
</script>
