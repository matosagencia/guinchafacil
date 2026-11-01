<?php
// src/Views/public/registro_funil_etapas.php
// §23 · B-UNIFIC-23: registro em 2 etapas.
// Etapa 1: contato leve (persiste lead rápido). Etapa 2: CPF + CEP + veículo.
// Regra B-009: fieldset data-step nativo; sem hidden manipulado por JS para
// transporte de veículo/serviço — os campos relevantes são inputs reais.

$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$e   = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$draft = is_array($_SESSION['pre_cotacao_draft'] ?? null) ? $_SESSION['pre_cotacao_draft'] : [];
$flash = $flash ?? null;
$cotacao = is_array($_SESSION['pre_cotacao'] ?? null) ? $_SESSION['pre_cotacao'] : [];

// Pré-seleção de veículo vinda do draft (só leitura — quem escreve é o controller)
$veiculoDraft = (string)($draft['categoria'] ?? 'popular');
$veiculoOpcoes = [
    'popular'     => ['Carro', 'Veículo de passeio comum.'],
    'moto'        => ['Moto', 'Motocicleta ou scooter.'],
    'suv'         => ['SUV', 'Veículo alto ou utilitário esportivo.'],
    'utilitario'  => ['Utilitário', 'Van, picape ou caminhonete.'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Criar sua conta — GuinchaFácil</title>
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/pre-cotacao.css?v=20261101-4">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/gf-layout.css?v=20261101-1">
<style>
:root { --gf-green:#2fb34a; --gf-green-dark:#1f8a36; --gf-ink:#10233f; }
body { margin:0; background:#f4f8f5; color:var(--gf-ink); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
.wrap { max-width:640px; margin:0 auto; padding:36px 20px 60px; }
.brand { display:flex; align-items:center; gap:8px; font-weight:800; font-size:1.15rem; color:var(--gf-ink); text-decoration:none; }
.brand span { color:var(--gf-green); }
.card { background:#fff; border:1px solid #d4e6d8; border-radius:20px; padding:28px; box-shadow:0 12px 40px rgba(20,80,35,.08); margin-top:20px; }
.eyebrow { color:var(--gf-green-dark); font-size:.75rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
h1 { font-size:clamp(1.5rem,4vw,2rem); font-weight:800; letter-spacing:-.02em; margin:6px 0 6px; }
.steps { display:flex; gap:8px; margin:18px 0 22px; }
.steps .step { flex:1; height:4px; border-radius:2px; background:#e3ebe6; }
.steps .step.is-active { background:var(--gf-green); }
.fieldset { border:0; padding:0; margin:0; }
.fieldset[data-step]:not([data-active]) { display:none; }
.fieldset legend { font-weight:650; font-size:.95rem; padding:0; margin-bottom:14px; color:var(--gf-ink); }
label.field { display:block; margin-bottom:14px; }
label.field > span { display:block; font-size:.82rem; font-weight:600; color:#4b5f52; margin-bottom:6px; }
.form-control { min-height:48px; border-radius:10px; border:1.5px solid #dce4ef; padding:.7rem .9rem; font-size:.95rem; width:100%; box-sizing:border-box; }
.form-control:focus { border-color:var(--gf-green); box-shadow:0 0 0 3px rgba(47,179,74,.15); outline:none; }
.hint { font-size:.75rem; color:#6b7a70; margin-top:4px; }
.hint.ok { color:#1f8a36; }
.hint.err { color:#c53030; }
.vehicle-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:14px; }
.vehicle-card { padding:12px; border:1.5px solid #dce4ef; border-radius:12px; background:#fff; cursor:pointer; text-align:center; font-size:.85rem; }
.vehicle-card input { position:absolute; opacity:0; pointer-events:none; }
.vehicle-card:has(input:checked) { border-color:var(--gf-green); background:#edf8ef; box-shadow:0 0 0 2px rgba(47,179,74,.15); }
.vehicle-card strong { display:block; margin-bottom:2px; }
.vehicle-card small { color:#6b7a70; font-size:.72rem; }
.actions { display:flex; gap:10px; margin-top:20px; }
.btn { flex:1; min-height:50px; border-radius:10px; font-weight:700; border:0; cursor:pointer; font-size:.95rem; }
.btn-primary { background:linear-gradient(135deg,var(--gf-green),var(--gf-green-dark)); color:#fff; box-shadow:0 6px 20px rgba(47,179,74,.3); }
.btn-secondary { background:#fff; border:1.5px solid #dce4ef; color:#334155; }
.alerta { background:#fff5f5; border:1px solid #fed7d7; color:#c53030; border-radius:10px; padding:10px 14px; font-size:.88rem; margin-bottom:16px; }
.form-control.is-invalid { border-color:#dc3545; background:#fff8f8; }
.form-control.is-invalid:focus { border-color:#dc3545; box-shadow:0 0 0 3px rgba(220,53,69,.15); }
.gf-preq-body .wrap { width:min(1200px,100% - 40px); max-width:none; padding:24px 0 76px; }
.gf-preq-body .registro-grid { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:56px; align-items:start; margin-top:24px; }
.gf-preq-body .registro-grid > .card { margin-top:0; }
.gf-preq-body .registro-aside { background:#10233f; color:#fff; border-radius:20px; padding:26px; position:sticky; top:22px; box-shadow:0 14px 34px rgba(16,35,63,.18); }
.gf-preq-body .registro-aside h2 { color:#fff; margin:8px 0 18px; font-size:1.35rem; }
.gf-preq-body .registro-aside p { color:rgba(255,255,255,.72); }
.gf-preq-body .registro-aside__price { color:#7dff96; font-size:2rem; font-weight:800; margin-top:18px; }
@media (max-width:1050px) { .gf-preq-body .registro-grid { grid-template-columns:minmax(0,1fr) 320px; gap:30px; } }
@media (max-width:760px) { .gf-preq-body .wrap { width:min(100% - 28px,640px); padding-top:18px; } .gf-preq-body .registro-grid { grid-template-columns:1fr; gap:22px; } .gf-preq-body .registro-aside { position:static; order:-1; } }
</style>
</head>
<body class="gf-preq-body">
<div class="gf-utility-bar"><div class="gf-container"><span><span class="gf-status-dot"></span> Socorro 24h. Todos os dias. Em qualquer imprevisto.</span><a href="<?= $e($bp) ?>/guincho/niteroi-rj">Niterói, RJ</a></div></div>
<header class="gf-site-header"><div class="gf-container"><a class="gf-brand" href="<?= $e($bp) ?>/">Guincha<span>Fácil</span>.</a><nav class="gf-nav" aria-label="Navegação principal"><a href="<?= $e($bp) ?>/pre-cotacao">Como funciona</a><a href="<?= $e($bp) ?>/guincho/niteroi-rj">Onde atendemos</a><a href="<?= $e($bp) ?>/parceiros">Seja um parceiro</a></nav><a class="gf-btn gf-btn--primary gf-btn--sm" href="<?= $e($bp) ?>/pre-cotacao">Pedir guincho</a></div></header>
<div class="wrap">
    <a class="brand" href="<?= $e($bp) ?>/">
        <img src="<?= $e($bp) ?>/public/assets/img/logo-48.png" alt="" width="34" height="34">
        Guincha<span>Fácil</span>
    </a>

    <div class="registro-grid">
    <div class="card">
        <div class="eyebrow">Falta pouco</div>
        <h1 id="titulo">Como podemos te chamar?</h1>

        <div class="steps" aria-hidden="true">
            <div class="step is-active" data-step-indicator="1"></div>
            <div class="step" data-step-indicator="2"></div>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alerta"><?= $e($flash['message'] ?? '') ?></div>
        <?php endif; ?>

        <form method="post" action="<?= $e($bp) ?>/registro/funil" id="formRegistro" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $e((string)($csrf_token ?? '')) ?>">

            <!-- ═══ ETAPA 1 — contato leve ═══════════════════════════════ -->
            <fieldset class="fieldset" data-step="1" data-active>
                <legend>1. Seus dados de contato</legend>

                <label class="field">
                    <span>Nome completo</span>
                    <input class="form-control" type="text" name="nome" required autocomplete="name"
                           value="<?= $e((string)($draft['nome'] ?? '')) ?>" maxlength="120">
                </label>

                <label class="field">
                    <span>WhatsApp</span>
                    <input class="form-control" type="tel" name="telefone" id="telefone" required
                           inputmode="numeric" autocomplete="tel" placeholder="(21) 99999-9999"
                           value="<?= $e((string)($draft['telefone'] ?? '')) ?>" maxlength="16">
                    <div class="hint" id="hintTelefone"></div>
                </label>

                <label class="field">
                    <span>E-mail</span>
                    <input class="form-control" type="email" name="email" required
                           autocomplete="email" placeholder="voce@email.com"
                           value="<?= $e((string)($draft['email'] ?? '')) ?>" maxlength="160">
                </label>

                <div class="actions">
                    <button type="button" class="btn btn-primary" data-next="2">Continuar</button>
                </div>
                <p class="hint">Sem cadastro nesta etapa. Só precisamos para te avisar quando o socorro sair.</p>
            </fieldset>

            <!-- ═══ ETAPA 2 — validação + veículo ════════════════════════ -->
            <fieldset class="fieldset" data-step="2">
                <legend>2. Confirmação e veículo</legend>

                <label class="field">
                    <span>CPF</span>
                    <input class="form-control" type="text" name="cpf" id="cpf" required
                           inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" maxlength="14">
                    <div class="hint" id="hintCpf"></div>
                </label>

                <label class="field">
                    <span>CEP</span>
                    <input class="form-control" type="text" name="cep" id="cep" required
                           inputmode="numeric" autocomplete="postal-code" placeholder="00000-000" maxlength="9">
                    <div class="hint" id="hintCep"></div>
                </label>

                <label class="field">
                    <span>Endereço</span>
                    <input class="form-control" type="text" name="endereco" id="endereco" required
                           placeholder="Rua, número, bairro e cidade" autocomplete="street-address" maxlength="220">
                </label>

                <div class="field">
                    <span style="display:block;font-size:.82rem;font-weight:600;color:#4b5f52;margin-bottom:6px;">
                        Tipo de veículo
                    </span>
                    <div class="vehicle-grid">
                        <?php foreach ($veiculoOpcoes as $slug => [$nome, $desc]): ?>
                        <label class="vehicle-card">
                            <input type="radio" name="categoria" value="<?= $e($slug) ?>"
                                   <?= $slug === $veiculoDraft ? 'checked' : '' ?> required>
                            <strong><?= $e($nome) ?></strong>
                            <small><?= $e($desc) ?></small>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-secondary" data-back="1">Voltar</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">Criar conta e continuar</button>
                </div>
            </fieldset>
        </form>
    </div>
    <aside class="registro-aside">
        <div class="eyebrow">Sua solicitação</div>
        <h2>Cotação preservada</h2>
        <p><?= $e($cotacao['endereco_origem'] ?? 'Atendimento no local informado') ?></p>
        <?php if (!empty($cotacao['endereco_destino'])): ?><p>→ <?= $e($cotacao['endereco_destino']) ?></p><?php endif; ?>
        <div class="registro-aside__price">R$ <?= number_format((float)($cotacao['valor'] ?? 0), 2, ',', '.') ?></div>
        <p class="mb-0">Sem cobrança nesta etapa. Você decide antes de continuar.</p>
    </aside>
    </div>
</div>
<footer class="gf-site-footer"><div class="gf-container"><div class="gf-footer-bottom"><span>&copy; <?= date('Y') ?> GuinchaFácil. Todos os direitos reservados.</span><span>Assistência 24 horas · Niterói, RJ</span></div></div></footer>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
    'use strict';

    // ─── Navegação entre etapas (sem estado de negócio em JS) ────────
    // Só visibilidade: cada fieldset tem [data-step]; [data-active] mostra.
    var form = document.getElementById('formRegistro');
    if (!form) return;

    function mostrarEtapa(n) {
        form.querySelectorAll('[data-step]').forEach(function (fs) {
            if (fs.getAttribute('data-step') === String(n)) {
                fs.setAttribute('data-active', '');
            } else {
                fs.removeAttribute('data-active');
            }
        });
        form.querySelectorAll('[data-step-indicator]').forEach(function (el, i) {
            el.classList.toggle('is-active', i < n);
        });
        var titulo = document.getElementById('titulo');
        if (titulo) titulo.textContent = n === 1 ? 'Como podemos te chamar?' : 'Confirme seus dados.';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.querySelectorAll('[data-next]').forEach(function (b) {
        b.addEventListener('click', function () {
            var atual = form.querySelector('[data-step][data-active]');
            if (!atual) return;
            if (!validarEtapa(atual)) return;
            mostrarEtapa(parseInt(b.getAttribute('data-next'), 10));
        });
    });
    form.querySelectorAll('[data-back]').forEach(function (b) {
        b.addEventListener('click', function () {
            mostrarEtapa(parseInt(b.getAttribute('data-back'), 10));
        });
    });

    function validarEtapa(fieldset) {
        var ok = true;
        fieldset.querySelectorAll('input[required]').forEach(function (inp) {
            if (inp.type === 'radio') {
                var grupo = form.querySelectorAll('input[name="' + inp.name + '"]:checked');
                if (!grupo.length) ok = false;
            } else if (!inp.value.trim()) {
                ok = false; inp.classList.add('is-invalid');
            } else {
                inp.classList.remove('is-invalid');
            }
        });
        return ok;
    }

    // ─── Máscara de WhatsApp (não valida regra de negócio, só formata) ─
    var tel = document.getElementById('telefone');
    if (tel) tel.addEventListener('input', function () {
        var v = tel.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10)       tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
        else if (v.length > 6)   tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2,6) + '-' + v.slice(6);
        else if (v.length > 2)   tel.value = '(' + v.slice(0,2) + ') ' + v.slice(2);
        else if (v.length > 0)   tel.value = '(' + v;
    });

    // ─── Máscara CPF + validação algorítmica (§ handoff B→A br-validators) ─
    var cpf = document.getElementById('cpf');
    var hintCpf = document.getElementById('hintCpf');
    function validarCpf(v) {
        var d = String(v || '').replace(/\D/g, '');
        if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) return false;
        for (var t = 9; t < 11; t++) {
            var soma = 0;
            for (var i = 0; i < t; i++) soma += parseInt(d.charAt(i),10) * ((t+1) - i);
            var dig = ((10 * soma) % 11) % 10;
            if (parseInt(d.charAt(t),10) !== dig) return false;
        }
        return true;
    }
    if (cpf && hintCpf) {
        cpf.addEventListener('input', function () {
            var v = cpf.value.replace(/\D/g, '').slice(0, 11);
            cpf.value = v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
            if (v.length === 11) {
                if (validarCpf(v)) { hintCpf.textContent = 'CPF válido.'; hintCpf.className = 'hint ok'; }
                else               { hintCpf.textContent = 'CPF inválido.'; hintCpf.className = 'hint err'; }
            } else { hintCpf.textContent = ''; hintCpf.className = 'hint'; }
        });
    }

    // ─── ViaCEP (auto-preenchimento sem refresh) ────────────────────
    var cep = document.getElementById('cep');
    var endereco = document.getElementById('endereco');
    var hintCep = document.getElementById('hintCep');
    if (cep && endereco) {
        cep.addEventListener('input', function () {
            var v = cep.value.replace(/\D/g, '').slice(0, 8);
            cep.value = v.length > 5 ? v.slice(0,5) + '-' + v.slice(5) : v;
            if (v.length === 8) {
                if (hintCep) { hintCep.textContent = 'Buscando endereço...'; hintCep.className = 'hint'; }
                fetch('https://viacep.com.br/ws/' + v + '/json/')
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.erro) {
                            if (hintCep) { hintCep.textContent = 'CEP não encontrado.'; hintCep.className = 'hint err'; }
                            return;
                        }
                        var partes = [data.logradouro, data.bairro, data.localidade, data.uf].filter(Boolean);
                        endereco.value = partes.join(', ');
                        if (hintCep) { hintCep.textContent = 'Endereço preenchido. Adicione o número.'; hintCep.className = 'hint ok'; }
                    })
                    .catch(function () {
                        if (hintCep) { hintCep.textContent = 'Não foi possível buscar o CEP agora.'; hintCep.className = 'hint err'; }
                    });
            } else if (hintCep) { hintCep.textContent = ''; hintCep.className = 'hint'; }
        });
    }

    // ─── Evita duplo submit ────────────────────────────────────────
    form.addEventListener('submit', function () {
        var b = document.getElementById('btnSubmit');
        if (b && !b.disabled) { b.disabled = true; b.textContent = 'Enviando...'; }
    });
})();
</script>
</body>
</html>
