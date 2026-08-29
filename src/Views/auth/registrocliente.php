<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
    <title>Cadastro de Cliente — GuinchaFácil</title>
    <style>
        body { margin: 0; min-height: 100vh; background: #0a1a0d; }
        .reg-wrapper { min-height: 100vh; display: grid; grid-template-columns: 1fr 1fr; }
        @media (max-width:991px) { .reg-wrapper { grid-template-columns: 1fr; } .reg-hero { display:none!important; } }

        .reg-hero {
            position: relative; overflow: hidden;
            background: linear-gradient(145deg, #071a0a 0%, #0f3318 40%, #1a5c2a 100%);
            display: flex; flex-direction: column; justify-content: flex-start; padding: 3rem;
        }
        .reg-hero::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(ellipse at 20% 80%, rgba(47,179,74,.25) 0%, transparent 60%),
                        radial-gradient(ellipse at 80% 20%, rgba(47,179,74,.12) 0%, transparent 50%);
        }
        .hero-pattern {
            position: absolute; inset: 0; opacity: .04;
            background-image: repeating-linear-gradient(45deg, #2fb34a 0, #2fb34a 1px, transparent 0, transparent 50%);
            background-size: 30px 30px;
        }
        /* Car with flat tyre SVG scene */
        .hero-car-scene {
            position: absolute; bottom: 80px; left: 50%; transform: translateX(-50%);
            text-align: center; opacity: .2;
        }
        .hero-car-scene i { font-size: 6rem; color: #2fb34a; }
        .hero-sos {
            position: absolute; top: 3rem; right: 3rem;
            width: 70px; height: 70px; border-radius: 50%;
            background: rgba(239,68,68,.15); border: 2px solid rgba(239,68,68,.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: 900; color: #f87171;
            animation: pulse-sos 2s infinite;
        }
        @keyframes pulse-sos {
            0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.3); }
            50% { box-shadow: 0 0 0 16px rgba(239,68,68,0); }
        }
        .hero-road {
            position: absolute; bottom: 40px; left: 0; right: 0; height: 3px;
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
        .hero-features { display: flex; flex-direction: column; gap: .75rem; }
        .hero-feat {
            display: flex; align-items: center; gap: .75rem;
            color: rgba(255,255,255,.7); font-size: .9rem;
        }
        .hero-feat i { width: 32px; height: 32px; border-radius: 8px;
            background: rgba(47,179,74,.15); display: flex; align-items: center; justify-content: center;
            color: #2fb34a; font-size: .85rem; flex-shrink: 0; }

        .reg-form-side {
            background: var(--theme-bg, #0f1f12);
            display: flex; flex-direction: column; overflow-y: auto;
        }
        .reg-form-nav {
            padding: 1.2rem 2rem; display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(47,179,74,.1);
        }
        .reg-form-nav .logo-sm { font-size: 1.1rem; font-weight: 700; color: var(--theme-text, #e8fcea); }
        .reg-form-nav .logo-sm span { color: #2fb34a; }
        .reg-form-body { flex: 1; padding: 2rem; max-width: 560px; width: 100%; margin: 0 auto; }

        .form-section-hd {
            display: flex; align-items: center; gap: .6rem;
            font-size: .72rem; font-weight: 800; letter-spacing: 1.2px;
            text-transform: uppercase; color: #2fb34a;
            margin: 1.8rem 0 1rem; padding-bottom: .5rem;
            border-bottom: 1px solid rgba(47,179,74,.2);
        }
        .form-section-hd:first-of-type { margin-top: 0; }

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
        .input-group-text { background: rgba(47,179,74,.1); border-color: rgba(47,179,74,.2); color: #2fb34a; }
        .btn-register {
            background: linear-gradient(135deg, #2fb34a, #1f8a36);
            border: none; color: #fff; font-weight: 700;
            font-size: 1rem; padding: .9rem; border-radius: 12px;
            width: 100%; transition: all .2s;
            box-shadow: 0 4px 15px rgba(47,179,74,.3);
        }
        .btn-register:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(47,179,74,.4); }

        /* Perfil cliente: claro, acolhedor e orientado ao pedido de socorro. */
        body.cliente.registro-publico { background: #f4f8f5; color: #142018; }
        body.cliente.registro-publico .reg-form-side { background: #f4f8f5; }
        body.cliente.registro-publico .reg-form-nav { border-bottom-color: #d4e6d8; }
        body.cliente.registro-publico .reg-form-nav .logo-sm,
        body.cliente.registro-publico .reg-form-body h4 { color: #142018 !important; }
        body.cliente.registro-publico .reg-form-body > p { color: #607066 !important; }
        body.cliente.registro-publico .form-label { color: #405247; }
        body.cliente.registro-publico .form-control,
        body.cliente.registro-publico .form-select { background: #fff !important; color: #142018 !important; border-color: #d4e6d8 !important; }
        body.cliente.registro-publico .form-control:focus,
        body.cliente.registro-publico .form-select:focus { border-color: #2fb34a !important; box-shadow: 0 0 0 3px rgba(47,179,74,.15) !important; }
        body.cliente.registro-publico ::placeholder { color: #8a9a8f !important; }
        body.cliente.registro-publico select option { background: #fff; color: #142018; }
        body.cliente.registro-publico .form-section-hd { color: #248f3a; border-bottom-color: #d4e6d8; }
        body.cliente.registro-publico .input-group-text { background: #edf6ef; border-color: #d4e6d8; color: #248f3a; }
        body.cliente.registro-publico .btn-outline-secondary { border-color: #b8d8bd !important; color: #248f3a !important; }
    </style>
</head>
<body class="cliente registro-publico">
<div class="reg-wrapper">

    <!-- Hero -->
    <div class="reg-hero">
        <div class="hero-pattern"></div>
        <div class="hero-sos">SOS</div>
        <div class="hero-car-scene">
            <i class="fas fa-car-burst"></i>
        </div>
        <div class="hero-road"></div>

        <div class="hero-content">
            <div class="hero-logo">
                <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png" alt="GuinchaFácil">
                <div class="brand">Guincha<span>Fácil</span></div>
            </div>

            <div class="hero-badge">
                <i class="fas fa-bolt"></i>Socorro rápido
            </div>

            <h1 class="hero-title">Pneu furado?<br>A gente está <span>a caminho</span></h1>

            <p class="hero-desc">
                Cadastre-se gratuitamente e tenha socorro de guincho<br>
                em minutos, onde quer que você esteja.
            </p>

            <div class="hero-features">
                <div class="hero-feat">
                    <i class="fas fa-map-marker-alt"></i>
                    Rastreio em tempo real do guincho
                </div>
                <div class="hero-feat">
                    <i class="fas fa-clock"></i>
                    Atendimento 24 horas, 7 dias por semana
                </div>
                <div class="hero-feat">
                    <i class="fas fa-shield-halved"></i>
                    Guincheiros verificados e avaliados
                </div>
                <div class="hero-feat">
                    <i class="fas fa-money-bill-wave"></i>
                    Preço justo, calculado pela distância
                </div>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="reg-form-side">
        <nav class="reg-form-nav">
            <a href="<?php echo $bp; ?>/" class="logo-sm">Guincha<span>Fácil</span></a>
            <a href="<?php echo $bp; ?>/login<?php echo ($retorno ?? '/') !== '/' ? '?retorno=' . rawurlencode($retorno) : ''; ?>" class="btn btn-sm btn-outline-secondary" style="border-color:rgba(47,179,74,.3);color:#7dff96">
                <i class="fas fa-right-to-bracket me-1"></i>Já tenho conta
            </a>
        </nav>

        <div class="reg-form-body">

            <?php if (!empty($flash)): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?> mb-4">
                <?php echo htmlspecialchars($flash['message'] ?? ''); ?>
            </div>
            <?php endif; ?>

            <h4 style="color:var(--theme-text, #e8fcea);font-weight:700;margin-bottom:.25rem">Criar Conta de Cliente</h4>
            <p style="color:rgba(232,252,234,.4);font-size:.88rem;margin-bottom:1.5rem">Rápido e gratuito — solicite socorro em segundos</p>

            <form method="POST" action="<?php echo $bp; ?>/registro/cliente" id="formCliente" novalidate data-marketing-event="sign_up">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                <input type="hidden" name="retorno" value="<?php echo htmlspecialchars($retorno ?? '/', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-section-hd"><i class="fas fa-user"></i>Dados Pessoais</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome Completo *</label>
                        <input type="text" class="form-control" name="nome" autocomplete="name" placeholder="Seu nome" required minlength="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">E-mail *</label>
                        <input type="email" class="form-control" name="email" autocomplete="email" placeholder="seu@email.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Telefone / WhatsApp *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" name="telefone" autocomplete="tel" id="telefone"
                                   placeholder="(11) 99999-9999" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">CPF *</label>
                        <input type="text" class="form-control" name="cpf" autocomplete="off" id="cpf"
                               placeholder="000.000.000-00" maxlength="14" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha *</label>
                        <input type="password" class="form-control" name="senha" id="senha"
                               placeholder="Mínimo 8 caracteres" required minlength="8">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmar Senha *</label>
                        <input type="password" class="form-control" name="confirmar_senha" id="confirmar_senha"
                               placeholder="Repita a senha" required>
                        <div id="senhaFeedback" class="small mt-1 d-none" style="color:#f87171">As senhas não conferem.</div>
                    </div>
                </div>

                <div class="form-section-hd"><i class="fas fa-map-marker-alt"></i>Endereço Principal</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">CEP *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="cep" autocomplete="postal-code" id="cep"
                                   placeholder="00000-000" maxlength="9" required>
                        </div>
                        <div id="cepStatus" class="small mt-1" style="color:var(--theme-muted)"></div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Logradouro *</label>
                        <input type="text" class="form-control" name="logradouro" autocomplete="street-address" id="logradouro"
                               placeholder="Rua, Avenida..." required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Número *</label>
                        <input type="text" class="form-control" name="numero" placeholder="Nº" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Complemento</label>
                        <input type="text" class="form-control" name="complemento" placeholder="Apto, Bloco...">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Bairro *</label>
                        <input type="text" class="form-control" name="bairro" autocomplete="address-level3" id="bairro" required>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">Cidade *</label>
                        <input type="text" class="form-control" name="cidade" autocomplete="address-level2" id="cidade" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Estado *</label>
                        <select class="form-select" name="estado" id="estado" required>
                            <option value="">UF</option>
                            <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                            <option value="<?php echo $uf; ?>"><?php echo $uf; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-register mb-3">
                    <i class="fas fa-user-plus me-2"></i>Criar Minha Conta Grátis
                </button>

                <p style="color:rgba(232,252,234,.3);font-size:.78rem;text-align:center">
                    Ao criar sua conta você concorda com os
                    <a href="<?php echo $bp; ?>/termos-servico.php" target="_blank" rel="noopener noreferrer" style="color:#2fb34a">Termos de Uso</a> e
                    <a href="<?php echo $bp; ?>/politica-privacidade.php" target="_blank" rel="noopener noreferrer" style="color:#2fb34a">Política de Privacidade</a>.
                </p>
            </form>

            <div class="text-center mt-2" style="font-size:.85rem;color:rgba(232,252,234,.35)">
                Já tem conta? <a href="<?php echo $bp; ?>/login" style="color:#2fb34a">Fazer login</a>
                &nbsp;·&nbsp;
                É guincheiro? <a href="<?php echo $bp; ?>/registro/guincho" style="color:#2fb34a">Cadastre seu guincho</a>
            </div>
        </div>
    </div>
</div>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/app.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/auth-registro-cliente.js"></script>
</body>
</html>
