<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-user-plus me-2" style="color:var(--primary)"></i>Criar Novo Usuário</div>
            <div class="page-subtitle">Cadastro manual — selecione o tipo para ver os campos correspondentes</div>
        </div>
        <a href="<?php echo $bp; ?>/admin/usuarios" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>

    <?php if (isset($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <?php $erros = ['1'=>'Dados inválidos. Verifique os campos.','email_duplicado'=>'E-mail ou CPF já cadastrado.'];
        echo $erros[$_GET['erro']] ?? 'Erro desconhecido.'; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/salvar" id="formUsuario" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- Tipo selector -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-users me-2"></i>Tipo de Perfil</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ([
                        'cliente' => ['fa-user','Cliente','Solicita socorro de guincho','var(--primary)'],
                        'guincho' => ['fa-truck','Guincheiro','Opera o reboque e aceita pedidos','#f59e0b'],
                        'admin'   => ['fa-shield-halved','Administrador','Gerencia toda a plataforma','#60a5fa'],
                    ] as $tipo => [$icon, $label, $desc, $color]): ?>
                    <div class="col-md-4">
                        <label class="d-flex gap-3 p-3 rounded border h-100"
                               style="cursor:pointer;border-color:var(--theme-border)!important;align-items:flex-start"
                               id="label_<?php echo $tipo; ?>">
                            <input type="radio" name="tipo" value="<?php echo $tipo; ?>"
                                   onchange="mudarTipo('<?php echo $tipo; ?>')"
                                   <?php echo $tipo === 'cliente' ? 'checked' : ''; ?> style="margin-top:.2rem">
                            <div>
                                <i class="fas <?php echo $icon; ?> mb-1" style="color:<?php echo $color; ?>"></i>
                                <div style="font-weight:600"><?php echo $label; ?></div>
                                <div style="font-size:.78rem;color:var(--theme-muted)"><?php echo $desc; ?></div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Dados básicos (todos os tipos) -->
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Dados Básicos</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" class="form-control" name="nome"
                               value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail *</label>
                        <input type="email" class="form-control" name="email"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone *</label>
                        <input type="text" class="form-control" name="telefone" id="telefone"
                               value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>"
                               placeholder="(11) 99999-9999" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CPF *</label>
                        <input type="text" class="form-control" name="cpf" id="cpfInput"
                               value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>"
                               placeholder="000.000.000-00" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha *</label>
                        <input type="password" class="form-control" name="senha" id="senhaInput"
                               minlength="6" required placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmar Senha *</label>
                        <input type="password" class="form-control" name="confirmar_senha" id="confirmarSenha" required>
                        <div id="senhaFeedback" class="small mt-1" style="color:var(--theme-muted)"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campos extras de GUINCHO -->
        <div id="camposGuincho" style="display:none">
            <div class="card mb-4" style="border-left:3px solid #f59e0b">
                <div class="card-header" style="background:rgba(245,158,11,.08)">
                    <i class="fas fa-truck me-2" style="color:#f59e0b"></i>Dados do Guincheiro
                    <span class="badge ms-2" style="background:#f59e0b;color:#000">Obrigatório para tipo Guincho</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Placa do Reboque *</label>
                            <input type="text" class="form-control" name="placa_guincho" id="placaGuincho"
                                   value="<?php echo htmlspecialchars($_POST['placa_guincho'] ?? ''); ?>"
                                   placeholder="ABC1D23" style="text-transform:uppercase">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacidade (ton)</label>
                            <input type="number" step="0.1" min="0" max="50" class="form-control" name="capacidade_ton"
                                   value="<?php echo htmlspecialchars($_POST['capacidade_ton'] ?? '1'); ?>"
                                   placeholder="ex: 3.5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número CNH</label>
                            <input type="text" class="form-control" name="cnh_numero"
                                   value="<?php echo htmlspecialchars($_POST['cnh_numero'] ?? ''); ?>"
                                   placeholder="00000000000" maxlength="11">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Validade CNH</label>
                            <input type="date" class="form-control" name="cnh_validade"
                                   value="<?php echo htmlspecialchars($_POST['cnh_validade'] ?? date('Y-m-d', strtotime('+5 years'))); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Raio de Cobertura (km)</label>
                            <input type="number" class="form-control" name="raio_cobertura_km"
                                   value="<?php echo htmlspecialchars($_POST['raio_cobertura_km'] ?? '20'); ?>"
                                   min="1" max="500">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Chave Pix</label>
                            <input type="text" class="form-control" name="chave_pix"
                                   value="<?php echo htmlspecialchars($_POST['chave_pix'] ?? ''); ?>"
                                   placeholder="CPF, e-mail ou chave aleatória">
                        </div>
                    </div>

                    <div class="mt-3 pt-3" style="border-top:1px solid rgba(47,179,74,.15)">
                        <div style="font-size:.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#f59e0b;margin-bottom:.75rem">
                            <i class="fas fa-file-alt me-2"></i>Documentos (opcional)
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:.82rem">CNH — Frente</label>
                                <input type="file" class="form-control form-control-sm" name="doc_cnh_frente" accept="image/*,.pdf">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:.82rem">CNH — Verso</label>
                                <input type="file" class="form-control form-control-sm" name="doc_cnh_verso" accept="image/*,.pdf">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-size:.82rem">Foto do Veículo</label>
                                <input type="file" class="form-control form-control-sm" name="foto_veiculo" accept="image/*">
                            </div>
                        </div>
                        <div class="small mt-1" style="color:var(--theme-muted)">Formatos: JPG, PNG, PDF. Máx. 5MB cada.</div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 mb-4" id="btnSubmit">
            <i class="fas fa-user-plus me-2"></i><span id="btnLabel">Criar Cliente</span>
        </button>
    </form>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script>
function mudarTipo(tipo) {
    // Highlight selecionado
    ['cliente','guincho','admin'].forEach(t => {
        const lbl = document.getElementById('label_' + t);
        lbl.style.borderColor = t === tipo ? 'var(--primary)' : 'var(--theme-border)';
        lbl.style.background  = t === tipo ? 'rgba(47,179,74,.08)' : '';
    });

    // Mostrar campos de guincho
    document.getElementById('camposGuincho').style.display = tipo === 'guincho' ? 'block' : 'none';

    // Tornar placa required apenas para guincho
    document.getElementById('placaGuincho').required = tipo === 'guincho';

    // Atualizar botão
    const labels = {cliente:'Criar Cliente', guincho:'Criar Guincheiro', admin:'Criar Administrador'};
    document.getElementById('btnLabel').textContent = labels[tipo] || 'Criar Usuário';
}

// Inicializar
mudarTipo('cliente');

// Senha
document.getElementById('confirmarSenha').addEventListener('input', function() {
    const s = document.getElementById('senhaInput').value;
    const fb = document.getElementById('senhaFeedback');
    if (!this.value) { fb.textContent = ''; return; }
    if (s === this.value) { fb.style.color = 'var(--primary)'; fb.textContent = '✓ Senhas conferem'; }
    else { fb.style.color = '#f87171'; fb.textContent = '✗ Senhas não conferem'; }
});

// CPF
document.getElementById('cpfInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/,'$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/,'$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/,'$1.$2');
    this.value = v;
});

// Telefone
document.getElementById('telefone').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 6) v = v.replace(/(\d{2})(\d{5})(\d{0,4})/,'($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/,'($1) $2');
    this.value = v;
});

// Placa uppercase
document.getElementById('placaGuincho').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
});
</script>
