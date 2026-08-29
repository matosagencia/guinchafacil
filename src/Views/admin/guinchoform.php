<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-guinchoform.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-truck me-2 guinchoform-icon-accent"></i>Cadastrar Guincheiro</h1>
            <p>Registro de novo operador de guincho/reboque pelo administrador</p>
        </div>
        <a href="<?php echo $bp; ?>/admin/guinchos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </header>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <?php $erros = ['1'=>'Dados inválidos. Verifique todos os campos.','email_duplicado'=>'E-mail ou CPF já cadastrado.'];
        echo $erros[$_GET['erro']] ?? 'Erro desconhecido.'; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo $bp; ?>/admin/guincho/criar"
          enctype="multipart/form-data" id="formGuincho">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <div class="row g-4">
            <!-- Dados Pessoais -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-id-card me-2"></i>Dados Pessoais do Operador</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
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
                                <input type="text" class="form-control" name="telefone" id="telefoneInput"
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
                                <input type="password" class="form-control" name="senha"
                                       id="senhaInput" minlength="8"
                                       placeholder="Mínimo 8 caracteres" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Confirmar Senha *</label>
                                <input type="password" class="form-control" name="confirmar_senha"
                                       id="confirmarSenha" required>
                                <div id="senhaFeedback" class="small mt-1 guinchoform-hint"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Cidade-alvo de atuação *</label>
                                <select class="form-select" name="cidade_id" required>
                                    <option value="">Selecione a cidade</option>
                                    <?php foreach (($cidadesAtivas ?? []) as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)($_POST['cidade_id'] ?? 0) === (int)$c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome'] . '/' . $c['uf']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($cidadesAtivas)): ?>
                                <div class="form-text text-warning">Nenhuma cidade-alvo cadastrada. <a href="<?php echo $bp; ?>/admin/cidades">Cadastrar em Cidades-alvo</a>.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documentos -->
                <div class="card">
                    <div class="card-header"><i class="fas fa-file-alt me-2"></i>Documentos do Operador</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">CNH — Frente</label>
                                <input type="file" class="form-control" name="doc_cnh_frente"
                                       accept="image/*,.pdf" id="cnhFrente">
                                <div id="prevCnhFrente" class="mt-2"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">CNH — Verso</label>
                                <input type="file" class="form-control" name="doc_cnh_verso"
                                       accept="image/*,.pdf" id="cnhVerso">
                                <div id="prevCnhVerso" class="mt-2"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Foto do Veículo Reboque</label>
                                <input type="file" class="form-control" name="foto_veiculo"
                                       accept="image/*" id="fotoVeiculo">
                                <div id="prevFotoVeiculo" class="mt-2"></div>
                            </div>
                        </div>
                        <div class="small mt-2 guinchoform-hint">
                            <i class="fas fa-info-circle me-1"></i>Formatos aceitos: JPG, PNG, PDF. Máx. 5MB por arquivo.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados do Veículo e Pagamento -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Veículo e Habilitação</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Placa do Reboque *</label>
                                <input type="text" class="form-control text-uppercase" name="placa_guincho" id="placaInput"
                                       value="<?php echo htmlspecialchars($_POST['placa_guincho'] ?? ''); ?>"
                                       placeholder="ABC1D23" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade do Emplacamento</label>
                                <input type="text" class="form-control" name="cidade_placa"
                                       value="<?php echo htmlspecialchars($_POST['cidade_placa'] ?? ''); ?>"
                                       placeholder="Ex: Rio de Janeiro">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">UF da Placa</label>
                                <input type="text" class="form-control text-uppercase" name="uf_placa"
                                       value="<?php echo htmlspecialchars($_POST['uf_placa'] ?? ''); ?>"
                                       maxlength="2" placeholder="RJ">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Capacidade (ton) *</label>
                                <input type="number" step="0.1" min="0" max="50" class="form-control" name="capacidade_ton"
                                       value="<?php echo htmlspecialchars($_POST['capacidade_ton'] ?? ''); ?>"
                                       placeholder="ex: 3.5" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Número CNH *</label>
                                <input type="text" class="form-control" name="cnh_numero"
                                       value="<?php echo htmlspecialchars($_POST['cnh_numero'] ?? ''); ?>"
                                       placeholder="00000000000" maxlength="11" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Validade CNH *</label>
                                <input type="date" class="form-control" name="cnh_validade"
                                       value="<?php echo htmlspecialchars($_POST['cnh_validade'] ?? date('Y-m-d', strtotime('+5 years'))); ?>"
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Raio de Cobertura (km)</label>
                                <input type="number" class="form-control" name="raio_cobertura_km"
                                       value="<?php echo htmlspecialchars($_POST['raio_cobertura_km'] ?? '20'); ?>"
                                       min="1" max="500">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Latitude Operacional</label>
                                <input type="number" class="form-control" name="lat_operacao" step="0.00000001"
                                       value="<?php echo htmlspecialchars($_POST['lat_operacao'] ?? '-23.5505'); ?>"
                                       placeholder="-23.55050000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitude Operacional</label>
                                <input type="number" class="form-control" name="lng_operacao" step="0.00000001"
                                       value="<?php echo htmlspecialchars($_POST['lng_operacao'] ?? '-46.6333'); ?>"
                                       placeholder="-46.63330000">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><i class="fas fa-qrcode me-2"></i>Dados para Pagamento (Pix)</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Chave Pix</label>
                                <select class="form-select" name="chave_pix_tipo">
                                    <?php foreach (['cpf'=>'CPF','cnpj'=>'CNPJ','telefone'=>'Telefone','email'=>'E-mail','aleatoria'=>'Aleatória'] as $v=>$l): ?>
                                    <option value="<?php echo $v; ?>" <?php echo ($_POST['chave_pix_tipo'] ?? 'cpf') === $v ? 'selected' : ''; ?>>
                                        <?php echo $l; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Chave Pix</label>
                                <input type="text" class="form-control" name="chave_pix"
                                       value="<?php echo htmlspecialchars($_POST['chave_pix'] ?? ''); ?>"
                                       placeholder="Chave pix do guincheiro">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fas fa-check-circle me-2"></i>Status de Aprovação</div>
                    <div class="card-body">
                        <label class="d-flex align-items-center gap-3 p-3 rounded border guinchoform-aprovado-label">
                            <input type="checkbox" name="aprovado" value="1" class="form-check-input mt-0 guinchoform-aprovado-checkbox"
                                   <?php echo !empty($_POST['aprovado']) ? 'checked' : ''; ?>>
                            <div>
                                <div class="guinchoform-aprovado-title">Aprovar imediatamente</div>
                                <div class="guinchoform-aprovado-desc">Se marcado, o guincheiro pode receber pedidos imediatamente</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary w-100 py-3 guinchoform-submit" id="btnSubmit">
                <i class="fas fa-truck-medical me-2"></i>Cadastrar Guincheiro
            </button>
        </div>
    </form>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<script<?php echo csp_script_nonce_attr(); ?>>
// Senha match
document.getElementById('confirmarSenha').addEventListener('input', function() {
    const s = document.getElementById('senhaInput').value;
    const fb = document.getElementById('senhaFeedback');
    if (!this.value) { fb.textContent = ''; return; }
    if (s === this.value) { fb.style.color = 'var(--primary)'; fb.textContent = '✓ Senhas conferem'; }
    else { fb.style.color = '#f87171'; fb.textContent = '✗ Senhas não conferem'; }
});

// Placa uppercase
document.getElementById('placaInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
});

const ufPlacaInput = document.querySelector('input[name="uf_placa"]');
if (ufPlacaInput) {
    ufPlacaInput.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
    });
}

// CPF mask
document.getElementById('cpfInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/,'$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{0,3})/,'$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{0,3})/,'$1.$2');
    this.value = v;
});

// Telefone mask
document.getElementById('telefoneInput').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 6) v = v.replace(/(\d{2})(\d{5})(\d{0,4})/,'($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/,'($1) $2');
    this.value = v;
});

// Preview imagens
function previewFile(inputId, previewId) {
    document.getElementById(inputId).addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const el = document.getElementById(previewId);
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                el.innerHTML = `<img src="${e.target.result}" class="guinchoform-preview-img">`;
            };
            reader.readAsDataURL(file);
        } else {
            el.innerHTML = `<span class="guinchoform-preview-pdf"><i class="fas fa-file-pdf me-1"></i>${file.name}</span>`;
        }
    });
}
previewFile('cnhFrente', 'prevCnhFrente');
previewFile('cnhVerso', 'prevCnhVerso');
previewFile('fotoVeiculo', 'prevFotoVeiculo');
</script>
