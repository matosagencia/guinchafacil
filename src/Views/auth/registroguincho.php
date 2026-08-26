<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
    <title>Cadastro Guincheiro — GuinchaFácil</title>
    <style>
        body { margin: 0; min-height: 100vh; background: #0a1a0d; }

        .reg-wrapper {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        @media (max-width:991px) { .reg-wrapper { grid-template-columns: 1fr; } .reg-hero { display:none!important; } }

        /* Hero side */
        .reg-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, #071a0a 0%, #0f3318 40%, #1a5c2a 100%);
            display: flex; flex-direction: column; justify-content: flex-end; padding: 3rem;
        }
        .reg-hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(ellipse at 20% 80%, rgba(47,179,74,.25) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(47,179,74,.12) 0%, transparent 50%);
        }
        .hero-pattern {
            position: absolute; inset: 0; opacity: .04;
            background-image: repeating-linear-gradient(45deg, #2fb34a 0, #2fb34a 1px, transparent 0, transparent 50%);
            background-size: 30px 30px;
        }
        .hero-truck-icon {
            font-size: 7rem;
            color: rgba(47,179,74,.15);
            position: absolute;
            top: 2rem; right: 2rem;
            transform: scaleX(-1);
            filter: drop-shadow(0 0 40px rgba(47,179,74,.1));
        }
        .hero-scene {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 220px;
            background: linear-gradient(to top, rgba(0,0,0,.6), transparent);
        }
        .hero-road {
            position: absolute;
            bottom: 40px; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(47,179,74,.4), transparent);
        }
        .hero-content { position: relative; z-index: 2; }
        .hero-logo { display: flex; align-items: center; gap: .75rem; margin-bottom: 2rem; }
        .hero-logo img { width: 48px; height: 48px; border-radius: 12px; }
        .hero-logo .brand { font-size: 1.5rem; font-weight: 700; color: #fff; }
        .hero-logo .brand span { color: #2fb34a; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .4rem .9rem; border-radius: 20px;
            background: rgba(47,179,74,.15); border: 1px solid rgba(47,179,74,.3);
            color: #7dff96; font-size: .78rem; font-weight: 600;
            letter-spacing: .05em; text-transform: uppercase; margin-bottom: 1.2rem;
        }
        .hero-title { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 1rem; }
        .hero-title span { color: #2fb34a; }
        .hero-desc { color: rgba(255,255,255,.6); font-size: .95rem; line-height: 1.7; margin-bottom: 2rem; }
        .hero-stats { display: flex; gap: 2rem; }
        .hero-stat { text-align: center; }
        .hero-stat .num { font-size: 1.6rem; font-weight: 800; color: #2fb34a; display: block; }
        .hero-stat .lbl { font-size: .72rem; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .05em; }

        /* Form side */
        .reg-form-side {
            background: var(--theme-bg, #0f1f12);
            display: flex; flex-direction: column; overflow-y: auto;
        }
        .reg-form-nav {
            padding: 1.2rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(47,179,74,.1);
        }
        .reg-form-nav .logo-sm { font-size: 1.1rem; font-weight: 700; color: var(--theme-text, #e8fcea); }
        .reg-form-nav .logo-sm span { color: #2fb34a; }
        .reg-form-body { flex: 1; padding: 2rem; max-width: 560px; width: 100%; margin: 0 auto; }

        /* Steps indicator */
        .steps-bar { display: flex; gap: .5rem; margin-bottom: 2rem; }
        .step-dot {
            height: 4px; flex: 1; border-radius: 2px;
            background: rgba(47,179,74,.2); transition: all .3s;
        }
        .step-dot.active { background: #2fb34a; }

        /* Section headings */
        .form-section-hd {
            display: flex; align-items: center; gap: .6rem;
            font-size: .72rem; font-weight: 800; letter-spacing: 1.2px;
            text-transform: uppercase; color: #2fb34a;
            margin: 1.8rem 0 1rem; padding-bottom: .5rem;
            border-bottom: 1px solid rgba(47,179,74,.2);
        }
        .form-section-hd:first-of-type { margin-top: 0; }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed rgba(47,179,74,.3);
            border-radius: 12px; padding: 1.2rem;
            text-align: center; cursor: pointer;
            transition: all .2s; background: rgba(47,179,74,.03);
        }
        .upload-zone:hover { border-color: #2fb34a; background: rgba(47,179,74,.07); }
        .upload-zone input[type=file] { display: none; }
        .upload-preview { max-width: 100%; max-height: 120px; border-radius: 8px; margin-top: .5rem; }

        /* Form controls */
        .form-control, .form-select {
            background: var(--theme-surface2, #162b1a) !important;
            border-color: rgba(47,179,74,.2) !important;
            color: var(--theme-text, #e8fcea) !important;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2fb34a !important;
            box-shadow: 0 0 0 3px rgba(47,179,74,.15) !important;
        }
        .form-label { color: rgba(232,252,234,.75); font-size: .875rem; margin-bottom: .35rem; }
        ::placeholder { color: rgba(232,252,234,.25) !important; }
        select option { background: #162b1a; }

        .btn-register {
            background: linear-gradient(135deg, #2fb34a, #1f8a36);
            border: none; color: #fff; font-weight: 700;
            font-size: 1rem; padding: .9rem; border-radius: 12px;
            width: 100%; transition: all .2s;
            box-shadow: 0 4px 15px rgba(47,179,74,.3);
        }
        .btn-register:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(47,179,74,.4); }
        .btn-register:disabled { opacity: .5; transform: none; }

        .input-group-text { background: rgba(47,179,74,.1); border-color: rgba(47,179,74,.2); color: #2fb34a; }
    </style>
</head>
<body>

<div class="reg-wrapper">

    <!-- Hero -->
    <div class="reg-hero">
        <div class="hero-pattern"></div>
        <i class="fas fa-truck hero-truck-icon"></i>
        <div class="hero-scene"><div class="hero-road"></div></div>

        <div class="hero-content">
            <div class="hero-logo">
                <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png" alt="GuinchaFácil">
                <div class="brand">Guincha<span>Fácil</span></div>
            </div>

            <div class="hero-badge">
                <i class="fas fa-shield-halved"></i>Parceiro Verificado
            </div>

            <h1 class="hero-title">Seja um guincheiro<br><span>GuinchaFácil</span></h1>

            <p class="hero-desc">
                Cadastre seu reboque e receba pedidos de socorro 24h.<br>
                Defina seu raio de atendimento e trabalhe quando quiser.
            </p>

            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="num">R$0</span>
                    <span class="lbl">Mensalidade</span>
                </div>
                <div class="hero-stat">
                    <span class="num">24h</span>
                    <span class="lbl">Pedidos</span>
                </div>
                <div class="hero-stat">
                    <span class="num">+500</span>
                    <span class="lbl">Parceiros</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="reg-form-side">
        <nav class="reg-form-nav">
            <a href="<?php echo $bp; ?>/" class="logo-sm">Guincha<span>Fácil</span></a>
            <a href="<?php echo $bp; ?>/login" class="btn btn-sm btn-outline-secondary" style="border-color:rgba(47,179,74,.3);color:#7dff96">
                <i class="fas fa-right-to-bracket me-1"></i>Já tenho conta
            </a>
        </nav>

        <div class="reg-form-body">

            <?php if (!empty($flash)): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?> mb-4">
                <?php echo htmlspecialchars($flash['message'] ?? ''); ?>
            </div>
            <?php endif; ?>

            <div class="mb-1" style="color:rgba(232,252,234,.5);font-size:.82rem">Passo 1 de 3</div>
            <h4 style="color:var(--theme-text, #e8fcea);font-weight:700;margin-bottom:.25rem">Cadastro de Guincheiro</h4>
            <p style="color:rgba(232,252,234,.4);font-size:.88rem;margin-bottom:1.5rem">Preencha os dados para criar sua conta de parceiro</p>

            <div class="steps-bar">
                <div class="step-dot active" id="dot1"></div>
                <div class="step-dot" id="dot2"></div>
                <div class="step-dot" id="dot3"></div>
            </div>

            <form method="POST" action="<?php echo $bp; ?>/registro/guincho"
                  enctype="multipart/form-data" id="formGuincho" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

                <!-- STEP 1: Dados pessoais -->
                <div id="step1">
                    <div class="form-section-hd"><i class="fas fa-user"></i>Dados Pessoais</div>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" class="form-control" name="nome" id="f_nome"
                                   placeholder="Seu nome completo" required minlength="3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail *</label>
                            <input type="email" class="form-control" name="email" id="f_email"
                                   placeholder="seu@email.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone / WhatsApp *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="telefone" id="f_tel"
                                       placeholder="(11) 99999-9999" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF *</label>
                            <input type="text" class="form-control" name="cpf" id="f_cpf"
                                   placeholder="000.000.000-00" maxlength="14" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data de Nascimento</label>
                            <input type="date" class="form-control" name="nascimento"
                                   max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Senha *</label>
                            <input type="password" class="form-control" name="senha" id="f_senha"
                                   placeholder="Mínimo 6 caracteres" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar Senha *</label>
                            <input type="password" class="form-control" name="confirmar_senha" id="f_conf"
                                   placeholder="Repita a senha" required>
                            <div id="senhaFb" class="small mt-1 d-none" style="color:#f87171">Senhas não conferem</div>
                        </div>
                    </div>
                    <button type="button" class="btn-register mb-3" onclick="irStep(2)">
                        Continuar <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>

                <!-- STEP 2: Veículo e CNH -->
                <div id="step2" style="display:none">
                    <div class="form-section-hd"><i class="fas fa-truck"></i>Dados do Veículo</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Placa do Reboque *</label>
                            <input type="text" class="form-control" name="placa_guincho" id="f_placa"
                                   placeholder="ABC1D23" style="text-transform:uppercase" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Capacidade de Carga (ton) *</label>
                            <input type="number" step="0.1" min="0.5" max="50" class="form-control" name="capacidade_ton"
                                   placeholder="ex: 3.5" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Raio de Atendimento (km)</label>
                            <div class="d-flex align-items-center gap-3">
                                <input type="range" class="form-range flex-fill" id="raioRange" name="raio_cobertura_km"
                                       min="5" max="200" value="30" oninput="document.getElementById('raioVal').textContent=this.value">
                                <span id="raioVal" style="color:#2fb34a;font-weight:700;min-width:40px">30</span>
                                <span style="color:rgba(232,252,234,.4);font-size:.82rem">km</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-hd"><i class="fas fa-id-card"></i>Habilitação (CNH)</div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Número da CNH *</label>
                            <input type="text" class="form-control" name="cnh_numero" id="f_cnh"
                                   placeholder="00000000000" maxlength="11" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Validade da CNH *</label>
                            <input type="date" class="form-control" name="cnh_validade"
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-secondary flex-fill" onclick="irStep(1)">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </button>
                        <button type="button" class="btn-register flex-fill" onclick="irStep(3)" style="width:auto">
                            Continuar <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Documentos e PIX -->
                <div id="step3" style="display:none">
                    <div class="form-section-hd"><i class="fas fa-file-alt"></i>Documentos</div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label">CNH — Frente</label>
                            <div class="upload-zone" onclick="document.getElementById('u_cnh_frente').click()">
                                <input type="file" id="u_cnh_frente" name="doc_cnh_frente"
                                       accept="image/*,.pdf" onchange="previewUpload(this,'prev_cnh_frente')">
                                <div id="prev_cnh_frente">
                                    <i class="fas fa-id-card fa-2x mb-2" style="color:rgba(47,179,74,.4)"></i>
                                    <div style="color:rgba(232,252,234,.4);font-size:.85rem">Clique para enviar foto da CNH (frente)</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">CNH — Verso</label>
                            <div class="upload-zone" onclick="document.getElementById('u_cnh_verso').click()">
                                <input type="file" id="u_cnh_verso" name="doc_cnh_verso"
                                       accept="image/*,.pdf" onchange="previewUpload(this,'prev_cnh_verso')">
                                <div id="prev_cnh_verso">
                                    <i class="fas fa-id-card fa-2x mb-2" style="color:rgba(47,179,74,.4)"></i>
                                    <div style="color:rgba(232,252,234,.4);font-size:.85rem">Clique para enviar foto da CNH (verso)</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Foto do Veículo Reboque</label>
                            <div class="upload-zone" onclick="document.getElementById('u_foto').click()">
                                <input type="file" id="u_foto" name="foto_veiculo"
                                       accept="image/*" onchange="previewUpload(this,'prev_foto')">
                                <div id="prev_foto">
                                    <i class="fas fa-truck fa-2x mb-2" style="color:rgba(47,179,74,.4)"></i>
                                    <div style="color:rgba(232,252,234,.4);font-size:.85rem">Clique para enviar foto do reboque</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-hd"><i class="fas fa-qrcode"></i>Dados para Recebimento (Pix)</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Chave</label>
                            <select class="form-select" name="chave_pix_tipo">
                                <option value="cpf">CPF</option>
                                <option value="cnpj">CNPJ</option>
                                <option value="telefone">Telefone</option>
                                <option value="email">E-mail</option>
                                <option value="aleatoria">Aleatória</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Chave Pix</label>
                            <input type="text" class="form-control" name="chave_pix" placeholder="Sua chave Pix">
                        </div>
                    </div>

                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-secondary flex-fill" onclick="irStep(2)">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </button>
                        <button type="submit" class="btn-register flex-fill" id="btnFinal" style="width:auto">
                            <i class="fas fa-check me-2"></i>Criar Conta de Guincheiro
                        </button>
                    </div>

                    <p style="color:rgba(232,252,234,.35);font-size:.78rem;text-align:center">
                        Seu cadastro passará por revisão antes de ser aprovado.
                        Você será notificado por e-mail.
                    </p>
                </div>

            </form>

            <div class="text-center mt-3" style="font-size:.85rem;color:rgba(232,252,234,.35)">
                Já tem conta? <a href="<?php echo $bp; ?>/login" style="color:#2fb34a">Fazer login</a>
                &nbsp;·&nbsp;
                É cliente? <a href="<?php echo $bp; ?>/registro/cliente" style="color:#2fb34a">Cadastre-se aqui</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Steps
function irStep(n) {
    if (n === 2 && !validarStep1()) return;
    if (n === 3 && !validarStep2()) return;
    [1,2,3].forEach(i => {
        document.getElementById(`step${i}`).style.display = i === n ? 'block' : 'none';
        document.getElementById(`dot${i}`).classList.toggle('active', i <= n);
    });
    window.scrollTo({top:0, behavior:'smooth'});
}

function validarStep1() {
    const nome  = document.getElementById('f_nome').value.trim();
    const email = document.getElementById('f_email').value.trim();
    const senha = document.getElementById('f_senha').value;
    const conf  = document.getElementById('f_conf').value;
    const cpf   = document.getElementById('f_cpf').value;
    const tel   = document.getElementById('f_tel').value;
    if (nome.length < 3) { alert('Informe seu nome completo.'); return false; }
    if (!email.includes('@')) { alert('Informe um e-mail válido.'); return false; }
    if (tel.replace(/\D/g,'').length < 10) { alert('Informe um telefone válido.'); return false; }
    if (cpf.replace(/\D/g,'').length < 11) { alert('Informe um CPF válido.'); return false; }
    if (senha.length < 6) { alert('Senha mínima de 6 caracteres.'); return false; }
    if (senha !== conf) { alert('As senhas não conferem.'); return false; }
    return true;
}

function validarStep2() {
    const placa = document.getElementById('f_placa').value.trim();
    const cnh   = document.getElementById('f_cnh').value.trim();
    if (placa.length < 7) { alert('Informe a placa do veículo.'); return false; }
    if (cnh.length < 9) { alert('Informe o número da CNH.'); return false; }
    return true;
}

// Masks
document.getElementById('f_cpf').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/,'$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/,'$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/,'$1.$2');
    this.value = v;
});
document.getElementById('f_tel').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'').slice(0,11);
    if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');
    else if (v.length > 6) v = v.replace(/(\d{2})(\d{4,5})(\d{0,4})/,'($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,5})/,'($1) $2');
    this.value = v;
});
document.getElementById('f_placa').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
});
document.getElementById('f_conf').addEventListener('input', function() {
    const ok = this.value === document.getElementById('f_senha').value;
    document.getElementById('senhaFb').classList.toggle('d-none', ok || !this.value);
});

// Preview uploads
function previewUpload(input, previewId) {
    const file = input.files[0];
    const el = document.getElementById(previewId);
    if (!file) return;
    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            el.innerHTML = `<img src="${e.target.result}" class="upload-preview"><div style="color:#2fb34a;font-size:.8rem;margin-top:.4rem"><i class="fas fa-check me-1"></i>${file.name}</div>`;
        };
        reader.readAsDataURL(file);
    } else {
        el.innerHTML = `<i class="fas fa-file-pdf fa-2x" style="color:#2fb34a"></i><div style="color:#2fb34a;font-size:.8rem;margin-top:.4rem">${file.name}</div>`;
    }
}
</script>
</body>
</html>
