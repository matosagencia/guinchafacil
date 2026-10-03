<?php
/**
 * /admin/pedido/novo/v2 - Etapa 1 do funil admin (Caminho 3).
 *
 * Segue o MESMO padrao de layout das outras views admin (pedidodetalhe.php,
 * pedidos.php): header.php + sidebar_admin.php + <main> + footer.php.
 *
 * Variaveis do controller:
 *   $bp, $csrf_token, $clientes, $flash
 */
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$clientes = $clientes ?? [];
$flash = $flash ?? null;

include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/admin-pedido-flow.css?v=20261002-1">

<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>
                <i class="fas fa-route me-2 text-primary-custom"></i>
                Novo pedido
            </h1>
            <p class="text-muted mb-0">
                Passo 1 de 2 — escolha em nome de quem o pedido será criado.
            </p>
        </div>
    </header>

    <?php if (!empty($flash) && !empty($flash['message'])): ?>
    <div class="alert alert-<?= $e(($flash['type'] ?? '') === 'success' ? 'success' : 'danger') ?> mb-3">
        <?= $e($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['erro'])): ?>
    <div class="alert alert-danger mb-3">
        <?php if ($_GET['erro'] === 'veiculo'): ?>
            O veículo selecionado não pertence a esse cliente.
        <?php elseif ($_GET['erro'] === 'expirado'): ?>
            O contexto anterior expirou. Escolha novamente.
        <?php else: ?>
            Dados inválidos. Confira as escolhas abaixo.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($clientes)): ?>
    <div class="alert alert-warning">
        Nenhum cliente ativo cadastrado. Crie um cliente antes de abrir pedido em nome de terceiros.
    </div>
    <?php else: ?>

    <form method="POST" action="<?= $e($bp) ?>/admin/pedido/novo/contexto" class="admin-flow-card">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf_token ?? '') ?>">

        <div class="mb-3">
            <label class="form-label">Cliente <span class="text-danger">*</span></label>
            <select class="form-select" name="cliente_id" id="cliente_id" required>
                <option value="">Selecione um cliente…</option>
                <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>">
                    <?= $e($c['nome']) ?><?= !empty($c['email']) ? ' — ' . $e($c['email']) : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Veículo <span class="text-danger">*</span></label>
            <select class="form-select" name="veiculo_id" id="veiculo_id" required disabled>
                <option value="">Selecione primeiro o cliente…</option>
            </select>
            <small class="text-muted d-block mt-1" id="veiculoHint">
                Os veículos aparecem assim que você escolher o cliente.
            </small>
        </div>


        <div class="admin-flow-actions">
            <a href="<?= $e($bp) ?>/admin/pedidos" class="btn btn-secondary">
                Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-arrow-right me-1"></i>Continuar para o funil
            </button>
        </div>
    </form>

    <?php endif; ?>

</main>
</div>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
    'use strict';
    var basePath = <?= json_encode($bp) ?>;
    var selCliente = document.getElementById('cliente_id');
    var selVeiculo = document.getElementById('veiculo_id');
    var hint = document.getElementById('veiculoHint');
    if (!selCliente || !selVeiculo) return;

    selCliente.addEventListener('change', function () {
        var id = parseInt(selCliente.value, 10) || 0;
        selVeiculo.innerHTML = '<option value="">Carregando…</option>';
        selVeiculo.disabled = true;
        if (hint) hint.textContent = 'Carregando veículos…';

        if (id <= 0) {
            selVeiculo.innerHTML = '<option value="">Selecione primeiro o cliente…</option>';
            if (hint) hint.textContent = 'Os veículos aparecem assim que você escolher o cliente.';
            return;
        }

        fetch(basePath + '/admin/veiculos/ajax?cliente_id=' + id, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                var veiculos = (j && j.veiculos) || [];
                if (!veiculos.length) {
                    selVeiculo.innerHTML = '<option value="">Cliente sem veículos cadastrados</option>';
                    if (hint) hint.textContent = 'Cadastre um veículo para este cliente antes.';
                    return;
                }
                var html = '<option value="">Selecione…</option>';
                veiculos.forEach(function (v) {
                    var label = [v.marca, v.modelo].filter(Boolean).join(' ');
                    if (v.placa) label += ' — ' + v.placa;
                    html += '<option value="' + parseInt(v.id, 10) + '">' + label + '</option>';
                });
                selVeiculo.innerHTML = html;
                selVeiculo.disabled = false;
                if (hint) hint.textContent = veiculos.length + ' veículo(s) encontrado(s).';
            })
            .catch(function () {
                selVeiculo.innerHTML = '<option value="">Erro ao carregar</option>';
                if (hint) hint.textContent = 'Falha ao buscar veículos.';
            });
    });
})();
</script>

<dialog id="qf-dlg-cliente" class="qf-dlg">
    <form id="qf-form-cliente" autocomplete="off" novalidate>
        <h3>Novo cliente</h3>
        <div class="qf-field">
            <label>Nome <span class="req">*</span></label>
            <input type="text" name="nome" required minlength="3" maxlength="120">
        </div>
        <div class="qf-field">
            <label>E-mail <span class="req">*</span></label>
            <input type="email" name="email" required maxlength="160" autocomplete="email" placeholder="nome@dominio.com">
            <small class="qf-help">Formato: nome@dominio.com</small>
        </div>
        <div class="qf-grid-2">
            <div class="qf-field">
                <label>Telefone</label>
                <input type="tel" name="telefone" inputmode="tel" maxlength="16" placeholder="(21) 99999-9999" autocomplete="tel">
                <small class="qf-help">DDD + numero (10 ou 11 digitos)</small>
            </div>
            <div class="qf-field">
                <label>CPF</label>
                <input type="text" name="cpf" inputmode="numeric" maxlength="14" placeholder="000.000.000-00">
                <small class="qf-help">Opcional. Validado pelo digito verificador.</small>
            </div>
        </div>
        <p class="qf-hint">Senha provisoria aleatoria: o cliente a redefine via "esqueceu senha".</p>
        <p class="qf-erro" id="qf-erro-cliente"></p>
        <div class="qf-actions">
            <button type="button" class="btn btn-secondary" data-qf-close>Cancelar</button>
            <button type="submit" class="btn btn-primary" id="qf-submit-cliente">Criar cliente</button>
        </div>
    </form>
</dialog>

<dialog id="qf-dlg-veiculo" class="qf-dlg">
    <form id="qf-form-veiculo" autocomplete="off" novalidate>
        <h3>Novo veiculo</h3>

        <div class="qf-field">
            <label>Tipo de veiculo <span class="req">*</span></label>
            <div class="qf-type-cards" id="qf-type-cards">
                <button type="button" class="qf-type-card" data-tipo="carro">Carro</button>
                <button type="button" class="qf-type-card" data-tipo="moto">Moto</button>
                <button type="button" class="qf-type-card" data-tipo="caminhonete">Caminhonete</button>
                <button type="button" class="qf-type-card" data-tipo="suv">SUV</button>
                <button type="button" class="qf-type-card" data-tipo="van">Van</button>
            </div>
            <input type="hidden" name="tipo_veiculo" id="qf-tipo-veiculo" value="">
        </div>

        <div class="qf-grid-2">
            <div class="qf-field">
                <label>Marca <span class="req">*</span></label>
                <select name="marca_id" id="qf-marca" required disabled>
                    <option value="">Escolha o tipo primeiro…</option>
                </select>
            </div>
            <div class="qf-field">
                <label>Modelo <span class="req">*</span></label>
                <select name="modelo_id" id="qf-modelo" required disabled>
                    <option value="">Escolha a marca primeiro…</option>
                </select>
            </div>
        </div>

        <div class="qf-grid-3">
            <div class="qf-field">
                <label>Ano</label>
                <input type="number" name="ano" min="1950" max="2100">
            </div>
            <div class="qf-field">
                <label>Placa</label>
                <input type="text" name="placa" maxlength="8" style="text-transform:uppercase" placeholder="ABC1D23">
            </div>
            <div class="qf-field">
                <label>Cor</label>
                <input type="text" name="cor" maxlength="30">
            </div>
        </div>

        <p class="qf-erro" id="qf-erro-veiculo"></p>
        <div class="qf-actions">
            <button type="button" class="btn btn-secondary" data-qf-close>Cancelar</button>
            <button type="submit" class="btn btn-primary" id="qf-submit-veiculo">Criar veiculo</button>
        </div>
    </form>
</dialog>

<style>
.qf-dlg { border:0; border-radius:12px; padding:0; max-width:560px; width:94vw; box-shadow:0 12px 40px rgba(0,0,0,.25); }
.qf-dlg::backdrop { background: rgba(0,0,0,.45); }
.qf-dlg form { padding:22px 24px; display:flex; flex-direction:column; gap:14px; }
.qf-dlg h3 { margin:0 0 4px; font-size:1.15rem; }
.qf-field { display:flex; flex-direction:column; gap:4px; }
.qf-field label { font-size:.85rem; color:#444; }
.qf-field input, .qf-field select { padding:9px 11px; border:1px solid #cfd6de; border-radius:8px; font:inherit; background:#fff; }
.qf-field input:focus, .qf-field select:focus { outline:2px solid #2fb34a33; border-color:#2fb34a; }
.qf-field select[disabled] { background:#f5f7fa; color:#8a94a2; }
.qf-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.qf-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.qf-hint { font-size:.78rem; color:#667; margin:0; }
.qf-help { font-size:.72rem; color:#7a8a99; }
.qf-erro { color:#b02a37; font-size:.85rem; min-height:1em; margin:0; }
.qf-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:6px; }
.qf-inline-add { margin-left:8px; padding:2px 10px; border:1px solid #2fb34a; background:#fff; color:#1f7a34; border-radius:6px; font-size:.78rem; cursor:pointer; }
.qf-inline-add:hover { background:#2fb34a; color:#fff; }
.qf-inline-add[disabled] { opacity:.5; cursor:not-allowed; }
.req { color:#d33; }
.qf-type-cards { display:flex; gap:8px; flex-wrap:wrap; }
.qf-type-card { padding:8px 14px; border:1px solid #cfd6de; background:#fff; border-radius:20px; font-size:.82rem; cursor:pointer; color:#445; transition:all .15s; }
.qf-type-card:hover { border-color:#2fb34a; color:#1f7a34; }
.qf-type-card.is-selected { background:#2fb34a; border-color:#2fb34a; color:#fff; }
</style>

<script<?= function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '' ?>>
(function () {
    'use strict';
    var bp      = window.__gfBasePath || <?= json_encode($bp) ?>;
    var csrf    = (document.querySelector('input[name="csrf_token"]') || {}).value || '';
    var selCli  = document.getElementById('cliente_id');
    var selVei  = document.getElementById('veiculo_id');
    var dlgCli  = document.getElementById('qf-dlg-cliente');
    var dlgVei  = document.getElementById('qf-dlg-veiculo');
    var formCli = document.getElementById('qf-form-cliente');
    var formVei = document.getElementById('qf-form-veiculo');
    var errCli  = document.getElementById('qf-erro-cliente');
    var errVei  = document.getElementById('qf-erro-veiculo');
    if (!selCli || !dlgCli || !dlgVei) return;

    // ---------- Validadores ----------
    function validarEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v || '').trim());
    }
    function validarCPF(v) {
        var cpf = String(v || '').replace(/\D/g, '');
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        for (var t = 9; t < 11; t++) {
            var soma = 0;
            for (var i = 0; i < t; i++) {
                soma += parseInt(cpf.charAt(i), 10) * ((t + 1) - i);
            }
            var dig = ((10 * soma) % 11) % 10;
            if (parseInt(cpf.charAt(t), 10) !== dig) return false;
        }
        return true;
    }
    function validarTelefone(v) {
        var d = String(v || '').replace(/\D/g, '');
        if (d.length < 10 || d.length > 11) return false;
        var ddd = parseInt(d.substring(0, 2), 10);
        if (ddd < 11 || ddd > 99) return false;
        if (d.length === 11 && d.charAt(2) !== '9') return false;
        return true;
    }

    // ---------- Formatadores ----------
    function fmtCPF(v) {
        var d = String(v || '').replace(/\D/g, '').substring(0, 11);
        return d
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    }
    function fmtTel(v) {
        var d = String(v || '').replace(/\D/g, '').substring(0, 11);
        if (d.length <= 2) return d;
        if (d.length <= 6) return '(' + d.substring(0, 2) + ') ' + d.substring(2);
        if (d.length <= 10) return '(' + d.substring(0, 2) + ') ' + d.substring(2, 6) + '-' + d.substring(6);
        return '(' + d.substring(0, 2) + ') ' + d.substring(2, 7) + '-' + d.substring(7);
    }

    var inTel = formCli.querySelector('[name="telefone"]');
    var inCpf = formCli.querySelector('[name="cpf"]');
    if (inTel) inTel.addEventListener('input', function () { this.value = fmtTel(this.value); });
    if (inCpf) inCpf.addEventListener('input', function () { this.value = fmtCPF(this.value); });

    // ---------- Botoes "+ Novo" injetados nos labels ----------
    function addInlineBtn(labelText, btnId, onClick) {
        var labels = document.querySelectorAll('.form-label');
        for (var i = 0; i < labels.length; i++) {
            if (labels[i].textContent.trim().indexOf(labelText) === 0) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'qf-inline-add';
                b.id = btnId;
                b.textContent = '+ Novo';
                b.addEventListener('click', onClick);
                labels[i].appendChild(b);
                return b;
            }
        }
        return null;
    }

    var btnCli = addInlineBtn('Cliente', 'qf-btn-cli', function () {
        errCli.textContent = '';
        formCli.reset();
        dlgCli.showModal();
        setTimeout(function () { var f = formCli.querySelector('[name="nome"]'); if (f) f.focus(); }, 30);
    });

    // ---------- Modal veiculo: cards de tipo + marca + modelo ----------
    var typeCards = dlgVei.querySelectorAll('[data-tipo]');
    var tipoHidden = document.getElementById('qf-tipo-veiculo');
    var selMarca = document.getElementById('qf-marca');
    var selModelo = document.getElementById('qf-modelo');

    function extractList(j, key) {
        if (!j) return [];
        if (Array.isArray(j)) return j;
        if (Array.isArray(j[key])) return j[key];
        if (Array.isArray(j.data)) return j.data;
        if (j.data && Array.isArray(j.data[key])) return j.data[key];
        return [];
    }

    function loadMarcas(tipo) {
        selMarca.innerHTML = '<option value="">Carregando…</option>';
        selMarca.disabled = true;
        selModelo.innerHTML = '<option value="">Escolha a marca primeiro…</option>';
        selModelo.disabled = true;

        var qs = tipo ? ('?tipo=' + encodeURIComponent(tipo)) : '';
        fetch(bp + '/veiculo-catalogo/marcas' + qs, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                var marcas = extractList(j, 'marcas');
                if (!marcas.length) {
                    selMarca.innerHTML = '<option value="">Nenhuma marca cadastrada</option>';
                    return;
                }
                var html = '<option value="">Selecione a marca…</option>';
                marcas.forEach(function (m) {
                    var id = m.id || m.marca_id;
                    var nome = m.nome || m.name || m.marca || '';
                    if (id && nome) {
                        html += '<option value="' + String(id) + '" data-nome="' + String(nome).replace(/"/g, '&quot;') + '">' + String(nome) + '</option>';
                    }
                });
                selMarca.innerHTML = html;
                selMarca.disabled = false;
            })
            .catch(function () {
                selMarca.innerHTML = '<option value="">Erro ao carregar marcas</option>';
            });
    }

    function loadModelos(marcaId) {
        selModelo.innerHTML = '<option value="">Carregando…</option>';
        selModelo.disabled = true;

        fetch(bp + '/veiculo-catalogo/modelos?marca_id=' + encodeURIComponent(marcaId), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                var modelos = extractList(j, 'modelos');
                if (!modelos.length) {
                    selModelo.innerHTML = '<option value="">Nenhum modelo cadastrado</option>';
                    return;
                }
                var html = '<option value="">Selecione o modelo…</option>';
                modelos.forEach(function (m) {
                    var id = m.id || m.modelo_id;
                    var nome = m.nome || m.name || m.modelo || '';
                    if (id && nome) {
                        html += '<option value="' + String(id) + '" data-nome="' + String(nome).replace(/"/g, '&quot;') + '">' + String(nome) + '</option>';
                    }
                });
                selModelo.innerHTML = html;
                selModelo.disabled = false;
            })
            .catch(function () {
                selModelo.innerHTML = '<option value="">Erro ao carregar modelos</option>';
            });
    }

    typeCards.forEach(function (card) {
        card.addEventListener('click', function () {
            var tipo = card.getAttribute('data-tipo');
            typeCards.forEach(function (c) { c.classList.toggle('is-selected', c === card); });
            if (tipoHidden) tipoHidden.value = tipo;
            loadMarcas(tipo);
        });
    });

    if (selMarca) {
        selMarca.addEventListener('change', function () {
            var id = selMarca.value;
            if (!id) {
                selModelo.innerHTML = '<option value="">Escolha a marca primeiro…</option>';
                selModelo.disabled = true;
                return;
            }
            loadModelos(id);
        });
    }

    var btnVei = addInlineBtn('Veículo', 'qf-btn-vei', function () {
        if (!selCli.value) { alert('Escolha um cliente primeiro.'); return; }
        errVei.textContent = '';
        formVei.reset();
        // reseta cards e selects
        typeCards.forEach(function (c) { c.classList.remove('is-selected'); });
        if (tipoHidden) tipoHidden.value = '';
        selMarca.innerHTML = '<option value="">Escolha o tipo primeiro…</option>';
        selMarca.disabled = true;
        selModelo.innerHTML = '<option value="">Escolha a marca primeiro…</option>';
        selModelo.disabled = true;
        dlgVei.showModal();
    });
    if (btnVei) { btnVei.disabled = !selCli.value; }
    selCli.addEventListener('change', function () { if (btnVei) btnVei.disabled = !selCli.value; });

    // Fechar
    document.querySelectorAll('[data-qf-close]').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('dialog').close(); });
    });

    // ---------- Submit cliente (com validacao) ----------
    formCli.addEventListener('submit', function (ev) {
        ev.preventDefault();
        errCli.textContent = '';
        var nome  = (formCli.querySelector('[name="nome"]').value || '').trim();
        var email = (formCli.querySelector('[name="email"]').value || '').trim();
        var tel   = (inTel ? inTel.value : '').trim();
        var cpf   = (inCpf ? inCpf.value : '').trim();

        if (nome.length < 3) { errCli.textContent = 'Nome precisa ter ao menos 3 caracteres.'; return; }
        if (!validarEmail(email)) { errCli.textContent = 'E-mail invalido. Use o formato nome@dominio.com'; return; }
        if (tel !== '' && !validarTelefone(tel)) { errCli.textContent = 'Telefone invalido. Use DDD + numero (10 ou 11 digitos).'; return; }
        if (cpf !== '' && !validarCPF(cpf)) { errCli.textContent = 'CPF invalido (digito verificador).'; return; }

        var btn = document.getElementById('qf-submit-cliente');
        btn.disabled = true;

        var body = new URLSearchParams();
        body.append('csrf_token', csrf);
        body.append('nome', nome);
        body.append('email', email);
        body.append('telefone', tel);
        body.append('cpf', cpf);

        fetch(bp + '/admin/pedido/novo/api/cliente', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.ok) {
                errCli.textContent = (j && j.erro) || 'Falha ao criar cliente.';
                btn.disabled = false;
                return;
            }
            var opt = document.createElement('option');
            opt.value = String(j.cliente.id);
            opt.textContent = j.cliente.nome + (j.cliente.email ? ' \u2014 ' + j.cliente.email : '');
            opt.selected = true;
            selCli.appendChild(opt);
            selCli.dispatchEvent(new Event('change', { bubbles: true }));
            dlgCli.close();
            btn.disabled = false;
        }).catch(function () {
            errCli.textContent = 'Erro de rede ao criar cliente.';
            btn.disabled = false;
        });
    });

    // ---------- Submit veiculo (com validacao de tipo/marca/modelo) ----------
    formVei.addEventListener('submit', function (ev) {
        ev.preventDefault();
        errVei.textContent = '';
        if (!selCli.value) { errVei.textContent = 'Cliente nao selecionado.'; return; }
        if (!tipoHidden.value) { errVei.textContent = 'Escolha o tipo de veiculo.'; return; }
        if (!selMarca.value) { errVei.textContent = 'Escolha a marca.'; return; }
        if (!selModelo.value) { errVei.textContent = 'Escolha o modelo.'; return; }

        var optMarca = selMarca.options[selMarca.selectedIndex];
        var optModelo = selModelo.options[selModelo.selectedIndex];
        var marcaNome = optMarca.getAttribute('data-nome') || optMarca.textContent;
        var modeloNome = optModelo.getAttribute('data-nome') || optModelo.textContent;

        var btn = document.getElementById('qf-submit-veiculo');
        btn.disabled = true;

        var body = new URLSearchParams();
        body.append('csrf_token', csrf);
        body.append('cliente_id', selCli.value);
        body.append('tipo_veiculo', tipoHidden.value);
        body.append('marca_id', selMarca.value);
        body.append('modelo_id', selModelo.value);
        body.append('marca', marcaNome);
        body.append('modelo', modeloNome);
        body.append('ano', (formVei.querySelector('[name="ano"]').value || ''));
        body.append('placa', (formVei.querySelector('[name="placa"]').value || ''));
        body.append('cor', (formVei.querySelector('[name="cor"]').value || ''));

        fetch(bp + '/admin/pedido/novo/api/veiculo', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.ok) {
                errVei.textContent = (j && j.erro) || 'Falha ao criar veiculo.';
                btn.disabled = false;
                return;
            }
            var label = [j.veiculo.marca, j.veiculo.modelo].filter(Boolean).join(' ');
            if (j.veiculo.placa) label += ' \u2014 ' + j.veiculo.placa;
            var opt = document.createElement('option');
            opt.value = String(j.veiculo.id);
            opt.textContent = label;
            opt.selected = true;
            selVei.appendChild(opt);
            selVei.disabled = false;
            dlgVei.close();
            btn.disabled = false;
        }).catch(function () {
            errVei.textContent = 'Erro de rede ao criar veiculo.';
            btn.disabled = false;
        });
    });
})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>