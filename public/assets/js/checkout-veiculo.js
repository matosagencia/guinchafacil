/**
 * Checkout veiculo — cascata + toggle form novo/existente + debug.
 * Versao: v10 (2026-09-29)
 */
(function () {
    'use strict';

    var DEBUG = true;
    function log() {
        if (DEBUG) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[checkout-veiculo]');
            console.log.apply(console, args);
        }
    }

    log('iniciado');

    // ============================================================
    // 1) Toggle form novo/existente
    // ============================================================
    var formNovo = document.getElementById('form-novo-veiculo');
    var divisor  = document.getElementById('divisor-novo');
    var radiosVeiculo = document.querySelectorAll('input[name="veiculo_id"]');

    log('formNovo:', !!formNovo, 'divisor:', !!divisor, 'radios:', radiosVeiculo.length);

    if (formNovo) {
        // Marca os required originais
        formNovo.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.hasAttribute('required') && el.type !== 'hidden') {
                el.dataset.wasRequired = '1';
            }
        });
        log('required originais marcados');
    }

    function atualizarVisual() {
        document.querySelectorAll('.veiculo-opcao').forEach(function (lbl) {
            var r = lbl.querySelector('input[type=radio]');
            if (r && r.checked) lbl.classList.add('selecionado');
            else lbl.classList.remove('selecionado');
        });
    }

    function aplicarToggle() {
        if (!formNovo) { log('sem formNovo, abort'); return; }

        atualizarVisual();

        var sel = document.querySelector('input[name="veiculo_id"]:checked');
        var valor = sel ? sel.value : '0';
        var ehNovo = (valor === '0');

        log('toggle: valor selecionado =', valor, 'ehNovo =', ehNovo);

        formNovo.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'hidden') return;
            if (ehNovo) {
                el.disabled = false;
                if (el.dataset.wasRequired === '1') el.required = true;
            } else {
                el.disabled = true;
                el.required = false;
            }
        });

        formNovo.style.display = ehNovo ? '' : 'none';
        if (divisor) divisor.style.display = ehNovo ? '' : 'none';

        var reqVisiveis = 0;
        formNovo.querySelectorAll('[required]').forEach(function (el) {
            if (!el.disabled) reqVisiveis++;
        });
        log('required visiveis:', reqVisiveis, 'display:', formNovo.style.display);
    }

    if (radiosVeiculo.length) {
        document.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'veiculo_id') {
                log('change detectado:', e.target.value);
                aplicarToggle();
            }
        });
    }

    aplicarToggle();

    // ============================================================
    // 2) Cascata marca/modelo
    // ============================================================
    var inputMarca   = document.getElementById('marca');
    var inputModelo  = document.getElementById('modelo');
    var listaMarcas  = document.getElementById('marcas-lista');
    var listaModelos = document.getElementById('modelos-lista');
    var hidBrand     = document.getElementById('vehicle_brand_id');
    var hidModel     = document.getElementById('vehicle_model_id');
    var inputPlaca   = document.getElementById('placa');

    if (inputMarca && inputModelo && listaMarcas && listaModelos) {
        log('carregando marcas...');
        fetch('/veiculo-catalogo/marcas', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (marcas) {
                log('marcas carregadas:', marcas.length);
                listaMarcas.innerHTML = '';
                marcas.forEach(function (m) {
                    var opt = document.createElement('option');
                    opt.value = m.name;
                    opt.dataset.id = m.id;
                    listaMarcas.appendChild(opt);
                });
            })
            .catch(function (e) { console.warn('[checkout-veiculo] marcas', e); });

        inputMarca.addEventListener('input', function () {
            var escolhida = null;
            Array.prototype.forEach.call(listaMarcas.options, function (opt) {
                if (opt.value === inputMarca.value) escolhida = opt;
            });
            if (hidBrand) hidBrand.value = escolhida ? escolhida.dataset.id : '';
            inputModelo.value = '';
            if (hidModel) hidModel.value = '';
            listaModelos.innerHTML = '';
            inputModelo.disabled = true;
            if (!escolhida) return;

            log('marca escolhida:', escolhida.value, 'id:', escolhida.dataset.id);
            inputModelo.disabled = false;
            fetch('/veiculo-catalogo/modelos?marca_id=' + encodeURIComponent(escolhida.dataset.id), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.json(); })
                .then(function (modelos) {
                    log('modelos carregados:', modelos.length);
                    listaModelos.innerHTML = '';
                    modelos.forEach(function (mo) {
                        var opt = document.createElement('option');
                        opt.value = mo.name;
                        opt.dataset.id = mo.id;
                        listaModelos.appendChild(opt);
                    });
                })
                .catch(function (e) { console.warn('[checkout-veiculo] modelos', e); });
        });

        inputModelo.addEventListener('input', function () {
            var escolhido = null;
            Array.prototype.forEach.call(listaModelos.options, function (opt) {
                if (opt.value === inputModelo.value) escolhido = opt;
            });
            if (hidModel) hidModel.value = escolhido ? escolhido.dataset.id : '';
        });

        if (inputPlaca) {
            inputPlaca.addEventListener('input', function () {
                inputPlaca.value = inputPlaca.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
            });
        }
    } else {
        log('campos de marca/modelo nao encontrados');
    }

    // ============================================================
    // 3) Anti-double-submit + debug do submit
    // ============================================================
    var form = document.querySelector('form.card-panel');
    if (form) {
        form.addEventListener('submit', function (e) {
            log('submit disparado');
            var fd = new FormData(form);
            var entries = {};
            fd.forEach(function (v, k) { entries[k] = v; });
            log('payload:', entries);

            var btn = document.getElementById('btn-continuar');
            if (btn && btn.disabled) { e.preventDefault(); return; }
            if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
        });
    }
})();